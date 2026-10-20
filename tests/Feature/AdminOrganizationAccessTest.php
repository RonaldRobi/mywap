<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\Organization;
use App\Models\Registration;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AdminOrganizationAccessTest extends TestCase
{
    use RefreshDatabase;

    private Organization $org;
    private Organization $other;
    private User $admin;
    private User $otherAdmin;

    protected function setUp(): void
    {
        parent::setUp();

        Role::create(['name' => 'Superadmin']);
        Role::create(['name' => 'Admin']);
        Role::create(['name' => 'org-admin']);
        Role::create(['name' => 'Member']);

        $this->org = Organization::factory()->create();
        $this->other = Organization::factory()->create();

        $this->admin = User::factory()->create([
            'current_organization_id' => $this->org->id,
            'profile_completed_at' => now(),
        ]);
        $this->admin->assignRole('Admin');

        $this->otherAdmin = User::factory()->create([
            'current_organization_id' => $this->other->id,
            'profile_completed_at' => now(),
        ]);
        $this->otherAdmin->assignRole('org-admin');
    }

    private function makeEvent(?Organization $owner = null, array $pivot = []): Event
    {
        $event = Event::create([
            'organization_id' => $owner?->id,
            'title' => 'Event '.uniqid(),
            'type' => 'physical',
            'status' => 'published',
            'category' => 'seminar',
            'start_time' => now()->addMonth(),
            'end_time' => now()->addMonth()->addHour(),
        ]);
        $event->organizations()->sync($pivot ?: array_filter([$owner?->id]));

        return $event;
    }

    public function test_admin_own_org_participants(): void
    {
        $event = $this->makeEvent($this->org);
        Registration::create([
            'event_id' => $event->id,
            'organization_id' => $this->other->id,
            'name' => 'Peserta Lain',
            'status' => 'confirmed',
        ]);

        $this->actingAs($this->admin);
        $this->get(route('admin.events.registrations', $event->id))
            ->assertOk()
            ->assertInertia(fn ($p) => $p->component('Admin/Events/Registrations')->has('registrations.data', 1));
    }

    public function test_org_admin_alias_can_manage_own_event(): void
    {
        $event = $this->makeEvent($this->other);

        $this->actingAs($this->otherAdmin);
        $this->get(route('admin.events.create'))->assertOk();
        $this->get(route('admin.events.edit', $event->id))->assertOk();
        $this->get(route('admin.events.registrations', $event->id))->assertOk();
    }

    public function test_admin_can_see_participants_of_coorganised_event(): void
    {
        $event = $this->makeEvent($this->other, [$this->other->id, $this->org->id]);
        Registration::create([
            'event_id' => $event->id,
            'organization_id' => $this->other->id,
            'name' => 'Peserta Bersama',
            'status' => 'confirmed',
        ]);

        $this->actingAs($this->admin);

        $this->get(route('admin.attendance'))
            ->assertOk()
            ->assertInertia(fn ($p) => $p->component('Admin/Events/Attendance')->has('programs', 1));
        $this->get(route('admin.events.registrations', $event->id))
            ->assertOk()
            ->assertInertia(fn ($p) => $p->has('registrations.data', 1));
    }

    public function test_org_admin_cannot_manage_other_org_event(): void
    {
        $event = $this->makeEvent($this->org);

        $this->actingAs($this->otherAdmin);
        $this->get(route('admin.events.edit', $event->id))->assertForbidden();
        $this->get(route('admin.events.registrations', $event->id))->assertForbidden();
    }

    public function test_admin_creates_member_only_in_own_org(): void
    {
        $this->actingAs($this->admin);

        $this->post(route('superadmin.members.store'), [
            'name' => 'Ahli Baru',
            'email' => 'ahli.baru@example.test',
            'ic_number' => '900101011234',
            'phone' => '0123456789',
            'dob' => '1990-01-01',
            'password' => 'password123',
        ])->assertRedirect();

        $member = User::where('email', 'ahli.baru@example.test')->firstOrFail();
        $this->assertSame($this->org->id, $member->current_organization_id);
    }

    public function test_admin_can_update_ic_for_own_org_only(): void
    {
        $ownMember = User::factory()->create(['current_organization_id' => $this->org->id]);
        $otherMember = User::factory()->create(['current_organization_id' => $this->other->id]);

        $this->actingAs($this->admin);

        $this->patch(route('admin.hub.members.ic.update', $ownMember->id), ['ic_number' => '880101015555'])
            ->assertRedirect();
        $this->patch(route('admin.hub.members.ic.update', $otherMember->id), ['ic_number' => '880101016666'])
            ->assertNotFound();
    }

    public function test_org_admin_alias_can_access_finance_and_forms(): void
    {
        $this->actingAs($this->otherAdmin);
        $this->get(route('admin.forms.index'))->assertOk();
        $this->get(route('admin.finance.index'))->assertOk();
    }
}
