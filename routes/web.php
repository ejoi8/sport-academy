<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\Payments\CheckoutController;
use App\Http\Controllers\Payments\ProofDownloadController;
use App\Http\Controllers\Payments\ReturnController;
use App\Http\Controllers\StudentReportController;
use App\Livewire\PublicSite\BookingWizard;
use App\Livewire\PublicSite\FamilyDashboard;
use App\Livewire\PublicSite\ProgramBrowser;
use App\Livewire\PublicSite\ProgramShow;
use Illuminate\Support\Facades\Route;

Route::get('/', ProgramBrowser::class)->name('home');
Route::get('/programs/{program}', ProgramShow::class)->name('programs.show');
Route::get('/book/{offering}', BookingWizard::class)->name('bookings.create');

Route::middleware('guest')->group(function (): void {
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store'])->middleware('throttle:5,1');
    Route::get('/register', [RegisteredUserController::class, 'create'])->name('register');
    Route::post('/register', [RegisteredUserController::class, 'store'])->middleware('throttle:5,1');
    Route::get('/forgot-password', [PasswordResetLinkController::class, 'create'])->name('password.request');
    Route::post('/forgot-password', [PasswordResetLinkController::class, 'store'])->name('password.email')->middleware('throttle:5,1');
    Route::get('/reset-password/{token}', [NewPasswordController::class, 'create'])->name('password.reset');
    Route::post('/reset-password', [NewPasswordController::class, 'store'])->name('password.store')->middleware('throttle:5,1');
});

Route::middleware('auth')->group(function (): void {
    Route::get('/family', FamilyDashboard::class)->name('family.index');
    Route::post('/payments/enrollments/{enrollment}/checkout', CheckoutController::class)
        ->middleware('throttle:5,1')
        ->name('payments.checkout');
    Route::get('/payments/enrollments/{enrollment}/return', ReturnController::class)
        ->name('payments.return');
    Route::get('/payments/proofs/{proof}', ProofDownloadController::class)
        ->name('payments.proofs.show');
    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');
});

Route::middleware('auth')
    ->get('/students/{student}/report', StudentReportController::class)
    ->name('students.report');
