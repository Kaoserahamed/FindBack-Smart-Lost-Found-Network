<?php

namespace Tests\Feature;

use App\Jobs\GenerateMatches;
use App\Models\Category;
use App\Models\Community;
use App\Models\CommunityMembership;
use App\Models\Report;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class StoreReportTest extends TestCase
{
    use RefreshDatabase;

    private function actingMember(array $membership = ['status' => 'active', 'role' => 'member']): array
    {
        $user = User::factory()->create();
        $community = Community::factory()->university()->create(['status' => 'approved']);
        $category = Category::factory()->create();

        CommunityMembership::create([
            'community_id' => $community->id,
            'user_id' => $user->id,
            'role' => $membership['role'],
            'status' => $membership['status'],
        ]);

        return [$user, $community, $category];
    }

    private function validPayload(Community $community, Category $category): array
    {
        return [
            'community_id' => $community->id,
            'category_id' => $category->id,
            'type' => 'lost',
            'title' => 'Lost black wallet',
            'description' => 'A black leather wallet lost near the library.',
            'color' => 'black',
            'brand' => 'LeatherCo',
            'event_at' => now()->subDay()->toDateTimeString(),
            'location_name' => 'Central Library',
            'latitude' => 24.9186,
            'longitude' => 91.8315,
        ];
    }

    public function test_authenticated_active_member_can_create_a_report(): void
    {
        Storage::fake('local');
        Queue::fake();

        [$user, $community, $category] = $this->actingMember();

        $payload = $this->validPayload($community, $category);
        $payload['photos'] = [
            UploadedFile::fake()->image('photo1.jpg', 800, 600)->size(1024),
            UploadedFile::fake()->image('photo2.png', 800, 600)->size(1024),
        ];

        $response = $this->actingAs($user)->post(route('reports.store'), $payload);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $report = Report::first();
        $this->assertNotNull($report);
        $this->assertSame($user->id, $report->user_id);
        $this->assertSame('open', $report->status);
        $this->assertNotEmpty($report->uuid);

        $this->assertCount(2, $report->photos);
        foreach ($report->photos as $photo) {
            $this->assertSame('private', $photo->visibility);
            Storage::disk('local')->assertExists($photo->storage_path);
        }

        $this->assertSame('created', $report->events()->first()->action);

        Queue::assertPushed(GenerateMatches::class, function ($job) use ($report) {
            return $job->reportId === $report->id;
        });
    }

    public function test_guest_is_redirected_to_login(): void
    {
        [, $community, $category] = $this->actingMember();

        $response = $this->post(route('reports.store'), $this->validPayload($community, $category));

        $response->assertRedirect('/login');
        $this->assertSame(0, Report::count());
    }

    public function test_non_member_is_forbidden(): void
    {
        Queue::fake();

        $outsider = User::factory()->create();
        $community = Community::factory()->university()->create(['status' => 'approved']);
        $category = Category::factory()->create();

        $response = $this->actingAs($outsider)->post(
            route('reports.store'),
            $this->validPayload($community, $category)
        );

        $response->assertForbidden();
        $this->assertSame(0, Report::count());
        Queue::assertNotPushed(GenerateMatches::class);
    }

    public function test_suspended_member_is_forbidden(): void
    {
        Queue::fake();

        [$user, $community, $category] = $this->actingMember(['status' => 'suspended', 'role' => 'member']);

        $response = $this->actingAs($user)->post(
            route('reports.store'),
            $this->validPayload($community, $category)
        );

        $response->assertForbidden();
        $this->assertSame(0, Report::count());
    }

    public function test_validation_errors_are_returned(): void
    {
        [$user, $community, $category] = $this->actingMember();

        $response = $this->actingAs($user)->post(route('reports.store'), [
            'community_id' => $community->id,
            'category_id' => $category->id,
            'type' => 'invalid-type',
        ]);

        $response->assertSessionHasErrors(['type', 'title', 'description', 'event_at', 'location_name']);
        $this->assertSame(0, Report::count());
    }

    public function test_future_event_at_is_rejected(): void
    {
        [$user, $community, $category] = $this->actingMember();

        $payload = $this->validPayload($community, $category);
        $payload['event_at'] = now()->addDay()->toDateTimeString();

        $response = $this->actingAs($user)->post(route('reports.store'), $payload);

        $response->assertSessionHasErrors('event_at');
        $this->assertSame(0, Report::count());
    }

    public function test_too_many_photos_are_rejected(): void
    {
        Storage::fake('local');
        [$user, $community, $category] = $this->actingMember();

        $payload = $this->validPayload($community, $category);
        $payload['photos'] = [
            UploadedFile::fake()->image('a.jpg'),
            UploadedFile::fake()->image('b.jpg'),
            UploadedFile::fake()->image('c.jpg'),
            UploadedFile::fake()->image('d.jpg'),
            UploadedFile::fake()->image('e.jpg'),
        ];

        $response = $this->actingAs($user)->post(route('reports.store'), $payload);

        $response->assertSessionHasErrors('photos');
        $this->assertSame(0, Report::count());
    }

    public function test_invalid_photo_type_is_rejected(): void
    {
        Storage::fake('local');
        [$user, $community, $category] = $this->actingMember();

        $payload = $this->validPayload($community, $category);
        $payload['photos'] = [
            UploadedFile::fake()->create('document.pdf', 100, 'application/pdf'),
        ];

        $response = $this->actingAs($user)->post(route('reports.store'), $payload);

        $response->assertSessionHasErrors('photos.0');
        $this->assertSame(0, Report::count());
    }
}
