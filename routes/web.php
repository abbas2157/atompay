<?php

use App\Http\Controllers\Account\ApplicationController;
use App\Http\Controllers\Account\DashboardController;
use App\Http\Controllers\Account\DocumentController;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Auth\PasswordResetController;
use App\Http\Controllers\Web\EmailAlertsController;
use App\Http\Controllers\Staff\AssessmentController as StaffAssessmentController;
use App\Http\Controllers\Web\AssessmentController;
use App\Http\Controllers\Web\HomeController;
use App\Http\Controllers\Web\QuoteController;
use App\Http\Controllers\Web\RobotsController;
use App\Http\Controllers\Web\SitemapController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public (indexable)
|--------------------------------------------------------------------------
*/
Route::get('/', [HomeController::class, 'index'])->name('home');
Route::get('/faq', [HomeController::class, 'faq'])->name('faq');
Route::get('/sitemap.xml', SitemapController::class)->name('sitemap');
// Generated, not public/robots.txt: the Sitemap line needs this host's absolute URL.
Route::get('/robots.txt', RobotsController::class)->name('robots');

/*
|--------------------------------------------------------------------------
| Tools (public, not indexable)
|--------------------------------------------------------------------------
*/
Route::post('/quote', QuoteController::class)->name('quote')->middleware('throttle:quote');
Route::post('/assess', [AssessmentController::class, 'store'])->name('assess')->middleware('throttle:assess');

/*
|--------------------------------------------------------------------------
| Auth - against AtomShop's users table
|--------------------------------------------------------------------------
*/
Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:login')->name('login.perform');
    Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
    Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:register')->name('register.perform');
    // Sign-up codes: WhatsApp (mobile) + email, then the account is created.
    Route::get('/register/verify', [AuthController::class, 'showVerifySignup'])->name('register.verify');
    Route::post('/register/verify', [AuthController::class, 'verifySignup'])->middleware('throttle:otp_verify')->name('register.verify.perform');
    Route::post('/register/resend', [AuthController::class, 'resendSignupCode'])->middleware('throttle:otp_request')->name('register.resend');

    // Forgot password: code by email or WhatsApp, then a new password.
    Route::get('/forgot-password', [PasswordResetController::class, 'showRequest'])->name('password.request');
    Route::post('/forgot-password', [PasswordResetController::class, 'sendCode'])->middleware('throttle:otp_request')->name('password.email');
    Route::get('/forgot-password/verify', [PasswordResetController::class, 'showVerify'])->name('password.verify');
    Route::post('/forgot-password/verify', [PasswordResetController::class, 'verify'])->middleware('throttle:otp_verify')->name('password.verify.perform');
    Route::post('/forgot-password/resend', [PasswordResetController::class, 'resend'])->middleware('throttle:otp_request')->name('password.resend');
    Route::get('/reset-password', [PasswordResetController::class, 'showReset'])->name('password.reset');
    Route::post('/reset-password', [PasswordResetController::class, 'reset'])->middleware('throttle:otp_verify')->name('password.update');
});
Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth')->name('logout');

// Alert-email unsubscribe from the email itself - signed links, no sign-in.
Route::match(['get', 'post'], '/email/alerts/{user}/off', [EmailAlertsController::class, 'off'])
    ->middleware('signed')->whereNumber('user')->name('email.alerts.off');
Route::post('/email/alerts/{user}/on', [EmailAlertsController::class, 'on'])
    ->middleware('signed')->whereNumber('user')->name('email.alerts.on');

/*
|--------------------------------------------------------------------------
| My AtomPay (customers)
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'customer'])->prefix('my')->name('account.')->group(function () {
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/application', [ApplicationController::class, 'create'])->name('application');
    Route::post('/application', [ApplicationController::class, 'store'])->middleware('throttle:application')->name('application.store');
});

// KYC documents - owner or staff only (checked in the controller).
Route::get('/documents/{profile}/{document}', DocumentController::class)
    ->middleware(['auth', 'throttle:documents'])->where('document', '[a-z-]+')->name('documents.show');

/*
|--------------------------------------------------------------------------
| Staff review (AtomShop admin / amos / manager / recovery roles)
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'staff'])->prefix('staff')->name('staff.')->group(function () {
    Route::get('/assessments', [StaffAssessmentController::class, 'index'])->name('assessments.index');
    Route::get('/assessments/{assessment}', [StaffAssessmentController::class, 'show'])->name('assessments.show');
    Route::post('/assessments/{assessment}/address', [StaffAssessmentController::class, 'verifyAddress'])->name('assessments.address');
    Route::post('/assessments/{assessment}/decide', [StaffAssessmentController::class, 'decide'])->name('assessments.decide');
});
