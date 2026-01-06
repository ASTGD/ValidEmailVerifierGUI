<?php

use App\Http\Controllers\Api\Verifier\VerifierJobCompleteController;
use App\Http\Controllers\Api\Verifier\VerifierJobDownloadController;
use App\Http\Controllers\Api\Verifier\VerifierJobStatusController;
use App\Http\Controllers\Api\Verifier\VerifierJobsController;
use App\Http\Middleware\EnsureVerifierService;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', EnsureVerifierService::class, 'throttle:verifier-api'])
    ->prefix('verifier')
    ->name('api.verifier.')
    ->group(function () {
        Route::get('jobs', [VerifierJobsController::class, 'index'])->name('jobs.index');
        Route::post('jobs/{job}/status', VerifierJobStatusController::class)
            ->whereUuid('job')
            ->name('jobs.status');
        Route::post('jobs/{job}/complete', VerifierJobCompleteController::class)
            ->whereUuid('job')
            ->name('jobs.complete');
        Route::get('jobs/{job}/download', VerifierJobDownloadController::class)
            ->whereUuid('job')
            ->name('jobs.download');
    });
