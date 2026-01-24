<?php

namespace Tests\Feature;

use App\Enums\VerificationJobOrigin;
use App\Enums\VerificationJobStatus;
use App\Enums\VerificationMode;
use App\Livewire\Portal\SingleCheck;
use App\Livewire\Portal\Upload;
use App\Models\EngineSetting;
use App\Models\EngineVerificationPolicy;
use App\Models\User;
use App\Models\VerificationJob;
use App\Support\Roles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PortalSingleCheckTest extends TestCase
{
    use RefreshDatabase;

    private function makeCustomer(array $overrides = []): User
    {
        $user = User::factory()->create(array_merge([
            'email_verified_at' => now(),
        ], $overrides));
        Role::findOrCreate(Roles::CUSTOMER, config('auth.defaults.guard'));
        $user->assignRole(Roles::CUSTOMER);

        return $user;
    }

    private function enableEnhanced(): void
    {
        EngineSetting::query()->updateOrCreate([], ['enhanced_mode_enabled' => true]);
        EngineVerificationPolicy::query()
            ->where('mode', VerificationMode::Enhanced->value)
            ->update(['enabled' => true]);
        config(['engine.enhanced_mode_enabled' => true]);
    }

    public function test_single_check_creates_job_with_origin(): void
    {
        Storage::fake('local');
        config(['filesystems.default' => 'local']);

        $user = $this->makeCustomer();

        Livewire::actingAs($user)
            ->test(SingleCheck::class)
            ->set('email', 'user@example.com')
            ->call('submit')
            ->assertHasNoErrors();

        $job = VerificationJob::first();

        $this->assertNotNull($job);
        $this->assertSame($user->id, $job->user_id);
        $this->assertSame(VerificationJobOrigin::SingleCheck, $job->origin);
        $this->assertSame(VerificationMode::Standard, $job->verification_mode);
        $this->assertSame('user@example.com', $job->subject_email);
        $this->assertSame(VerificationJobStatus::Processing, $job->status);
        Storage::disk('local')->assertExists($job->input_key);
    }

    public function test_single_check_enhanced_blocked_when_not_entitled(): void
    {
        $this->enableEnhanced();
        config(['engine.enhanced_requires_entitlement' => true]);

        $user = $this->makeCustomer(['enhanced_enabled' => false]);

        Livewire::actingAs($user)
            ->test(SingleCheck::class)
            ->set('email', 'user@example.com')
            ->set('verification_mode', VerificationMode::Enhanced->value)
            ->call('submit')
            ->assertHasErrors(['verification_mode']);

        $this->assertSame(0, VerificationJob::query()->count());
    }

    public function test_upload_respects_enhanced_entitlement(): void
    {
        Storage::fake('local');
        config(['filesystems.default' => 'local']);

        $this->enableEnhanced();
        $user = $this->makeCustomer(['enhanced_enabled' => true]);

        Livewire::actingAs($user)
            ->test(Upload::class)
            ->set('file', UploadedFile::fake()->create('emails.csv', 1, 'text/csv'))
            ->set('verification_mode', VerificationMode::Enhanced->value)
            ->call('save')
            ->assertHasNoErrors();

        $job = VerificationJob::first();
        $this->assertSame(VerificationMode::Enhanced, $job->verification_mode);
    }

    public function test_single_check_does_not_expose_other_user_job(): void
    {
        $owner = $this->makeCustomer();
        $other = $this->makeCustomer(['email' => 'other@example.com']);

        $job = VerificationJob::create([
            'user_id' => $owner->id,
            'status' => VerificationJobStatus::Completed,
            'verification_mode' => VerificationMode::Standard,
            'origin' => VerificationJobOrigin::SingleCheck,
            'subject_email' => 'private@example.com',
            'single_result_status' => 'valid',
            'single_result_sub_status' => 'smtp_connect_ok',
            'single_result_score' => 90,
            'single_result_reason' => 'smtp_connect_ok',
            'single_result_verified_at' => now(),
            'original_filename' => 'single-check.txt',
            'input_key' => 'uploads/'.$owner->id.'/job/input.csv',
        ]);

        Livewire::actingAs($other)
            ->test(SingleCheck::class)
            ->set('jobId', $job->id)
            ->assertDontSee('private@example.com');
    }

    public function test_single_check_rate_limit_applies(): void
    {
        Storage::fake('local');
        config(['filesystems.default' => 'local']);

        $user = $this->makeCustomer();
        RateLimiter::clear('portal-single-check|'.$user->id.'|'.VerificationMode::Standard->value);

        config([
            'engine.single_check_rate_limit_standard' => 1,
            'engine.single_check_rate_limit_decay_seconds' => 60,
        ]);

        Livewire::actingAs($user)
            ->test(SingleCheck::class)
            ->set('email', 'first@example.com')
            ->call('submit')
            ->assertHasNoErrors()
            ->set('email', 'second@example.com')
            ->call('submit')
            ->assertHasErrors(['email']);
    }
}
