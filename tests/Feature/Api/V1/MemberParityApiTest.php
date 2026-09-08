<?php

namespace Tests\Feature\Api\V1;

use App\Enums\EventCategory;
use App\Enums\EventStatus;
use App\Models\Event;
use App\Models\Form;
use App\Models\FormQuestion;
use App\Models\Organization;
use App\Models\Registration;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class MemberParityApiTest extends TestCase
{
    use RefreshDatabase;

    private Organization $org;

    private User $member;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Role::create(['name' => 'Superadmin', 'guard_name' => 'web']);
        Role::create(['name' => 'Admin', 'guard_name' => 'web']);
        Role::create(['name' => 'Member', 'guard_name' => 'web']);

        $this->org = Organization::factory()->create(['name' => 'PKPIM', 'fee_amount' => 50.00]);
        $this->member = User::factory()->create([
            'current_organization_id' => $this->org->id,
            'profile_completed_at' => now(),
            'member_no' => 'PKPIM-0001',
            'password' => Hash::make('password123'),
        ]);
        $this->member->assignRole('Member');

        $this->admin = User::factory()->create([
            'current_organization_id' => $this->org->id,
            'profile_completed_at' => now(),
        ]);
        $this->admin->assignRole('Admin');

        Notification::fake();
    }

    public function test_member_pays_fee_in_dummy_mode_and_is_marked_paid(): void
    {
        Sanctum::actingAs($this->member);

        $this->postJson('/api/v1/member/pay-fee')
            ->assertOk()
            ->assertJsonPath('data.status', 'success');

        $this->assertDatabaseHas('membership_fees', [
            'user_id' => $this->member->id,
            'year' => now()->year,
            'status' => 'paid',
        ]);
    }

    public function test_fee_payment_forbidden_for_non_member(): void
    {
        Sanctum::actingAs($this->admin);

        $this->postJson('/api/v1/member/pay-fee')
            ->assertStatus(403);
    }

    public function test_fee_payment_rejected_when_already_active(): void
    {
        Sanctum::actingAs($this->member);
        app(\App\Services\FeeService::class)->markAsPaid($this->member, now()->year, 50.0);

        $this->postJson('/api/v1/member/pay-fee')
            ->assertStatus(422)
            ->assertJsonPath('message', 'Yuran keahlian anda untuk tahun ini sudah dibayar.');
    }

    public function test_member_card_letter_returns_signed_url(): void
    {
        Sanctum::actingAs($this->member);

        $this->getJson('/api/v1/member/card/letter')
            ->assertOk()
            ->assertJsonStructure(['data' => ['url']])
            ->assertJsonPath('data.url', fn (string $url) => str_contains($url, '/kad/surat/'));
    }

    public function test_change_password_rejects_wrong_current_and_updates_correct(): void
    {
        Sanctum::actingAs($this->member);

        $this->postJson('/api/v1/profile/password', [
            'current_password' => 'wrong-pass',
            'password' => 'NewPassword1!',
            'password_confirmation' => 'NewPassword1!',
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['current_password']);

        $this->postJson('/api/v1/profile/password', [
            'current_password' => 'password123',
            'password' => 'NewPassword1!',
            'password_confirmation' => 'NewPassword1!',
        ])->assertOk();

        $this->assertTrue(Hash::check('NewPassword1!', $this->member->fresh()->password));
    }

    public function test_member_can_delete_own_account(): void
    {
        Sanctum::actingAs($this->member);

        $this->deleteJson('/api/v1/profile', ['password' => 'password123'])
            ->assertOk();

        $this->assertNull(User::find($this->member->id));
    }

    public function test_upload_profile_photo_stores_file_and_returns_url(): void
    {
        Sanctum::actingAs($this->member);
        Storage::fake('public');

        $response = $this->post('/api/v1/profile/photo', [
            'photo' => UploadedFile::fake()->image('avatar.jpg', 300, 300),
        ]);
        $response->assertOk()->assertJsonStructure(['data' => ['photo_url']]);

        $photoUrl = $response->json('data.photo_url');
        $path = ltrim(str_replace('/storage/', '', $photoUrl), '/');

        Storage::disk('public')->assertExists($path);
        $this->assertSame($photoUrl, $this->member->fresh()->profile_photo_path);
    }

    public function test_free_event_registration_succeeds_and_duplicate_blocked(): void
    {
        Sanctum::actingAs($this->member);

        $event = $this->makePublishedEvent();
        $form = $this->makeForm($event);

        $questionId = $form->questions()->first()->id;

        $this->postJson('/api/v1/events/'.$event->id.'/registration', [
            'form_id' => $form->id,
            'answers' => [$questionId => 'Ali Ahmad'],
        ])->assertOk()
            ->assertJsonPath('data.status', 'success');

        $this->assertDatabaseHas('registrations', [
            'event_id' => $event->id,
            'user_id' => $this->member->id,
            'status' => Registration::where('event_id', $event->id)->first()->status->value,
        ]);

        // Pendaftaran kedua dinafikan.
        $this->postJson('/api/v1/events/'.$event->id.'/registration', [
            'form_id' => $form->id,
            'answers' => [$questionId => 'Ali Ahmad'],
        ])->assertStatus(422);
    }

    public function test_event_registration_form_payload_is_returned(): void
    {
        Sanctum::actingAs($this->member);

        $event = $this->makePublishedEvent();
        $form = $this->makeForm($event);

        $this->getJson('/api/v1/events/'.$event->id.'/registration/'.$form->id)
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'form' => ['questions'],
                    'event' => ['title'],
                    'my_registration',
                ],
            ])
            ->assertJsonPath('data.event.id', $event->id);
    }

    private function makePublishedEvent(): Event
    {
        $event = Event::create([
            'organization_id' => $this->org->id,
            'title' => 'Muktamar Test',
            'description' => 'desc',
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

    private function makeForm(Event $event): Form
    {
        $form = Form::create([
            'event_id' => $event->id,
            'organization_id' => $this->org->id,
            'title' => 'Borang Pendaftaran Percuma',
            'payment_required' => false,
            'terms' => 'Syarat test',
            'is_active' => true,
            'allow_public' => true,
        ]);
        FormQuestion::create([
            'form_id' => $form->id,
            'label' => 'Nama Penuh',
            'type' => 'text',
            'required' => true,
            'sort_order' => 0,
        ]);

        return $form;
    }
}
