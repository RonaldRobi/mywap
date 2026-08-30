<?php

namespace Tests\Feature;

use App\Models\EmailTemplate;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
}
