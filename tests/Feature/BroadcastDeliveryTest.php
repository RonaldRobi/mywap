<?php

namespace Tests\Feature;

use App\Enums\EventCategory;
use App\Enums\EventStatus;
use App\Jobs\SendBroadcastJob;
use App\Models\BroadcastLog;
use App\Models\BroadcastMessage;
use App\Models\Category;
use App\Models\Event;
use App\Models\Organization;
use App\Models\Product;
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

    public function test_org_admin_all_stays_scoped_to_own_org_without_platform_label(): void
    {
        $admin = User::factory()->create([
            'name' => 'Pegawai PKPIM',
            'email' => 'pegawai@pkpim.test',
            'email_verified_at' => now(),
            'profile_completed_at' => now(),
            'current_organization_id' => $this->org->id,
        ]);
        $admin->assignRole('Admin');

        $this->actingAs($admin, 'web')
            ->post(route('admin.broadcasts.store'), [
                'title' => 'Untuk PKPIM',
                'content' => 'Hanya PKPIM.',
                'target_criteria' => 'all',
                'recipient_ids' => [],
                'notification_channels' => ['in_app'],
                'email_use_template' => false,
            ])
            ->assertSessionHasNoErrors();

        $message = BroadcastMessage::withoutGlobalScopes()->latest('id')->first();
        $this->assertSame('all', $message->target_criteria);
        $this->assertNull($message->sender_label);
        // 'Semua Ahli' untuk org-admin di-pin ke organisasi sendiri.
        $this->assertSame($this->org->id, (int) $message->target_organization_id);
    }

    public function test_superadmin_platform_send_gets_mywap_label_and_recipient_org_preview(): void
    {
        $abim = Organization::factory()->create(['name' => 'ABIM', 'slug' => 'abim']);
        $abimMember = User::factory()->create([
            'name' => 'Ahmad Bin Abu',
            'email' => 'ahmad.abim@test',
            'current_organization_id' => $abim->id,
        ]);

        $super = User::factory()->create([
            'name' => 'Super MyWAP',
            'email' => 'super@test',
            'email_verified_at' => now(),
            'profile_completed_at' => now(),
            'current_organization_id' => $this->org->id,
        ]);
        $super->assignRole('Superadmin');

        // Superadmin hantar ke individu tanpa pilih organisasi → sumber = MyWAP.
        $this->actingAs($super, 'web')
            ->post(route('admin.broadcasts.store'), [
                'title' => 'Hebahan Platform',
                'content' => 'Untuk semua tier.',
                'target_criteria' => 'specific_members',
                'recipient_ids' => [$abimMember->id],
                'notification_channels' => ['in_app'],
                'email_use_template' => false,
            ])
            ->assertSessionHasNoErrors();

        $message = BroadcastMessage::withoutGlobalScopes()->latest('id')->first();
        $this->assertSame('specific_members', $message->target_criteria);
        $this->assertSame(BroadcastMessage::PLATFORM_SENDER_LABEL, $message->sender_label);

        // Paparan sejarah: sumber 'MyWAP' (bukan org akaun) + penerima dari org sebenar (ABIM).
        $message->setRelation('organization', $this->org);
        $this->assertSame('MyWAP', $message->sender_label ?? $message->organization->name);

        $previews = $this->resolveRecipientPreviews($message);
        $this->assertSame($abimMember->name, $previews[$message->id][0]['name'] ?? null);
        $this->assertSame('ABIM', $previews[$message->id][0]['organization_name'] ?? null);
    }

    public function test_event_destination_is_saved_and_sent_in_notification_payload(): void
    {
        $admin = $this->makeAdmin();

        $event = Event::create([
            'organization_id' => $this->org->id,
            'title' => 'Program Kepimpinan Muda',
            'description' => 'Program ujian',
            'type' => 'physical',
            'status' => EventStatus::Published->value,
            'category' => EventCategory::Muktamar->value,
            'location_or_link' => 'Kuala Lumpur',
            'start_time' => now()->addMonth(),
            'end_time' => now()->addMonth()->addHours(3),
        ]);

        $this->actingAs($admin, 'web')
            ->post(route('admin.broadcasts.store'), [
                'title' => 'Program Akan Datang',
                'content' => 'Daftar sebelum tempat penuh.',
                'action_type' => 'event',
                'action_id' => $event->id,
                'action_label' => 'Daftar Program',
                'target_criteria' => 'organization',
                'notification_channels' => ['in_app'],
                'email_use_template' => false,
            ])
            ->assertSessionHasNoErrors();

        $message = BroadcastMessage::withoutGlobalScopes()->latest('id')->firstOrFail();

        $this->assertSame('event', $message->action_type);
        $this->assertSame($event->id, $message->action_id);
        $this->assertSame('Daftar Program', $message->action_label);
        $this->assertStringContainsString($event->slug, (string) $message->action_url);

        $notification = $this->memberA->fresh()->notifications()->firstOrFail();
        $this->assertSame('event', $notification->data['action_type']);
        $this->assertSame($event->id, $notification->data['action_id']);
        $this->assertSame('Daftar Program', $notification->data['action_label']);
    }

    public function test_product_destination_uses_member_product_url(): void
    {
        $admin = $this->makeAdmin();
        $category = Category::create(['name' => 'Pakaian']);

        $product = Product::create([
            'name' => 'Jersi PKPIM Baharu',
            'price' => 89,
            'stock' => 50,
            'postage_cost' => 8,
            'category_id' => $category->id,
            'organisasi_id' => $this->org->id,
            'status' => true,
        ]);

        $this->actingAs($admin, 'web')
            ->post(route('admin.broadcasts.store'), [
                'title' => 'Jersi Baharu',
                'content' => 'Koleksi terhad kini dibuka.',
                'action_type' => 'product',
                'action_id' => $product->id,
                'action_label' => 'Tempah Jersi',
                'target_criteria' => 'organization',
                'notification_channels' => ['in_app'],
                'email_use_template' => false,
            ])
            ->assertSessionHasNoErrors();

        $message = BroadcastMessage::withoutGlobalScopes()->latest('id')->firstOrFail();

        $this->assertSame('product', $message->action_type);
        $this->assertSame($product->id, $message->action_id);
        $this->assertStringContainsString('/products/'.$product->id, (string) $message->action_url);
    }

    public function test_web_notification_read_marks_only_selected_notification(): void
    {
        $first = $this->createBroadcast(['in_app']);
        $second = $this->createBroadcast(['in_app']);

        $this->memberA->notify(new GeneralBroadcastNotification($first));
        $this->memberA->notify(new GeneralBroadcastNotification($second));

        $notifications = $this->memberA->fresh()->notifications()->latest()->get();
        $this->assertCount(2, $notifications);

        $target = $notifications->first();

        $this->actingAs($this->memberA, 'web')
            ->post(route('notifications.read', $target->id))
            ->assertRedirect();

        $this->assertNotNull($target->fresh()->read_at);
        $this->assertNull($notifications->last()->fresh()->read_at);
    }

    private function makeAdmin(): User
    {
        $admin = User::factory()->create([
            'name' => 'Admin Siaran',
            'email' => 'admin-destination@pkpim.test',
            'email_verified_at' => now(),
            'profile_completed_at' => now(),
            'current_organization_id' => $this->org->id,
        ]);
        $admin->assignRole('Admin');

        return $admin;
    }

    private function resolveRecipientPreviews(BroadcastMessage $message): array
    {
        $method = new \ReflectionMethod(\App\Http\Controllers\BroadcastController::class, 'resolveRecipientPreviews');
        $method->setAccessible(true);

        return $method->invoke(app(\App\Http\Controllers\BroadcastController::class), collect([$message]));
    }
}
