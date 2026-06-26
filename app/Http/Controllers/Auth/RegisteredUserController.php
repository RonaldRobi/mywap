<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\AppSetting;
use App\Models\Branch;
use App\Models\BranchTransitionHistory;
use App\Models\Organization;
use App\Models\Payment;
use App\Models\User;
use App\Notifications\NewMemberAlert;
use App\Notifications\RegistrationActivated;
use App\Notifications\RegistrationReceived;
use App\Services\FeeService;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Role;

class RegisteredUserController extends Controller
{
    public function create(): Response
    {
        $refMemberNo = request()->query('ref');
        $referrer = null;

        if ($refMemberNo) {
            $referrer = User::where('member_no', $refMemberNo)->first(['id', 'name', 'member_no', 'current_organization_id']);
        }

        $organizations = Organization::query()
            ->orderBy('min_age')
            ->get(['id', 'name', 'slug', 'min_age', 'max_age', 'fee_amount', 'color_theme']);

        $branches = Branch::query()
            ->with('organization')
            ->where('is_active', true)
            ->get(['id', 'organization_id', 'name', 'state'])
            ->groupBy('organization_id')
            ->map->values();

        return Inertia::render('Auth/Register', [
            'organizations' => $organizations,
            'branches' => $branches,
            'referrer' => $referrer ? [
                'id' => $referrer->id,
                'name' => $referrer->name,
                'member_no' => $referrer->member_no,
            ] : null,
        ]);
    }

    /**
     * @throws ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        $normalizedIcNumber = Str::upper(preg_replace('/\s+/', '', trim((string) $request->input('ic_number'))) ?? '');
        $request->merge(['ic_number' => $normalizedIcNumber]);

        if (! $request->filled('dob') && $normalizedIcNumber) {
            $parsedDob = User::parseDobFromIc($normalizedIcNumber);
            if ($parsedDob) {
                $request->merge(['dob' => $parsedDob]);
            }
        }

        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|lowercase|email|max:255|unique:'.User::class,
            'ic_number' => 'required|string|max:32|unique:'.User::class.',ic_number',
            'phone' => 'nullable|string|max:20',
            'dob' => 'required|date',
            'branch_id' => 'nullable|exists:branches,id',
            'referred_by_user_id' => 'nullable|exists:users,id',
        ]);

        $dob = $request->date('dob');
        $gender = User::guessGenderFromIc($normalizedIcNumber);

        $organization = $dob ? Organization::forAge($dob->age) : null;
        $organization ??= Organization::query()->orderBy('min_age')->first();

        $branchId = $request->branch_id;
        if ($branchId && $organization) {
            $branch = Branch::find($branchId);
            if ($branch && $branch->organization_id !== $organization->id) {
                $branchId = null;
            }
        }

        $prefix = match ($organization?->slug) {
            'pkpim' => 'P',
            'abim' => 'A',
            'wadah' => 'W',
            default => 'M',
        };
        $padding = $prefix === 'W' ? 4 : 5;

        $max = User::where('member_no', 'like', $prefix.'%')
            ->max('member_no_sequence');
        $next = ($max ?? 0) + 1;
        $memberNo = $prefix.str_pad($next, $padding, '0', STR_PAD_LEFT);

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'ic_number' => $normalizedIcNumber,
            'phone' => $request->phone,
            'dob' => $dob,
            'gender' => $gender,
            'current_organization_id' => $organization?->id,
            'branch_id' => $branchId,
            'member_no' => $memberNo,
            'member_no_sequence' => $next,
            'original_member_no' => $memberNo,
            'referred_by_user_id' => $request->referred_by_user_id,
            'password' => Hash::make(Str::random(32)),
        ]);

        if ($branchId) {
            BranchTransitionHistory::create([
                'user_id' => $user->id,
                'from_branch_id' => null,
                'to_branch_id' => $branchId,
                'changed_by' => $user->id,
                'change_type' => 'registration',
            ]);
        }

        if (Role::query()->where('name', 'Member')->where('guard_name', 'web')->exists()) {
            $user->assignRole('Member');
        }

        event(new Registered($user));

        if ($user->email) {
            try {
                $user->notify(new RegistrationReceived($user));
            } catch (\Throwable) {}
        }

        session(['pending_registration_user_id' => $user->id]);

        return redirect()->route('register.payment');
    }

    /**
     * Show the registration payment page.
     */
    public function payment(): Response|RedirectResponse
    {
        $userId = session('pending_registration_user_id');

        if (! $userId) {
            return redirect()->route('register');
        }

        $user = User::with(['organization', 'branch'])->find($userId);

        if (! $user) {
            session()->forget('pending_registration_user_id');

            return redirect()->route('register');
        }

        return Inertia::render('Auth/RegisterPayment', [
            'registration' => [
                'id' => $user->id,
                'name' => $user->name,
                'ic_number' => $user->ic_number,
                'email' => $user->email,
                'phone' => $user->phone,
                'organization' => $user->organization?->name,
                'organization_color' => $user->organization?->color_theme,
                'branch' => $user->branch?->name ?? 'Tidak Berkenaan',
                'member_no' => $user->member_no,
                'fee_amount' => (float) ($user->organization?->fee_amount ?? 50.00),
            ],
        ]);
    }

    /**
     * Process the dummy registration payment.
     */
    public function processPayment(Request $request, FeeService $feeService): RedirectResponse
    {
        $userId = session('pending_registration_user_id');

        if (! $userId) {
            return redirect()->route('register');
        }

        $user = User::with(['organization', 'branch'])->find($userId);

        if (! $user) {
            session()->forget('pending_registration_user_id');

            return redirect()->route('register');
        }

        $year = now()->year;
        $feeAmount = (float) ($user->organization?->fee_amount ?? 50.00);

        $payment = Payment::create([
            'user_id' => $user->id,
            'payable_type' => 'membership_fee',
            'payable_id' => null,
            'amount' => $feeAmount,
            'status' => 'successful',
            'reference' => 'DUMMY-'.strtoupper(Str::random(8)),
            'description' => "Yuran Pendaftaran {$user->organization?->name} {$year}",
        ]);

        $feeService->markAsPaid($user, $year, $feeAmount, $payment->id);

        session()->forget('pending_registration_user_id');

        // Send emails
        if ($user->email) {
            try {
                $user->notify(new RegistrationActivated($user, route('login')));
            } catch (\Throwable) {}
        }

        try {
            $adminEmail = AppSetting::singleton()?->admin_contact_email;
            if ($adminEmail) {
                Notification::route('mail', $adminEmail)
                    ->notify(new NewMemberAlert($user));
            }
        } catch (\Throwable) {}

        return redirect()->route('login')->with('status', "Pendaftaran berjaya! No Ahli anda: {$user->member_no}. Sila log masuk kali pertama menggunakan No IC anda.");
    }
}
