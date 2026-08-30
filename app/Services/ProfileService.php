<?php

namespace App\Services;

use App\Http\Requests\ProfileUpdateRequest;
use App\Models\Branch;
use App\Models\BranchChangeRequest;
use App\Models\EventRsvp;
use App\Models\OrganizationPosition;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * ProfileService
 *
 * Logik tunggal untuk domain Profile — dikongsi oleh WebController (Inertia)
 * dan ApiController (JSON) supaya web & Flutter tidak drift.
 * Rujuk docs/FLUTTER_PLAN.md §4.
 */
class ProfileService
{
    public function __construct(private readonly FeeService $feeService) {}

    /**
     * Serialize satu User kepada bentuk profil yang sama untuk web & API.
     * Kekunci data mesti kekal konsisten dengan prop Inertia profileUser.
     */
    public function serializeProfile(User $user): array
    {
        $user->loadMissing(['organization', 'branch']);

        $isSuperadmin = $user->hasRole(['Superadmin', 'Admin']);

        return [
            'id' => $user->id,
            'member_no' => $user->member_no,
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'ic_number' => $user->ic_number,
            'roles' => $user->getRoleNames()->values(),
            'dob' => $isSuperadmin ? null : $user->dob?->format('d M Y'),
            'age' => $isSuperadmin ? null : $user->dob?->age,
            'gender' => $user->gender,
            'marital_status' => $user->marital_status,
            'education_level' => $user->education_level,
            'current_profession' => $user->current_profession,
            'industry' => $user->industry,
            'expertise' => $user->expertise,
            'topics' => $user->topics,
            'position' => $user->position,
            'branch_name' => $user->branch?->name,
            'locality' => $user->locality,
            'address_1' => $user->address_1,
            'address_2' => $user->address_2,
            'postcode' => $user->postcode,
            'city' => $user->city,
            'state' => $user->state,
            'emergency_contact_name' => $user->emergency_contact_name,
            'emergency_contact_phone' => $user->emergency_contact_phone,
            'organization' => $user->organization ? [
                'id' => $user->organization->id,
                'name' => $user->organization->name,
                'slug' => $user->organization->slug,
                'color_theme' => $user->organization->color_theme,
            ] : null,
            'feeStatus' => $this->feeService->getStatus($user),
        ];
    }

    /**
     * Payload penuh untuk halaman/endpoint show profil (perjalanan ahli).
     */
    public function showPayload(User $user): array
    {
        $user->loadMissing(['organization', 'branch']);

        $attendedPrograms = EventRsvp::query()
            ->where('user_id', $user->id)
            ->where('status', 'attended')
            ->with(['event.organization'])
            ->latest('attended_at')
            ->take(12)
            ->get()
            ->filter(fn ($rsvp) => $rsvp->event !== null)
            ->map(fn ($rsvp) => [
                'id' => $rsvp->id,
                'event' => [
                    'id' => $rsvp->event->id,
                    'title' => $rsvp->event->title,
                    'start_formatted' => $rsvp->event->start_time->locale('ms')->isoFormat('ddd, D MMM YYYY [•] h:mm A'),
                    'location_or_link' => $rsvp->event->location_or_link,
                    'organization' => [
                        'name' => $rsvp->event->organization?->name,
                        'color_theme' => $rsvp->event->organization?->color_theme,
                    ],
                ],
                'attended_at' => $rsvp->attended_at?->toISOString(),
                'attended_at_human' => $rsvp->attended_at?->locale('ms')->isoFormat('D MMM YYYY, h:mm A'),
            ])
            ->values();

        $history = $user->transitionHistory()
            ->limit(100)
            ->get()
            ->map(fn ($record) => [
                'id' => $record->id,
                'from_organization' => $record->fromOrganization
                    ? ['id' => $record->fromOrganization->id, 'name' => $record->fromOrganization->name, 'slug' => $record->fromOrganization->slug, 'color_theme' => $record->fromOrganization->color_theme]
                    : null,
                'to_organization' => ['id' => $record->toOrganization->id, 'name' => $record->toOrganization->name, 'slug' => $record->toOrganization->slug, 'color_theme' => $record->toOrganization->color_theme],
                'transitioned_at' => $record->transitioned_at->toISOString(),
                'transitioned_at_human' => $record->transitioned_at->translatedFormat('d F Y'),
            ]);

        return [
            'profileUser' => $this->serializeProfile($user),
            'history' => $history,
            'attendedPrograms' => $attendedPrograms,
        ];
    }

    /**
     * Meta borang profil (edit) — cawangan, jawatan, kebenaran edit IC.
     */
    public function editMeta(User $user): array
    {
        $branches = $user->current_organization_id
            ? Branch::where('organization_id', $user->current_organization_id)
                ->where('is_active', true)
                ->orderBy('state')
                ->get(['id', 'name', 'state'])
            : collect();

        $positions = $user->current_organization_id
            ? OrganizationPosition::where('organization_id', $user->current_organization_id)
                ->orderBy('display_order')
                ->get(['id', 'name'])
            : collect();

        $pendingRequest = BranchChangeRequest::where('user_id', $user->id)
            ->where('status', 'pending')
            ->with('toBranch:id,name')
            ->first();

        return [
            'branches' => $branches,
            'orgPositions' => $positions,
            'canEditIcNumber' => $user->hasRole(['Superadmin', 'Admin']),
            'pendingBranchRequest' => $pendingRequest ? [
                'to_branch' => $pendingRequest->toBranch?->name,
            ] : null,
        ];
    }

    /**
     * Meta untuk skrin "lengkapkan profil" — DOB & jantina diekstrak dari IC.
     */
    public function completeMeta(User $user): array
    {
        return [
            'parsedDob' => User::parseDobFromIc($user->ic_number),
            'parsedGender' => User::guessGenderFromIc($user->ic_number),
        ];
    }

    /**
     * Aturan validasi untuk skrin "lengkapkan profil" (dikongsi web + API).
     */
    public static function completeRules(): array
    {
        return [
            'education_level' => ['required', 'string', 'max:120'],
            'current_profession' => ['required', 'string', 'max:120'],
            'phone' => ['required', 'string', 'max:30'],
            'dob' => ['nullable', 'date'],
            'gender' => ['nullable', 'in:lelaki,perempuan'],
            'marital_status' => ['nullable', 'in:bujang,berkahwin,bercerai,duda/janda'],
            'address_1' => ['nullable', 'string', 'max:255'],
            'address_2' => ['nullable', 'string', 'max:255'],
            'postcode' => ['nullable', 'string', 'max:5'],
            'city' => ['nullable', 'string', 'max:100'],
            'state' => ['nullable', 'string', 'max:100'],
            'emergency_contact_name' => ['nullable', 'string', 'max:255'],
            'emergency_contact_phone' => ['nullable', 'string', 'max:30'],
            'topics' => ['nullable', 'string'],
        ];
    }

    /**
     * Simpan profil "lengkapkan" — auto-isi DOB & jantina dari IC jika kosong.
     */
    public function completeProfile(User $user, array $data): User
    {
        if (empty($data['dob']) && $user->ic_number) {
            $data['dob'] = User::parseDobFromIc($user->ic_number);
        }

        if (empty($data['gender']) && $user->ic_number) {
            $data['gender'] = User::guessGenderFromIc($user->ic_number);
        }

        $user->update([
            ...$data,
            'profile_completed_at' => now(),
        ]);

        return $user;
    }

    /**
     * Kemas kini profil (web & API). Pulangkan true jika wujud permohonan
     * pertukaran cawangan yang perlu dimaklumkan kepada user.
     */
    public function updateProfile(User $user, ProfileUpdateRequest $request): bool
    {
        $validated = $request->validated();
        $canEditIcNumber = $user->hasRole(['Superadmin', 'Admin']);
        $isSuperadmin = $user->hasRole('Superadmin');

        if (array_key_exists('ic_number', $validated)) {
            if ($canEditIcNumber) {
                $validated['ic_number'] = trim((string) $validated['ic_number']) === ''
                    ? null
                    : Str::upper(preg_replace('/\s+/', '', trim((string) $validated['ic_number'])) ?? '');
            } else {
                unset($validated['ic_number']);
            }
        }

        if ($isSuperadmin) {
            foreach ([
                'education_level',
                'current_profession',
                'industry',
                'branch_id',
                'locality',
                'expertise',
                'linkedin_url',
                'is_public_in_directory',
                'gender',
                'marital_status',
                'emergency_contact_name',
                'emergency_contact_phone',
                'position',
                'topics',
            ] as $field) {
                unset($validated[$field]);
            }
        }

        if (! $isSuperadmin) {
            $validated['is_public_in_directory'] = $request->boolean('is_public_in_directory');

            $submittedBranchId = ! empty($validated['branch_id']) ? (int) $validated['branch_id'] : null;
            $currentBranchId = $user->branch_id;

            if ($submittedBranchId !== $currentBranchId && $submittedBranchId) {
                BranchChangeRequest::where('user_id', $user->id)
                    ->where('status', 'pending')
                    ->update(['status' => 'cancelled']);

                BranchChangeRequest::create([
                    'user_id' => $user->id,
                    'from_branch_id' => $currentBranchId,
                    'to_branch_id' => $submittedBranchId,
                    'status' => 'pending',
                ]);

                unset($validated['branch_id']);
            }
        }

        if (! $isSuperadmin && $user->hasRole('Member')) {
            $ic = $validated['ic_number'] ?? $user->ic_number;

            if ($ic) {
                if (empty($validated['dob']) && ($user->isDirty('ic_number') || ! $user->dob)) {
                    $validated['dob'] = User::parseDobFromIc($ic);
                }
                if (empty($validated['gender']) && ($user->isDirty('ic_number') || ! $user->gender)) {
                    $validated['gender'] = User::guessGenderFromIc($ic);
                }
            }
        }

        if ($request->hasFile('profile_photo')) {
            $oldPath = ltrim(str_replace('/storage/', '', parse_url((string) $user->profile_photo_path, PHP_URL_PATH) ?? ''), '/');
            if ($oldPath !== '' && Storage::disk('public')->exists($oldPath)) {
                Storage::disk('public')->delete($oldPath);
            }

            $newPath = $request->file('profile_photo')->store('profiles', 'public');
            $validated['profile_photo_path'] = '/storage/'.ltrim($newPath, '/');
        }

        if (
            ! $isSuperadmin
            && $user->hasRole('Member')
            && ! $user->profile_completed_at
            && ! empty($validated['phone'])
            && ! empty($validated['education_level'])
            && ! empty($validated['current_profession'])
        ) {
            $validated['profile_completed_at'] = now();
        }

        $user->fill($validated);

        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }

        $user->save();

        return BranchChangeRequest::where('user_id', $user->id)
            ->where('status', 'pending')
            ->exists();
    }
}
