# AGENTS.md — myWAP

Panduan untuk ejen/AI yang bekerja dalam repo ini.

## Peraturan build & versi (WAJIB)

Sebelum membina release iOS/Android, **sentiasa bump build number** dalam
`mywap_mobile/pubspec.yaml` (bahagian `+N`). App Store Connect & Google Play
menolak build dengan versi yang sama atau lebih rendah daripada yang telah
dimuat naik.

- Format: `version: <semver>+<build_number>`, cth `1.1.2+14`.
- Setiap build baharu: naikkan `<build_number>` sekurang-kurangnya 1.
- Naikkan `<semver>` (cth `1.1.3`) hanya untuk perubahan besar/feature.
- Jangan sekali-kali build release tanpa bump — ia akan gagal di upload.

Contoh ralat yang akan berlaku jika terlupa:
`The bundle version must be higher than the previously uploaded version: '13'.`

## Arahan build

- **iOS (IPA untuk TestFlight/Transporter):**
  `./build_ios_release.sh`
  → hasil: `mywap_mobile/build/ios/ipa/myWAP.ipa`
- **Android (AAB untuk Play Store):**
  `cd mywap_mobile && flutter build appbundle --release --dart-define=API_BASE_URL=https://mywap.my`
  → hasil: `mywap_mobile/build/app/outputs/bundle/release/app-release.aab`

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
