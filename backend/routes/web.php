<?php

use App\Http\Controllers\ClaimController;
use App\Http\Controllers\LostFoundReportController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// Reporting workflow: submit a lost/found report (requires authentication).
Route::middleware('auth')->post('/reports', [LostFoundReportController::class, 'store'])
    ->name('reports.store');

// Claims workflow (all require authentication).
Route::middleware('auth')->group(function () {
    // Submit a claim against a found report.
    Route::post('/reports/{report}/claims', [ClaimController::class, 'store'])
        ->name('claims.store');

    // Approve/reject a claim (authorized via ClaimPolicy).
    Route::patch('/claims/{claim}/review', [ClaimController::class, 'review'])
        ->name('claims.review');

    // Finalize the handover for an approved claim.
    Route::post('/claims/{claim}/complete-handover', [ClaimController::class, 'completeHandover'])
        ->name('claims.complete-handover');
});


