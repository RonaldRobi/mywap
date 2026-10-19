# Backup Pangkalan Data

Backup automatik mingguan untuk pangkalan data myWAP. Fail disimpan **di VPS**
dalam `storage/app/backups` (di luar `public/`, jadi tidak boleh diakses melalui web).

## Jadual & retensi

- **Setiap Ahad, 2:00 pagi** (waktu Malaysia, `Asia/Kuala_Lumpur`).
- **8 backup terakhir** dikekalkan secara lalai (kira-kira 2 bulan).
- Fail dinamakan `mywap-db-YYYY-MM-DD_HHMMSS.sql.gz` (MySQL/PostgreSQL)
  atau `.sqlite.gz` (SQLite), bersama checksum `.sha256`.
- Log: `storage/logs/backup.log` dan `storage/logs/laravel.log`.

Ubah retensi melalui `.env` di VPS:

```env
BACKUP_KEEP=8
# BACKUP_PATH=/laluan/lain/di/luar/public
```

Selepas ubah `.env`, jalankan `php artisan config:clear` (deploy webhook sudah
menjalankan `artisan optimize`).

## Keperluan di VPS

Backup mingguan bergantung pada cron Laravel yang sama dengan kerja berjadual
lain:

```cron
* * * * * cd /path-to-project && php artisan schedule:run >> /dev/null 2>&1
```

Jika cron ini tiada, backup **tidak akan berjalan sendiri**. Semak dengan:

```bash
php artisan schedule:list
```

Baris `backup:database` sepatutnya muncul (Ahad 2:00 pagi).

## Pencetus manual

Melalui SSH/terminal di VPS:

```bash
php artisan backup:database
```

Atau melalui webhook (berguna jika tiada SSH), menggunakan `DEPLOY_TOKEN`
yang sama seperti deploy:

```bash
curl -X POST "https://mywap.my/__backup/$DEPLOY_TOKEN"
```

Respons mengandungi output backup dan senarai backup terkini (nama, saiz,
tarikh). Endpoint ini hanya mencipta backup; ia tidak boleh memuat turun atau
memadam data. Ia dihadkan kepada 6 permintaan sejam setiap IP.

Untuk ujian tanpa menyentuh backup produksi:

```bash
php artisan backup:database --path=/tmp/backup-test --no-prune
```

## Pengesahan

```bash
ls -lh storage/app/backups
cd storage/app/backups && sha256sum -c *.sha256
```

Untuk SQLite, sahkan integriti snapshot:

```bash
gunzip -c mywap-db-....sqlite.gz > /tmp/check.sqlite
sqlite3 /tmp/check.sqlite 'PRAGMA integrity_check;'
```

## Pulihkan (restore)

> Hentikan aplikasi/queue dahulu supaya tiada penulisan semasa restore.
> Sentiasa uji restore di persekitaran berasingan sebelum kecemasan.

### SQLite

```bash
# Di VPS
gunzip -c storage/app/backups/mywap-db-....sqlite.gz > database/database.sqlite
php artisan optimize:clear
```

### MySQL / MariaDB

```bash
gunzip -c storage/app/backups/mywap-db-....sql.gz \
  | mysql -u "$DB_USERNAME" -p "$DB_DATABASE"
```

### PostgreSQL

```bash
gunzip -c storage/app/backups/mywap-db-....sql.gz \
  | psql -U "$DB_USERNAME" -d "$DB_DATABASE"
```

## Amaran penting: backup pada server yang sama

Backup ini melindungi daripada:

- tersilap padam data / jadual,
- migration atau bug aplikasi yang merosakkan data,
- fail pangkalan data rosak.

Ia **tidak** melindungi jika keseluruhan VPS hilang (server down, cakera
rosak, akaun hosting ditamatkan). Untuk perlindungan penuh, salin backup ke
lokasi luar VPS sekurang-kurangnya sekali seminggu, contohnya:

- muat turun ke komputer sendiri, atau
- salin ke object storage (S3/Cloudflare R2) menggunakan `rclone`/`aws s3 cp`.

Cadangan cron tambahan di VPS (selepas backup Ahad):

```cron
30 2 * * 0 rclone copy /path-to-project/storage/app/backups remote:mywap-backups --max-age 8d
```

Uji restore sekurang-kurangnya sekali setahun — backup yang tidak pernah diuji
belum boleh dianggap selamat.
