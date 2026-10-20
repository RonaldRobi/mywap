<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('registrations', function (Blueprint $table) {
            $table->string('source', 20)->default('manual')->after('status')->index();
        });

        Schema::table('registration_imports', function (Blueprint $table) {
            $table->unsignedInteger('removed_count')->default(0)->after('skipped_count');
        });

        DB::table('registrations')
            ->select(['id', 'event_id', 'form_id', 'status', 'raw_data'])
            ->orderBy('id')
            ->chunkById(500, function ($rows) {
                $ids = collect($rows)->pluck('id');
                $eventIds = collect($rows)->pluck('event_id')->filter()->unique();

                $attended = DB::table('attendances')
                    ->whereIn('registration_id', $ids)
                    ->pluck('registration_id')
                    ->flip();

                $eventsWithImports = DB::table('registration_imports')
                    ->whereIn('event_id', $eventIds)
                    ->pluck('event_id')
                    ->flip();

                foreach ($rows as $row) {
                    if ($row->form_id !== null) {
                        $source = 'form';
                    } elseif ($row->status === 'walkin' || isset($attended[$row->id])) {
                        $source = 'walkin';
                    } elseif ($row->raw_data !== null || isset($eventsWithImports[$row->event_id])) {
                        $source = 'import';
                    } else {
                        $source = 'manual';
                    }

                    DB::table('registrations')
                        ->where('id', $row->id)
                        ->update(['source' => $source]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('registrations', function (Blueprint $table) {
            $table->dropColumn('source');
        });

        Schema::table('registration_imports', function (Blueprint $table) {
            $table->dropColumn('removed_count');
        });
    }
};
