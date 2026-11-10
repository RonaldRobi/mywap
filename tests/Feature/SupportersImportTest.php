<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\Supporter;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SupportersImportTest extends TestCase
{
    use RefreshDatabase;

    private Organization $org;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Role::create(['name' => 'Superadmin', 'guard_name' => 'web']);
        Role::create(['name' => 'Admin', 'guard_name' => 'web']);
        Role::create(['name' => 'Member', 'guard_name' => 'web']);

        $this->org = Organization::factory()->create(['name' => 'PKPIM', 'slug' => 'pkpim']);

        User::factory()->create([
            'email' => 'ahli@test.com',
            'current_organization_id' => $this->org->id,
            'profile_completed_at' => now(),
        ]);

        $this->admin = User::factory()->create([
            'current_organization_id' => $this->org->id,
            'profile_completed_at' => now(),
        ]);
        $this->admin->assignRole('Admin');
    }

    public function test_import_adds_new_emails_and_skips_existing_members(): void
    {
        $csv = "email,name,phone\n"
            ."ahli@test.com,Ahli Sedia Ada,0123456789\n"
            ."baru@test.com,Pendukung Baru,0129998888\n"
            ."bukan-emel,Nama Salah,\n";

        $file = UploadedFile::fake()->createWithContent('supporters.csv', $csv);

        $this->actingAs($this->admin)
            ->post(route('admin.supporters.import'), ['file' => $file])
            ->assertRedirect();

        // Emel baharu ditambah sebagai Pendukung.
        $this->assertDatabaseHas('supporters', [
            'email' => 'baru@test.com',
            'name' => 'Pendukung Baru',
            'organization_id' => $this->org->id,
            'source' => 'import',
        ]);

        // Emel ahli sedia ada tidak diduplikasi.
        $this->assertDatabaseMissing('supporters', ['email' => 'ahli@test.com']);

        // Baris tidak sah dilangkau.
        $this->assertSame(1, Supporter::query()->count());
    }
}
