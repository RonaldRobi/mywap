<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Organization;
use App\Models\Product;
use Illuminate\Database\Seeder;

class EcommerceDummySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            [
                'name' => 'Baju',
                'description' => 'T-shirt, polo, dan pakaian rasmi.',
            ],
            [
                'name' => 'Aksesori',
                'description' => 'Lanyard, pin, dan barang kecil.',
            ],
            [
                'name' => 'Buku & Media',
                'description' => 'Bahan bacaan & media organisasi.',
            ],
        ];

        $categoryModels = collect($categories)->map(function (array $payload) {
            return Category::firstOrCreate(
                ['name' => $payload['name']],
                ['description' => $payload['description']]
            );
        })->keyBy('name');

        $orgs = Organization::query()->get(['id', 'name', 'slug']);

        foreach ($orgs as $org) {
            Product::firstOrCreate(
                ['name' => 'T-Shirt Rasmi ' . strtoupper($org->slug), 'organisasi_id' => $org->id],
                [
                    'description' => 'T-Shirt rasmi untuk ' . $org->name . '.',
                    'price' => 35.00,
                    'stock' => 50,
                    'category_id' => $categoryModels['Baju']->id,
                    'status' => true,
                ]
            );

            Product::firstOrCreate(
                ['name' => 'Lanyard ' . strtoupper($org->slug), 'organisasi_id' => $org->id],
                [
                    'description' => 'Lanyard eksklusif ' . $org->name . '.',
                    'price' => 8.00,
                    'stock' => 200,
                    'category_id' => $categoryModels['Aksesori']->id,
                    'status' => true,
                ]
            );

            Product::firstOrCreate(
                ['name' => 'Buku Panduan Keahlian ' . strtoupper($org->slug), 'organisasi_id' => $org->id],
                [
                    'description' => 'Panduan ringkas modul keahlian untuk ' . $org->name . '.',
                    'price' => 12.00,
                    'stock' => 100,
                    'category_id' => $categoryModels['Buku & Media']->id,
                    'status' => true,
                ]
            );
        }
    }
}

