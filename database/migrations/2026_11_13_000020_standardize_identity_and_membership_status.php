<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('users', 'membership_status')) {
            Schema::table('users', function (Blueprint $table) {
                $table->string('membership_status', 20)->default('active')->after('is_active')->index();
            });
        }

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE users DROP CONSTRAINT IF EXISTS users_ic_number_unique');
        }

        DB::statement('DROP INDEX IF EXISTS users_ic_number_unique');
        DB::statement('CREATE INDEX IF NOT EXISTS users_org_ic_index ON users (current_organization_id, ic_number)');

        $deceasedPositionIds = DB::table('organization_positions')
            ->whereRaw('UPPER(name) = ?', ['MENINGGAL DUNIA'])
            ->pluck('id')
            ->all();

        if ($deceasedPositionIds !== []) {
            DB::table('users')->whereIn('position_id', $deceasedPositionIds)->update([
                'membership_status' => 'deceased',
                'updated_at' => now(),
            ]);
        }

        DB::table('users')->where('position', 'Meninggal Dunia')->update([
            'membership_status' => 'deceased',
            'updated_at' => now(),
        ]);

        DB::table('users')
            ->where('is_active', false)
            ->where('membership_status', 'active')
            ->update(['membership_status' => 'inactive', 'updated_at' => now()]);

        DB::table('users')->select('id', 'name', 'ic_number')->orderBy('id')->chunkById(500, function ($users) {
            foreach ($users as $user) {
                $updates = [];

                $formattedName = $this->formatName($user->name);
                if ($formattedName !== $user->name) {
                    $updates['name'] = $formattedName;
                }

                $normalizedIc = $this->normalizeIc($user->ic_number);
                if ($normalizedIc !== null
                    && preg_match('/^\d{12}$/', $normalizedIc)
                    && $normalizedIc !== $user->ic_number) {
                    $updates['ic_number'] = $normalizedIc;
                }

                if ($updates !== []) {
                    $updates['updated_at'] = now();
                    DB::table('users')->where('id', $user->id)->update($updates);
                }
            }
        });
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS users_org_ic_index');

        if (Schema::hasColumn('users', 'membership_status')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('membership_status');
            });
        }
    }

    private function normalizeIc(?string $value): ?string
    {
        $raw = trim((string) $value);
        if ($raw === '') {
            return null;
        }

        $raw = strtoupper(str_replace(['-', ' '], '', $raw));

        if (preg_match('/^(\d{12})\.0$/', $raw, $matches)) {
            return $matches[1];
        }

        $digits = (string) preg_replace('/\D/', '', $raw);

        return strlen($digits) === 12 ? $digits : $raw;
    }

    private function formatName(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $name = trim((string) preg_replace('/\s+/u', ' ', $value));

        return $name === '' ? $name : mb_convert_case($name, MB_CASE_TITLE, 'UTF-8');
    }
};
