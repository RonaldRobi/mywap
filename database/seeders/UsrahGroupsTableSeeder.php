<?php

namespace Database\Seeders;

use App\Models\Organization;
use App\Models\UsrahAttendance;
use App\Models\UsrahGroup;
use App\Models\User;
use Illuminate\Database\Seeder;

class UsrahGroupsTableSeeder extends Seeder
{
    public function run(): void
    {
        $pkpim = Organization::where('slug', 'pkpim')->first();
        $abim  = Organization::where('slug', 'abim')->first();
        $wadah = Organization::where('slug', 'wadah')->first();

        $superadmin = User::withoutGlobalScopes()->where('email', 'superadmin@mywap.my')->first();
        $adminPkpim = User::withoutGlobalScopes()->where('email', 'admin-pkpim@mywap.my')->first();
        $adminAbim  = User::withoutGlobalScopes()->where('email', 'admin-abim@mywap.my')->first();
        $adminWadah = User::withoutGlobalScopes()->where('email', 'admin-wadah@mywap.my')->first();
        $memberPkpim = User::withoutGlobalScopes()->where('email', 'member-pkpim@mywap.my')->first();
        $memberAbim  = User::withoutGlobalScopes()->where('email', 'member-abim@mywap.my')->first();
        $memberWadah = User::withoutGlobalScopes()->where('email', 'member-wadah@mywap.my')->first();

        $groups = [
            [
                'organization_id' => $pkpim?->id,
                'name'           => 'Usrah Al-Fateh PKPIM',
                'description'    => 'Usrah mingguan untuk pelajar PKPIM di sekitar Kuala Lumpur.',
                'meeting_day'    => 'Sabtu',
                'meeting_time'   => '09:00',
                'is_active'      => true,
                'leader'         => $adminPkpim,
                'members'        => [$memberPkpim, $superadmin],
            ],
            [
                'organization_id' => $abim?->id,
                'name'           => 'Usrah Al-Hidayah ABIM',
                'description'    => 'Halaqah belia ABIM fokus kepada pembangunan sahsiah dan kepimpinan.',
                'meeting_day'    => 'Ahad',
                'meeting_time'   => '10:30',
                'is_active'      => true,
                'leader'         => $adminAbim,
                'members'        => [$memberAbim],
            ],
            [
                'organization_id' => $wadah?->id,
                'name'           => 'Usrah Al-Ikhlas WADAH',
                'description'    => 'Usrah untuk golongan profesional WADAH, diadakan secara dalam talian.',
                'meeting_day'    => 'Rabu',
                'meeting_time'   => '21:00',
                'is_active'      => true,
                'leader'         => $adminWadah,
                'members'        => [$memberWadah],
            ],
        ];

        foreach ($groups as $data) {
            $leader = $data['leader'];
            $members = $data['members'];
            unset($data['leader'], $data['members']);

            $group = UsrahGroup::updateOrCreate(
                ['name' => $data['name']],
                $data
            );

            // Attach leader
            if ($leader) {
                $group->members()->syncWithoutDetaching([
                    $leader->id => ['role' => 'leader', 'is_naqib' => true, 'joined_at' => now()->subMonths(6)],
                ]);
            }

            // Attach members
            foreach ($members as $member) {
                if ($member && $member->id !== $leader?->id) {
                    $group->members()->syncWithoutDetaching([
                        $member->id => ['role' => 'member', 'is_naqib' => false, 'joined_at' => now()->subMonths(3)],
                    ]);
                }
            }

            // Attendance records for last 3 sessions
            $dayMap = [
                'Isnin' => 'Monday', 'Selasa' => 'Tuesday', 'Rabu' => 'Wednesday',
                'Khamis' => 'Thursday', 'Jumaat' => 'Friday', 'Sabtu' => 'Saturday', 'Ahad' => 'Sunday',
            ];
            $dayEn = $dayMap[$data['meeting_day']] ?? 'Saturday';
            $statuses = ['hadir', 'tidak_hadir', 'uzur'];
            for ($i = 1; $i <= 3; $i++) {
                $sessionDate = now()->subWeeks($i)->next($dayEn);

                foreach ([$leader, ...$members] as $user) {
                    if (! $user) {
                        continue;
                    }

                    $status = $statuses[array_rand($statuses)];
                    UsrahAttendance::updateOrCreate(
                        [
                            'usrah_group_id' => $group->id,
                            'user_id'        => $user->id,
                            'session_date'   => $sessionDate,
                        ],
                        [
                            'status'     => $status,
                            'notes'      => $status === 'uzur' ? 'Ada urusan penting' : null,
                            'created_by' => $leader?->id ?? $superadmin?->id,
                        ]
                    );
                }
            }
        }

        $this->command->info('✅  '.UsrahGroup::count().' usrah groups, '.UsrahAttendance::count().' attendance records seeded.');
    }
}
