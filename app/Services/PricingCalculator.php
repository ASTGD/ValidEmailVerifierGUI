<?php

namespace App\Services;

use App\Models\PricingPlan;
use Illuminate\Support\Collection;

class PricingCalculator
{
    public function tiers(): Collection
    {
        return PricingPlan::query()
            ->where('is_active', true)
            ->orderByRaw('min_emails is null')
            ->orderBy('min_emails')
            ->get();
    }

    public function quoteForEmailCount(int $emailCount): array
    {
        $tiers = $this->tiers();
        $selected = $this->selectTier($tiers, $emailCount);

        $amount = $selected ? $this->calculateAmount($selected, $emailCount) : 0.0;
        $currency = (string) config('cashier.currency', 'usd');

        return [
            'plan' => $selected,
            'amount' => $amount,
            'amount_cents' => (int) round($amount * 100),
            'currency' => $currency,
        ];
    }

    private function selectTier(Collection $tiers, int $emailCount): ?PricingPlan
    {
        if ($tiers->isEmpty()) {
            return null;
        }

        $match = $tiers->first(function (PricingPlan $plan) use ($emailCount) {
            $min = $plan->min_emails ?? 0;
            $max = $plan->max_emails;

            return $emailCount >= $min && ($max === null || $emailCount <= $max);
        });

        if ($match) {
            return $match;
        }

        return $tiers
            ->filter(fn (PricingPlan $plan) => ($plan->min_emails ?? 0) <= $emailCount)
            ->sortByDesc(fn (PricingPlan $plan) => $plan->min_emails ?? 0)
            ->first();
    }

    private function calculateAmount(PricingPlan $plan, int $emailCount): float
    {
        if ($plan->price_per_email !== null) {
            return $emailCount * (float) $plan->price_per_email;
        }

        if ($plan->price_per_1000 !== null) {
            return ($emailCount / 1000) * (float) $plan->price_per_1000;
        }

        return 0.0;
    }
}
