<?php

namespace Database\Seeders;

use App\Models\Organization;
use Illuminate\Database\Seeder;

/**
 * OrganizationSeeder
 *
 * Seeds the three canonical NGO tiers that form the MyMarhalah lifecycle:
 *   PKPIM  → Under 20    (Junior / Student)
 *   ABIM   → 20 – 29     (Youth)
 *   WADAH  → 30+         (Senior / Veteran)
 *
 * color_theme stores a CSS hex value used by the dynamic theming system to
 * shift the UI accent colour when a user transitions between organizations.
 */
class OrganizationSeeder extends Seeder
{
    public function run(): void
    {
        $organizations = [
            [
                'name'        => 'PKPIM',
                'slug'        => 'pkpim',
                'color_theme' => '#6366f1', // Indigo — youthful, academic
                'min_age'     => 0,
                'max_age'     => 19,
            ],
            [
                'name'        => 'ABIM',
                'slug'        => 'abim',
                'color_theme' => '#10b981', // Emerald — energetic, growth
                'min_age'     => 20,
                'max_age'     => 29,
            ],
            [
                'name'        => 'WADAH',
                'slug'        => 'wadah',
                'color_theme' => '#f59e0b', // Amber — wisdom, experience
                'min_age'     => 30,
                'max_age'     => null,      // No upper bound
            ],
        ];

        foreach ($organizations as $org) {
            Organization::updateOrCreate(
                ['slug' => $org['slug']],
                $org
            );
        }

        $this->command->info('✅  Organizations seeded: PKPIM, ABIM, WADAH');
    }
}
