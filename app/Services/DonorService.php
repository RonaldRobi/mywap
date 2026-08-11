<?php

namespace App\Services;

use App\Models\Donor;
use App\Models\InfaqDonation;

class DonorService
{
    public function findOrCreate(InfaqDonation $donation): Donor
    {
        $donor = null;

        if ($donation->user_id) {
            $donor = Donor::where('user_id', $donation->user_id)->first();
        }

        if (! $donor && $donation->donor_email) {
            $donor = Donor::where('email', $donation->donor_email)->first();
        }

        if (! $donor && $donation->donor_phone) {
            $donor = Donor::where('phone', $donation->donor_phone)->first();
        }

        if (! $donor) {
            $donor = Donor::create([
                'name' => $donation->donor_name ?? $donation->user?->name ?? 'Tanpa Nama',
                'email' => $donation->donor_email,
                'phone' => $donation->donor_phone,
                'user_id' => $donation->user_id,
            ]);
        } else {
            if (! $donor->user_id && $donation->user_id) {
                $donor->update(['user_id' => $donation->user_id]);
            }
            if (empty($donor->email) && $donation->donor_email) {
                $donor->update(['email' => $donation->donor_email]);
            }
            if (empty($donor->phone) && $donation->donor_phone) {
                $donor->update(['phone' => $donation->donor_phone]);
            }
        }

        return $donor;
    }

    public function incrementDonor(Donor $donor, float $amount): void
    {
        $donor->update([
            'total_donated' => $donor->total_donated + $amount,
            'donation_count' => $donor->donation_count + 1,
            'last_donated_at' => now(),
        ]);
    }

    public function recalculateAll(): void
    {
        $donors = Donor::with('donations')->get();

        foreach ($donors as $donor) {
            $confirmed = $donor->donations->where('status', 'confirmed');
            $donor->update([
                'total_donated' => $confirmed->sum('amount'),
                'donation_count' => $confirmed->count(),
                'last_donated_at' => $confirmed->max('created_at'),
            ]);
        }
    }
}
