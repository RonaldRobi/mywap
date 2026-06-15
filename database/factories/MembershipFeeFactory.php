<?php

namespace Database\Factories;

use App\Models\MembershipFee;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class MembershipFeeFactory extends Factory
{
    protected $model = MembershipFee::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'organization_id' => Organization::factory(),
            'year' => now()->year,
            'amount' => 50.00,
            'status' => 'unpaid',
        ];
    }
}
