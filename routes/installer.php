<?php

use App\Http\Middleware\EnsureNotInstalled;
use App\Livewire\Installer\AdminAccountStep;
use App\Livewire\Installer\EnvironmentStep;
use App\Livewire\Installer\FinishStep;
use App\Livewire\Installer\MigrateStep;
use App\Livewire\Installer\RequirementsStep;
use Illuminate\Support\Facades\Route;

Route::middleware(EnsureNotInstalled::class)->prefix('install')->name('install.')->group(function () {
    Route::redirect('/', '/install/requirements');
    Route::get('/requirements', RequirementsStep::class)->name('requirements');
    Route::get('/environment', EnvironmentStep::class)->name('environment');
    Route::get('/migrate', MigrateStep::class)->name('migrate');
    Route::get('/admin', AdminAccountStep::class)->name('admin');
    Route::get('/finish', FinishStep::class)->name('finish');
});
