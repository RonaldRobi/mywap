<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\AppSetting;
use App\Models\Branch;
use App\Models\BranchTransitionHistory;
use App\Models\Organization;
use App\Models\User;
use App\Notifications\NewMemberAlert;
use App\Notifications\RegistrationActivated;
use App\Services\MembershipLetterService;
use App\Support\MemberRegistration;
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
            ->get(['id', 'name', 'slug', 'min_age', 'max_age', 'color_theme', 'logo_path']);

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
    public function store(Request $request, MembershipLetterService $letters): RedirectResponse
    {
        $normalizedIcNumber = MemberRegistration::normalizeIc($request->input('ic_number'));
        $request->merge([
            'ic_number' => $normalizedIcNumber,
            'email' => MemberRegistration::normalizeEmail($request->input('email')),
        ]);

        if (! $request->filled('dob') && $normalizedIcNumber) {
            $parsedDob = User::parseDobFromIc($normalizedIcNumber);
            if ($parsedDob) {
                $request->merge(['dob' => $parsedDob]);
            }
        }

        $request->validate(
            MemberRegistration::rules() + ['referred_by_user_id' => ['nullable', 'exists:users,id']],
            MemberRegistration::messages(),
            MemberRegistration::attributes(),
        );

        $dob = $request->date('dob');
        $gender = User::guessGenderFromIc($normalizedIcNumber);

        $organization = $dob ? Organization::forAge($dob->age) : null;
        $organization ??= Organization::query()->orderBy('min_age')->first();

        if ($organization && User::icExistsInOrganization($normalizedIcNumber, (int) $organization->id)) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'ic_number' => 'No. Kad Pengenalan ini sudah didaftarkan dalam organisasi ini.',
            ]);
        }

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
                $user->notify(new RegistrationActivated($user, route('login')));
            } catch (\Throwable) {
            }
        }

        try {
            $adminEmail = AppSetting::singleton()?->admin_contact_email;
            if ($adminEmail) {
                Notification::route('mail', $adminEmail)
                    ->notify(new NewMemberAlert($user));
            }
        } catch (\Throwable) {
        }

        try {
            $letters->sendIfEligible($user);
        } catch (\Throwable) {
        }

        return redirect()->route('login')->with(
            'status',
            "Pendaftaran berjaya! No Ahli anda: {$user->member_no}. Semak emel anda untuk panduan log masuk kali pertama."
        );
    }
}
