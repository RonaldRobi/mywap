<?php

namespace Tests\Feature\Api\V1;

use App\Enums\EventCategory;
use App\Enums\EventStatus;
use App\Models\Event;
use App\Models\Form;
use App\Models\Organization;
use App\Models\Registration;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class MemberRegistrationApiTest extends TestCase
{
    use RefreshDatabase;

    private Organization $org;

    private User $member;

    protected function setUp(): void
    {
        parent::setUp();

        Role::create(['name' => 'Superadmin', 'guard_name' => 'web']);
        Role::create(['name' => 'Admin', 'guard_name' => 'web']);
        Role::create(['name' => 'Member', 'guard_name' => 'web']);

        $this->org = Organization::factory()->create(['name' => 'PKPIM']);
        $this->member = User::factory()->create([
            'current_organization_id' => $this->org->id,
            'profile_completed_at' => now(),
            'member_no' => 'PKPIM-0001',
        ]);
        $this->member->assignRole('Member');
    }

    private function makeRegistration(User $user): Registration
    {
        $event = Event::create([
            'organization_id' => $this->org->id,
            'title' => 'Muktamar Nasional',
            'description' => 'x',
            'type' => 'physical',
            'status' => EventStatus::Published->value,
            'category' => EventCategory::Muktamar->value,
            'location_or_link' => 'KL',
            'start_time' => now()->addMonth(),
            'end_time' => now()->addMonth()->addHours(3),
        ]);

        $form = Form::create([
            'event_id' => $event->id,
            'organization_id' => $this->org->id,
            'title' => 'Borang Pendaftaran',
            'is_active' => true,
            'allow_public' => true,
        ]);

        return Registration::create([
            'event_id' => $event->id,
            'form_id' => $form->id,
            'organization_id' => $this->org->id,
            'user_id' => $user->id,
            'member_no' => $user->member_no,
            'name' => $user->name,
            'status' => 'confirmed',
        ]);
    }

    public function test_member_can_list_own_registrations(): void
    {
        $this->makeRegistration($this->member);

        Sanctum::actingAs($this->member);

        $this->getJson('/api/v1/member/registrations')
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    ['id', 'registration_no', 'status', 'payment_status', 'event'],
                ],
                'meta' => ['current_page', 'last_page', 'per_page', 'total'],
            ])
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.event.title', 'Muktamar Nasional');
    }

    public function test_member_only_sees_own_registrations(): void
    {
        $other = User::factory()->create([
            'current_organization_id' => $this->org->id,
            'profile_completed_at' => now(),
        ]);
        $this->makeRegistration($other);

        Sanctum::actingAs($this->member);

        $this->getJson('/api/v1/member/registrations')
            ->assertOk()
            ->assertJsonPath('meta.total', 0);
    }

    public function test_requires_auth(): void
    {
        $this->getJson('/api/v1/member/registrations')->assertStatus(401);
    }
}
