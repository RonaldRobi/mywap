<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Manual — dokumen panduan penggunaan (PDF) yang dimuat naik oleh Superadmin.
 *
 * Setiap manual disasarkan kepada satu kumpulan pengguna melalui `audience`:
 *   - `user`  → hanya ahli biasa nampak
 *   - `admin` → hanya admin organisasi nampak
 *   - `all`   → kedua-dua kumpulan nampak
 *
 * Aplikasi mobile menapis senarai mengikut peranan pengguna yang log masuk.
 */
class Manual extends Model
{
    public const AUDIENCES = ['user', 'admin', 'all'];

    protected $fillable = [
        'title',
        'description',
        'audience',
        'file_path',
        'sort_order',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }
}
