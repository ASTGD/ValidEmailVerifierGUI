<?php

namespace Tests\Feature;

use App\Models\EmailVerificationOutcomeIngestion;
use App\Models\User;
use App\Support\Roles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class FeedbackApiLimitsTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsAdmin(): User
    {
        $user = User::factory()->create();
        Role::findOrCreate(Roles::ADMIN, config('auth.defaults.guard'));
        $user->assignRole(Roles::ADMIN);

        Sanctum::actingAs($user);

        return $user;
    }

    public function test_feedback_api_rejects_too_many_items(): void
    {
        $this->actingAsAdmin();

        config([
            'engine.feedback_api_enabled' => true,
            'engine.feedback_max_items_per_request' => 1,
        ]);

        $payload = [
            'source' => 'api_test',
            'items' => [
                ['email' => 'one@example.com', 'outcome' => 'valid'],
                ['email' => 'two@example.com', 'outcome' => 'invalid'],
            ],
        ];

        $this->postJson(route('api.feedback.outcomes.store'), $payload)
            ->assertStatus(422);
    }

    public function test_feedback_api_disabled_returns_forbidden(): void
    {
        $this->actingAsAdmin();

        config([
            'engine.feedback_api_enabled' => false,
        ]);

        $payload = [
            'source' => 'api_test',
            'items' => [
                ['email' => 'one@example.com', 'outcome' => 'valid'],
            ],
        ];

        $this->postJson(route('api.feedback.outcomes.store'), $payload)
            ->assertStatus(403);
    }

    public function test_feedback_api_creates_ingestion_audit_record(): void
    {
        $user = $this->actingAsAdmin();

        config([
            'engine.feedback_api_enabled' => true,
            'engine.feedback_max_items_per_request' => 10,
            'engine.feedback_max_payload_kb' => 512,
        ]);

        $payload = [
            'source' => 'api_test',
            'items' => [
                ['email' => 'one@example.com', 'outcome' => 'valid'],
                ['email' => 'two@example.com', 'outcome' => 'invalid'],
            ],
        ];

        $this->postJson(route('api.feedback.outcomes.store'), $payload)
            ->assertOk();

        $this->assertDatabaseHas('email_verification_outcome_ingestions', [
            'type' => 'api',
            'source' => 'api_test',
            'item_count' => 2,
            'imported_count' => 2,
            'skipped_count' => 0,
            'user_id' => $user->id,
        ]);

        $this->assertSame(1, EmailVerificationOutcomeIngestion::query()->count());
    }
}
