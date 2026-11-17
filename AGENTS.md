# AGENTS.md — myWAP

Panduan untuk ejen/AI yang bekerja dalam repo ini.

## Peraturan build & versi (WAJIB)

Jangan bump nombor secara manual. Guna `mywap_mobile/tool/version.rb`, yang
membaca App Store Connect dan Google Play dan memilih build seterusnya yang
selamat (`max(iOS builds, Play versionCodes, pubspec) + 1`).

- Semak: `ruby mywap_mobile/tool/version.rb status`
- Bump: `ruby mywap_mobile/tool/version.rb bump-build --write`
- Lebih mudah: `./ship.sh` (lihat bawah) — ia bump, build dua-dua, dan upload.

Peraturan nombor depan (`2.5` → `2.6`):

- Upload ke TestFlight/Transporter: **build number sahaja**, nombor depan kekal.
- Version App Store masih `PREPARE_FOR_SUBMISSION` atau ditolak: guna semula
  nombor depan yang sama, bump build.
- Version App Store dah `READY_FOR_SALE`: **wajib** naik nombor depan untuk
  release baru. `version.rb next-release --write` buat ini secara automatik.
- Version App Store `IN_REVIEW`: tunggu; Apple tak benarkan build baru masuk
  ke version itu. `next-release` akan berhenti dengan arahan.

Google Play: `versionCode` mesti lebih tinggi daripada semua track
(internal/alpha/beta/production). `versionName` ikut pubspec, tiada logik
berasingan.

## Arahan build

Cara biasa (satu command, build + upload):

- `./ship.sh` → TestFlight + Play internal
- `./ship.sh --release` → App Store review + Play production
- `./ship.sh --ios-only` / `--android-only`, `--track beta`, `--dry-run`

Prasyarat sekali sahaja: `cd mywap_mobile && bundle install` (guna Homebrew
Ruby 3.4; skrip sudah set PATH `/opt/homebrew/opt/ruby/bin`).

Build sahaja (tanpa upload):

- **iOS (IPA):** `./build_ios_release.sh`
  → `mywap_mobile/build/ios/ipa/myWAP.ipa`
- **Android (AAB):** `./build_android_release.sh`
  → `mywap_mobile/build/app/outputs/bundle/release/app-release.aab`

Kedua-dua skrip bump build secara automatik; flag `--no-bump` hanya untuk
kes di mana pubspec sudah disediakan (cth dalam `ship.sh`). Skrip juga
mengesahkan versi dalam IPA/AAB sepadan dengan pubspec sebelum selesai.

## Nota ruang cakera (macOS)

Build iOS/Android memerlukan beberapa GB. Jika `No space left on device`:
- Buang `~/Library/Developer/Xcode/DerivedData/*`
- Buang `~/.gradle/caches`
- Buang `mywap_mobile/build`

## Verifikasi sebelum selesai

- Flutter: `cd mywap_mobile && flutter analyze && flutter test`
- Backend: `php artisan test`

## Backend

- Endpoint awam untuk app (tanpa log masuk): `GET /api/v1/public/home`,
  `GET /api/v1/app-config` (pulangkan `logo_url`).
- Selepas ubah backend, deploy (`./deploy.sh`) sebelum app release diuji.

## Peraturan App Store — derma kebajikan (WAJIB)

Apple (Guideline 3.2.2(iv)) tidak benarkan app mengumpul derma kebajikan
(infaq) **di dalam** app melainkan organisasi diluluskan Benevity/Candid.

- App **tidak boleh** kumpul jumlah/maklumat penderma dalam app.
- Butang derma mesti **buka laman web kempen** dalam default browser atau
  SFSafariViewController (`url_launcher` `LaunchMode.inAppBrowserView`).
- Rujuk `mywap_mobile/lib/features/infaq/presentation/infaq_detail_screen.dart`.
- Bayaran bukan-derma (yuran, e-dagang, tempahan) belum dihadkan, tetapi
  perlu diberi perhatian.

<!-- antislop:start -->
## antislop
For UI, copy, people, mobile layout, or code comments work, read these installed skill files directly (use these paths even if a same-named global skill exists):
- Core filter, always on: `antislop`: `.opencode/skills/antislop/SKILL.md`
- UI / visual: `antislop-ui`: `.opencode/skills/antislop-ui/SKILL.md`
- Copy & text: `antislop-copywriting`: `.opencode/skills/antislop-copywriting/SKILL.md`
- People: `antislop-human`: `.opencode/skills/antislop-human/SKILL.md`
- Mobile / responsive: `antislop-layoutmobile`: `.opencode/skills/antislop-layoutmobile/SKILL.md`
- Code comments: `antislop-code`: `.opencode/skills/antislop-code/SKILL.md`
Before starting, follow the core's "Two Usage Modes" section in strict order: explicit session instruction first, then global preference, then ask. A session instruction always wins. For a resolved mode, say `antislop active: <mode> (session override).` or `antislop active: <mode> (global preference).` once before presenting findings or making edits, using the actual mode and source. Acknowledging the user's request without naming the source does not replace this notice.
Only an explicit choice of antislop during or after selects a session mode. A request to review, audit, or avoid file edits does not select a mode; read the global preference in that case. Another skill's mode does not select antislop's mode.
If the mode is unresolved, ask during/after and end the response; wait for the answer before any UI review, planning, or concept. For read-only tasks, put the active-mode notice only at the start of the final answer, never in progress messages. For editing tasks, announce before the first edit and omit it from the final answer.
To update antislop later: `npx antislop-ai --update`, or run `npx antislop-ai` and pick Overwrite them.
<!-- antislop:end -->
