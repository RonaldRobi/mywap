<?php

namespace Database\Seeders;

use App\Models\ArticleCategory;
use Illuminate\Database\Seeder;

class ArticleCategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            ['name' => 'Tarbiyah', 'description' => 'Pendidikan dan pembentukan diri', 'order' => 1],
            ['name' => 'Kepimpinan', 'description' => 'Kepimpinan dan pengurusan organisasi', 'order' => 2],
            ['name' => 'Belia', 'description' => 'Isu dan perkembangan belia', 'order' => 3],
            ['name' => 'Keluarga', 'description' => 'Keibubapaan dan institusi keluarga', 'order' => 4],
            ['name' => 'Kewangan', 'description' => 'Kewangan patuh Syariah', 'order' => 5],
            ['name' => 'Teknologi', 'description' => 'Teknologi dan digital', 'order' => 6],
            ['name' => 'Semasa', 'description' => 'Isu dan berita semasa', 'order' => 7],
        ];

        foreach ($categories as $data) {
            ArticleCategory::updateOrCreate(
                ['slug' => \Illuminate\Support\Str::slug($data['name'])],
                $data
            );
        }

        $this->command->info('✅  '.ArticleCategory::count(). ' article categories seeded.');
    }
}
