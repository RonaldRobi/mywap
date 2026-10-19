<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Backup Pangkalan Data
    |--------------------------------------------------------------------------
    |
    | Bilangan backup mingguan yang disimpan sebelum yang paling lama
    | dipadam. Lapan minggu = kira-kira dua bulan sejarah.
    |
    | BACKUP_PATH boleh menukar lokasi simpanan (mesti di luar public/).
    | Lalai: storage/app/backups.
    |
    */

    'keep' => (int) env('BACKUP_KEEP', 8),

    'path' => env('BACKUP_PATH'),

];
