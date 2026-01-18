<?php

namespace Tests\Feature;

use App\Enums\VerificationJobStatus;
use App\Enums\VerificationMode;
use App\Filament\Resources\VerificationJobs\Pages\ViewVerificationJob;
use App\Models\EngineSetting;
use App\Livewire\Portal\Upload;
use App\Models\User;
use App\Models\VerificationJob;
use App\Support\Roles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class VerificationJobsTest extends TestCase
{
    use RefreshDatabase;

    private function makeAdmin(): User
    {
        $admin = User::factory()->create();
        Role::findOrCreate(Roles::ADMIN, config('auth.defaults.guard'));
        $admin->assignRole(Roles::ADMIN);

        return $admin;
    }

    public function test_customer_can_upload_and_job_is_created(): void
    {
        Storage::fake('local');
        config(['filesystems.default' => 'local']);

        $user = User::factory()->create(['email_verified_at' => now()]);
        Role::findOrCreate(Roles::CUSTOMER, config('auth.defaults.guard'));
        $user->assignRole(Roles::CUSTOMER);

        Livewire::actingAs($user)
            ->test(Upload::class)
            ->set('file', UploadedFile::fake()->create('emails.csv', 1, 'text/csv'))
            ->call('save')
            ->assertHasNoErrors();

        $job = VerificationJob::first();

        $this->assertNotNull($job);
        $this->assertSame($user->id, $job->user_id);
        $this->assertSame(VerificationJobStatus::Processing, $job->status);
        $this->assertSame(VerificationMode::Standard, $job->verification_mode);
        Storage::disk('local')->assertExists($job->input_key);

        $log = $job->logs()->where('event', 'verification_mode_set')->first();
        $this->assertNotNull($log);
        $this->assertSame(VerificationMode::Standard->value, $log->context['to'] ?? null);
        $this->assertSame($user->id, $log->context['actor_id'] ?? null);
    }

    public function test_verification_mode_defaults_to_standard(): void
    {
        $user = User::factory()->create();

        $job = VerificationJob::create([
            'user_id' => $user->id,
            'status' => VerificationJobStatus::Pending,
            'original_filename' => 'emails.csv',
            'input_key' => 'uploads/'.$user->id.'/job/input.csv',
        ]);

        $this->assertSame(VerificationMode::Standard, $job->verification_mode);
    }

    public function test_customer_cannot_request_enhanced_when_disabled(): void
    {
        Storage::fake('local');
        config([
            'filesystems.default' => 'local',
            'engine.enhanced_mode_enabled' => false,
        ]);

        $user = User::factory()->create(['email_verified_at' => now()]);
        Role::findOrCreate(Roles::CUSTOMER, config('auth.defaults.guard'));
        $user->assignRole(Roles::CUSTOMER);

        Livewire::actingAs($user)
            ->test(Upload::class)
            ->set('file', UploadedFile::fake()->create('emails.csv', 1, 'text/csv'))
            ->set('verification_mode', VerificationMode::Enhanced->value)
            ->call('save')
            ->assertHasErrors(['verification_mode']);

        $this->assertSame(0, VerificationJob::query()->count());
    }

    public function test_admin_can_change_mode_only_when_enabled(): void
    {
        $admin = $this->makeAdmin();
        $user = User::factory()->create();

        $job = VerificationJob::create([
            'user_id' => $user->id,
            'status' => VerificationJobStatus::Pending,
            'original_filename' => 'emails.csv',
            'input_key' => 'uploads/'.$user->id.'/job/input.csv',
        ]);

        EngineSetting::query()->update(['enhanced_mode_enabled' => false]);

        $this->actingAs($admin);

        Livewire::test(ViewVerificationJob::class, ['record' => $job->id])
            ->callAction('change_mode', ['verification_mode' => VerificationMode::Enhanced->value]);

        $job->refresh();
        $this->assertSame(VerificationMode::Standard, $job->verification_mode);
        $this->assertDatabaseMissing('verification_job_logs', [
            'verification_job_id' => $job->id,
            'event' => 'verification_mode_changed',
        ]);

        EngineSetting::query()->update(['enhanced_mode_enabled' => true]);

        Livewire::test(ViewVerificationJob::class, ['record' => $job->id])
            ->callAction('change_mode', ['verification_mode' => VerificationMode::Enhanced->value]);

        $job->refresh();
        $this->assertSame(VerificationMode::Enhanced, $job->verification_mode);

        $log = $job->logs()->where('event', 'verification_mode_changed')->first();
        $this->assertNotNull($log);
        $this->assertSame(VerificationMode::Standard->value, $log->context['from'] ?? null);
        $this->assertSame(VerificationMode::Enhanced->value, $log->context['to'] ?? null);
        $this->assertSame($admin->id, $log->context['actor_id'] ?? null);
    }

    public function test_customer_can_download_completed_job(): void
    {
        Storage::fake('local');
        config(['filesystems.default' => 'local']);

        $user = User::factory()->create(['email_verified_at' => now()]);
        Role::findOrCreate(Roles::CUSTOMER, config('auth.defaults.guard'));
        $user->assignRole(Roles::CUSTOMER);

        $job = VerificationJob::create([
            'user_id' => $user->id,
            'status' => VerificationJobStatus::Completed,
            'original_filename' => 'emails.csv',
            'input_key' => 'uploads/'.$user->id.'/job/input.csv',
            'output_key' => 'results/'.$user->id.'/job/cleaned.csv',
            'finished_at' => now(),
        ]);

        Storage::disk('local')->put($job->output_key, 'ok');

        $this->actingAs($user)
            ->get(route('portal.jobs.download', $job))
            ->assertOk();
    }

    public function test_customer_cannot_download_pending_job(): void
    {
        Storage::fake('local');
        config(['filesystems.default' => 'local']);

        $user = User::factory()->create(['email_verified_at' => now()]);
        Role::findOrCreate(Roles::CUSTOMER, config('auth.defaults.guard'));
        $user->assignRole(Roles::CUSTOMER);

        $job = VerificationJob::create([
            'user_id' => $user->id,
            'status' => VerificationJobStatus::Pending,
            'original_filename' => 'emails.csv',
            'input_key' => 'uploads/'.$user->id.'/job/input.csv',
        ]);

        $this->actingAs($user)
            ->get(route('portal.jobs.download', $job))
            ->assertForbidden();
    }
}
