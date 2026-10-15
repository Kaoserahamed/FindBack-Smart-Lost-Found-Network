<?php

namespace Tests\Feature;

use App\Livewire\ClaimManager;
use App\Livewire\MatchList;
use App\Livewire\ReportCreate;
use App\Livewire\ReportList;
use App\Models\Category;
use App\Models\Claim;
use App\Models\Community;
use App\Models\CommunityMembership;
use App\Models\ItemMatch;
use App\Models\Report;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class FrontendComponentsTest extends TestCase
{
    use RefreshDatabase;

    private function activeMember(User $user, Community $community, string $role = 'member'): void
    {
        CommunityMembership::create([
            'community_id' => $community->id,
            'user_id' => $user->id,
            'role' => $role,
            'status' => 'active',
        ]);
    }

    public function test_report_list_renders_and_filters_by_type(): void
    {
        $community = Community::factory()->university()->create();
        $category = Category::factory()->create();

        Report::factory()->lost()->create([
            'user_id' => User::factory()->create()->id,
            'community_id' => $community->id, 'category_id' => $category->id,
            'title' => 'Lost Keys', 'status' => 'open',
        ]);
        Report::factory()->found()->create([
            'user_id' => User::factory()->create()->id,
            'community_id' => $community->id, 'category_id' => $category->id,
            'title' => 'Found Wallet', 'status' => 'open',
        ]);

        Livewire::test(ReportList::class)
            ->assertSee('Lost Keys')
            ->assertSee('Found Wallet')
            ->set('type', 'found')
            ->assertSee('Found Wallet')
            ->assertDontSee('Lost Keys');
    }

    public function test_report_list_search_filters_results(): void
    {
        $community = Community::factory()->university()->create();
        $category = Category::factory()->create();

        Report::factory()->lost()->create([
            'user_id' => User::factory()->create()->id,
            'community_id' => $community->id, 'category_id' => $category->id,
            'title' => 'Unique Zanzibar Item', 'status' => 'open',
        ]);
        Report::factory()->lost()->create([
            'user_id' => User::factory()->create()->id,
            'community_id' => $community->id, 'category_id' => $category->id,
            'title' => 'Ordinary Book', 'status' => 'open',
        ]);

        Livewire::test(ReportList::class)
            ->set('search', 'Zanzibar')
            ->assertSee('Unique Zanzibar Item')
            ->assertDontSee('Ordinary Book');
    }
    public function test_report_create_requires_active_membership_to_submit(): void
    {
        $community = Community::factory()->university()->create();
        $category = Category::factory()->create();

        // A non-member attempting to save is blocked (403) even with full data.
        $outsider = User::factory()->create();

        Livewire::actingAs($outsider)->test(ReportCreate::class)
            ->set('community_id', $community->id)
            ->set('category_id', $category->id)
            ->set('type', 'lost')
            ->set('title', 'X')
            ->set('description', 'Y')
            ->set('event_at', now()->subHour()->format('Y-m-d\TH:i'))
            ->set('location_name', 'Gate')
            ->call('save')
            ->assertStatus(403);

        $this->assertSame(0, Report::count());
    }

    public function test_active_member_can_create_report_via_component(): void
    {
        Queue::fake();
        Storage::fake('local');

        $user = User::factory()->create();
        $community = Community::factory()->university()->create();
        $category = Category::factory()->create();
        $this->activeMember($user, $community);

        Livewire::actingAs($user)->test(ReportCreate::class)
            ->set('community_id', $community->id)
            ->set('category_id', $category->id)
            ->set('type', 'lost')
            ->set('title', 'Lost Umbrella')
            ->set('description', 'Black umbrella')
            ->set('event_at', now()->subHour()->format('Y-m-d\TH:i'))
            ->set('location_name', 'Library')
            ->call('save')
            ->assertRedirect(route('reports.index'));

        $this->assertSame(1, Report::count());
        $this->assertSame('open', Report::first()->status);
    }

    public function test_match_list_requires_authorization(): void
    {
        $owner = User::factory()->create();
        $community = Community::factory()->university()->create();
        $category = Category::factory()->create();
        $report = Report::factory()->lost()->create([
            'user_id' => $owner->id, 'community_id' => $community->id, 'category_id' => $category->id,
        ]);

        Livewire::test(MatchList::class, ['report' => $report])
            ->assertStatus(403);
    }

    public function test_owner_can_view_their_matches_with_reasons(): void
    {
        $owner = User::factory()->create();
        $community = Community::factory()->university()->create();
        $category = Category::factory()->create();
        $lost = Report::factory()->lost()->create([
            'user_id' => $owner->id, 'community_id' => $community->id, 'category_id' => $category->id,
            'title' => 'Lost Phone',
        ]);
        $found = Report::factory()->found()->create([
            'user_id' => User::factory()->create()->id, 'community_id' => $community->id, 'category_id' => $category->id,
            'title' => 'Found Phone',
        ]);
        ItemMatch::create([
            'lost_report_id' => $lost->id, 'found_report_id' => $found->id,
            'score' => 88, 'reasons' => ['Same category', 'Located about 0.2 km apart'],
            'status' => 'pending', 'algorithm_version' => 'v1.0',
        ]);

        Livewire::actingAs($owner)->test(MatchList::class, ['report' => $lost])
            ->assertSee('Found Phone')
            ->assertSee('Same category')
            ->assertSee('88');
    }

    public function test_claim_manager_blocks_unauthorized_review(): void
    {
        $reporter = User::factory()->create();
        $community = Community::factory()->university()->create();
        $category = Category::factory()->create();
        $report = Report::factory()->found()->create([
            'user_id' => $reporter->id, 'community_id' => $community->id, 'category_id' => $category->id,
            'status' => 'open',
        ]);
        $claim = Claim::factory()->create([
            'report_id' => $report->id, 'claimant_id' => User::factory()->create()->id, 'status' => 'submitted',
        ]);

        $outsider = User::factory()->create();
        Livewire::actingAs($outsider)->test(ClaimManager::class, ['report' => $report])
            ->call('review', $claim->id, 'approved')
            ->assertStatus(403);

        $this->assertSame('submitted', $claim->fresh()->status);
    }

    public function test_claimant_cannot_review_own_claim_via_component(): void
    {
        $reporter = User::factory()->create();
        $community = Community::factory()->university()->create();
        $category = Category::factory()->create();
        $report = Report::factory()->found()->create([
            'user_id' => $reporter->id, 'community_id' => $community->id, 'category_id' => $category->id,
            'status' => 'open',
        ]);
        $claimant = User::factory()->create();
        $claim = Claim::factory()->create([
            'report_id' => $report->id, 'claimant_id' => $claimant->id, 'status' => 'submitted',
        ]);

        Livewire::actingAs($claimant)->test(ClaimManager::class, ['report' => $report])
            ->call('review', $claim->id, 'approved')
            ->assertStatus(403);

        $this->assertSame('submitted', $claim->fresh()->status);
    }

    public function test_moderator_can_review_claim_via_component(): void
    {
        Notification::fake();

        $reporter = User::factory()->create();
        $community = Community::factory()->university()->create();
        $category = Category::factory()->create();
        $report = Report::factory()->found()->create([
            'user_id' => $reporter->id, 'community_id' => $community->id, 'category_id' => $category->id,
            'status' => 'open',
        ]);
        $claimant = User::factory()->create();
        $claim = Claim::factory()->create([
            'report_id' => $report->id, 'claimant_id' => $claimant->id, 'status' => 'submitted',
        ]);

        $moderator = User::factory()->create();
        $this->activeMember($moderator, $community, 'moderator');

        Livewire::actingAs($moderator)->test(ClaimManager::class, ['report' => $report])
            ->set("reviewReason.{$claim->id}", 'Verified ownership.')
            ->call('review', $claim->id, 'approved')
            ->assertHasNoErrors();

        $claim->refresh();
        $this->assertSame('approved', $claim->status);
        $this->assertSame($moderator->id, $claim->reviewed_by);
    }

    public function test_active_member_can_submit_claim_via_component(): void
    {
        Storage::fake('local');

        $reporter = User::factory()->create();
        $community = Community::factory()->university()->create();
        $category = Category::factory()->create();
        $report = Report::factory()->found()->create([
            'user_id' => $reporter->id, 'community_id' => $community->id, 'category_id' => $category->id,
            'status' => 'open',
        ]);

        $claimant = User::factory()->create();
        $this->activeMember($claimant, $community);

        Livewire::actingAs($claimant)->test(ClaimManager::class, ['report' => $report])
            ->set('answers', ['serial' => 'SN-42'])
            ->call('submitClaim')
            ->assertHasNoErrors();

        $this->assertSame(1, Claim::count());
        $this->assertSame('submitted', Claim::first()->status);
    }
}