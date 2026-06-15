<?php

namespace Database\Seeders;

use App\Models\Postcode;
use Illuminate\Database\Seeder;

class PostcodeSeeder extends Seeder
{
    public function run(): void
    {
        $path = database_path('seeders/data/postcodes.json');
        $postcodes = json_decode(file_get_contents($path), true);

        foreach (array_chunk($postcodes, 200) as $chunk) {
            Postcode::insert($chunk);
        }

        $this->command->info('Seeded ' . count($postcodes) . ' postcodes.');
    }
}
