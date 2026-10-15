<?php

use App\Http\Controllers\ClaimController;
use App\Http\Controllers\LostFoundReportController;
use App\Models\Report;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('pages.home');
})->name('home');

// Authentication (minimal session login).
Route::middleware('guest')->group(function () {
    Route::view('/login', 'auth.login')->name('login');
    Route::post('/login', function (\Illuminate\Http\Request $request) {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        if (! auth()->attempt($credentials, $request->boolean('remember'))) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'email' => 'These credentials do not match our records.',
            ]);
        }

        $request->session()->regenerate();

        return redirect()->intended(route('dashboard'));
    })->name('login.attempt');
});

// Public discovery feed.
Route::get('/reports', fn () => view('reports.index'))->name('reports.index');

// Reporting workflow: submit a lost/found report (requires authentication).
Route::middleware('auth')->group(function () {
    Route::post('/reports', [LostFoundReportController::class, 'store'])
        ->name('reports.store');

    // Interactive create form (Livewire).
    Route::get('/reports/create', fn () => view('reports.create'))->name('reports.create');

    // Report detail / claim manager (Livewire).
    Route::get('/reports/{report}', fn (Report $report) => view('reports.show', ['report' => $report]))
        ->name('reports.show');

    // Potential matches for a report (Livewire).
    Route::get('/reports/{report}/matches', fn (Report $report) => view('reports.matches', ['report' => $report]))
        ->name('reports.matches');

    // Dashboard & notifications.
    Route::view('/dashboard', 'dashboard')->name('dashboard');
    Route::view('/notifications', 'notifications')->name('notifications');

    // Submit a claim against a found report.
    Route::post('/reports/{report}/claims', [ClaimController::class, 'store'])
        ->name('claims.store');

    // Approve/reject a claim (authorized via ClaimPolicy).
    Route::patch('/claims/{claim}/review', [ClaimController::class, 'review'])
        ->name('claims.review');

    // Finalize the handover for an approved claim.
    Route::post('/claims/{claim}/complete-handover', [ClaimController::class, 'completeHandover'])
        ->name('claims.complete-handover');

    // Logout.
    Route::post('/logout', function () {
        auth()->logout();
        request()->session()->invalidate();
        request()->session()->regenerateToken();

        return redirect()->route('home');
    })->name('logout');
});



