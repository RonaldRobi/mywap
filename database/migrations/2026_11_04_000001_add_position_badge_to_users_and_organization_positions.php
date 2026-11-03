<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organization_positions', function (Blueprint $table) {
            $table->boolean('show_badge')->default(true)->after('color');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('position_id')->nullable()->after('position')
                ->constrained('organization_positions')->nullOnDelete();
        });

        DB::table('organization_positions')
            ->where('name', 'Ahli Biasa')
            ->update(['show_badge' => false]);

        $byOrgAndName = [];
        foreach (DB::table('organization_positions')->get(['id', 'organization_id', 'name']) as $position) {
            $byOrgAndName[$position->organization_id][$position->name] = $position->id;
        }

        DB::table('users')
            ->whereNotNull('position')
            ->where('position', '<>', '')
            ->orderBy('id')
            ->chunkById(500, function ($users) use ($byOrgAndName) {
                foreach ($users as $user) {
                    $name = trim((string) $user->position);
                    $orgId = $user->current_organization_id;
                    $positionId = $orgId ? ($byOrgAndName[$orgId][$name] ?? null) : null;

                    if ($positionId) {
                        DB::table('users')->where('id', $user->id)->update([
                            'position_id' => $positionId,
                        ]);
                    }
                }
            });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('position_id');
        });

        Schema::table('organization_positions', function (Blueprint $table) {
            $table->dropColumn('show_badge');
        });
    }
};
