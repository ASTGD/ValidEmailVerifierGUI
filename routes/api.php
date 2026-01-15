<?php

use App\Http\Controllers\Api\Verifier\VerifierHeartbeatController;
use App\Http\Controllers\Api\Verifier\VerifierChunkCompleteController;
use App\Http\Controllers\Api\Verifier\VerifierChunkDetailsController;
use App\Http\Controllers\Api\Verifier\VerifierChunkFailController;
use App\Http\Controllers\Api\Verifier\VerifierChunkLogController;
use App\Http\Controllers\Api\Verifier\VerifierJobClaimController;
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
        Route::post('heartbeat', VerifierHeartbeatController::class)->name('heartbeat');
        Route::get('jobs', [VerifierJobsController::class, 'index'])->name('jobs.index');
        Route::post('jobs/{job}/claim', VerifierJobClaimController::class)
            ->whereUuid('job')
            ->name('jobs.claim');
        Route::post('jobs/{job}/status', VerifierJobStatusController::class)
            ->whereUuid('job')
            ->name('jobs.status');
        Route::post('jobs/{job}/complete', VerifierJobCompleteController::class)
            ->whereUuid('job')
            ->name('jobs.complete');
        Route::get('jobs/{job}/download', VerifierJobDownloadController::class)
            ->whereUuid('job')
            ->name('jobs.download');

        Route::prefix('chunks')->name('chunks.')->group(function () {
            Route::get('{chunk}', VerifierChunkDetailsController::class)
                ->whereUuid('chunk')
                ->name('show');
            Route::post('{chunk}/log', VerifierChunkLogController::class)
                ->whereUuid('chunk')
                ->name('log');
            Route::post('{chunk}/fail', VerifierChunkFailController::class)
                ->whereUuid('chunk')
                ->name('fail');
            Route::post('{chunk}/complete', VerifierChunkCompleteController::class)
                ->whereUuid('chunk')
                ->name('complete');
        });
    });
