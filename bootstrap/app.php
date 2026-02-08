<?php

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        //
    })
    ->withSchedule(function (Schedule $schedule): void {
        $schedule->command('app:purge-verification-jobs')->daily();
        $schedule->command('prune:email-outcomes')->daily();
        $schedule->command('prune:feedback-imports')->daily();
        $schedule->command('prune:worker-provisioning-bundles')->hourly();
        $schedule->command('metrics:system')->everyMinute()->withoutOverlapping();
        $schedule->command('metrics:queue')->everyMinute()->withoutOverlapping();
        $schedule->command('horizon:snapshot')->everyFiveMinutes()->withoutOverlapping();
        $schedule->command('ops:queue-health')->everyMinute()->withoutOverlapping();
        $schedule->command('ops:queue-rollup')->hourly()->withoutOverlapping();
        $schedule->command('ops:queue-slo-report')->everyFiveMinutes()->withoutOverlapping();
        $schedule->command('ops:queue-prune')->daily()->withoutOverlapping();
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
