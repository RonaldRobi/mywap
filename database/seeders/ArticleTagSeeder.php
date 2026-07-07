<?php

namespace Database\Seeders;

use App\Models\ArticleTag;
use Illuminate\Database\Seeder;

class ArticleTagSeeder extends Seeder
{
    public function run(): void
    {
        $tags = [
            'tarbiyah', 'belia', 'kepimpinan', 'ramadhan', 'digital',
            'perpaduan', 'keluarga', 'kewangan', 'islam', 'profesional',
        ];

        foreach ($tags as $name) {
            ArticleTag::updateOrCreate(
                ['slug' => $name],
                ['name' => ucfirst($name)]
            );
        }

        $this->command->info('✅  '.ArticleTag::count(). ' article tags seeded.');
    }
}
