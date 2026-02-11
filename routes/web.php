<?php

use App\Http\Controllers\Admin\SeedSendCampaignCancelController;
use App\Http\Controllers\Admin\SeedSendCampaignHealthController;
use App\Http\Controllers\Admin\SeedSendCampaignPauseController;
use App\Http\Controllers\Admin\SeedSendCampaignRetryFailedController;
use App\Http\Controllers\Admin\SeedSendCampaignStartController;
use App\Http\Controllers\Admin\SeedSendConsentApproveController;
use App\Http\Controllers\Admin\SeedSendConsentRevokeController;
use App\Http\Controllers\BillingController;
use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\MarketingController;
use App\Http\Controllers\Portal\SeedSendConsentRequestController;
use App\Http\Controllers\Portal\SeedSendReportDownloadController;
use App\Http\Controllers\Portal\UploadController;
use App\Http\Controllers\Portal\VerificationJobDownloadController;
use App\Http\Controllers\ProvisioningBundleDownloadController;
use App\Http\Controllers\StripeWebhookController;
use App\Livewire\Portal\Dashboard;
use App\Livewire\Portal\JobShow;
use App\Livewire\Portal\JobsIndex;
use App\Livewire\Portal\OrdersIndex;
use App\Livewire\Portal\Settings;
use App\Livewire\Portal\SingleCheck;
use App\Livewire\Portal\Support;
use App\Livewire\Portal\Upload;
use Illuminate\Support\Facades\Route;

Route::get('/', [MarketingController::class, 'index'])->name('marketing.home');

Route::post(config('cashier.path').'/webhook', [StripeWebhookController::class, 'handleWebhook'])
    ->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class])
    ->name('stripe.webhook');

Route::get('dashboard', function () {
    return redirect()->route('portal.dashboard');
})
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::post('checkout/intent', [CheckoutController::class, 'store'])->name('checkout.intent.store');
Route::get('checkout/{intent}', [CheckoutController::class, 'show'])->name('checkout.show');
Route::get('checkout/{intent}/login', [CheckoutController::class, 'login'])->name('checkout.login');
Route::get('checkout/{intent}/register', [CheckoutController::class, 'register'])->name('checkout.register');
Route::post('checkout/{intent}/pay', [CheckoutController::class, 'pay'])
    ->middleware(['auth', 'verified'])
    ->name('checkout.pay');
Route::post('checkout/{intent}/fake-pay', [CheckoutController::class, 'fakePay'])
    ->middleware(['auth', 'verified'])
    ->name('checkout.fake-pay');

Route::view('profile', 'profile')
    ->middleware(['auth'])
    ->name('profile');

Route::middleware(['auth', 'verified'])
    ->prefix('portal')
    ->name('portal.')
    ->group(function () {
        Route::get('dashboard', Dashboard::class)->name('dashboard');
        Route::get('upload', Upload::class)->name('upload');
        Route::post('upload', UploadController::class)->name('upload.store');
        Route::get('single-check', SingleCheck::class)->name('single-check');
        Route::get('jobs', JobsIndex::class)->name('jobs.index');
        Route::get('jobs/{job}', JobShow::class)
            ->whereUuid('job')
            ->name('jobs.show');
        Route::get('jobs/{job}/download', VerificationJobDownloadController::class)
            ->whereUuid('job')
            ->name('jobs.download');
        Route::post('jobs/{job}/seed-send-consent', SeedSendConsentRequestController::class)
            ->whereUuid('job')
            ->name('jobs.seed-send-consent');
        Route::get('jobs/{job}/seed-send-report', SeedSendReportDownloadController::class)
            ->whereUuid('job')
            ->name('jobs.seed-send-report');
        Route::get('orders', OrdersIndex::class)->name('orders.index');
        Route::get('settings', Settings::class)->name('settings');
        Route::get('support', Support::class)->name('support');
        // Add this line inside the portal route group
        // Route::get('support/{ticket}', \App\Livewire\Portal\SupportDetail::class)->name('support.show');
        Route::get('support/{ticket}', \App\Livewire\Portal\SupportDetail::class)->name('support.show');
    });

Route::middleware(['auth', 'verified', 'admin.role'])
    ->prefix('internal/admin/seed-send')
    ->name('internal.admin.seed-send.')
    ->group(function () {
        Route::post('consents/{consent}/approve', SeedSendConsentApproveController::class)
            ->name('consents.approve');
        Route::post('consents/{consent}/revoke', SeedSendConsentRevokeController::class)
            ->name('consents.revoke');
        Route::post('jobs/{job}/campaigns/start', SeedSendCampaignStartController::class)
            ->whereUuid('job')
            ->name('campaigns.start');
        Route::post('campaigns/{campaign}/state', SeedSendCampaignPauseController::class)
            ->whereUuid('campaign')
            ->name('campaigns.state');
        Route::post('campaigns/{campaign}/cancel', SeedSendCampaignCancelController::class)
            ->whereUuid('campaign')
            ->name('campaigns.cancel');
        Route::post('campaigns/{campaign}/retry-failed', SeedSendCampaignRetryFailedController::class)
            ->whereUuid('campaign')
            ->name('campaigns.retry-failed');
        Route::get('health', SeedSendCampaignHealthController::class)
            ->name('health');
    });

Route::middleware(['auth', 'verified'])
    ->prefix('billing')
    ->name('billing.')
    ->group(function () {
        Route::get('/', [BillingController::class, 'index'])->name('index');
        Route::post('subscribe', [BillingController::class, 'subscribe'])->name('subscribe');
        Route::get('success', [BillingController::class, 'success'])->name('success');
        Route::get('cancel', [BillingController::class, 'cancel'])->name('cancel');
    });

Route::get('provisioning/bundles/{bundle}/{file}', ProvisioningBundleDownloadController::class)
    ->middleware('signed')
    ->name('provisioning-bundles.download');

require __DIR__.'/auth.php';
