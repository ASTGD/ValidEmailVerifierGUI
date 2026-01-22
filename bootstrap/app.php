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
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
