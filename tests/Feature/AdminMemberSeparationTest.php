<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AdminMemberSeparationTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_cannot_open_member_card_or_letter(): void
    {
        Role::findOrCreate('Admin');

        $org = Organization::factory()->create();
        $admin = User::factory()->create([
            'current_organization_id' => $org->id,
            'member_no' => 'ADMIN-001',
        ]);
        $admin->assignRole('Admin');

        $this->actingAs($admin)->get(route('member.card'))->assertForbidden();
        $this->actingAs($admin)->get(route('member.card.letter'))->assertForbidden();
        $this->get(route('public.card', ['memberNo' => 'ADMIN-001']))->assertNotFound();
    }
}
