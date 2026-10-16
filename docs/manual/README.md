# Manual myWAP — Struktur & Panduan Sedia PDF

Folder ini mengandungi kandungan manual untuk **dua peranan** sahaja:

| Fail | Untuk siapa | Role sistem |
|---|---|---|
| `Manual-Pengguna.md` | Ahli / pengguna biasa | `Member` |
| `Manual-Admin-Organisasi.md` | Admin organisasi | `Admin` / `org-admin` |

> **Superadmin TIDAK mempunyai manual di sini** (mengikut keperluan).

## Peraturan akses (WAJIB)

- **Admin** hanya boleh melihat **Manual Admin Organisasi**.
- **Ahli/User** hanya boleh melihat **Manual Pengguna**.
- Manual diakses melalui menu **Manual Pengguna** di bahagian bawah menu.

## Cara siapkan PDF + screenshot

1. Buka fail `.md` menggunakan editor Markdown (Typora, VS Code + extension, Obsidian, dsb.).
2. Cari setiap blok:

   ```
   > 📸 SCREENSHOT ...
   ```

3. Gantikan blok tersebut dengan imej tangkapan skrin sebenar.
   Blok juga menyatakan **fail cadangan** dan **apa yang perlu ditangkap**.
4. Eksport kepada PDF (contoh: VS Code "Markdown PDF", Typora "Export → PDF", atau `pandoc`).

Contoh arahan `pandoc`:

```bash
pandoc Manual-Pengguna.md -o Manual-Pengguna.pdf
pandoc Manual-Admin-Organisasi.md -o Manual-Admin-Organisasi.pdf
```

## Folder screenshot

```
screenshots/
├── user/     # tangkapan skrin untuk Manual-Pengguna.md (U-01 … U-48)
└── admin/    # tangkapan skrin untuk Manual-Admin-Organisasi.md (A-01 … A-44)
```

## Senarai ringkas screenshot

### Manual Pengguna (User)
U-01 Splash · U-02 Onboarding · U-03 Halaman tetamu · U-04 Daftar · U-05 Daftar berjaya ·
U-06 Log masuk · U-07 OTP · U-08 Lupa kata laluan · U-09 Lupa No. ahli · U-10 Biometrik ·
U-11 Lengkap profil · U-12 Papan pemuka · U-13 Profil · U-14 Edit profil · U-15 Padam akaun ·
U-16 Kad ahli · U-17 Jemput ahli · U-18 Kewangan · U-19 Status yuran · U-20 Notifikasi ·
U-21 Pengumuman · U-22 Pustaka · U-23 Pembaca · U-24 Info organisasi · U-25 Program ·
U-26 Butiran program · U-27 Daftar program · U-28 Pendaftaran saya · U-29 Imbas QR ·
U-30 Kehadiran disahkan · U-31 Info terkini · U-32 Artikel · U-33 Video · U-34 Infaq ·
U-35 Infaq web · U-36 Pasar · U-37 Produk · U-38 Troli · U-39 Checkout · U-40 Pesanan ·
U-41 Butiran pesanan · U-42 Kemudahan · U-43 Tempahan · U-44 Usrah · U-45 Undian ·
U-46 Borang · U-47 Sidebar · U-48 Log keluar

### Manual Admin Organisasi
A-01 Tab admin · A-02 Login web · A-03 Papan pemuka admin · A-04 Senarai ahli · A-05 Butiran ahli ·
A-06 Ahli web · A-07 Profil ahli web · A-08 Yuran mobile · A-09 Yuran web · A-10 Bayaran manual ·
A-11 Kehadiran acara · A-12 Imbas QR admin · A-13 Kehadiran web · A-14 Siaran · A-15 Push web ·
A-16 Pengumuman web · A-17 Program web · A-18 Cipta program · A-19 Peserta · A-20 Kempen ·
A-21 Info terkini · A-22 Artikel · A-23 Video · A-24 Popup · A-25 Infaq · A-26 Penderma ·
A-27 Borang · A-28 Bina borang · A-29 Undian · A-30 Keputusan undian · A-31 Fasiliti ·
A-32 Tempahan · A-33 Usrah · A-34 Cawangan · A-35 Jawatan · A-36 Tukar cawangan ·
A-37 Bulk cawangan · A-38 Transaksi · A-39 Kewangan · A-40 Produk · A-41 Pesanan ·
A-42 Direktori · A-43 Info org · A-44 Export

## Muat naik ke panel Superadmin

Feature "Manual Pengguna" kini tersedia dalam sistem:

1. Log masuk panel web sebagai **Superadmin**.
2. Buka **Sistem → Manual Pengguna** (`/superadmin/manuals`).
3. Klik **+ Muat Naik Manual**, isi:
   - **Tajuk** (cth. `Manual Pengguna (Ahli)`)
   - **Penerangan** (pilihan)
   - **Sasaran** — pilih `Ahli / Pengguna`, `Admin Organisasi`, atau `Semua`
   - **Susunan** (nombor kecil dipapar dahulu)
   - **Fail PDF** (maks 30 MB)
   - Tanda **Aktif** untuk memaparkan dalam app.
4. Untuk `Manual-Pengguna.pdf` → sasaran **Ahli / Pengguna**.
   Untuk `Manual-Admin-Organisasi.pdf` → sasaran **Admin Organisasi**.

Kawalan akses dilakukan automatik oleh backend:
- Ahli (role `Member`) hanya menerima manual sasaran `Ahli` dan `Semua`.
- Admin organisasi (role `Admin`/`org-admin`) hanya menerima manual sasaran `Admin` dan `Semua`.

Dalam app, ahli/admin membuka **Menu → Manual Pengguna** (di bahagian paling bawah)
atau **sidebar → Akaun → Manual Pengguna**. Senarai manual dipaparkan mengikut
peranan dan dibuka sebagai PDF dalam app.

