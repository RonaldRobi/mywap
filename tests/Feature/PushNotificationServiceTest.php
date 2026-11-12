<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\User;
use App\Services\PushNotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PushNotificationServiceTest extends TestCase
{
    use RefreshDatabase;

    private Organization $org;

    private User $user;

    private User $otherUser;

    protected function setUp(): void
    {
        parent::setUp();

        Role::create(['name' => 'Superadmin', 'guard_name' => 'web']);
        Role::create(['name' => 'Admin', 'guard_name' => 'web']);
        Role::create(['name' => 'Member', 'guard_name' => 'web']);

        $this->org = Organization::factory()->create(['name' => 'PKPIM', 'slug' => 'pkpim']);

        $this->user = User::factory()->create([
            'email' => 'ahli@pkpim.test',
            'current_organization_id' => $this->org->id,
            'first_login_at' => now(),
            'profile_completed_at' => now(),
        ]);

        // Paksa tiada kredential FCM supaya test kekal offline/deterministik
        // (tanpa config, service jadi no-op — kontrak asal).
        config()->set('services.fcm.service_account', '');
        config()->set('services.fcm.server_key', '');
        $this->otherUser = User::factory()->create([
            'email' => 'ahli2@pkpim.test',
            'current_organization_id' => $this->org->id,
            'first_login_at' => now(),
            'profile_completed_at' => now(),
        ]);
        $this->user->assignRole('Member');
        $this->otherUser->assignRole('Member');
    }

    public function test_register_plus_send_does_not_throw_without_fcm_key(): void
    {
        Http::fake();

        $service = app(PushNotificationService::class);

        $this->assertTrue($service->register($this->user, 'fcm-token-a', 'android'));
        $service->sendToUsers([$this->user], 'Title', 'Body', ['type' => 'broadcast']);

        Http::assertNothingSent();
    }

    public function test_send_to_user_filters_tokens(): void
    {
        Http::fake();

        $service = app(PushNotificationService::class);

        $service->register($this->user, 'fcm-token-a', 'android');
        $service->register($this->otherUser, 'fcm-token-b', 'ios');

        $service->sendToUser($this->user, 'Title', 'Body');

        $this->assertDatabaseCount('device_tokens', 2);
        Http::assertNothingSent();
    }

    public function test_unregister_removes_token(): void
    {
        $service = app(PushNotificationService::class);

        $service->register($this->user, 'fcm-token-a', 'android');
        $this->assertDatabaseCount('device_tokens', 1);

        $service->unregister($this->user, 'fcm-token-a');

        $this->assertDatabaseCount('device_tokens', 0);
    }

    public function test_send_to_organization_collects_only_org_tokens(): void
    {
        Http::fake();

        $otherOrg = Organization::factory()->create(['name' => 'ABIM', 'slug' => 'abim']);
        $outsider = User::factory()->create([
            'email' => 'abim@test',
            'current_organization_id' => $otherOrg->id,
            'first_login_at' => now(),
            'profile_completed_at' => now(),
        ]);
        $outsider->assignRole('Member');

        $service = app(PushNotificationService::class);

        $service->register($this->user, 'fcm-token-a', 'android');
        $service->register($this->otherUser, 'fcm-token-b', 'android');
        $service->register($outsider, 'fcm-token-outside', 'ios');

        $service->sendToOrganization($this->org->id, 'Title', 'Body');

        Http::assertNothingSent();
        $this->assertDatabaseCount('device_tokens', 3);
    }

    public function test_send_sets_unread_badge_in_push_payload(): void
    {
        // Jana service account palsu (kunci RSA sebenar diperlukan oleh JWT).
        $privateKey = null;
        $key = openssl_pkey_new([
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ]);
        $this->assertNotFalse($key, 'OpenSSL diperlukan untuk menjana kunci ujian.');
        openssl_pkey_export($key, $privateKey);

        $path = tempnam(sys_get_temp_dir(), 'fcm-sa-').'.json';
        file_put_contents($path, json_encode([
            'project_id' => 'test-project',
            'client_email' => 'test@test.iam.gserviceaccount.com',
            'private_key' => $privateKey,
        ]));

        config()->set('services.fcm.service_account', $path);
        config()->set('services.fcm.server_key', '');

        Http::fake([
            'oauth2.googleapis.com/token' => Http::response([
                'access_token' => 'fake-token',
                'expires_in' => 3600,
            ]),
            'fcm.googleapis.com/*' => Http::response([
                'name' => 'projects/test-project/messages/1',
            ]),
        ]);

        // Satu notifikasi belum dibaca untuk user ini.
        $this->user->notifications()->create([
            'id' => (string) \Illuminate\Support\Str::uuid(),
            'type' => 'App\\Notifications\\Test',
            'data' => ['title' => 'Test'],
        ]);

        $service = app(PushNotificationService::class);
        $service->register($this->user, 'fcm-token-a', 'ios');
        $service->sendToUser($this->user, 'Title', 'Body');

        Http::assertSent(function ($request) {
            $body = $request->data();

            return ($body['message']['apns']['payload']['aps']['badge'] ?? null) === 1
                && ($body['message']['android']['notification']['notification_count'] ?? null) === 1;
        });

        @unlink($path);
    }
}
