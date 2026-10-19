<?php

namespace Tests\Feature;

use App\Enums\EventCategory;
use App\Enums\EventStatus;
use App\Models\Event;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class EventQrPrintTest extends TestCase
{
    use RefreshDatabase;

    private Organization $org;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Role::create(['name' => 'Superadmin', 'guard_name' => 'web']);
        Role::create(['name' => 'Admin', 'guard_name' => 'web']);
        Role::create(['name' => 'org-admin', 'guard_name' => 'web']);

        $this->org = Organization::factory()->create(['name' => 'PKPIM']);

        $this->admin = User::factory()->create([
            'current_organization_id' => $this->org->id,
            'profile_completed_at' => now(),
        ]);
        $this->admin->assignRole('Admin');
    }

    private function event(): Event
    {
        return Event::create([
            'organization_id' => $this->org->id,
            'title' => 'Muktamar Nasional 2026',
            'description' => 'Test',
            'type' => 'physical',
            'status' => EventStatus::Published->value,
            'category' => EventCategory::Muktamar->value,
            'location_or_link' => 'Dewan Tun Dr Ismail, Kuala Lumpur',
            'start_time' => now()->addDay()->setTime(9, 0),
            'end_time' => now()->addDay()->setTime(17, 0),
        ]);
    }

    public function test_admin_can_print_qr_only_sheet(): void
    {
        $event = $this->event();

        $this->actingAs($this->admin)
            ->get(route('events.qr.print', ['event' => $event->id, 'layout' => 'qr']))
            ->assertOk()
            ->assertSee('sheet-qr', false)
            ->assertSee('<svg', false);
    }

    public function test_admin_can_print_programme_poster_with_event_details(): void
    {
        $event = $this->event();

        $this->actingAs($this->admin)
            ->get(route('events.qr.print', ['event' => $event->id, 'layout' => 'poster']))
            ->assertOk()
            ->assertSee('Muktamar Nasional 2026')
            ->assertSee('Dewan Tun Dr Ismail, Kuala Lumpur')
            ->assertSee('Sahkan kehadiran anda')
            ->assertSee('Satu imbasan untuk setiap peserta.')
            ->assertSee('myWAP &middot; Wadah &middot; ABIM &middot; PKPIM', false)
            ->assertSee('<svg', false);
    }

    public function test_poster_includes_mywap_wordmark_and_organization_logo(): void
    {
        Storage::fake('public');

        $png = base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAFgwJ/lK3V9wAAAABJRU5ErkJggg=='
        );
        Storage::disk('public')->put('logos/organizations/test.png', $png);

        $this->org->update(['logo_path' => '/storage/logos/organizations/test.png']);

        $event = $this->event();

        $this->actingAs($this->admin)
            ->get(route('events.qr.print', ['event' => $event->id, 'layout' => 'poster']))
            ->assertOk()
            ->assertSee('myWAP')
            ->assertSee('Anjuran')
            ->assertSee('Logo PKPIM')
            ->assertSee('data:image/png;base64,', false);
    }

    public function test_other_organization_admin_cannot_print_event_qr(): void
    {
        $event = $this->event();

        $otherOrg = Organization::factory()->create(['name' => 'ABIM']);
        $otherAdmin = User::factory()->create([
            'current_organization_id' => $otherOrg->id,
            'profile_completed_at' => now(),
        ]);
        $otherAdmin->assignRole('Admin');

        $this->actingAs($otherAdmin)
            ->get(route('events.qr.print', ['event' => $event->id, 'layout' => 'poster']))
            ->assertForbidden();
    }

    public function test_invalid_layout_returns_not_found(): void
    {
        $event = $this->event();

        $this->actingAs($this->admin)
            ->get(route('events.qr.print', ['event' => $event->id, 'layout' => 'banner']))
            ->assertNotFound();
    }

    public function test_qr_download_is_transparent_png_by_default(): void
    {
        if (! extension_loaded('gd')) {
            $this->markTestSkipped('GD extension is required to inspect PNG alpha.');
        }

        $event = $this->event();

        $response = $this->actingAs($this->admin)
            ->get(route('events.qr.download', $event->id))
            ->assertOk()
            ->assertHeader('Content-Type', 'image/png');

        $this->assertStringContainsString('transparent', $response->headers->get('Content-Disposition'));

        $image = imagecreatefromstring($response->getContent());
        $this->assertNotFalse($image);

        $corner = imagecolorsforindex($image, imagecolorat($image, 0, 0));
        $this->assertSame(127, $corner['alpha'], 'The quiet-zone corner should be fully transparent.');

        $opaque = 0;
        for ($x = 0; $x < imagesx($image); $x += 4) {
            for ($y = 0; $y < imagesy($image); $y += 4) {
                $color = imagecolorsforindex($image, imagecolorat($image, $x, $y));
                if ($color['alpha'] === 0) {
                    $opaque++;
                }
            }
        }

        $this->assertGreaterThan(0, $opaque, 'The QR modules should remain opaque.');
    }

    public function test_qr_download_can_still_return_white_background(): void
    {
        if (! extension_loaded('gd')) {
            $this->markTestSkipped('GD extension is required to inspect PNG pixels.');
        }

        $event = $this->event();

        $response = $this->actingAs($this->admin)
            ->get(route('events.qr.download', ['event' => $event->id, 'white' => 1]))
            ->assertOk();

        $this->assertStringNotContainsString('transparent', $response->headers->get('Content-Disposition'));

        $image = imagecreatefromstring($response->getContent());
        $corner = imagecolorsforindex($image, imagecolorat($image, 0, 0));

        $this->assertSame(0, $corner['alpha']);
        $this->assertSame(255, $corner['red']);
        $this->assertSame(255, $corner['green']);
        $this->assertSame(255, $corner['blue']);
    }
}
