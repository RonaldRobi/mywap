<?php

namespace Database\Factories;

use App\Models\Organization;
use App\Models\UsrahGroup;
use Illuminate\Database\Eloquent\Factories\Factory;

class UsrahGroupFactory extends Factory
{
    protected $model = UsrahGroup::class;

    public function definition(): array
    {
        $days = ['Isnin', 'Selasa', 'Rabu', 'Khamis', 'Jumaat', 'Sabtu', 'Ahad'];

        return [
            'organization_id' => Organization::factory(),
            'name' => 'Usrah ' . fake()->words(2, true),
            'description' => fake()->sentence(),
            'meeting_day' => fake()->randomElement($days),
            'meeting_time' => fake()->time('H:i'),
            'is_active' => true,
        ];
    }
}
