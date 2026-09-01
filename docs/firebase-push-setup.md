# Firebase Push Notification Setup — myWAP

Project: `mywap-f6b01` · Project number: `587545407330`

## Status: HAMPIR SELESAI — tinggal 2 API key ⬅️ KAU (30 saat)

Aku (agent) dah selesaikan hampir semua melalui Firebase CLI + API, sebab
`afrohafiz@gmail.com` diberi akses Owner ke project. Satu-satunya yang tinggal:
**2 API key** (Android + iOS) yang Firebase **sengaja sembunyi** dari semua
endpoint CLI/API (security hardening — hanya dapat dari web console, yang hanya
manusia boleh baca).

---

## ✅ Yang DAH SELESAI (akar buat semua ni)

| Benda | Status |
|-------|--------|
| Akses project `mywap-f6b01` | ✅ via Firebase CLI (`afrohafiz@gmail.com`) |
| App **Android** `com.mywap.mywap_mobile` | ✅ `1:587545407330:android:d8946388a9812fe545063b` |
| App **iOS** `com.mywap.mywapMobile` | ✅ `1:587545407330:ios:19e26099610413a745063b` |
| `firebase_options.dart` (platform-aware) | ✅ siap, cuma apiKey placeholder |
| Laravel **FCM HTTP v1** (`PushNotificationService`) | ✅ + `firebase/php-jwt` |
| Service account JSON untuk FCM v1 | ✅ `storage/firebase/mywap-f6b01-firebase-adminsdk.json` |
| OAuth2 token flow | ✅ teruji — mint token OK (Bearer, 3599s) |
| `.env` / `.env.example` | ✅ `FCM_SERVICE_ACCOUNT` set |
| `.gitignore` (selamatkan key dari git) | ✅ `/storage/firebase/` |
| Android `POST_NOTIFICATIONS` + compileSdk 36 / AGP 8.9.1 | ✅ dibina OK |
| `flutter analyze` + `flutter build apk` | ✅ hambar, built OK |

---

## ⬅️ KAU: satu-satunya tugasan (30 saat)

Aku perlukan **2 API key** daripada web console. Ikut:

1. Buka Firebase Console → project **mywap-f6b01**
2. **Project settings ⚙️ → General** → di bawah **Your apps**, ada 2 app:
   - **myWAP Android**
   - **myWAP iOS**
3. Untuk setiap app, klik **icon Android/Apple**. API key ada dalam menu pop-up
   **"Add app" / details** — sebenarnya senang: dalam **Google Services** bahagian,
   item **"API key"** bagi setiap app.
4. Salin 2 API key (panjang, format `AIzaSy...` — Android lain, iOS lain).

Bagi aku:
- `API_KEY_ANDROID`  → isi dalam `firebase_options.dart`
- `API_KEY_IOS`      → isi dalam `firebase_options.dart`

> Nota: API key ni BUKAN rahsia ketat (ia memang di-embed dalam APK/IPA).
> Kalau terdedah ia hanya terhad kepada project Firebase. Hanya kau yang boleh
> lihat dari console sebab Firebase block semua akses programatik.

---

## Nota FCM backend

- Simple utk **HTTP v1** guna service account. If nak guna **legacy server key**
  (fallback), isi `FCM_SERVER_KEY` dalam `.env` (dari console → Cloud Messaging).
  Tp v1 dah cukup & bukan deprecated.
- Service account disimpan dalam `storage/firebase/` — **jangan commit ke git**
  (dah add `.gitignore`).

---

## ⬜ BELUM SET: APNs Auth Key (iOS — BLOCKER sebelum build iOS)

Apple Developer account belum didaftar. Sebelum build iOS, kena:

1. Daftar Apple Developer account (`developer.apple.com`)
2. Buat **APNs Auth Key** (`.p8`) di Apple Developer → Certificates, Identifiers & Profiles
3. Upload `.p8` ke **Firebase Console → mywap-f6b01 → Project settings ⚙️ → Cloud Messaging → Apple app configuration**
4. Pastikan `Runner.entitlements` `aps-environment` = `production` untuk TestFlight/App Store (dev build OK dengan `development`)

Tanpa ini, FCM iOS akan gagal dengan ralat "APNs auth key not found".

## Build & test selepas API key masuk

```bash
cd mywap_mobile
# isi dulu 2 apiKey dalam lib/core/push/firebase_options.dart
flutter clean && flutter pub get && flutter run
```

Backend:
```bash
cd ~/Sites/mywap
php artisan config:clear   # refresh selepas edit .env
```
