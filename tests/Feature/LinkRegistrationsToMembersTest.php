<?php

namespace Tests\Feature;

use App\Enums\EventCategory;
use App\Enums\EventStatus;
use App\Models\Event;
use App\Models\Organization;
use App\Models\Registration;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Backfill peserta awam → akaun ahli melalui padanan No. IC.
 */
class LinkRegistrationsToMembersTest extends TestCase
{
    use RefreshDatabase;

    private function makeEvent(Organization $org, array $overrides = []): Event
    {
        return Event::create(array_merge([
            'organization_id' => $org->id,
            'title' => 'Program Ujian',
            'description' => 'Test',
            'type' => 'physical',
            'status' => EventStatus::Published->value,
            'category' => EventCategory::Seminar->value,
            'location_or_link' => 'Kuala Lumpur',
            'start_time' => now()->addDay(),
            'end_time' => now()->addDays(2),
        ], $overrides));
    }

    private function makeGuest(Event $event, array $overrides = []): Registration
    {
        return Registration::create(array_merge([
            'event_id' => $event->id,
            'name' => 'Peserta Awam',
            'status' => 'confirmed',
            'source' => Registration::SOURCE_FORM,
        ], $overrides));
    }

    public function test_guest_with_matching_ic_is_linked_to_member(): void
    {
        $org = Organization::factory()->create(['name' => 'WADAH']);
        $event = $this->makeEvent($org);

        $member = User::factory()->create([
            'current_organization_id' => $org->id,
            'ic_number' => '900101011234',
            'member_no' => 'W12345',
        ]);

        $guest = $this->makeGuest($event, ['ic_number' => '900101-01-1234']);

        $this->artisan('registrations:link-members', ['--event' => $event->id, '--commit' => true])
            ->assertSuccessful();

        $guest->refresh();
        $this->assertSame($member->id, $guest->user_id);
        $this->assertSame('W12345', $guest->member_no);
        $this->assertSame($org->id, $guest->organization_id);
    }

    public function test_dry_run_does_not_write_changes(): void
    {
        $org = Organization::factory()->create();
        $event = $this->makeEvent($org);

        User::factory()->create([
            'current_organization_id' => $org->id,
            'ic_number' => '900101011234',
            'member_no' => 'W12345',
        ]);

        $guest = $this->makeGuest($event, ['ic_number' => '900101011234']);

        $this->artisan('registrations:link-members', ['--event' => $event->id])
            ->assertSuccessful();

        $guest->refresh();
        $this->assertNull($guest->user_id);
        $this->assertNull($guest->organization_id);
    }

    public function test_invalid_ic_stays_as_public_participant(): void
    {
        $org = Organization::factory()->create();
        $event = $this->makeEvent($org);

        User::factory()->create([
            'current_organization_id' => $org->id,
            'ic_number' => '900101011234',
            'member_no' => 'W12345',
        ]);

        $guest = $this->makeGuest($event, ['ic_number' => '3852598']);

        $this->artisan('registrations:link-members', ['--event' => $event->id, '--commit' => true])
            ->assertSuccessful();

        $guest->refresh();
        $this->assertNull($guest->user_id);
        $this->assertNull($guest->member_no);
        $this->assertNull($guest->organization_id);
    }

    public function test_ic_without_matching_member_stays_as_public_participant(): void
    {
        $org = Organization::factory()->create();
        $event = $this->makeEvent($org);

        $guest = $this->makeGuest($event, ['ic_number' => '800101015555']);

        $this->artisan('registrations:link-members', ['--event' => $event->id, '--commit' => true])
            ->assertSuccessful();

        $guest->refresh();
        $this->assertNull($guest->user_id);
        $this->assertNull($guest->organization_id);
    }

    public function test_registration_linked_while_logged_in_is_untouched(): void
    {
        $orgA = Organization::factory()->create(['name' => 'WADAH']);
        $orgB = Organization::factory()->create(['name' => 'ABIM']);
        $event = $this->makeEvent($orgA);

        $loggedInMember = User::factory()->create([
            'current_organization_id' => $orgB->id,
            'member_no' => 'A999',
        ]);

        // Rekod ini sudah ada user_id (isi form semasa login) walaupun IC-nya
        // sepadan dengan ahli lain. Organisasi asal mesti kekal.
        User::factory()->create([
            'current_organization_id' => $orgA->id,
            'ic_number' => '900101011234',
            'member_no' => 'W12345',
        ]);

        $registration = $this->makeGuest($event, [
            'ic_number' => '900101011234',
            'user_id' => $loggedInMember->id,
            'organization_id' => $orgB->id,
            'member_no' => 'A999',
        ]);

        $this->artisan('registrations:link-members', ['--event' => $event->id, '--commit' => true])
            ->assertSuccessful();

        $registration->refresh();
        $this->assertSame($loggedInMember->id, $registration->user_id);
        $this->assertSame($orgB->id, $registration->organization_id);
        $this->assertSame('A999', $registration->member_no);
    }

    public function test_ambiguous_ic_prefers_event_organization(): void
    {
        $orgA = Organization::factory()->create(['name' => 'WADAH']);
        $orgB = Organization::factory()->create(['name' => 'ABIM']);
        $event = $this->makeEvent($orgA);

        $memberA = User::factory()->create([
            'current_organization_id' => $orgA->id,
            'ic_number' => '900101011234',
            'member_no' => 'W1',
        ]);

        User::factory()->create([
            'current_organization_id' => $orgB->id,
            'ic_number' => '900101011234',
            'member_no' => 'A1',
        ]);

        $guest = $this->makeGuest($event, ['ic_number' => '900101011234']);

        $this->artisan('registrations:link-members', ['--event' => $event->id, '--commit' => true])
            ->assertSuccessful();

        $guest->refresh();
        $this->assertSame($memberA->id, $guest->user_id);
        $this->assertSame($orgA->id, $guest->organization_id);
    }
}
