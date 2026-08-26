<?php

namespace Tests\Feature\Api\V1;

use App\Enums\EventCategory;
use App\Enums\EventStatus;
use App\Models\Event;
use App\Models\EventRsvp;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class EventApiTest extends TestCase
{
    use RefreshDatabase;

    private Organization $org;

    private Organization $otherOrg;

    private User $member;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Role::create(['name' => 'Superadmin', 'guard_name' => 'web']);
        Role::create(['name' => 'Admin', 'guard_name' => 'web']);
        Role::create(['name' => 'Member', 'guard_name' => 'web']);

        $this->org = Organization::factory()->create(['name' => 'PKPIM']);
        $this->otherOrg = Organization::factory()->create(['name' => 'ABIM']);

        $this->member = User::factory()->create([
            'current_organization_id' => $this->org->id,
            'profile_completed_at' => now(),
            'member_no' => 'PKPIM-0001',
        ]);
        $this->member->assignRole('Member');

        $this->admin = User::factory()->create([
            'current_organization_id' => $this->org->id,
            'profile_completed_at' => now(),
        ]);
        $this->admin->assignRole('Admin');
    }

    private function makeEvent(?Organization $owner = null): Event
    {
        $event = Event::create([
            'organization_id' => $owner?->id,
            'title' => 'Muktamar Nasional Test',
            'description' => 'Test',
            'type' => 'physical',
            'status' => EventStatus::Published->value,
            'category' => EventCategory::Muktamar->value,
            'location_or_link' => 'Kuala Lumpur',
            'start_time' => now()->addMonth(),
            'end_time' => now()->addMonth()->addHours(3),
        ]);
        $event->organizations()->sync([$this->org->id]);

        return $event;
    }

    public function test_member_can_list_events_with_pagination_envelope(): void
    {
        $this->makeEvent();

        Sanctum::actingAs($this->member);

        $this->getJson('/api/v1/events')
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    ['id', 'title', 'slug', 'start_time', 'organization', 'my_rsvp'],
                ],
                'meta' => ['current_page', 'last_page', 'per_page', 'total'],
                'links' => ['first', 'last', 'prev', 'next'],
            ])
            ->assertJsonPath('meta.total', 1);
    }

    public function test_member_does_not_see_other_org_events(): void
    {
        Event::create([
            'organization_id' => $this->otherOrg->id,
            'title' => 'Event ABIM',
            'description' => 'x',
            'type' => 'physical',
            'status' => EventStatus::Published->value,
            'category' => EventCategory::Seminar->value,
            'location_or_link' => 'KL',
            'start_time' => now()->addMonth(),
            'end_time' => now()->addMonth()->addHours(2),
        ]);

        Sanctum::actingAs($this->member);

        $this->getJson('/api/v1/events')
            ->assertOk()
            ->assertJsonPath('meta.total', 0);
    }

    public function test_member_can_view_event_detail(): void
    {
        $event = $this->makeEvent();

        Sanctum::actingAs($this->member);

        $this->getJson("/api/v1/events/{$event->id}")
            ->assertOk()
            ->assertJsonPath('data.event.id', $event->id)
            ->assertJsonPath('data.event.organizations.0.name', 'PKPIM')
            ->assertJsonPath('data.event.my_rsvp', null);
    }

    public function test_member_can_rsvp_to_event(): void
    {
        $event = $this->makeEvent();

        Sanctum::actingAs($this->member);

        $this->postJson("/api/v1/events/{$event->id}/rsvp", ['status' => 'going'])
            ->assertOk()
            ->assertJsonPath('data.status', 'going');

        $this->assertDatabaseHas('event_rsvps', [
            'event_id' => $event->id,
            'user_id' => $this->member->id,
            'status' => 'going',
        ]);
    }

    public function test_member_can_update_rsvp_status(): void
    {
        $event = $this->makeEvent();
        EventRsvp::create(['event_id' => $event->id, 'user_id' => $this->member->id, 'status' => 'maybe']);

        Sanctum::actingAs($this->member);

        $this->postJson("/api/v1/events/{$event->id}/rsvp", ['status' => 'declined'])
            ->assertOk();

        $this->assertDatabaseHas('event_rsvps', [
            'event_id' => $event->id,
            'user_id' => $this->member->id,
            'status' => 'declined',
        ]);
    }

    public function test_rsvp_requires_valid_status(): void
    {
        $event = $this->makeEvent();

        Sanctum::actingAs($this->member);

        $this->postJson("/api/v1/events/{$event->id}/rsvp", ['status' => 'unknown'])
            ->assertStatus(422);
    }

    public function test_admin_cannot_rsvp(): void
    {
        $event = $this->makeEvent();

        Sanctum::actingAs($this->admin);

        $this->postJson("/api/v1/events/{$event->id}/rsvp", ['status' => 'going'])
            ->assertStatus(403);
    }
}
