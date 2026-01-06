<?php

use App\Http\Controllers\Portal\VerificationJobDownloadController;
use App\Livewire\Portal\JobShow;
use App\Livewire\Portal\JobsIndex;
use App\Livewire\Portal\Upload;
use Illuminate\Support\Facades\Route;

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
        Route::get('upload', Upload::class)->name('upload');
        Route::get('jobs', JobsIndex::class)->name('jobs.index');
        Route::get('jobs/{job}', JobShow::class)
            ->whereUuid('job')
            ->name('jobs.show');
        Route::get('jobs/{job}/download', VerificationJobDownloadController::class)
            ->whereUuid('job')
            ->name('jobs.download');
    });

require __DIR__.'/auth.php';
