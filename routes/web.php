<?php

use App\Http\Controllers\AdminDashboardController;
use App\Http\Controllers\AdminUserController;
use App\Http\Controllers\Auth\GoogleAuthController;
use App\Http\Controllers\ContentFilterController;
use App\Http\Controllers\GeneratedImageController;
use App\Http\Controllers\MenfessAttachmentController;
use App\Http\Controllers\MenfessController;
use App\Http\Controllers\ModerationController;
use App\Http\Controllers\PublicationController;
use App\Http\Controllers\QrisCallbackController;
use App\Http\Controllers\UserDashboardController;
use App\Http\Controllers\WalletController;
use App\Livewire\Menfess\CreateMenfess;
use Illuminate\Support\Facades\Route;

Route::view('/', 'home')->name('home');
Route::view('/aturan', 'rules')->name('rules');
Route::post('/payments/qris/webhook', QrisCallbackController::class)->middleware('throttle:30,1')->name('payments.qris.webhook');
Route::post('/webhooks/qris', QrisCallbackController::class)->middleware('throttle:30,1')->name('webhooks.qris');

Route::middleware('guest')->group(function () {
    Route::view('/login', 'auth.login')->name('login');
    Route::get('/auth/google', [GoogleAuthController::class, 'redirect'])->middleware('throttle:10,1')->name('auth.google');
    Route::get('/auth/google/callback', [GoogleAuthController::class, 'callback'])->middleware('throttle:20,1')->name('auth.google.callback');
});

Route::middleware(['auth', 'active'])->group(function () {
    Route::post('/logout', [GoogleAuthController::class, 'logout'])->name('logout');
    Route::get('/dashboard', UserDashboardController::class)->name('dashboard');
    Route::get('/menfesses', [MenfessController::class, 'index'])->name('menfesses.index');
    Route::get('/menfesses/create', CreateMenfess::class)->name('menfesses.create');
    Route::get('/menfesses/{menfess}', [MenfessController::class, 'show'])->name('menfesses.show');
    Route::get('/menfess-attachments/{attachment}', [MenfessAttachmentController::class, 'show'])->name('menfess-attachments.show');
    Route::post('/menfesses/{menfess}/images', [GeneratedImageController::class, 'store'])->middleware('throttle:10,1')->name('menfesses.images.store');
    Route::get('/generated-images/{image}/download', [GeneratedImageController::class, 'download'])->name('generated-images.download');
    Route::get('/wallet', [WalletController::class, 'index'])->name('wallet.index');
    Route::post('/wallet/packages/{package}/checkout', [WalletController::class, 'checkout'])->middleware('throttle:10,1')->name('wallet.checkout');
    Route::get('/wallet/orders/{order}', [WalletController::class, 'show'])->name('wallet.orders.show');
    Route::post('/wallet/orders/{order}/check-status', [WalletController::class, 'checkStatus'])->middleware('throttle:15,1')->name('wallet.orders.check-status');
    Route::get('/admin', AdminDashboardController::class)->middleware('role:moderator,publisher,super_admin')->name('admin.dashboard');
    Route::middleware(['role:super_admin', 'throttle:30,1'])->prefix('admin/content-filters')->name('admin.content-filters.')->group(function () {
        Route::get('/', [ContentFilterController::class, 'index'])->name('index');
        Route::post('/rules', [ContentFilterController::class, 'store'])->name('rules.store');
        Route::put('/rules/{rule}', [ContentFilterController::class, 'update'])->name('rules.update');
        Route::delete('/rules/{rule}', [ContentFilterController::class, 'destroy'])->name('rules.destroy');
        Route::post('/exceptions', [ContentFilterController::class, 'storeException'])->name('exceptions.store');
        Route::put('/exceptions/{exception}', [ContentFilterController::class, 'updateException'])->name('exceptions.update');
        Route::delete('/exceptions/{exception}', [ContentFilterController::class, 'destroyException'])->name('exceptions.destroy');
    });
    Route::middleware(['role:super_admin', 'throttle:30,1'])->prefix('admin/users')->name('admin.users.')->group(function () {
        Route::get('/', [AdminUserController::class, 'index'])->name('index');
        Route::get('/{user}', [AdminUserController::class, 'show'])->name('show');
        Route::delete('/{user}/menfesses/{menfess}', [AdminUserController::class, 'destroyMenfess'])->name('menfesses.destroy');
    });
    Route::middleware(['role:moderator,super_admin', 'throttle:30,1'])->prefix('admin/moderation')->name('admin.moderation.')->group(function () {
        Route::get('/', [ModerationController::class, 'index'])->name('index');
        Route::post('/{menfess}/approve', [ModerationController::class, 'approve'])->name('approve');
        Route::post('/{menfess}/reject', [ModerationController::class, 'reject'])->name('reject');
    });
    Route::middleware(['role:publisher,super_admin', 'throttle:30,1'])->prefix('admin/publications')->name('admin.publications.')->group(function () {
        Route::get('/', [PublicationController::class, 'index'])->name('index');
        Route::post('/{publication}/schedule', [PublicationController::class, 'schedule'])->name('schedule');
    });
});
