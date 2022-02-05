<?php
/** API Routes */

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\NewPasswordController;
use App\Http\Controllers\Api\Auth\RegisterController;
use App\Http\Controllers\Api\Account\CreatePinController;
use App\Http\Controllers\Api\Auth\PasswordResetController;
use App\Http\Controllers\Api\Auth\AuthenticationController;
use App\Http\Controllers\Api\NewVerificationTokenController;
use App\Http\Controllers\Api\Account\ChangePasswordController;
use App\Http\Controllers\Api\Auth\AccountTypeCollectionController;

/** Authentication routes */
Route::prefix('auth')->group(function () {
    /** Account type routes */
    Route::get('/account-type', AccountTypeCollectionController::class)->name('auth.account-type');

    /** Generate new token */
    Route::get('/new-verification-token', NewVerificationTokenController::class)->name('auth.new-verification-token');

    /** Login route */
    Route::post('/login', [AuthenticationController::class, 'login']);

    /** Register routes */
    Route::prefix('/register')->group(function () {
            Route::post('/', [RegisterController::class, 'register'])->name('auth.register');
            Route::middleware('auth:sanctum')
                ->post('/verify', [RegisterController::class, 'verify'])->name('auth.verify');
        });

    Route::prefix('/password')->group(function () {
        /** Forgot password route */
        Route::post('/forgot-password', PasswordResetController::class)->name('password.phone');

        /** Reset password route */
        Route::post('/reset-password', NewPasswordController::class)->name('password.update');
    });

    /** Authenticated routes */
    Route::middleware('auth:sanctum')->prefix('account')->group(function () {
        Route::middleware('account.verified')->group(function () {
            /** Updates routes */
            Route::prefix('/update')->group(function () {
                /** Change password route */
                Route::post('/change-password', ChangePasswordController::class)->name('account.update.change-password');
            });

            /** Create pin route */
            Route::post('/create-pin', CreatePinController::class)->name('account.create-pin');

        });

        /** Logout route */
        Route::post('/logout', [AuthenticationController::class, 'logout'])->name('account.logout');
    });
});
