<?php

use App\Http\Controllers\Admin\AuditLogController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\SettingController;
use App\Http\Controllers\Admin\ShareController;
use App\Http\Controllers\Admin\UserController;
use Illuminate\Support\Facades\Route;

Route::prefix(config('kexi.admin_path'))
    ->name('admin.')
    ->middleware(['auth', 'active', 'admin', 'password.confirm'])
    ->group(function (): void {
        Route::get('/', DashboardController::class)->name('dashboard');
        Route::get('/users', [UserController::class, 'index'])->name('users.index');
        Route::get('/shares', [ShareController::class, 'index'])->name('shares.index');
        Route::get('/settings', [SettingController::class, 'edit'])->name('settings.edit');
        Route::get('/audits', [AuditLogController::class, 'index'])->name('audits.index');

        Route::middleware('throttle:admin-mutation')->group(function (): void {
            Route::patch('/users/{user}/ban', [UserController::class, 'ban'])
                ->whereNumber('user')
                ->name('users.ban');
            Route::patch('/users/{user}/unban', [UserController::class, 'unban'])
                ->whereNumber('user')
                ->name('users.unban');
            Route::patch('/users/{user}/review/start', [UserController::class, 'startReview'])
                ->whereNumber('user')
                ->name('users.review.start');
            Route::patch('/users/{user}/review/clear', [UserController::class, 'clearReview'])
                ->whereNumber('user')
                ->name('users.review.clear');
            Route::patch('/users/{user}/sharing/disable', [UserController::class, 'disableSharing'])
                ->whereNumber('user')
                ->name('users.sharing.disable');
            Route::patch('/users/{user}/sharing/enable', [UserController::class, 'enableSharing'])
                ->whereNumber('user')
                ->name('users.sharing.enable');

            Route::put('/settings', [SettingController::class, 'update'])->name('settings.update');

            Route::patch('/shares/{share}/disable', [ShareController::class, 'disable'])
                ->whereNumber('share')
                ->name('shares.disable');
            Route::patch('/shares/{share}/enable', [ShareController::class, 'enable'])
                ->whereNumber('share')
                ->name('shares.enable');
            Route::delete('/shares/{share}', [ShareController::class, 'revoke'])
                ->whereNumber('share')
                ->name('shares.revoke');
        });
    });
