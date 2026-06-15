<?php

namespace Database\Seeders;

use App\Models\Organization;
use App\Models\OrganizationPosition;
use Illuminate\Database\Seeder;

class OrganizationPositionSeeder extends Seeder
{
    public function run(): void
    {
        $defaults = [
            'Ahli Biasa',
            'AJK',
            'Presiden',
            'Naib Presiden',
            'Timbalan Presiden',
            'Setiausaha',
            'Timbalan Setiausaha',
            'Bendahari',
            'Timbalan Bendahari',
        ];

        foreach (Organization::all() as $org) {
            foreach ($defaults as $i => $name) {
                OrganizationPosition::firstOrCreate(
                    ['organization_id' => $org->id, 'name' => $name],
                    ['display_order' => $i]
                );
            }
        }
    }
}
