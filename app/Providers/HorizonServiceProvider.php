<?php

namespace App\Providers;

use App\Support\EngineSettings;
use App\Support\Roles;
use Illuminate\Support\Facades\Gate;
use Laravel\Horizon\Horizon;
use Laravel\Horizon\HorizonApplicationServiceProvider;

class HorizonServiceProvider extends HorizonApplicationServiceProvider
{
    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        parent::boot();

        Horizon::auth(function ($request) {
            $user = $request->user();

            if (! $user) {
                return false;
            }

            if (! method_exists($user, 'hasRole') || ! $user->hasRole(Roles::ADMIN)) {
                return false;
            }

            return EngineSettings::horizonEnabled();
        });

        // Horizon::routeSmsNotificationsTo('15556667777');
        // Horizon::routeMailNotificationsTo('example@example.com');
        // Horizon::routeSlackNotificationsTo('slack-webhook-url', '#channel');
    }

    /**
     * Register the Horizon gate.
     *
     * This gate determines who can access Horizon in non-local environments.
     */
    protected function gate(): void
    {
        Gate::define('viewHorizon', function ($user = null) {
            if (! $user) {
                return false;
            }

            if (! method_exists($user, 'hasRole') || ! $user->hasRole(Roles::ADMIN)) {
                return false;
            }

            return EngineSettings::horizonEnabled();
        });
    }
}
