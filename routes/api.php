<?php

use App\Http\Controllers\Api\Feedback\FeedbackOutcomesController;
use App\Http\Controllers\Api\Monitor\MonitorChecksController;
use App\Http\Controllers\Api\Monitor\MonitorConfigController;
use App\Http\Controllers\Api\Monitor\MonitorServersController;
use App\Http\Controllers\Api\SeedSend\SeedSendWebhookController;
use App\Http\Controllers\Api\Verifier\VerifierChunkClaimNextController;
use App\Http\Controllers\Api\Verifier\VerifierChunkCompleteController;
use App\Http\Controllers\Api\Verifier\VerifierChunkDetailsController;
use App\Http\Controllers\Api\Verifier\VerifierChunkFailController;
use App\Http\Controllers\Api\Verifier\VerifierChunkInputUrlController;
use App\Http\Controllers\Api\Verifier\VerifierChunkLogController;
use App\Http\Controllers\Api\Verifier\VerifierChunkOutputUrlsController;
use App\Http\Controllers\Api\Verifier\VerifierHeartbeatController;
use App\Http\Controllers\Api\Verifier\VerifierJobClaimController;
use App\Http\Controllers\Api\Verifier\VerifierJobCompleteController;
use App\Http\Controllers\Api\Verifier\VerifierJobDownloadController;
use App\Http\Controllers\Api\Verifier\VerifierJobsController;
use App\Http\Controllers\Api\Verifier\VerifierJobStatusController;
use App\Http\Controllers\Api\Verifier\VerifierPolicyController;
use App\Http\Controllers\Api\Verifier\VerifierStorageDownloadController;
use App\Http\Controllers\Api\Verifier\VerifierStorageUploadController;
use App\Http\Middleware\EnsureFeedbackIngestor;
use App\Http\Middleware\EnsureVerifierService;
use Illuminate\Support\Facades\Route;

Route::post('seed-send/webhooks/{provider}', SeedSendWebhookController::class)
    ->name('api.seed-send.webhook');

Route::middleware('signed')
    ->prefix('verifier/storage')
    ->name('api.verifier.storage.')
    ->group(function () {
        Route::get('download', VerifierStorageDownloadController::class)->name('download');
        Route::match(['put', 'post'], 'upload', VerifierStorageUploadController::class)->name('upload');
    });

Route::middleware(['auth:sanctum', EnsureVerifierService::class, 'throttle:verifier-api'])
    ->prefix('verifier')
    ->name('api.verifier.')
    ->group(function () {
        Route::get('policy', VerifierPolicyController::class)->name('policy');
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
            Route::post('claim-next', VerifierChunkClaimNextController::class)->name('claim-next');
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
            Route::get('{chunk}/input-url', VerifierChunkInputUrlController::class)
                ->whereUuid('chunk')
                ->name('input-url');
            Route::post('{chunk}/output-urls', VerifierChunkOutputUrlsController::class)
                ->whereUuid('chunk')
                ->name('output-urls');
        });
    });

Route::middleware(['auth:sanctum', EnsureFeedbackIngestor::class, 'throttle:feedback-api'])
    ->prefix('feedback')
    ->name('api.feedback.')
    ->group(function () {
        Route::post('outcomes', FeedbackOutcomesController::class)->name('outcomes.store');
    });

Route::middleware(['auth:sanctum', EnsureVerifierService::class, 'throttle:verifier-api'])
    ->prefix('monitor')
    ->name('api.monitor.')
    ->group(function () {
        Route::get('config', MonitorConfigController::class)->name('config');
        Route::get('servers', MonitorServersController::class)->name('servers');
        Route::post('checks', MonitorChecksController::class)->name('checks');
    });
