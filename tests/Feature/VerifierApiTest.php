<?php

namespace Tests\Feature;

use App\Enums\VerificationJobStatus;
use App\Models\User;
use App\Models\VerificationJob;
use App\Support\Roles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class VerifierApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_verifier_api_requires_authentication(): void
    {
        $this->getJson('/api/verifier/jobs')
            ->assertUnauthorized();
    }

    public function test_verifier_api_requires_verifier_role(): void
    {
        $user = User::factory()->create();
        Role::findOrCreate(Roles::CUSTOMER, config('auth.defaults.guard'));
        $user->assignRole(Roles::CUSTOMER);

        Sanctum::actingAs($user);

        $this->getJson('/api/verifier/jobs')
            ->assertForbidden();
    }

    public function test_verifier_can_list_and_update_jobs(): void
    {
        $verifier = User::factory()->create();
        Role::findOrCreate(Roles::VERIFIER_SERVICE, config('auth.defaults.guard'));
        $verifier->assignRole(Roles::VERIFIER_SERVICE);

        $customer = User::factory()->create();

        $job = VerificationJob::create([
            'user_id' => $customer->id,
            'status' => VerificationJobStatus::Pending,
            'original_filename' => 'emails.csv',
            'input_key' => 'uploads/'.$customer->id.'/job/input.csv',
        ]);

        Sanctum::actingAs($verifier);

        $this->getJson('/api/verifier/jobs')
            ->assertOk()
            ->assertJsonFragment(['id' => (string) $job->id]);

        $this->postJson(route('api.verifier.jobs.status', $job), [
            'status' => 'processing',
        ])->assertOk();

        $job->refresh();
        $this->assertSame(VerificationJobStatus::Processing, $job->status);
        $this->assertNotNull($job->started_at);

        $this->postJson(route('api.verifier.jobs.complete', $job), [
            'output_key' => 'results/'.$customer->id.'/job/cleaned.csv',
            'total_emails' => 10,
            'valid_count' => 7,
            'invalid_count' => 2,
            'risky_count' => 1,
            'unknown_count' => 0,
        ])->assertOk();

        $job->refresh();
        $this->assertSame(VerificationJobStatus::Completed, $job->status);
        $this->assertSame(10, $job->total_emails);
    }
}
