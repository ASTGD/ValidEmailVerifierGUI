<?php

namespace App\Models;

use App\Enums\CheckoutIntentStatus;
use App\Enums\VerificationJobStatus;
use App\Enums\VerificationOrderStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class VerificationOrder extends Model
{

    protected $fillable = [
        'user_id',
        'verification_job_id',
        'checkout_intent_id',
        'pricing_plan_id',
        'order_number',
        'status',
        'original_filename',
        'input_disk',
        'input_key',
        'email_count',
        'amount_cents',
        'currency',
        'refunded_at',
    ];

    protected $casts = [
        'status' => VerificationOrderStatus::class,
        'email_count' => 'integer',
        'amount_cents' => 'integer',
        'refunded_at' => 'datetime',
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

    public function paymentStatusKey(): string
    {
        if ($this->refunded_at) {
            return 'refunded';
        }

        $status = $this->checkoutIntent?->status;

        return match ($status) {
            CheckoutIntentStatus::Completed => 'paid',
            CheckoutIntentStatus::Pending => 'unpaid',
            CheckoutIntentStatus::Expired, CheckoutIntentStatus::Canceled => 'failed',
            default => 'unpaid',
        };
    }

    public function paymentStatusLabel(): string
    {
        return match ($this->paymentStatusKey()) {
            'paid' => __('Paid'),
            'failed' => __('Failed'),
            'refunded' => __('Refunded'),
            default => __('Unpaid'),
        };
    }

    public function paymentMethodLabel(): string
    {
        $method = $this->checkoutIntent?->payment_method;

        if (! $method) {
            return '-';
        }

        return Str::headline($method);
    }

    public static function generateOrderNumber(): string
    {
        $prefix = (string) config('verifier.order_number_prefix', 'ORD');

        do {
            $candidate = sprintf('%s-%s', strtoupper($prefix), strtoupper(Str::random(8)));
        } while (self::query()->where('order_number', $candidate)->exists());

        return $candidate;
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
