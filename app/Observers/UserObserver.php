<?php

namespace App\Observers;

use App\Models\User;
use App\Services\SupporterService;

class UserObserver
{
    public function __construct(
        private SupporterService $supporters,
    ) {}

    public function created(User $user): void
    {
        $this->supporters->markConverted($user);
    }
}
