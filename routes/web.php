<?php

use App\Http\Controllers\BillingController;
use App\Http\Controllers\Portal\VerificationJobDownloadController;
use App\Livewire\Portal\Dashboard;
use App\Livewire\Portal\JobShow;
use App\Livewire\Portal\JobsIndex;
use App\Livewire\Portal\Settings;
use App\Livewire\Portal\Support;
use App\Livewire\Portal\Upload;
use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;

Route::view('/', 'welcome');

Route::view('dashboard', 'dashboard')
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::view('profile', 'profile')
    ->middleware(['auth'])
    ->name('profile');

Route::middleware(['auth', 'verified'])
    ->prefix('portal')
    ->name('portal.')
    ->group(function () {
        Route::get('dashboard', Dashboard::class)->name('dashboard');
        Route::get('upload', Upload::class)->name('upload');
        Route::get('jobs', JobsIndex::class)->name('jobs.index');
        Route::get('jobs/{job}', JobShow::class)
            ->whereUuid('job')
            ->name('jobs.show');
        Route::get('jobs/{job}/download', VerificationJobDownloadController::class)
            ->whereUuid('job')
            ->name('jobs.download');
        Route::get('settings', Settings::class)->name('settings');
        Route::get('support', Support::class)->name('support');
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

require __DIR__.'/auth.php';

Route::get('/checkout', function (Request $request) {
    return view('checkout');
})->name('checkout');
