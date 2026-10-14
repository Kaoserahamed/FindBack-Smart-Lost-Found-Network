<?php

use App\Http\Controllers\LostFoundReportController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// Reporting workflow: submit a lost/found report (requires authentication).
Route::middleware('auth')->post('/reports', [LostFoundReportController::class, 'store'])
    ->name('reports.store');

