<?php

use App\Http\Controllers\Api\Feedback\FeedbackOutcomesController;
use App\Http\Controllers\Api\Internal\EnginePoolController;
use App\Http\Controllers\Api\Internal\EngineServerCommandController;
use App\Http\Controllers\Api\Internal\EngineServerController;
use App\Http\Controllers\Api\Internal\EngineServerProvisioningBundleController;
use App\Http\Controllers\Api\Internal\SmtpDecisionTraceController;
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
use App\Http\Controllers\Api\Verifier\VerifierPolicySuggestionReviewController;
use App\Http\Controllers\Api\Verifier\VerifierPolicyVersionPayloadController;
use App\Http\Controllers\Api\Verifier\VerifierStorageDownloadController;
use App\Http\Controllers\Api\Verifier\VerifierStorageUploadController;
use App\Http\Middleware\EnsureFeedbackIngestor;
use App\Http\Middleware\EnsureVerifierService;
use Illuminate\Support\Facades\Route;

Route::post('seed-send/webhooks/{provider}', SeedSendWebhookController::class)
    ->middleware('throttle:seed-send-webhooks')
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
        Route::get('policy-versions/{version}/payload', VerifierPolicyVersionPayloadController::class)
            ->name('policy-versions.payload');
        Route::post('policies/suggestions/review', VerifierPolicySuggestionReviewController::class)
            ->name('policies.suggestions.review');
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

Route::middleware(['go.internal.token', 'throttle:go-internal-api'])
    ->prefix('internal')
    ->name('api.internal.')
    ->group(function () {
        Route::get('engine-servers', [EngineServerController::class, 'index'])->name('engine-servers.index');
        Route::post('engine-servers', [EngineServerController::class, 'store'])->name('engine-servers.store');
        Route::put('engine-servers/{engineServer}', [EngineServerController::class, 'update'])
            ->name('engine-servers.update');
        Route::delete('engine-servers/{engineServer}', [EngineServerController::class, 'destroy'])
            ->name('engine-servers.destroy');
        Route::post(
            'engine-servers/{engineServer}/provisioning-bundles',
            [EngineServerProvisioningBundleController::class, 'store']
        )->name('engine-servers.provisioning-bundles.store');
        Route::post(
            'engine-servers/{engineServer}/commands',
            [EngineServerCommandController::class, 'store']
        )->name('engine-servers.commands.store');
        Route::get(
            'engine-servers/{engineServer}/commands/{engineServerCommand}',
            [EngineServerCommandController::class, 'show']
        )->name('engine-servers.commands.show');
        Route::get(
            'engine-servers/{engineServer}/provisioning-bundles/latest',
            [EngineServerProvisioningBundleController::class, 'showLatest']
        )->name('engine-servers.provisioning-bundles.latest');
        Route::get('engine-pools', [EnginePoolController::class, 'index'])->name('engine-pools.index');
        Route::post('engine-pools', [EnginePoolController::class, 'store'])->name('engine-pools.store');
        Route::put('engine-pools/{enginePool}', [EnginePoolController::class, 'update'])->name('engine-pools.update');
        Route::post('engine-pools/{enginePool}/archive', [EnginePoolController::class, 'archive'])
            ->name('engine-pools.archive');
        Route::post('engine-pools/{enginePool}/set-default', [EnginePoolController::class, 'setDefault'])
            ->name('engine-pools.set-default');
        Route::get('smtp-decision-traces', [SmtpDecisionTraceController::class, 'index'])
            ->name('smtp-decision-traces.index');
    });
