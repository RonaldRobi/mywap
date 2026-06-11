<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $targets = [
            ['table' => 'app_settings', 'column' => 'system_logo_path'],
            ['table' => 'organizations', 'column' => 'logo_path'],
            ['table' => 'users', 'column' => 'profile_photo_path'],
            ['table' => 'dashboard_banners', 'column' => 'image_path'],
            ['table' => 'infaq', 'column' => 'image_path'],
            ['table' => 'library_items', 'column' => 'file_path'],
            ['table' => 'library_items', 'column' => 'cover_image_path'],
        ];

        foreach ($targets as $target) {
            $table = $target['table'];
            $column = $target['column'];

            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $column)) {
                continue;
            }

            DB::table($table)
                ->select('id', $column)
                ->orderBy('id')
                ->chunkById(200, function ($rows) use ($table, $column) {
                    foreach ($rows as $row) {
                        $normalized = $this->normalizeStoragePath($row->{$column});

                        if ($normalized !== $row->{$column}) {
                            DB::table($table)
                                ->where('id', $row->id)
                                ->update([$column => $normalized]);
                        }
                    }
                });
        }
    }

    public function down(): void
    {
    }

    private function normalizeStoragePath($value): ?string
    {
        if (! is_string($value) || $value === '') {
            return $value;
        }

        if (str_starts_with($value, '/storage/')) {
            return $value;
        }

        $parsedPath = parse_url($value, PHP_URL_PATH);

        if (is_string($parsedPath) && str_starts_with($parsedPath, '/storage/')) {
            return $parsedPath;
        }

        return $value;
    }
};
