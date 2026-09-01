<?php

namespace Tests\Feature;

use App\Enums\EventCategory;
use App\Enums\EventStatus;
use App\Models\Event;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PublicEventAccessTest extends TestCase
{
    use RefreshDatabase;

    private Organization $org;

    private Organization $otherOrg;

    protected function setUp(): void
    {
        parent::setUp();

        Role::create(['name' => 'Superadmin', 'guard_name' => 'web']);
        Role::create(['name' => 'Admin', 'guard_name' => 'web']);
        Role::create(['name' => 'Member', 'guard_name' => 'web']);

        $this->org = Organization::factory()->create(['fee_amount' => 50.00]);
        $this->otherOrg = Organization::factory()->create(['fee_amount' => 50.00]);
    }

    private function publishedEvent(?Organization $owner = null, ?string $title = null): Event
    {
        return Event::create([
            'organization_id' => $owner?->id,
            'title' => $title ?? 'Program Awam Test',
            'description' => 'Test',
            'type' => 'physical',
            'status' => EventStatus::Published->value,
            'category' => EventCategory::Muktamar->value,
            'location_or_link' => 'Kuala Lumpur',
            'start_time' => now()->addMonth(),
            'end_time' => now()->addMonth()->addHours(3),
        ]);
    }

    private function draftEvent(?Organization $owner = null): Event
    {
        return Event::create([
            'organization_id' => $owner?->id,
            'title' => 'Program Draf Test',
            'description' => 'Test',
            'type' => 'physical',
            'status' => EventStatus::Draft->value,
            'category' => EventCategory::Seminar->value,
            'location_or_link' => 'KL',
            'start_time' => now()->addMonth(),
            'end_time' => now()->addMonth()->addHours(3),
        ]);
    }

    public function test_guest_can_view_public_events_index(): void
    {
        $this->publishedEvent($this->org, 'Program Untuk Guest');
        $this->draftEvent($this->org);

        $this->get(route('events.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Events/Index')
                ->has('events.data', 1)
                ->where('events.data.0.title', 'Program Untuk Guest'));
    }

    public function test_guest_only_sees_published_events_across_all_organizations(): void
    {
        $this->publishedEvent($this->org);
        $this->publishedEvent($this->otherOrg, 'Program Org Lain');
        $this->draftEvent($this->otherOrg);

        $this->get(route('events.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Events/Index')
                ->has('events.data', 2));
    }

    public function test_guest_filter_events_by_organization(): void
    {
        $this->publishedEvent($this->org);
        $this->publishedEvent($this->otherOrg, 'Program Org Lain');

        $this->get(route('events.index', ['org' => $this->otherOrg->id]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Events/Index')
                ->has('events.data', 1)
                ->where('events.data.0.title', 'Program Org Lain'));
    }

    public function test_guest_can_view_published_event_detail(): void
    {
        $event = $this->publishedEvent($this->org, 'Detail Program Awam');

        $this->get(route('events.show', $event->slug))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Events/Show')
                ->where('event.title', 'Detail Program Awam'));
    }

    public function test_guest_cannot_view_draft_event_detail(): void
    {
        $event = $this->draftEvent($this->org);

        $this->get(route('events.show', $event->slug))
            ->assertNotFound();
    }

    public function test_admin_can_still_see_draft_and_closed_events(): void
    {
        $admin = User::factory()->create([
            'current_organization_id' => $this->org->id,
            'profile_completed_at' => now(),
        ]);
        $admin->assignRole('Superadmin');

        $this->draftEvent($this->org);

        $this->actingAs($admin)
            ->get(route('events.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Events/Index')
                ->has('events.data', 1));
    }
}
