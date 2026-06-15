<?php

namespace Database\Seeders;

use App\Enums\FeeStatus;
use App\Models\ActivityLog;
use App\Models\MembershipFee;
use App\Models\Organization;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class FeeDemoSeeder extends Seeder
{
    public function run(): void
    {
        $pkpim = Organization::where('slug', 'pkpim')->first();
        $abim  = Organization::where('slug', 'abim')->first();
        $wadah = Organization::where('slug', 'wadah')->first();

        if (! $pkpim || ! $abim || ! $wadah) {
            $this->command->error('Jalankan OrganizationsTableSeeder dulu.');
            return;
        }

        $now = now();
        $thisYear  = (int) $now->year;
        $lastYear  = $thisYear - 1;
        $prevYear  = $thisYear - 2;

        // ─── Helper ──────────────────────────────────────────────────────────────

        $createUser = function (array $data) {
            return User::withoutGlobalScopes()->updateOrCreate(
                ['ic_number' => $data['ic_number']],
                array_merge([
                    'password' => Hash::make('password'),
                    'email_verified_at' => now(),
                    'profile_completed_at' => now(),
                    'gender' => 'lelaki',
                ], $data)
            );
        };

        $createFee = function (User $user, Organization $org, int $year, FeeStatus $status, string $paidAt = null, string $notes = null) {
            return MembershipFee::updateOrCreate(
                ['user_id' => $user->id, 'year' => $year],
                [
                    'organization_id' => $org->id,
                    'amount' => $org->fee_amount ?? 0,
                    'status' => $status,
                    'paid_at' => $paidAt ? now()->subDays($paidAt) : null,
                    'notes' => $notes,
                ]
            );
        };

        $createPayment = function (User $user, MembershipFee $fee, string $prefix, float $amount, string $proofPath = null) {
            $payment = Payment::create([
                'user_id' => $user->id,
                'payable_type' => MembershipFee::class,
                'payable_id' => $fee->id,
                'amount' => $amount,
                'status' => 'successful',
                'reference' => $prefix . '-' . strtoupper(Str::random(8)),
                'description' => "Yuran {$user->organization?->name} {$fee->year}",
                'proof_path' => $proofPath,
                'uploaded_by' => $proofPath ? 1 : null,
            ]);
            $fee->update(['payment_id' => $payment->id]);
        };

        // ══════════════════════════════════════════════════════════════════════
        // 1. PKPIM MEMBERS  (fee: RM30)
        // ══════════════════════════════════════════════════════════════════════

        $amir = $createUser([
            'name' => 'Amir Hakim',
            'email' => 'amir@mywap.my',
            'ic_number' => '050101101234',
            'phone' => '01122334455',
            'member_no' => 'P050101',
            'original_member_no' => 'P050101',
            'current_organization_id' => $pkpim->id,
        ]);
        $amir->assignRole('Member');
        $createFee($amir, $pkpim, $prevYear, FeeStatus::Paid, '370');
        $createFee($amir, $pkpim, $lastYear, FeeStatus::Paid, '5');
        $createFee($amir, $pkpim, $thisYear, FeeStatus::Unpaid);

        $siti = $createUser([
            'name' => 'Siti Fatimah',
            'email' => 'siti@mywap.my',
            'ic_number' => '060201101234',
            'phone' => '01233445566',
            'member_no' => 'P060201',
            'original_member_no' => 'P060201',
            'current_organization_id' => $pkpim->id,
            'gender' => 'perempuan',
        ]);
        $siti->assignRole('Member');
        $createFee($siti, $pkpim, $prevYear, FeeStatus::Paid, '730');
        $createFee($siti, $pkpim, $lastYear, FeeStatus::Paid, '368');
        $feeSiti = $createFee($siti, $pkpim, $thisYear, FeeStatus::Paid, '2');
        $createPayment($siti, $feeSiti, 'CSV', 30.00, 'fee-imports/proofs/demo-bank-statement.pdf');

        $faiz = $createUser([
            'name' => 'Mohd Faiz',
            'email' => 'faiz@mywap.my',
            'ic_number' => '070301101234',
            'phone' => '01344556677',
            'member_no' => 'P070301',
            'original_member_no' => 'P070301',
            'current_organization_id' => $pkpim->id,
        ]);
        $faiz->assignRole('Member');
        // Exempted (perpetual)
        MembershipFee::updateOrCreate(
            ['user_id' => $faiz->id, 'year' => $thisYear],
            ['organization_id' => $pkpim->id, 'amount' => 0, 'status' => FeeStatus::Exempted, 'paid_at' => now(), 'notes' => 'OKU sepenuhnya']
        );

        $aisyah = $createUser([
            'name' => 'Aisyah Harun',
            'email' => 'aisyah@mywap.my',
            'ic_number' => '080401101234',
            'phone' => '01455667788',
            'member_no' => 'P080401',
            'original_member_no' => 'P080401',
            'current_organization_id' => $pkpim->id,
            'gender' => 'perempuan',
        ]);
        $aisyah->assignRole('Member');
        $createFee($aisyah, $pkpim, $lastYear, FeeStatus::Paid, '180');
        $createFee($aisyah, $pkpim, $thisYear, FeeStatus::Unpaid);

        // ══════════════════════════════════════════════════════════════════════
        // 2. ABIM MEMBERS  (fee: RM50)
        // ══════════════════════════════════════════════════════════════════════

        $khairul = $createUser([
            'name' => 'Khairul Azman',
            'email' => 'khairul@mywap.my',
            'ic_number' => '090501101234',
            'phone' => '01566778899',
            'member_no' => 'A090501',
            'original_member_no' => 'A090501',
            'current_organization_id' => $abim->id,
        ]);
        $khairul->assignRole('Member');
        // Life member
        MembershipFee::updateOrCreate(
            ['user_id' => $khairul->id, 'year' => $thisYear],
            ['organization_id' => $abim->id, 'amount' => 0, 'status' => FeeStatus::LifeMember, 'paid_at' => now(), 'notes' => 'Pengasas ABIM']
        );

        $farah = $createUser([
            'name' => 'Farah Nadia',
            'email' => 'farah@mywap.my',
            'ic_number' => '100601101234',
            'phone' => '01677889900',
            'member_no' => 'A100601',
            'original_member_no' => 'A100601',
            'current_organization_id' => $abim->id,
            'gender' => 'perempuan',
        ]);
        $farah->assignRole('Member');
        $createFee($farah, $abim, $prevYear, FeeStatus::Paid, '400');
        $createFee($farah, $abim, $lastYear, FeeStatus::Paid, '35');
        $feeFarah = $createFee($farah, $abim, $thisYear, FeeStatus::Paid, '3');
        $createPayment($farah, $feeFarah, 'MANUAL', 50.00, 'manual-payments/demo-receipt.pdf');

        $iqbal = $createUser([
            'name' => 'Muhammad Iqbal',
            'email' => 'iqbal@mywap.my',
            'ic_number' => '110701101234',
            'phone' => '01788990011',
            'member_no' => 'A110701',
            'original_member_no' => 'A110701',
            'current_organization_id' => $abim->id,
        ]);
        $iqbal->assignRole('Member');
        $createFee($iqbal, $abim, $lastYear, FeeStatus::Paid, '300');
        $createFee($iqbal, $abim, $thisYear, FeeStatus::Unpaid);

        // ══════════════════════════════════════════════════════════════════════
        // 3. WADAH MEMBERS  (fee: RM60)
        // ══════════════════════════════════════════════════════════════════════

        $raja = $createUser([
            'name' => 'Dr Raja Ahmad',
            'email' => 'raja@mywap.my',
            'ic_number' => '120801101234',
            'phone' => '01890011223',
            'member_no' => 'W120801',
            'original_member_no' => 'W120801',
            'current_organization_id' => $wadah->id,
        ]);
        $raja->assignRole('Member');
        MembershipFee::updateOrCreate(
            ['user_id' => $raja->id, 'year' => $thisYear],
            ['organization_id' => $wadah->id, 'amount' => 0, 'status' => FeeStatus::LifeMember, 'paid_at' => now(), 'notes' => 'Tokoh Maal Hijrah']
        );

        $sarimah = $createUser([
            'name' => 'Sarimah Yusof',
            'email' => 'sarimah@mywap.my',
            'ic_number' => '130901101234',
            'phone' => '01901122334',
            'member_no' => 'W130901',
            'original_member_no' => 'W130901',
            'current_organization_id' => $wadah->id,
            'gender' => 'perempuan',
        ]);
        $sarimah->assignRole('Member');
        $feeSarimah = $createFee($sarimah, $wadah, $thisYear, FeeStatus::Paid, '1');
        $createPayment($sarimah, $feeSarimah, 'CSV', 60.00, 'fee-imports/proofs/demo-bank-statement.pdf');

        $kamal = $createUser([
            'name' => 'Kamal Ariffin',
            'email' => 'kamal@mywap.my',
            'ic_number' => '141001101234',
            'phone' => '01912233445',
            'member_no' => 'W141001',
            'original_member_no' => 'W141001',
            'current_organization_id' => $wadah->id,
        ]);
        $kamal->assignRole('Member');
        $createFee($kamal, $wadah, $lastYear, FeeStatus::Unpaid);
        $createFee($kamal, $wadah, $thisYear, FeeStatus::Unpaid);

        $zainab = $createUser([
            'name' => 'Zainab Mat Zin',
            'email' => 'zainab@mywap.my',
            'ic_number' => '151101101234',
            'phone' => '01923344556',
            'member_no' => 'W151101',
            'original_member_no' => 'W151101',
            'current_organization_id' => $wadah->id,
            'gender' => 'perempuan',
        ]);
        $zainab->assignRole('Member');
        MembershipFee::updateOrCreate(
            ['user_id' => $zainab->id, 'year' => $thisYear],
            ['organization_id' => $wadah->id, 'amount' => 0, 'status' => FeeStatus::Exempted, 'paid_at' => now(), 'notes' => 'Warga emas, tidak bekerja']
        );

        // ══════════════════════════════════════════════════════════════════════
        // 4. BEBERAPA ACTIVITY LOGS
        // ══════════════════════════════════════════════════════════════════════

        ActivityLog::create(['user_id' => 1, 'target_type' => User::class, 'target_id' => $khairul->id, 'action' => 'mark_life_member', 'description' => "{$khairul->name} dilantik sebagai ahli seumur hidup."]);
        ActivityLog::create(['user_id' => 1, 'target_type' => User::class, 'target_id' => $faiz->id, 'action' => 'exempted', 'description' => "{$faiz->name} dikecualikan yuran. Sebab: OKU sepenuhnya"]);
        ActivityLog::create(['user_id' => 1, 'target_type' => MembershipFee::class, 'target_id' => $feeSiti->id, 'action' => 'csv_import', 'description' => "Import CSV yuran {$thisYear}: 3 berjaya, 0 skip."]);
        ActivityLog::create(['user_id' => 1, 'target_type' => User::class, 'target_id' => $farah->id, 'action' => 'manual_payment', 'description' => "Bayaran manual RM 50.00 untuk {$farah->name} tahun {$thisYear}."]);

        $this->command->info('✅  FeeDemoSeeder siap! 12 ahli demo dengan pelbagai status yuran.');
    }
}
