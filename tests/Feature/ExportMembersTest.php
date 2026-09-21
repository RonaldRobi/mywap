<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ExportMembersTest extends TestCase
{
    use RefreshDatabase;

    public function test_full_export_contains_address_columns(): void
    {
        Role::create(['name' => 'Superadmin', 'guard_name' => 'web']);

        $superadmin = User::factory()->create();
        $superadmin->assignRole('Superadmin');

        $member = User::factory()->create([
            'name' => 'Ali Test',
            'address_1' => 'No 1 Jalan Satu',
            'address_2' => 'Taman Test',
            'postcode' => '43000',
            'city' => 'Kajang',
            'state' => 'Selangor',
        ]);

        $response = $this->actingAs($superadmin)->get('/admin/members/export?type=full');

        $response->assertOk();
        $content = $response->streamedContent();

        fwrite(STDERR, "\nHEADER: ".strtok($content, "\n")."\n");
        $lines = explode("\n", $content);
        foreach (array_slice($lines, 1, 3) as $l) {
            fwrite(STDERR, "ROW: ".$l."\n");
        }

        $this->assertStringContainsString('Alamat 1', $content);
        $this->assertStringContainsString('No 1 Jalan Satu', $content);
    }
}
