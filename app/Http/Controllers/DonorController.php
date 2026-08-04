<?php

namespace App\Http\Controllers;

use App\Models\Donor;
use App\Models\InfaqDonation;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DonorController extends Controller
{
    public function index(Request $request): Response
    {
        $query = Donor::query()->orderByDesc('last_donated_at');

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        if ($request->filled('sort')) {
            match ($request->sort) {
                'most'       => $query->orderByDesc('total_donated'),
                'recent'     => $query->orderByDesc('last_donated_at'),
                'frequent'   => $query->orderByDesc('donation_count'),
                default      => $query->orderByDesc('last_donated_at'),
            };
        }

        $donors = $query->paginate(25)->withQueryString()->through(fn (Donor $d) => [
            'id'             => $d->id,
            'name'           => $d->name,
            'email'          => $d->email,
            'phone'          => $d->phone,
            'total_donated'  => (float) $d->total_donated,
            'donation_count' => $d->donation_count,
            'last_donated_at'=> $d->last_donated_at?->toDateTimeString(),
            'user_id'        => $d->user_id,
        ]);

        $summary = [
            'total_donors'  => Donor::count(),
            'total_amount'  => Donor::sum('total_donated'),
            'total_donations'=> Donor::sum('donation_count'),
            'avg_per_donor' => Donor::avg('total_donated') ?? 0,
        ];

        return Inertia::render('Admin/Donors/Index', [
            'donors'  => $donors,
            'summary' => $summary,
            'filters' => $request->only(['search', 'sort']),
        ]);
    }

    public function show(Donor $donor): Response
    {
        $donor->load('user');

        $donations = InfaqDonation::with('infaq:id,title,slug,year,month,day')
            ->where('donor_id', $donor->id)
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (InfaqDonation $d) => [
                'id'           => $d->id,
                'amount'       => (float) $d->amount,
                'status'       => $d->status,
                'reference'    => $d->reference,
                'is_anonymous' => $d->is_anonymous,
                'is_recurring' => $d->is_recurring,
                'frequency'    => $d->frequency,
                'recurring_status' => $d->recurring_status,
                'prayer_message' => $d->prayer_message,
                'created_at'   => $d->created_at?->toDateTimeString(),
                'infaq_title'  => $d->infaq?->title,
                'infaq_url'    => $d->infaq ? route('infaq.show', [
                    'year' => $d->infaq->year,
                    'month' => $d->infaq->month,
                    'day' => $d->infaq->day,
                    'infaq' => $d->infaq->slug,
                ]) : null,
            ]);

        $stats = [
            'confirmed_count'   => $donations->where('status', 'confirmed')->count(),
            'confirmed_amount'  => $donations->where('status', 'confirmed')->sum('amount'),
            'pending_count'     => $donations->where('status', 'pending')->count(),
            'recurring_count'   => $donations->where('is_recurring', true)->count(),
        ];

        return Inertia::render('Admin/Donors/Show', [
            'donor' => [
                'id'             => $donor->id,
                'name'           => $donor->name,
                'email'          => $donor->email,
                'phone'          => $donor->phone,
                'total_donated'  => (float) $donor->total_donated,
                'donation_count' => $donor->donation_count,
                'last_donated_at'=> $donor->last_donated_at?->toDateTimeString(),
                'user_name'      => $donor->user?->name,
                'user_email'     => $donor->user?->email,
            ],
            'donations' => $donations,
            'stats'     => $stats,
        ]);
    }
}
