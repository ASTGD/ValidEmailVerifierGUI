<?php

namespace App\Models;

use App\Enums\VerificationJobStatus;
use App\Enums\VerificationOrderStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VerificationOrder extends Model
{
    use HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'user_id',
        'verification_job_id',
        'checkout_intent_id',
        'pricing_plan_id',
        'status',
        'original_filename',
        'input_disk',
        'input_key',
        'email_count',
        'amount_cents',
        'currency',
    ];

    protected $casts = [
        'status' => VerificationOrderStatus::class,
        'email_count' => 'integer',
        'amount_cents' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function job(): BelongsTo
    {
        return $this->belongsTo(VerificationJob::class, 'verification_job_id');
    }

    public function checkoutIntent(): BelongsTo
    {
        return $this->belongsTo(CheckoutIntent::class);
    }

    public function pricingPlan(): BelongsTo
    {
        return $this->belongsTo(PricingPlan::class);
    }

    public function syncStatusFromJob(VerificationJob $job): void
    {
        if (in_array($this->status, [VerificationOrderStatus::Cancelled, VerificationOrderStatus::Fraud], true)) {
            return;
        }

        $mapped = match ($job->status) {
            VerificationJobStatus::Pending => VerificationOrderStatus::Pending,
            VerificationJobStatus::Processing => VerificationOrderStatus::Processing,
            VerificationJobStatus::Completed => VerificationOrderStatus::Delivered,
            VerificationJobStatus::Failed => VerificationOrderStatus::Failed,
        };

        if ($this->status !== $mapped) {
            $this->status = $mapped;
            $this->save();
        }
    }
}
