<?php

namespace Tests\Feature;

use App\Enums\VerificationJobStatus;
use App\Enums\VerificationMode;
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
