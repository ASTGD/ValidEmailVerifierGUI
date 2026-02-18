<?php

namespace Tests\Feature;

use App\Models\SmtpPolicyActionAudit;
use App\Models\SmtpPolicySuggestion;
use App\Models\User;
use App\Support\Roles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SmtpPolicySuggestionReviewApiTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsVerifier(): void
    {
        $verifier = User::factory()->create();
        Role::findOrCreate(Roles::VERIFIER_SERVICE, config('auth.defaults.guard'));
        $verifier->assignRole(Roles::VERIFIER_SERVICE);
        Sanctum::actingAs($verifier);
    }

    public function test_review_endpoint_updates_suggestion_status_and_writes_audit(): void
    {
        $this->actingAsVerifier();

        $suggestion = SmtpPolicySuggestion::query()->create([
            'provider' => 'gmail',
            'status' => 'draft',
            'suggestion_type' => 'unknown_rate_regression',
            'source_window' => '7d',
            'suggestion_payload' => ['recommended_actions' => ['set_provider_mode' => 'cautious']],
            'sample_size' => 1500,
            'created_by' => 'system',
        ]);

        $this->postJson(route('api.verifier.policies.suggestions.review'), [
            'suggestion_id' => $suggestion->id,
            'status' => 'approved',
            'review_notes' => [
                'summary' => 'Apply as canary for gmail.',
            ],
        ])->assertOk()
            ->assertJsonPath('data.suggestion_id', $suggestion->id)
            ->assertJsonPath('data.status', 'approved');

        $suggestion->refresh();
        $this->assertSame('approved', $suggestion->status);
        $this->assertNotNull($suggestion->reviewed_at);

        $this->assertDatabaseHas(SmtpPolicyActionAudit::class, [
            'action' => 'suggestion_review',
            'provider' => 'gmail',
            'result' => 'success',
        ]);
    }
}
