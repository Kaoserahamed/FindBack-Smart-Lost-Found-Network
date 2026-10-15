<?php

namespace App\Livewire;

use App\Jobs\GenerateMatches;
use App\Models\Category;
use App\Models\Community;
use App\Models\CommunityMembership;
use App\Models\Report;
use App\Traits\RecordsItemEvents;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Validate;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Layout('components.layout')]
class ReportCreate extends Component
{
    use RecordsItemEvents, WithFileUploads;

    public int $step = 1;

    // Step 1 — classification
    #[Validate('required|integer')]
    public ?int $community_id = null;

    #[Validate('required|integer')]
    public ?int $category_id = null;

    #[Validate('required|in:lost,found')]
    public string $type = 'lost';

    // Step 2 — details
    #[Validate('required|string|max:120')]
    public string $title = '';

    #[Validate('required|string|max:3000')]
    public string $description = '';

    #[Validate('nullable|string|max:80')]
    public string $color = '';

    #[Validate('nullable|string|max:100')]
    public string $brand = '';

    // Step 3 — when & where
    #[Validate('required|date|before_or_equal:now')]
    public string $event_at = '';

    #[Validate('required|string|max:255')]
    public string $location_name = '';

    #[Validate('nullable|numeric|between:-90,90')]
    public ?string $latitude = null;

    #[Validate('nullable|numeric|between:-180,180')]
    public ?string $longitude = null;

    // Step 4 — photos
    #[Validate([
        'photos' => 'nullable|array|max:4',
        'photos.*' => 'image|mimes:jpg,jpeg,png,webp|max:5120',
    ])]
    public array $photos = [];

    /**
     * The communities the authenticated user may post into (active member).
     */
    public function communities()
    {
        return Community::query()
            ->whereHas('memberships', fn ($q) => $q
                ->where('user_id', Auth::id())
                ->where('status', 'active'))
            ->orderBy('name')
            ->get();
    }

    public function categories()
    {
        return Category::where('is_active', true)->orderBy('name')->get();
    }

    /**
     * Ensure the selected community is one the user is an active member of.
     * Enforced both when advancing and again at submit time (never trust client).
     */
    protected function userIsActiveMember(int $communityId): bool
    {
        return CommunityMembership::query()
            ->where('community_id', $communityId)
            ->where('user_id', Auth::id())
            ->where('status', 'active')
            ->exists();
    }

    public function nextStep(): void
    {
        if ($this->step === 1) {
            $this->validateOnly('community_id');
            $this->validateOnly('category_id');
            $this->validateOnly('type');

            // Authorization gate for the chosen community.
            abort_unless($this->community_id && $this->userIsActiveMember($this->community_id), 403);
        }

        if ($this->step === 2) {
            $this->validateOnly('title');
            $this->validateOnly('description');
        }

        if ($this->step === 3) {
            $this->validateOnly('event_at');
            $this->validateOnly('location_name');
            $this->validateOnly('latitude');
            $this->validateOnly('longitude');
        }

        $this->step = min(4, $this->step + 1);
    }

    public function previousStep(): void
    {
        $this->step = max(1, $this->step - 1);
    }

    public function removePhoto(int $index): void
    {
        unset($this->photos[$index]);
        $this->photos = array_values($this->photos);
    }

    public function save()
    {
        $this->validate();

        // Re-check authorization server-side before persisting.
        abort_unless($this->community_id && $this->userIsActiveMember($this->community_id), 403);

        abort_if(count($this->photos) > 4, 422, 'You may upload at most 4 photos.');

        $report = null;

        DB::transaction(function () use (&$report) {
            $report = Report::create([
                'user_id' => Auth::id(),
                'community_id' => $this->community_id,
                'category_id' => $this->category_id,
                'type' => $this->type,
                'title' => $this->title,
                'description' => $this->description,
                'color' => $this->color ?: null,
                'brand' => $this->brand ?: null,
                'event_at' => $this->event_at,
                'location_name' => $this->location_name,
                'latitude' => $this->latitude,
                'longitude' => $this->longitude,
                'status' => 'open',
            ]);

            foreach ($this->photos as $photo) {
                $path = $photo->store("reports/{$report->uuid}", 'local');
                $report->photos()->create(['storage_path' => $path, 'visibility' => 'private']);
            }

            $this->recordItemEvent($report, 'created', Auth::id(), [
                'type' => $report->type,
                'community_id' => $report->community_id,
                'photos' => $report->photos()->count(),
            ]);

        });

        GenerateMatches::dispatch($report->id, 'v1.0')->afterCommit();

        session()->flash('success', 'Your report has been submitted successfully.');

        return $this->redirect(route('reports.index'), navigate: true);
    }

    public function render()
    {
        return view('livewire.report-create');
    }
}
