<?php

namespace Tests\Feature\Console;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class ReportPlaceholderEmailsTest extends TestCase
{
    use RefreshDatabase;

    public function test_reports_placeholder_email_members_without_mutating_data(): void
    {
        $org = Organization::factory()->create(['name' => 'PKPIM', 'slug' => 'pkpim']);

        $legacy = User::factory()->create([
            'name' => 'Ahli Lama',
            'email' => 'a1234567@mywap.my',
            'ic_number' => 'A1234567',
            'current_organization_id' => $org->id,
            'first_login_at' => null,
        ]);

        User::factory()->create([
            'name' => 'Ahli Baharu',
            'email' => 'baru@real-mail.test',
            'ic_number' => 'C9999999',
            'current_organization_id' => $org->id,
        ]);

        $exitCode = Artisan::call('members:email-health');
        $output = Artisan::output();

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('Jumlah ahli dengan emel placeholder: 1', $output);
        $this->assertStringContainsString('Ahli Lama', $output);

        // Laporan sahaja: tiada perubahan pada data ahli.
        $this->assertSame('a1234567@mywap.my', $legacy->fresh()->email);
        $this->assertNull($legacy->fresh()->first_login_at);
    }
}
