<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IdentityAndMembershipTest extends TestCase
{
    use RefreshDatabase;

    public function test_ic_is_normalized_to_twelve_digits_and_keeps_leading_zero(): void
    {
        $user = User::factory()->create([
            'name' => 'MUHAMAD HAFIZZUDIN BIN ZAMRI',
            'ic_number' => '940729-04-5407',
        ]);

        $this->assertSame('940729045407', $user->ic_number);
        $this->assertSame('Muhamad Hafizzudin Bin Zamri', $user->name);

        $leadingZero = User::factory()->create([
            'ic_number' => '011130 07 0810',
        ]);

        $this->assertSame('011130070810', $leadingZero->ic_number);
    }

    public function test_same_ic_can_exist_in_different_organizations(): void
    {
        $abim = Organization::factory()->create(['slug' => 'abim']);
        $wadah = Organization::factory()->create(['slug' => 'wadah']);

        $first = User::factory()->create([
            'current_organization_id' => $abim->id,
            'ic_number' => '900101011234',
        ]);

        $second = User::factory()->create([
            'current_organization_id' => $wadah->id,
            'ic_number' => '900101-01-1234',
        ]);

        $this->assertSame('900101011234', $first->ic_number);
        $this->assertSame('900101011234', $second->ic_number);
        $this->assertNotSame($first->id, $second->id);
        $this->assertTrue(User::icExistsInOrganization('900101011234', $abim->id));
        $this->assertFalse(User::icExistsInOrganization('900101011234', Organization::factory()->create()->id));
    }

    public function test_deceased_membership_status_exposes_badge(): void
    {
        $user = User::factory()->create([
            'membership_status' => 'deceased',
        ]);

        $this->assertTrue($user->isDeceased());
        $this->assertSame('Meninggal Dunia', $user->positionBadgePayload()['name']);
    }

    public function test_name_and_ic_normalizers_keep_identity(): void
    {
        $this->assertSame('Siti Nur Aisyah Binti Abu Bakar', User::formatName('SITI NUR AISYAH BINTI ABU BAKAR'));
        $this->assertSame('Mohd. Idris Bin Jusi', User::formatName('MOHD.  IDRIS BIN JUSI'));
        $this->assertSame('011130070810', User::normalizeIc('011130-07-0810'));
        $this->assertNull(User::normalizeIc(''));
    }
}
