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
        $user = $request->user();
        $isSuperadmin = $user->isSuperadmin();
        $orgId = (int) $user->current_organization_id;

        // Donors are a platform-wide aggregate, so an organisation admin may
        // only see donors who actually donated to their own organisation.
        $base = Donor::query();
        if (! $isSuperadmin) {
            $base->whereHas('donations.infaq', fn ($q) => $q->where('organization_id', $orgId));
        }

        $query = (clone $base)->orderByDesc('last_donated_at');

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
                'most' => $query->orderByDesc('total_donated'),
                'recent' => $query->orderByDesc('last_donated_at'),
                'frequent' => $query->orderByDesc('donation_count'),
                default => $query->orderByDesc('last_donated_at'),
            };
        }

        if (! $isSuperadmin) {
            $scopedConfirmed = fn ($q) => $q->where('status', 'confirmed')
                ->whereHas('infaq', fn ($q2) => $q2->where('organization_id', $orgId));
            $query->withSum(['donations as scoped_total' => $scopedConfirmed], 'amount')
                ->withCount(['donations as scoped_count' => $scopedConfirmed]);
        }

        $donors = $query->paginate(25)->withQueryString()->through(fn (Donor $d) => [
            'id' => $d->id,
            'name' => $d->name,
            'email' => $d->email,
            'phone' => $d->phone,
            'total_donated' => $isSuperadmin ? (float) $d->total_donated : (float) ($d->scoped_total ?? 0),
            'donation_count' => $isSuperadmin ? $d->donation_count : (int) ($d->scoped_count ?? 0),
            'last_donated_at' => $d->last_donated_at?->toDateTimeString(),
            'user_id' => $d->user_id,
        ]);

        if ($isSuperadmin) {
            $summary = [
                'total_donors' => Donor::count(),
                'total_amount' => Donor::sum('total_donated'),
                'total_donations' => Donor::sum('donation_count'),
                'avg_per_donor' => Donor::avg('total_donated') ?? 0,
            ];
        } else {
            $confirmed = InfaqDonation::query()
                ->where('status', 'confirmed')
                ->whereHas('infaq', fn ($q) => $q->where('organization_id', $orgId));

            $totalDonors = (clone $base)->count();
            $totalAmount = (float) (clone $confirmed)->sum('amount');

            $summary = [
                'total_donors' => $totalDonors,
                'total_amount' => $totalAmount,
                'total_donations' => (clone $confirmed)->count(),
                'avg_per_donor' => $totalDonors > 0 ? $totalAmount / $totalDonors : 0,
            ];
        }

        return Inertia::render('Admin/Donors/Index', [
            'donors' => $donors,
            'summary' => $summary,
            'filters' => $request->only(['search', 'sort']),
        ]);
    }

    public function show(Request $request, Donor $donor): Response
    {
        $user = $request->user();
        $isSuperadmin = $user->isSuperadmin();
        $orgId = (int) $user->current_organization_id;

        $donor->load('user');

        $donationQuery = InfaqDonation::with('infaq:id,title,slug,year,month,day')
            ->where('donor_id', $donor->id);

        if (! $isSuperadmin) {
            $donationQuery->whereHas('infaq', fn ($q) => $q->where('organization_id', $orgId));

            // An org admin must not open a donor who has no donation history
            // with their organisation.
            abort_if(! (clone $donationQuery)->exists(), 403);
        }

        $donations = $donationQuery
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (InfaqDonation $d) => [
                'id' => $d->id,
                'amount' => (float) $d->amount,
                'status' => $d->status,
                'reference' => $d->reference,
                'is_anonymous' => $d->is_anonymous,
                'is_recurring' => $d->is_recurring,
                'frequency' => $d->frequency,
                'recurring_status' => $d->recurring_status,
                'prayer_message' => $d->prayer_message,
                'created_at' => $d->created_at?->toDateTimeString(),
                'infaq_title' => $d->infaq?->title,
                'infaq_url' => $d->infaq ? route('infaq.show', [
                    'year' => $d->infaq->year,
                    'month' => $d->infaq->month,
                    'day' => $d->infaq->day,
                    'infaq' => $d->infaq->slug,
                ]) : null,
            ]);

        $confirmed = $donations->where('status', 'confirmed');

        $stats = [
            'confirmed_count' => $confirmed->count(),
            'confirmed_amount' => $confirmed->sum('amount'),
            'pending_count' => $donations->where('status', 'pending')->count(),
            'recurring_count' => $donations->where('is_recurring', true)->count(),
        ];

        return Inertia::render('Admin/Donors/Show', [
            'donor' => [
                'id' => $donor->id,
                'name' => $donor->name,
                'email' => $donor->email,
                'phone' => $donor->phone,
                'total_donated' => $isSuperadmin ? (float) $donor->total_donated : (float) $confirmed->sum('amount'),
                'donation_count' => $isSuperadmin ? $donor->donation_count : $confirmed->count(),
                'last_donated_at' => $donor->last_donated_at?->toDateTimeString(),
                'user_name' => $donor->user?->name,
                'user_email' => $donor->user?->email,
            ],
            'donations' => $donations,
            'stats' => $stats,
        ]);
    }
}
