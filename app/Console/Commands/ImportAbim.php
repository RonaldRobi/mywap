<?php

namespace App\Console\Commands;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class ImportAbim extends Command
{
    protected $signature = 'import:abim {file? : Path ke CSV ABIM}';

    protected $description = 'Import ahli ABIM dari CSV (format laporan ABIM)';

    public function handle()
    {
        $file = $this->argument('file')
            ?? database_path('seeders/data/SENARAI NAMA MYWAP(Laporan Ahli).csv');

        if (! file_exists($file)) {
            $this->error("File tak jumpa: {$file}");
            return 1;
        }

        $abim = Organization::where('slug', 'abim')->first();
        if (! $abim) {
            $this->error('Organisasi ABIM tak jumpa');
            return 1;
        }

        $memberRole = \Spatie\Permission\Models\Role::where('name', 'Member')->first();
        if (! $memberRole) {
            $this->error('Role Member tak jumpa, jalankan db:seed dulu');
            return 1;
        }

        // ── Parse CSV ──────────────────────────────────────────────────
        $this->info('📄 Membaca CSV...');
        $rows = $this->parseCsv($file);
        if (empty($rows)) {
            $this->error('CSV kosong atau gagal parse');
            return 1;
        }

        $this->info('📊 ' . count($rows) . ' rekod jumpa');

        // ── Separate new / existing ────────────────────────────────────
        $this->info('🔍 Memeriksa duplikasi IC...');
        $existingIcs = User::withoutGlobalScopes()
            ->where('ic_number', '!=', '')
            ->pluck('id', 'ic_number')
            ->toArray();

        $now = now();
        $toInsert = [];
        $toUpdate = [];
        $errors = [];

        $bar = $this->output->createProgressBar(count($rows));
        $bar->setFormat(" %current%/%max% [%bar%] %percent:3s%%");

        foreach ($rows as $r) {
            $exists = isset($existingIcs[$r['ic']]);
            $record = [
                'name'                    => $r['nama'],
                'member_no'               => $r['member_no'],
                'member_no_sequence'      => $r['member_no_seq'],
                'password'                => Hash::make($r['member_no'] ?: 'password123'),
                'email'                   => $r['email'],
                'phone'                   => $r['phone'],
                'dob'                     => $r['dob'],
                'gender'                  => $r['gender'],
                'address_1'               => $r['address_1'],
                'state'                   => $r['state'],
                'registration_year'       => $r['reg_year'],
                'notes'                   => $r['notes'],
                'current_organization_id' => $abim->id,
                'ic_number'               => $r['ic'],
            ];

            if ($exists) {
                $record['id'] = $existingIcs[$r['ic']];
                $toUpdate[] = $record;
            } else {
                $record['created_at'] = $now;
                $record['updated_at'] = $now;
                $toInsert[] = $record;
            }
            $bar->advance();
        }
        $bar->finish();
        $this->newLine();

        // ── Bulk insert new users ──────────────────────────────────────
        $insertedCount = 0;
        $newIds = [];
        if (! empty($toInsert)) {
            $this->info("⚡ Insert " . count($toInsert) . " ahli baru...");
            foreach (array_chunk($toInsert, 500) as $chunk) {
                foreach ($chunk as &$rec) {
                    unset($rec['id']);
                }
                DB::table('users')->insert($chunk);
                $insertedCount += count($chunk);
            }

            // Ambil ID user baru yang baru diinsert
            $ics = array_column($toInsert, 'ic_number');
            $newUsers = User::withoutGlobalScopes()
                ->whereIn('ic_number', $ics)
                ->get(['id', 'ic_number']);
            $newIds = $newUsers->pluck('id')->toArray();

            // Set original_member_no
            foreach (array_chunk($newUsers->toArray(), 500) as $chunk) {
                $ids = array_column($chunk, 'id');
                DB::table('users')
                    ->whereIn('id', $ids)
                    ->whereNull('original_member_no')
                    ->update(['original_member_no' => DB::raw('member_no')]);
            }

            $this->info("✅ {$insertedCount} rekod baru ditambah");
        }

        // ── Bulk update existing users ─────────────────────────────────
        if (! empty($toUpdate)) {
            $this->info("⚡ Update " . count($toUpdate) . " ahli sedia ada...");
            $updatedCount = 0;
            foreach (array_chunk($toUpdate, 500) as $chunk) {
                $now = now();
                foreach ($chunk as $rec) {
                    $id = $rec['id'];
                    unset($rec['id'], $rec['created_at']);
                    $rec['updated_at'] = $now;
                    DB::table('users')->where('id', $id)->update($rec);
                    $updatedCount++;
                }
            }
            $this->info("✅ {$updatedCount} rekod dikemaskini");
        }

        // ── Batch assign role Member ───────────────────────────────────
        $allIds = array_merge(
            $newIds,
            array_column($toUpdate, 'id')
        );

        if (! empty($allIds)) {
            $this->info("⚡ Assign role Member...");
            $existingRole = DB::table('model_has_roles')
                ->where('role_id', $memberRole->id)
                ->where('model_type', User::class)
                ->whereIn('model_id', $allIds)
                ->pluck('model_id')
                ->toArray();

            $needRole = array_diff($allIds, $existingRole);
            $roleInserts = [];
            foreach ($needRole as $uid) {
                $roleInserts[] = [
                    'role_id'    => $memberRole->id,
                    'model_type' => User::class,
                    'model_id'   => $uid,
                ];
            }

            if (! empty($roleInserts)) {
                foreach (array_chunk($roleInserts, 500) as $chunk) {
                    DB::table('model_has_roles')->insertOrIgnore($chunk);
                }
                $this->info("✅ Role Member diberikan kepada " . count($needRole) . " ahli");
            }
        }

        $this->newLine();
        $this->info("🎉 Selesai! Jumlah diproses: " . ($insertedCount + count($toUpdate)));
        if (! empty($errors)) {
            $this->warn('⚠️  Error / dilewati:');
            foreach ($errors as $e) {
                $this->warn("  • {$e}");
            }
        }

        return 0;
    }

    protected function parseCsv(string $file): array
    {
        $handle = fopen($file, 'r');
        if (! $handle) {
            return [];
        }

        $headers = fgetcsv($handle);
        if (! $headers) {
            fclose($handle);
            return [];
        }

        $col = array_flip($headers);
        $required = ['NAMA', 'MYKAD', 'NO.RUJ'];
        foreach ($required as $key) {
            if (! isset($col[$key])) {
                fclose($handle);
                return [];
            }
        }

        $rows = [];
        while (($row = fgetcsv($handle)) !== false) {
            if (count($row) < count($headers)) {
                continue;
            }

            $nama = trim($row[$col['NAMA']] ?? '');
            $mykadRaw = trim($row[$col['MYKAD']] ?? '');
            $noRuj = trim($row[$col['NO.RUJ']] ?? '');

            $ic = ltrim($mykadRaw, "'");
            $ic = preg_replace('/[^0-9]/', '', $ic);

            if (empty($nama) && empty($ic)) {
                continue;
            }

            if (empty($ic) || strlen($ic) < 6) {
                continue;
            }

            $icPadded = str_pad($ic, 12, '0', STR_PAD_RIGHT);

            $dob = User::parseDobFromIc($ic);
            $gender = User::guessGenderFromIc($ic);

            $email = trim($row[$col['EMEL'] ?? ''] ?? '');
            if (empty($email)) {
                $email = strtolower($ic) . '@mywap.my';
            }

            $phone = trim($row[$col['MOBILE NO'] ?? ''] ?? '');
            $phone = ltrim($phone, "'");

            $memberNo = 'A' . $noRuj;

            $lokality = trim($row[$col['LOKALITI'] ?? ''] ?? '');
            $state = null;
            if (preg_match('/NEGERI\s+(.+?)(?:,|$)/i', $lokality, $m)) {
                $state = $this->normalizeState(trim($m[1]));
            }

            $alamat = trim($row[$col['ALAMAT'] ?? ''] ?? '');
            $address1 = (! empty($alamat) && ! in_array($alamat, ['-', '#N/A', ' - ']))
                ? $alamat
                : null;

            $tarikh = trim($row[$col['TARIKH DAFTAR'] ?? ''] ?? '');
            $regYear = null;
            if (! empty($tarikh)) {
                $parts = explode('/', $tarikh);
                if (count($parts) === 3) {
                    $regYear = $parts[2];
                }
            }

            $peranan = trim($row[$col['PERANAN'] ?? ''] ?? '');
            $notes = null;
            if (! empty($peranan)) {
                $notes = "PERANAN: {$peranan}";
            }

            $rows[] = [
                'nama'       => $nama,
                'ic'         => $icPadded,
                'email'      => $email,
                'phone'      => $phone ?: null,
                'member_no'  => $memberNo,
                'member_no_seq' => (int) $noRuj,
                'dob'        => $dob,
                'gender'     => $gender,
                'address_1'  => $address1,
                'state'      => $state,
                'reg_year'   => $regYear,
                'notes'      => $notes,
            ];
        }

        fclose($handle);
        return $rows;
    }

    protected function normalizeState(string $raw): string
    {
        $map = [
            'K.LUMPUR'    => 'Kuala Lumpur',
            'K.Lumpur'    => 'Kuala Lumpur',
            'N.SEMBILAN'  => 'Negeri Sembilan',
            'N.Sembilan'  => 'Negeri Sembilan',
            'P.PINANG'    => 'Pulau Pinang',
            'P.Pinang'    => 'Pulau Pinang',
            'W.P.KUALA LUMPUR' => 'Kuala Lumpur',
        ];

        $upper = strtoupper($raw);
        if (isset($map[$upper])) {
            return $map[$upper];
        }

        return ucwords(strtolower($raw));
    }
}
