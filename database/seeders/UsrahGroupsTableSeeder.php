<?php

namespace Database\Seeders;

use App\Models\UsrahGroup;
use Illuminate\Database\Seeder;

class UsrahGroupsTableSeeder extends Seeder
{
    public function run(): void
    {
        UsrahGroup::factory()->count(3)->create();
    }
}
