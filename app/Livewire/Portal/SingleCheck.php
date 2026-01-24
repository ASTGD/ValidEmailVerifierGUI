<?php

namespace App\Livewire\Portal;

use App\Enums\VerificationJobOrigin;
use App\Enums\VerificationJobStatus;
use App\Enums\VerificationMode;
use App\Jobs\PrepareVerificationJob;
use App\Models\VerificationJob;
use App\Services\JobStorage;
use App\Services\VerificationOutputMapper;
use App\Support\EnhancedModeGate;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.portal')]
class SingleCheck extends Component
{
    use AuthorizesRequests;

    public string $email = '';
    public string $verification_mode = VerificationMode::Standard->value;
    public ?string $jobId = null;

    protected function rules(): array
    {
        $modes = array_map(static fn (VerificationMode $mode) => $mode->value, VerificationMode::cases());

        return [
            'email' => ['required', 'email'],
            'verification_mode' => ['required', 'string', Rule::in($modes)],
        ];
    }

    public function submit(JobStorage $storage): void
    {
        $this->validate();

        $user = Auth::user();
        $enhancedGate = EnhancedModeGate::evaluate($user);

        if (! $enhancedGate['allowed'] && $this->verification_mode === VerificationMode::Enhanced->value) {
            $this->addError('verification_mode', EnhancedModeGate::message($user));

            return;
        }

        $rateKey = 'portal-single-check|'.$user->id.'|'.$this->verification_mode;
        $standardLimit = (int) config('engine.single_check_rate_limit_standard', 30);
        $enhancedLimit = (int) config('engine.single_check_rate_limit_enhanced', 10);
        $decaySeconds = (int) config('engine.single_check_rate_limit_decay_seconds', 60);
        $maxAttempts = $this->verification_mode === VerificationMode::Enhanced->value ? $enhancedLimit : $standardLimit;

        if (RateLimiter::tooManyAttempts($rateKey, $maxAttempts)) {
            $seconds = RateLimiter::availableIn($rateKey);
            $this->addError('email', __('Too many single checks. Try again in :seconds seconds.', [
                'seconds' => $seconds,
            ]));

            return;
        }

        RateLimiter::hit($rateKey, $decaySeconds);

        if (
            config('verifier.require_active_subscription')
            && method_exists($user, 'subscribed')
            && ! $user->subscribed('default')
        ) {
            $this->addError('email', __('An active subscription is required to run single checks.'));

            return;
        }

        try {
            $this->authorize('create', VerificationJob::class);
        } catch (AuthorizationException) {
            $this->addError('email', __('You are not allowed to run single checks.'));

            return;
        }

        $subjectEmail = trim($this->email);

        $job = new VerificationJob([
            'user_id' => $user->id,
            'status' => VerificationJobStatus::Pending,
            'verification_mode' => $this->verification_mode,
            'origin' => VerificationJobOrigin::SingleCheck,
            'subject_email' => $subjectEmail,
            'original_filename' => 'single-check.txt',
        ]);

        $job->id = (string) Str::uuid();
        $job->input_disk = $storage->disk();
        $job->input_key = $storage->inputKey($job);

        $storage->storeSingleInput($subjectEmail, $job, $job->input_disk, $job->input_key);

        $job->save();
        $job->addLog('created', 'Single check job created via customer portal.', [
            'subject_email' => $subjectEmail,
        ], $user->id);
        $job->addLog('verification_mode_set', 'Verification mode set at job creation.', [
            'from' => null,
            'to' => $this->verification_mode,
            'actor_id' => $user->id,
        ], $user->id);

        PrepareVerificationJob::dispatch($job->id);

        $this->jobId = $job->id;
    }

    public function getEnhancedGateProperty(): array
    {
        return EnhancedModeGate::evaluate(Auth::user());
    }

    public function getSingleCheckJobProperty(): ?VerificationJob
    {
        if (! $this->jobId) {
            return null;
        }

        return VerificationJob::query()
            ->where('user_id', Auth::id())
            ->where('origin', VerificationJobOrigin::SingleCheck->value)
            ->find($this->jobId);
    }

    public function getSingleCheckResultProperty(): ?array
    {
        $job = $this->singleCheckJob;

        if (! $job || $job->status !== VerificationJobStatus::Completed) {
            return null;
        }

        if (! empty($job->single_result_status)) {
            return [
                'email' => $job->subject_email,
                'status' => $job->single_result_status,
                'sub_status' => $job->single_result_sub_status,
                'score' => $job->single_result_score,
                'reason' => $job->single_result_reason,
                'verified_at' => $job->single_result_verified_at,
            ];
        }

        $fallback = $this->readSingleResultFromOutput($job);

        if ($fallback) {
            $job->update([
                'single_result_status' => $fallback['status'] ?? null,
                'single_result_sub_status' => $fallback['sub_status'] ?? null,
                'single_result_score' => $fallback['score'] ?? null,
                'single_result_reason' => $fallback['reason'] ?? null,
                'single_result_verified_at' => $job->single_result_verified_at ?: now(),
            ]);

            return array_merge($fallback, [
                'email' => $job->subject_email,
                'verified_at' => $job->single_result_verified_at ?: now(),
            ]);
        }

        return [
            'email' => $job->subject_email,
            'status' => $job->single_result_status,
            'sub_status' => $job->single_result_sub_status,
            'score' => $job->single_result_score,
            'reason' => $job->single_result_reason,
            'verified_at' => $job->single_result_verified_at,
        ];
    }

    public function getShouldPollProperty(): bool
    {
        $job = $this->singleCheckJob;

        if (! $job) {
            return false;
        }

        return in_array($job->status, [VerificationJobStatus::Pending, VerificationJobStatus::Processing], true);
    }

    public function render()
    {
        return view('livewire.portal.single-check');
    }

    /**
     * @return array{status: string, sub_status: string, score: int|null, reason: string}|null
     */
    private function readSingleResultFromOutput(VerificationJob $job): ?array
    {
        $disk = $job->output_disk ?: ($job->input_disk ?: app(JobStorage::class)->disk());

        foreach (['valid', 'invalid', 'risky'] as $type) {
            $key = $job->{$type.'_key'} ?? null;

            if (! $key || ! Storage::disk($disk)->exists($key)) {
                continue;
            }

            $row = $this->readFirstResultRow($disk, $key, $type);

            if ($row) {
                return $row;
            }
        }

        return null;
    }

    /**
     * @return array{status: string, sub_status: string, score: int|null, reason: string}|null
     */
    private function readFirstResultRow(string $disk, string $key, string $sourceStatus): ?array
    {
        $stream = Storage::disk($disk)->readStream($key);

        if (! is_resource($stream)) {
            return null;
        }

        try {
            while (($line = fgets($stream)) !== false) {
                $line = rtrim($line, "\r\n");

                if ($line === '' || ! str_contains($line, '@')) {
                    continue;
                }

                $columns = str_getcsv($line);

                if ($columns === []) {
                    continue;
                }

                $email = trim((string) ($columns[0] ?? ''));
                $status = trim((string) ($columns[1] ?? ''));

                if ($email === '') {
                    continue;
                }

                if (count($columns) >= 5 && in_array(strtolower($status), ['valid', 'invalid', 'risky'], true)) {
                    $subStatus = trim((string) ($columns[2] ?? ''));
                    $scoreRaw = $columns[3] ?? null;
                    $reason = trim((string) ($columns[4] ?? ''));

                    return [
                        'status' => $status,
                        'sub_status' => $subStatus,
                        'score' => is_numeric($scoreRaw) ? (int) $scoreRaw : null,
                        'reason' => $reason,
                    ];
                }

                $reason = trim((string) ($columns[1] ?? ''));
                /** @var VerificationOutputMapper $mapper */
                $mapper = app(VerificationOutputMapper::class);
                $mapped = $mapper->map($email, $sourceStatus, $reason, null);

                return [
                    'status' => $mapped['status'],
                    'sub_status' => $mapped['sub_status'],
                    'score' => $mapped['score'],
                    'reason' => $mapped['reason'],
                ];
            }
        } finally {
            fclose($stream);
        }

        return null;
    }
}
