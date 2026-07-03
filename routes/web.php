<?php

use App\Http\Controllers\StudentReportController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::middleware('auth')
    ->get('/students/{student}/report', StudentReportController::class)
    ->name('students.report');
