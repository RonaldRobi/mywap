<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\Organization;
use Illuminate\Database\Seeder;

class BranchSeeder extends Seeder
{
    /**
     * Seed sample branches for all 3 organisations.
     *
     * Pattern: {OrgName} {State}
     * We use the Malaysian 14 states + WP as the base list.
     */
    public function run(): void
    {
        $states = [
            'Selangor',
            'Kuala Lumpur',
            'Johor',
            'Kedah',
            'Kelantan',
            'Melaka',
            'Negeri Sembilan',
            'Pahang',
            'Perak',
            'Perlis',
            'Pulau Pinang',
            'Sabah',
            'Sarawak',
            'Terengganu',
        ];

        $orgs = Organization::all()->keyBy('slug');

        foreach (['pkpim', 'abim', 'wadah'] as $slug) {
            $org = $orgs[$slug] ?? null;
            if (! $org) continue;

            foreach ($states as $state) {
                Branch::firstOrCreate(
                    [
                        'organization_id' => $org->id,
                        'name'            => $state,
                    ],
                    [
                        'state'     => $state,
                        'is_active' => true,
                    ]
                );
            }
        }

        $this->command->info('✅  Branches seeded for PKPIM, ABIM & WADAH.');
    }
}
