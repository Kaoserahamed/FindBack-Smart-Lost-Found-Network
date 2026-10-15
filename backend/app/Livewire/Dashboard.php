<?php

namespace App\Livewire;

use App\Models\Report;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layout')]
class Dashboard extends Component
{
    use WithPagination;

    /**
     * The authenticated user's own reports.
     *
     * Uses withCount() so the per-row match totals are computed with two
     * aggregate queries for the whole page instead of two queries per report
     * (previously 10 reports caused 20 separate match-count queries).
     */
    public function reports()
    {
        return Report::query()
            ->with(['community', 'category'])
            ->withCount(['lostMatches', 'foundMatches'])
            ->where('user_id', Auth::id())
            ->latest()
            ->paginate(15);
    }

    public function render()
    {
        return view('livewire.dashboard', [
            'reports' => $this->reports(),
        ]);
    }
}
