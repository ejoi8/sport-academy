<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\Payments\CheckoutController;
use App\Http\Controllers\Payments\ProofDownloadController;
use App\Http\Controllers\Payments\ReturnController;
use App\Http\Controllers\Reports\AttendanceReportController;
use App\Http\Controllers\Reports\CreditLiabilityReportController;
use App\Http\Controllers\Reports\EnrollmentsExportController;
use App\Http\Controllers\Reports\PaymentsExportController;
use App\Http\Controllers\Reports\ProgressReportController;
use App\Http\Controllers\Reports\RevenueReportController;
use App\Http\Controllers\SeoController;
use App\Http\Controllers\StudentReportController;
use App\Livewire\PublicSite\BookingWizard;
use App\Livewire\PublicSite\FamilyDashboard;
use App\Livewire\PublicSite\ProgramBrowser;
use App\Livewire\PublicSite\ProgramShow;
use Illuminate\Support\Facades\Route;

Route::get('/robots.txt', [SeoController::class, 'robots']);
Route::get('/sitemap.xml', [SeoController::class, 'sitemap'])->name('sitemap');

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

// Staff reports — print sheets and CSV exports (each controller enforces the staff role).
Route::middleware('auth')->group(function (): void {
    Route::get('/reports/revenue', RevenueReportController::class)->name('reports.revenue');
    Route::get('/reports/credit-liability', CreditLiabilityReportController::class)->name('reports.credit-liability');
    Route::get('/reports/attendance', AttendanceReportController::class)->name('reports.attendance');
    Route::get('/reports/progress', ProgressReportController::class)->name('reports.progress');
    Route::get('/reports/enrolments.csv', EnrollmentsExportController::class)->name('reports.enrollments.csv');
    Route::get('/reports/payments.csv', PaymentsExportController::class)->name('reports.payments.csv');
});
