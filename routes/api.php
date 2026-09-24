<?php

use App\Http\Controllers\Api\V1\AccountController;
use App\Http\Controllers\Api\V1\ApplicationController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\CalculatorController;
use App\Http\Controllers\Api\V1\CityController;
use App\Http\Controllers\Api\V1\DashboardController;
use App\Http\Controllers\Api\V1\DeviceController;
use App\Http\Controllers\Api\V1\KycProfileController;
use App\Http\Controllers\Api\V1\MetaController;
use App\Http\Controllers\Api\V1\NotificationController;
use App\Http\Controllers\Api\V1\PlanController;
use App\Http\Controllers\Api\V1\SessionController;
use App\Models\KycProfile;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Mobile app API - /api/v1/...
|--------------------------------------------------------------------------
| Bearer tokens only (Sanctum, see config/sanctum.php); every error is JSON.
| The contract the Flutter app is built against is docs/api/ (one file per area) -
| change them together, and never break a v1 response shape in place.
*/

Route::prefix('v1')->name('api.v1.')->group(function () {

    // Public: launch config, form options and the calculators.
    Route::middleware('throttle:global')->group(function () {
        Route::get('/app-config', [MetaController::class, 'appConfig'])->name('app-config');
        Route::get('/options', [MetaController::class, 'options'])->name('options');
        Route::get('/calculator', [CalculatorController::class, 'config'])->name('calculator');
    });
    Route::post('/quote', [CalculatorController::class, 'quote'])->middleware('throttle:quote')->name('quote');
    Route::post('/estimate', [CalculatorController::class, 'estimate'])->middleware('throttle:assess')->name('estimate');

    // Sign in / sign up - against AtomShop's users table.
    Route::post('/auth/register', [AuthController::class, 'register'])->middleware('throttle:register')->name('auth.register');
    Route::post('/auth/login', [AuthController::class, 'login'])->middleware('throttle:login')->name('auth.login');

    // Signed-in customers. `customer` also turns away (and signs out) blocked accounts.
    Route::middleware(['auth:sanctum', 'customer', 'throttle:api'])->group(function () {
        Route::post('/auth/logout', [AuthController::class, 'logout'])->name('auth.logout');
        Route::post('/auth/logout-all', [AuthController::class, 'logoutEverywhere'])->name('auth.logout-all');
        Route::get('/auth/sessions', [SessionController::class, 'index'])->name('auth.sessions');
        Route::delete('/auth/sessions/{session}', [SessionController::class, 'destroy'])->whereNumber('session')->name('auth.sessions.destroy');

        Route::get('/me', [AccountController::class, 'show'])->name('me');
        Route::get('/dashboard', [DashboardController::class, 'show'])->name('dashboard');

        // KYC Section 1 - identity and documents.
        Route::get('/profile', [KycProfileController::class, 'show'])->name('profile');
        Route::post('/profile', [KycProfileController::class, 'store'])->middleware('throttle:application')->name('profile.store');
        Route::get('/profile/documents/{document}', [KycProfileController::class, 'document'])
            ->middleware('throttle:documents')
            ->whereIn('document', array_keys(KycProfile::DOCUMENTS))
            ->name('profile.documents');

        // KYC Section 3 - income and the limit it leads to.
        Route::get('/application', [ApplicationController::class, 'show'])->name('application');
        Route::post('/application', [ApplicationController::class, 'store'])->middleware('throttle:application')->name('application.store');
        Route::get('/application/history', [ApplicationController::class, 'history'])->name('application.history');

        // AtomShop orders paid with AtomPay.
        Route::get('/plans', [PlanController::class, 'index'])->name('plans');
        Route::get('/plans/{order}', [PlanController::class, 'show'])->whereNumber('order')->name('plans.show');

        // Push + inbox.
        Route::post('/devices', [DeviceController::class, 'store'])->name('devices.store');
        Route::delete('/devices', [DeviceController::class, 'destroy'])->name('devices.destroy');
        Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications');
        Route::post('/notifications/read-all', [NotificationController::class, 'readAll'])->name('notifications.read-all');
        Route::post('/notifications/{notification}/read', [NotificationController::class, 'read'])->whereNumber('notification')->name('notifications.read');

        Route::get('/cities', [CityController::class, 'index'])->name('cities');
    });
});
