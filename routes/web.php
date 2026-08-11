<?php

use App\Http\Controllers\Web\AcquisitionWebController;
use App\Http\Controllers\Web\AnnouncementWebController;
use App\Http\Controllers\Web\Auth\ForgotPasswordController;
use App\Http\Controllers\Web\Auth\LoginController;
use App\Http\Controllers\Web\Auth\ResetPasswordController;
use App\Http\Controllers\Web\ContextController;
use App\Http\Controllers\Web\DashboardController;
use App\Http\Controllers\Web\DiagnosticsWebController;
use App\Http\Controllers\Web\EditorialWebController;
use App\Http\Controllers\Web\PreviewWebController;
use App\Http\Controllers\Web\ProfileController;
use App\Http\Controllers\Web\QueueWebController;
use App\Http\Controllers\Web\SettingsWebController;
use App\Http\Controllers\Web\SetupController;
use App\Http\Controllers\Web\SourceWebController;
use App\Http\Middleware\EnsureOperatorContext;
use App\Http\Middleware\EnsureWebPermission;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    if (auth()->check()) {
        return redirect()->route('app.home');
    }

    return redirect()->route('login');
});

Route::middleware('guest')->group(function (): void {
    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store']);
    Route::get('/forgot-password', [ForgotPasswordController::class, 'create'])->name('password.request');
    Route::post('/forgot-password', [ForgotPasswordController::class, 'store'])->name('password.email');
    Route::get('/reset-password/{token}', [ResetPasswordController::class, 'create'])->name('password.reset');
    Route::post('/reset-password', [ResetPasswordController::class, 'store'])->name('password.update');
});

Route::post('/logout', [LoginController::class, 'destroy'])
    ->middleware('auth')
    ->name('logout');

Route::get('/setup', [SetupController::class, 'show'])->name('setup.show');
Route::post('/setup', [SetupController::class, 'store'])->name('setup.store');

Route::middleware('auth')->group(function (): void {
    Route::get('/app', function () {
        return redirect()->route('app.dashboard');
    })->name('app.home');

    Route::get('/app/context', [ContextController::class, 'select'])->name('app.context.select');
    Route::post('/app/context', [ContextController::class, 'store'])->name('app.context.store');

    Route::get('/app/profile', [ProfileController::class, 'edit'])->name('app.profile.edit');
    Route::put('/app/profile', [ProfileController::class, 'update'])->name('app.profile.update');
    Route::put('/app/profile/password', [ProfileController::class, 'updatePassword'])->name('app.profile.password');

    Route::middleware(EnsureOperatorContext::class)->prefix('app')->name('app.')->group(function (): void {
        Route::get('/dashboard', DashboardController::class)->name('dashboard');

        Route::middleware(EnsureWebPermission::class.':sources.view')->group(function (): void {
            Route::get('/sources', [SourceWebController::class, 'index'])->name('sources.index');
        });
        Route::middleware(EnsureWebPermission::class.':sources.manage')->group(function (): void {
            Route::get('/sources/create', [SourceWebController::class, 'create'])->name('sources.create');
            Route::post('/sources', [SourceWebController::class, 'store'])->name('sources.store');
            Route::get('/sources/{source}/edit', [SourceWebController::class, 'edit'])->name('sources.edit');
            Route::put('/sources/{source}', [SourceWebController::class, 'update'])->name('sources.update');
            Route::delete('/sources/{source}', [SourceWebController::class, 'destroy'])->name('sources.destroy');
            Route::post('/sources/{source}/enable', [SourceWebController::class, 'enable'])->name('sources.enable');
            Route::post('/sources/{source}/disable', [SourceWebController::class, 'disable'])->name('sources.disable');
        });
        Route::middleware(EnsureWebPermission::class.':sources.run')->group(function (): void {
            Route::post('/sources/{source}/run', [SourceWebController::class, 'run'])->name('sources.run');
            Route::post('/sources/{source}/check', [SourceWebController::class, 'check'])->name('sources.check');
        });

        Route::middleware(EnsureWebPermission::class.':acquisition.view')->group(function (): void {
            Route::get('/acquisition', [AcquisitionWebController::class, 'index'])->name('acquisition.index');
            Route::get('/acquisition/runs/{run}', [AcquisitionWebController::class, 'show'])->name('acquisition.show');
        });
        Route::middleware(EnsureWebPermission::class.':acquisition.run')->group(function (): void {
            Route::post('/acquisition/run-due', [AcquisitionWebController::class, 'runDue'])->name('acquisition.run-due');
            Route::post('/acquisition/runs/{run}/retry', [AcquisitionWebController::class, 'retry'])->name('acquisition.retry');
            Route::post('/acquisition/jobs/{platformJob}/cancel', [AcquisitionWebController::class, 'cancelPending'])->name('acquisition.cancel');
        });

        Route::middleware(EnsureWebPermission::class.':announcements.view')->group(function (): void {
            Route::get('/announcements', [AnnouncementWebController::class, 'index'])->name('announcements.index');
            Route::get('/announcements/{announcement}', [AnnouncementWebController::class, 'show'])->name('announcements.show');
        });

        Route::middleware(EnsureWebPermission::class.':editorial.view')->group(function (): void {
            Route::get('/editorial/announcements/{announcement}', [EditorialWebController::class, 'show'])->name('editorial.show');
            Route::get('/preview/announcements/{announcement}', [PreviewWebController::class, 'show'])->name('preview.show');
            Route::get('/preview/announcements/{announcement}/download', [PreviewWebController::class, 'download'])->name('preview.download');
        });
        Route::middleware(EnsureWebPermission::class.':editorial.generate')->group(function (): void {
            Route::post('/editorial/announcements/{announcement}/generate', [EditorialWebController::class, 'generate'])->name('editorial.generate');
        });
        Route::middleware(EnsureWebPermission::class.':editorial.regenerate')->group(function (): void {
            Route::post('/editorial/announcements/{announcement}/regenerate', [EditorialWebController::class, 'regenerate'])->name('editorial.regenerate');
            Route::post('/preview/announcements/{announcement}/regenerate', [PreviewWebController::class, 'regenerate'])->name('preview.regenerate');
        });

        Route::middleware(EnsureWebPermission::class.':jobs.view')->group(function (): void {
            Route::get('/queue', [QueueWebController::class, 'index'])->name('queue.index');
            Route::get('/queue/{platformJob}', [QueueWebController::class, 'show'])->name('queue.show');
            Route::post('/queue/{platformJob}/retry', [QueueWebController::class, 'retry'])->name('queue.retry');
            Route::post('/queue/{platformJob}/cancel', [QueueWebController::class, 'cancel'])->name('queue.cancel');
        });

        Route::middleware(EnsureWebPermission::class.':editorial.diagnostics')->group(function (): void {
            Route::get('/diagnostics', DiagnosticsWebController::class)->name('diagnostics');
        });

        Route::middleware(EnsureWebPermission::class.':config.manage')->group(function (): void {
            Route::get('/settings', [SettingsWebController::class, 'edit'])->name('settings.edit');
            Route::post('/settings/ai', [SettingsWebController::class, 'updateAi'])->name('settings.ai');
            Route::post('/settings/flags', [SettingsWebController::class, 'updateFlags'])->name('settings.flags');
            Route::post('/settings/content-intelligence', [SettingsWebController::class, 'updateContentIntelligence'])->name('settings.content-intelligence');
        });
    });
});
