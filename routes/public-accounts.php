<?php

use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Auth\PasswordChangeController;
use App\Http\Controllers\PublicAccountController;
use Illuminate\Support\Facades\Route;

Route::prefix('public-portal')->name('public.')->group(function (): void {
    Route::middleware('guest')->group(function (): void {
        Route::get('/login', [AuthController::class, 'showPublicLoginForm'])->name('login');
        Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:10,1')->name('login.attempt');
        Route::get('/register', [PublicAccountController::class, 'register'])->name('register');
        Route::post('/register', [PublicAccountController::class, 'store'])->middleware('throttle:public-registration')->name('register.store');
    });
    Route::middleware(['auth', 'resident'])->group(function (): void {
        Route::get('/account', [PublicAccountController::class, 'edit'])->name('account');
        Route::put('/account', [PublicAccountController::class, 'update'])->middleware('throttle:20,1')->name('account.update');
        Route::put('/account/password', [PasswordChangeController::class, 'store'])->middleware('throttle:5,1,password-request-')->name('account.password');
        Route::get('/my-reports', [PublicAccountController::class, 'reports'])->name('reports');
    });
});
