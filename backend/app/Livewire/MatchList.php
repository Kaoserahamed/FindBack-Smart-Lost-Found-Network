<?php

namespace App\Livewire;

use App\Models\Report;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layout')]
class MatchList extends Component
{
    public Report $report;

    public function mount(Report $report): void
    {
        $this->report = $report->load(['community', 'category']);

        // Only the report owner (or a community moderator) may view its matches.
        abort_unless($this->canView(), 403);
    }

    protected function canView(): bool
    {
        $user = auth()->user();

        if (! $user) {
            return false;
        }

        if ($this->report->user_id === $user->id) {
            return true;
        }

        return $this->report->community
            ->memberships()
            ->where('user_id', $user->id)
            ->where('role', 'moderator')
            ->where('status', 'active')
            ->exists();
    }

    public function render()
    {
        // Matches where this report is the lost side…
        $asLost = $this->report->lostMatches()->with('foundReport')->get();

        // …and where it is the found side.
        $asFound = $this->report->foundMatches()->with('lostReport')->get();

        $matches = $asLost->merge($asFound)->sortByDesc('score')->values();

        return view('livewire.match-list', [
            'matches' => $matches,
        ]);
    }
}
