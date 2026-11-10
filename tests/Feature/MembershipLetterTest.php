<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\User;
use App\Notifications\MembershipLetterNotification;
use App\Services\MembershipLetterService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class MembershipLetterTest extends TestCase
{
    use RefreshDatabase;

    private Organization $org;

    private User $superadmin;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['Superadmin', 'Member'] as $role) {
            Role::create(['name' => $role, 'guard_name' => 'web']);
        }

        $this->org = Organization::factory()->create([
            'name' => 'PKPIM',
            'slug' => 'pkpim',
            'min_age' => 0,
            'max_age' => null,
            'fee_amount' => 50,
            'membership_letter_enabled' => false,
        ]);

        $this->superadmin = User::factory()->create();
        $this->superadmin->assignRole('Superadmin');
    }

    private function makeMember(array $overrides = []): User
    {
        $user = User::factory()->create(array_merge([
            'name' => 'Aminah binti Ali',
            'email' => 'aminah@example.test',
            'ic_number' => '900101011234',
            'current_organization_id' => $this->org->id,
            'member_no' => 'P00001',
            'dob' => '1990-01-01',
        ], $overrides));

        $user->assignRole('Member');

        return $user;
    }

    public function test_letter_is_not_sent_when_toggle_is_off(): void
    {
        Notification::fake();

        $user = $this->makeMember();

        $sent = app(MembershipLetterService::class)->sendIfEligible($user);

        $this->assertFalse($sent);
        Notification::assertNothingSent();
        $this->assertNull($user->fresh()->membership_letter_sent_at);
    }

    public function test_letter_is_sent_once_when_toggle_is_on(): void
    {
        Notification::fake();

        $this->org->update(['membership_letter_enabled' => true]);
        $user = $this->makeMember();

        $service = app(MembershipLetterService::class);

        $this->assertTrue($service->sendIfEligible($user));
        $this->assertFalse($service->sendIfEligible($user->fresh()));

        Notification::assertSentTo($user, MembershipLetterNotification::class, 1);
        $this->assertNotNull($user->fresh()->membership_letter_sent_at);
    }

    public function test_letter_is_skipped_for_placeholder_email(): void
    {
        Notification::fake();

        $this->org->update(['membership_letter_enabled' => true]);
        $user = $this->makeMember([
            'email' => '900101011234@mywap.my',
        ]);

        $this->assertFalse(app(MembershipLetterService::class)->sendIfEligible($user));
        Notification::assertNothingSent();
    }

    public function test_mobile_registration_sends_letter_after_activation(): void
    {
        Notification::fake();

        $this->org->update(['membership_letter_enabled' => true]);

        $this->postJson('/api/v1/auth/register', [
            'name' => 'Ahli Baharu',
            'email' => 'baru@example.test',
            'ic_number' => '000101-01-0101',
            'phone' => '0123456789',
            'dob' => '2000-01-01',
        ])->assertCreated();

        $user = User::where('email', 'baru@example.test')->firstOrFail();

        Notification::assertSentTo($user, MembershipLetterNotification::class);
        $this->assertNotNull($user->fresh()->membership_letter_sent_at);
    }

    public function test_web_registration_sends_letter_after_payment(): void
    {
        Notification::fake();

        $this->org->update(['membership_letter_enabled' => true]);

        $this->post('/register', [
            'name' => 'Ahli Web',
            'email' => 'web@example.test',
            'ic_number' => '900202025555',
            'phone' => '0123456789',
            'dob' => '1990-02-02',
        ])->assertRedirect(route('register.payment', absolute: false));

        $user = User::where('email', 'web@example.test')->firstOrFail();

        $this->post('/register/payment', [])
            ->assertRedirect(route('login', absolute: false));

        Notification::assertSentTo($user, MembershipLetterNotification::class);
    }

    public function test_body_placeholders_are_replaced(): void
    {
        $this->org->update([
            'membership_letter_enabled' => true,
            'membership_letter_body' => 'Ahli {{name}} ({{member_no}}) disahkan pada {{date}}.',
        ]);

        $user = $this->makeMember(['name' => 'Aminah']);

        $context = app(MembershipLetterService::class)->context($user);

        $this->assertSame('P00001', $context['member_no']);
        $this->assertStringContainsString('Ahli Aminah (P00001) disahkan pada ', $context['body']);
        $this->assertStringNotContainsString('{{', $context['body']);
    }

    public function test_default_body_is_used_when_template_is_empty(): void
    {
        $user = $this->makeMember();

        $context = app(MembershipLetterService::class)->context($user);

        $this->assertStringContainsString('Aminah binti Ali', $context['body']);
        $this->assertStringContainsString('P00001', $context['body']);
        $this->assertStringContainsString('PKPIM', $context['body']);
    }

    public function test_pdf_can_be_generated(): void
    {
        $user = $this->makeMember();

        $service = app(MembershipLetterService::class);

        $output = $service->pdf($user)->output();

        $this->assertStringStartsWith('%PDF', $output);
        $this->assertSame('surat-pengesahan-keahlian-P00001.pdf', $service->filename($user));
    }

    public function test_superadmin_can_enable_and_save_letter_template(): void
    {
        $this->actingAs($this->superadmin)
            ->put(route('superadmin.organizations.update', $this->org), [
                'name' => 'PKPIM',
                'min_age' => 0,
                'max_age' => null,
                'membership_letter_enabled' => true,
                'membership_letter_subject' => 'Selamat datang {{name}}',
                'membership_letter_body' => 'Badan surat untuk {{member_no}}.',
                'membership_letter_signatory_name' => 'Ali bin Abu',
                'membership_letter_signatory_position' => 'Setiausaha',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $org = $this->org->fresh();

        $this->assertTrue($org->membership_letter_enabled);
        $this->assertSame('Selamat datang {{name}}', $org->membership_letter_subject);
        $this->assertSame('Badan surat untuk {{member_no}}.', $org->membership_letter_body);
        $this->assertSame('Ali bin Abu', $org->membership_letter_signatory_name);
        $this->assertSame('Setiausaha', $org->membership_letter_signatory_position);
    }

    public function test_superadmin_can_upload_and_remove_letterhead(): void
    {
        Storage::fake('public');

        $this->actingAs($this->superadmin)
            ->post(route('superadmin.organizations.letterhead.update', $this->org), [
                'membership_letter_letterhead' => UploadedFile::fake()->image('kop-surat.png', 1200, 300),
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $path = $this->org->fresh()->membership_letter_letterhead_path;

        $this->assertNotNull($path);
        Storage::disk('public')->assertExists(str_replace('/storage/', '', $path));

        $this->actingAs($this->superadmin)
            ->delete(route('superadmin.organizations.letterhead.destroy', $this->org))
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertNull($this->org->fresh()->membership_letter_letterhead_path);
    }
}
