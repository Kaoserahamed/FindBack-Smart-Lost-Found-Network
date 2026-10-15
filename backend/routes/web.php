<?php

use App\Http\Controllers\ClaimController;
use App\Http\Controllers\LostFoundReportController;
use App\Http\Controllers\PhotoController;
use App\Livewire\Dashboard;
use App\Models\Report;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;

Route::get('/', function () {
    return view('pages.home');
})->name('home');

// Authentication (minimal session login).
// Throttled to slow credential-stuffing / brute-force attempts, which were
// previously unlimited.
Route::middleware(['guest', 'throttle:5,1'])->group(function () {
    Route::view('/login', 'auth.login')->name('login');
    Route::post('/login', function (Request $request) {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        if (! auth()->attempt($credentials, $request->boolean('remember'))) {
            throw ValidationException::withMessages([
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

    // Dashboard & notifications (Livewire components - no query logic in views).
    Route::get('/dashboard', Dashboard::class)->name('dashboard');
    Route::view('/notifications', 'notifications')->name('notifications');

    // Submit a claim against a found report.
    // Throttled because it performs file uploads and writes private data.
    Route::post('/reports/{report}/claims', [ClaimController::class, 'store'])
        ->middleware('throttle:10,1')
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

    // Private report photos are streamed through an authorized controller that
    // applies the ReportPolicy - never exposed via a public storage URL.
    Route::get('/photos/{photo}', [PhotoController::class, 'show'])
        ->name('photos.show');
});
