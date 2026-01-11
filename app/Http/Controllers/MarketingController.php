<?php

namespace App\Http\Controllers;

use App\Services\PricingCalculator;
use Illuminate\View\View;

class MarketingController
{
    public function index(PricingCalculator $pricing): View
    {
        return view('welcome', [
            'pricingTiers' => $pricing->tiers(),
        ]);
    }
}
