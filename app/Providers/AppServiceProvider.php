<?php

namespace App\Providers;

use App\Models\VerificationJob;
use App\Policies\VerificationJobPolicy;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::policy(VerificationJob::class, VerificationJobPolicy::class);

        RateLimiter::for('verifier-api', function (Request $request): Limit {
            $limit = (int) config('verifier.api_rate_limit_per_minute', 120);
            $key = $request->user()?->id ?: $request->ip();

            return Limit::perMinute($limit)->by('verifier-api|'.$key);
        });
    }
}
