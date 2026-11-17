<?php

namespace App\Console\Commands;

use App\Models\BranchAdmin;
use App\Models\Scopes\OrganizationScope;
use App\Models\User;
use App\Support\MemberNumber;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SeparateAdminsFromMembers extends Command
{
    protected $signature = 'admins:separate-from-members
                            {--apply : Apply the changes. Without this flag, only list the candidates.}
                            {--keep-ic : Keep IC numbers on admin-only accounts.}';

    protected $description = 'Turunkan akaun ahli yang dilantik sebagai admin kembali menjadi ahli, dan bersihkan akaun admin-only.';

    public function handle(): int
    {
        $apply = (bool) $this->option('apply');
        $keepIc = (bool) $this->option('keep-ic');

        $adminRoles = ['Admin', 'org-admin', 'Admin Cawangan'];

        $demote = User::withoutGlobalScope(OrganizationScope::class)
            ->whereHas('roles', fn ($query) => $query->whereIn('name', $adminRoles))
            ->where(function ($query) {
                $query->whereNotNull('member_no')
                    ->orWhereHas('roles', fn ($roleQuery) => $roleQuery->where('name', 'Member'))
                    ->orWhereHas('membershipFees');
            })
            ->whereRaw("LOWER(COALESCE(email, '')) NOT LIKE 'admin%@mywap.my'")
            ->whereRaw("LOWER(COALESCE(email, '')) NOT LIKE 'demo%@mywap.my'")
            ->whereRaw("LOWER(COALESCE(name, '')) NOT LIKE 'admin %'")
            ->whereRaw("LOWER(COALESCE(name, '')) NOT LIKE 'demo admin%'")
            ->whereRaw("COALESCE(member_no, '') NOT LIKE '%-%'")
            ->with(['organization', 'roles'])
            ->orderBy('id')
            ->get();

        $this->info(($apply ? 'Memproses' : 'Calon untuk diturunkan').': '.$demote->count().' akaun');

        foreach ($demote as $user) {
            $this->line("  #{$user->id} {$user->name} ({$user->email}) · member_no: ".($user->member_no ?? '-'));

            if (! $apply) {
                continue;
            }

            DB::transaction(function () use ($user) {
                $user->syncRoles(['Member']);

                if (! $user->member_no && $user->organization) {
                    $next = MemberNumber::allocate($user->organization);

                    if ($next) {
                        $user->member_no = $next['member_no'];
                        $user->member_no_sequence = $next['sequence'];
                        $user->original_member_no = $user->original_member_no ?: $next['member_no'];
                    }
                }

                $user->is_public_in_directory = false;
                $user->save();

                BranchAdmin::query()
                    ->where('user_id', $user->id)
                    ->where('role', BranchAdmin::ROLE_MANAGER)
                    ->delete();
            });
        }

        $adminOnly = User::withoutGlobalScope(OrganizationScope::class)
            ->whereHas('roles', fn ($query) => $query->whereIn('name', ['Superadmin', 'Admin', 'org-admin', 'Admin Cawangan']))
            ->where(function ($query) {
                $query->whereNull('member_no')
                    ->orWhere('member_no', 'like', '%-%')
                    ->orWhereRaw("LOWER(COALESCE(email, '')) LIKE 'admin%@mywap.my'")
                    ->orWhereRaw("LOWER(COALESCE(email, '')) LIKE 'demo%@mywap.my'")
                    ->orWhereRaw("LOWER(COALESCE(name, '')) LIKE 'admin %'")
                    ->orWhereRaw("LOWER(COALESCE(name, '')) LIKE 'demo admin%'");
            })
            ->whereDoesntHave('membershipFees')
            ->with('roles')
            ->orderBy('id')
            ->get();

        $this->newLine();
        $this->info(($apply ? 'Membersihkan' : 'Akaun admin-only').': '.$adminOnly->count().' akaun');

        foreach ($adminOnly as $user) {
            $this->line("  #{$user->id} {$user->name} ({$user->email})");

            if (! $apply) {
                continue;
            }

            $isSuperadmin = $user->hasRole('Superadmin');

            if ($user->hasRole('Member')) {
                $user->removeRole('Member');
            }

            $email = strtolower((string) $user->email);
            $fakeMemberNo = $user->member_no !== null && (
                str_contains($user->member_no, '-')
                || ((str_starts_with($email, 'admin') || str_starts_with($email, 'demo'))
                    && str_ends_with($email, '@mywap.my'))
            );

            if ($fakeMemberNo) {
                $user->member_no = null;
                $user->member_no_sequence = null;
                $user->original_member_no = null;
            }

            $user->is_public_in_directory = false;
            $user->position = null;
            $user->position_id = null;

            if (! $user->profile_completed_at) {
                $user->profile_completed_at = now();
            }

            if (! $keepIc && ! $isSuperadmin) {
                $user->ic_number = null;
            }

            $user->save();
        }

        if (! $apply) {
            $this->newLine();
            $this->warn('Tiada perubahan dibuat. Jalankan semula dengan --apply untuk menyimpan.');
        }

        return self::SUCCESS;
    }
}
