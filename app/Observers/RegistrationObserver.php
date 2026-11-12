<?php

namespace App\Observers;

use App\Models\Registration;
use App\Services\SupporterService;

class RegistrationObserver
{
    public function __construct(
        private SupporterService $supporters,
    ) {}

    public function created(Registration $registration): void
    {
        $this->supporters->syncFromRegistration($registration);
    }

    public function updated(Registration $registration): void
    {
        $relevant = $registration->wasChanged([
            'name',
            'email',
            'phone',
            'ic_number',
            'user_id',
            'organization_id',
            'event_id',
            'source',
        ]);

        if ($relevant) {
            $this->supporters->syncFromRegistration($registration);
        }
    }
}
