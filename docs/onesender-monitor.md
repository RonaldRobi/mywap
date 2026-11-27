# Amaran sambungan WhatsApp

Server menjalankan `onesender:monitor` setiap 5 minit melalui scheduler Laravel.
Ia membaca `GET /api/v1/device` menggunakan kunci penghantaran yang disimpan
dalam tetapan myWAP. Semakan tidak menghantar mesej WhatsApp.

Jika `data.is_online` bernilai `false`, sistem menghantar emel kepada
`cyberokettechnology@gmail.com`. Kegagalan API, timeout dan respons status yang
tidak sah juga mencetuskan amaran, dengan status `unknown`.

Satu emel dihantar bagi setiap kejadian. Semakan online seterusnya mengaktifkan
semula amaran untuk kejadian baharu. Jika penghantaran emel gagal, sistem
mencuba lagi pada semakan seterusnya. Keadaan ini disimpan dalam cache aplikasi;
mengosongkan cache turut menetapkan semula penanda amaran.

Emel menggunakan mailer lalai aplikasi (SMTP dalam produksi). Ia tidak
menggunakan queue WhatsApp. Ubah penerima melalui `ONESENDER_ALERT_EMAIL` dan
jalankan `php artisan config:cache` jika perlu.

Arahan untuk operasi:

```sh
php artisan onesender:monitor
php artisan onesender:monitor --test-email
php artisan schedule:list
php artisan app:ensure-scheduler
```

`--test-email` menghantar emel ujian tanpa menetapkan semula keadaan amaran.
`app:ensure-scheduler` mengekalkan crontab sedia ada dan menambah cron Laravel
setiap minit jika belum tersedia untuk projek ini. Deploy turut menjalankan
semakan pemasangan cron ini.

Log scheduler: `storage/logs/onesender-monitor.log`.
Log kegagalan emel: `storage/logs/laravel.log`.

Pemantauan ini bergantung pada server myWAP dan penghantaran emelnya. Ia tidak
dapat menghantar amaran jika keseluruhan server myWAP terhenti.
