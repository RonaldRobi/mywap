<?php

namespace Tests\Feature;

use App\Jobs\SendBroadcastJob;
use App\Models\BroadcastLog;
use App\Models\BroadcastMessage;
use App\Models\Organization;
use App\Models\User;
use App\Notifications\GeneralBroadcastNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class BroadcastDeliveryTest extends TestCase
{
    use RefreshDatabase;

    private Organization $org;

    private User $memberA;

    private User $memberB;

    private User $memberNoEmail;

    protected function setUp(): void
    {
        parent::setUp();

        Role::create(['name' => 'Superadmin', 'guard_name' => 'web']);
        Role::create(['name' => 'Admin', 'guard_name' => 'web']);
        Role::create(['name' => 'Member', 'guard_name' => 'web']);

        // Pastikan FCM no-op (offline) supaya tiada panggilan rangkaian sebenar.
        config()->set('services.fcm.service_account', '');
        config()->set('services.fcm.server_key', '');

        $this->org = Organization::factory()->create(['name' => 'PKPIM', 'slug' => 'pkpim']);

        $this->memberA = User::factory()->create([
            'name' => 'Ahmad Firdaus',
            'email' => 'ahmad@pkpim.test',
            'current_organization_id' => $this->org->id,
        ]);
        $this->memberB = User::factory()->create([
            'name' => 'Siti Fatimah',
            'email' => 'siti@pkpim.test',
            'current_organization_id' => $this->org->id,
        ]);
        $this->memberNoEmail = User::factory()->create([
            'name' => 'Tanpa Emel',
            'email' => '',
            'current_organization_id' => $this->org->id,
        ]);
    }

    private function createBroadcast(array $channels, ?string $criteria = 'organization'): BroadcastMessage
    {
        return BroadcastMessage::create([
            'organization_id' => $this->org->id,
            'target_organization_id' => $this->org->id,
            'title' => 'Tajuk Siaran',
            'content' => 'Kandungan siaran.',
            'target_criteria' => $criteria,
            'recipient_ids' => $criteria === 'specific_members' ? [$this->memberA->id] : null,
            'notification_channels' => $channels,
            'email_use_template' => false,
        ]);
    }

    public function test_in_app_broadcast_writes_database_notifications_and_marks_completed(): void
    {
        $message = $this->createBroadcast(['in_app']);

        Bus::dispatchSync(new SendBroadcastJob($message->id));

        $fresh = BroadcastMessage::withoutGlobalScopes()->find($message->id);

        $this->assertSame('completed', $fresh->status);
        $this->assertSame(3, $fresh->recipient_count);
        $this->assertSame(3, $fresh->success_count);
        $this->assertSame(0, $fresh->failed_count);
        $this->assertNotNull($fresh->started_at);
        $this->assertNotNull($fresh->finished_at);
        $this->assertNotNull($fresh->sent_at);

        // Setiap ahli menerima bell notifikasi dalam-app (table notifications).
        $this->assertSame(3, DB::table('notifications')->where('notifiable_type', User::class)->count());

        $this->assertTrue(BroadcastLog::query()
            ->where('broadcast_message_id', $message->id)
            ->where('event', 'processing')
            ->exists());
        $this->assertTrue(BroadcastLog::query()
            ->where('broadcast_message_id', $message->id)
            ->where('event', 'completed')
            ->exists());
    }

    public function test_email_only_broadcast_skips_users_without_email_and_sends_no_database_notifications(): void
    {
        $message = $this->createBroadcast(['email']);

        Bus::dispatchSync(new SendBroadcastJob($message->id));

        $fresh = BroadcastMessage::withoutGlobalScopes()->find($message->id);

        // Hanya 2 ahli ber-emel; ahli tanpa emel tidak menerima saluran emel.
        $this->assertSame('completed', $fresh->status);
        $this->assertSame(2, $fresh->recipient_count);
        $this->assertSame(2, $fresh->success_count);
        $this->assertSame(0, $fresh->failed_count);

        $this->assertSame(0, DB::table('notifications')->where('notifiable_type', User::class)->count());
    }

    public function test_via_maps_ui_channels_to_laravel_channels(): void
    {
        $inApp = $this->createBroadcast(['in_app']);
        $inAppAndEmail = $this->createBroadcast(['in_app', 'email']);
        $emailOnly = $this->createBroadcast(['email']);

        $notificationInApp = new GeneralBroadcastNotification($inApp);
        $notificationBoth = new GeneralBroadcastNotification($inAppAndEmail);
        $notificationEmail = new GeneralBroadcastNotification($emailOnly);

        $this->assertSame(['database'], $notificationInApp->via($this->memberA));
        $this->assertSame(['database', 'mail'], $notificationBoth->via($this->memberA));
        // Ahli tanpa emel tidak boleh menerima 'email'.
        $this->assertSame(['database'], $notificationBoth->via($this->memberNoEmail));
        $this->assertSame(['mail'], $notificationEmail->via($this->memberA));
        $this->assertSame([], $notificationEmail->via($this->memberNoEmail));
    }

    public function test_to_mail_carries_broadcast_title_and_content(): void
    {
        $message = $this->createBroadcast(['email']);
        $mail = (new GeneralBroadcastNotification($message))->toMail($this->memberA);

        $this->assertInstanceOf(MailMessage::class, $mail);
        $this->assertSame('Tajuk Siaran', $mail->subject);
    }

    public function test_web_admin_store_specific_members_creates_and_sends(): void
    {
        $admin = User::factory()->create([
            'name' => 'Admin Siaran',
            'email' => 'admin@pkpim.test',
            'email_verified_at' => now(),
            'profile_completed_at' => now(),
            'current_organization_id' => $this->org->id,
        ]);
        $admin->assignRole('Admin');

        $this->actingAs($admin, 'web')
            ->post(route('admin.broadcasts.store'), [
                'title' => 'Makluman Khas',
                'content' => 'Untuk ahli terpilih sahaja.',
                'target_criteria' => 'specific_members',
                'recipient_ids' => [$this->memberA->id],
                'notification_channels' => ['in_app'],
                'email_use_template' => false,
            ])
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success');

        $message = BroadcastMessage::withoutGlobalScopes()->latest('id')->first();
        $this->assertNotNull($message);
        $this->assertSame('specific_members', $message->target_criteria);
        $this->assertSame([$this->memberA->id], $message->recipient_ids);

        // Job berjalan segerak dalam test → bell dalam-app dihantar kepada memberA.
        $this->assertSame(1, DB::table('notifications')->count());

        $fresh = BroadcastMessage::withoutGlobalScopes()->find($message->id);
        $this->assertSame('completed', $fresh->status);
        $this->assertSame(1, $fresh->recipient_count);
        $this->assertSame(1, $fresh->success_count);
    }
}
