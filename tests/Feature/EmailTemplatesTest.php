<?php

namespace Tests\Feature;

use App\Models\EmailTemplate;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class EmailTemplatesTest extends TestCase
{
    use RefreshDatabase;

    public function test_all_email_templates_are_seeded(): void
    {
        $expected = [
            'otp_login',
            'otp_email_verify',
            'registration_received',
            'registration_activated',
            'new_member_alert',
            'registration_confirmation',
            'form_invitation',
            'password_reset',
        ];

        foreach ($expected as $key) {
            $this->assertNotNull(EmailTemplate::forKey($key), "Template {$key} tidak wujud.");
        }
    }

    public function test_password_reset_uses_email_template(): void
    {
        $user = User::factory()->create(['name' => 'Ahmad Firdaus']);

        $notification = new ResetPassword('reset-token-abc');
        $mail = $notification->toMail($user);

        $this->assertStringContainsString('Tetapkan Semula Kata Laluan', $mail->subject);
        $this->assertStringContainsString('Ahmad Firdaus', $mail->viewData['body']);
        $this->assertStringContainsString('reset-token-abc', $mail->viewData['url']);
    }

    public function test_template_renders_placeholders(): void
    {
        $template = EmailTemplate::forKey('password_reset');

        $this->assertSame(
            'Tetapkan Semula Kata Laluan - myWAP',
            $template->renderSubject(['name' => 'Ahmad'])
        );

        $body = $template->renderBody(['name' => 'Ahmad', 'url' => 'https://mywap.my/reset']);
        $this->assertStringContainsString('Ahmad', $body);
        $this->assertStringContainsString('https://mywap.my/reset', $body);
    }

    public function test_superadmin_can_upload_header_image(): void
    {
        Storage::fake('public');

        Role::create(['name' => 'Superadmin', 'guard_name' => 'web']);
        Role::create(['name' => 'Admin', 'guard_name' => 'web']);

        $org = Organization::factory()->create();
        $superadmin = User::factory()->create([
            'current_organization_id' => $org->id,
            'profile_completed_at' => now(),
            'email_verified_at' => now(),
        ]);
        $superadmin->assignRole('Superadmin');

        $template = EmailTemplate::forKey('otp_login');

        $image = UploadedFile::fake()->image('logo.png', 200, 60);

        $response = $this->actingAs($superadmin)->post("/superadmin/email-templates/{$template->id}", [
            '_method' => 'put',
            'subject' => 'OTP Log Masuk - myWAP',
            'body' => 'Hello {{name}}, kod anda: {{code}}',
            'header_image' => $image,
            'remove_header_image' => false,
        ]);

        $response->assertSessionHasNoErrors();

        $template->refresh();
        $this->assertNotNull($template->header_image_path);
        $this->assertStringContainsString('/storage/', $template->header_image_path);

        Storage::disk('public')->assertExists(
            str_replace('/storage/', '', $template->header_image_path)
        );
    }

    public function test_superadmin_can_remove_header_image(): void
    {
        Storage::fake('public');

        Role::create(['name' => 'Superadmin', 'guard_name' => 'web']);

        $org = Organization::factory()->create();
        $superadmin = User::factory()->create([
            'current_organization_id' => $org->id,
            'profile_completed_at' => now(),
            'email_verified_at' => now(),
        ]);
        $superadmin->assignRole('Superadmin');

        $template = EmailTemplate::forKey('otp_login');

        $image = UploadedFile::fake()->image('logo.png', 200, 60);
        $template->update([
            'header_image_path' => '/storage/email-templates/logo.png',
        ]);
        Storage::disk('public')->put('email-templates/logo.png', $image->getContent());

        $response = $this->actingAs($superadmin)->post("/superadmin/email-templates/{$template->id}", [
            '_method' => 'put',
            'subject' => 'OTP Log Masuk - myWAP',
            'body' => 'Hello {{name}}, kod anda: {{code}}',
            'remove_header_image' => true,
        ]);

        $response->assertSessionHasNoErrors();

        $template->refresh();
        $this->assertNull($template->header_image_path);
        Storage::disk('public')->assertMissing('email-templates/logo.png');
    }
}
