<?php

namespace App\Livewire;

use App\Models\Category;
use App\Models\Community;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layout')]
class ReportList extends Component
{
    use WithPagination;

    #[Url(as: 'q', history: true)]
    public string $search = '';

    #[Url(as: 'community', history: true)]
    public ?int $communityId = null;

    #[Url(as: 'category', history: true)]
    public ?int $categoryId = null;

    #[Url(as: 'type', history: true)]
    public ?string $type = null;

    /**
     * Reset pagination whenever a filter changes.
     */
    public function updated($property): void
    {
        if (in_array($property, ['search', 'communityId', 'categoryId', 'type'], true)) {
            $this->resetPage();
        }
    }

    /**
     * Clear all applied filters.
     */
    public function clearFilters(): void
    {
        $this->reset(['search', 'communityId', 'categoryId', 'type']);
        $this->resetPage();
    }

    public function render()
    {
        $reports = \App\Models\Report::query()
            ->with(['community', 'category', 'user'])
            ->where('status', 'open')
            ->when($this->search, fn ($q) => $q->where(function ($q) {
                $q->where('title', 'ilike', "%{$this->search}%")
                    ->orWhere('description', 'ilike', "%{$this->search}%")
                    ->orWhere('location_name', 'ilike', "%{$this->search}%");
            }))
            ->when($this->communityId, fn ($q) => $q->where('community_id', $this->communityId))
            ->when($this->categoryId, fn ($q) => $q->where('category_id', $this->categoryId))
            ->when(in_array($this->type, ['lost', 'found'], true), fn ($q) => $q->where('type', $this->type))
            ->latest()
            ->paginate(9);

        return view('livewire.report-list', [
            'reports' => $reports,
            'communities' => Community::orderBy('name')->get(),
            'categories' => Category::where('is_active', true)->orderBy('name')->get(),
        ]);
    }
}
