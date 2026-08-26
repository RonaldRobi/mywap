# FLUTTER_PLAN.md — myWAP Mobile (iOS + Android)

> Last updated: 2026-08-22
> Purpose: Blueprint penuh untuk bina aplikasi mobile **Flutter** myWAP, dengan **keutamaan #1 = SPEED & PERFORMANCE** (sebab majoriti pengguna adalah warga emas yang tak tahan app slow).
> Rujuk fail ini sahaja bila nak mula kerja. Lengkap dengan keputusan arkitektur, konvensyen, dan guideline prestasi.

---

## 0. Ringkasan

Kita bina aplikasi mobile **Flutter** (iOS + Android) yang berfungsi sebagai **client kedua** kepada backend Laravel sedia ada. Web app (Vue/Inertia) kekal. Backend Laravel menjadi **sumber kebenaran tunggal** dan diekspos sebagai **REST API** (`/api/v1/*`).

Matlamat:

1. **Speed-first** — cold start laju, scroll 60fps tanpa jank, payload API kecil, caching agresif.
2. **Full parity** — semua 112 skrin Vue akhirnya wujud dalam Flutter.
3. **Konsisten brand** — design system Flutter meniru token reka bentuk web (bukan pixel-perfect, tapi konsisten).
4. **Boleh paralel** — 10 domain, setiap satu boleh dibina oleh agent berasingan serentak tanpa konflik fail.
5. **Senang maintain** — satu logic perniagaan (di Service), dua client tak drift.

---

## 1. Keputusan Terkunci

| Aspek | Keputusan |
|---|---|
| Teknologi mobile | Flutter (iOS + Android) |
| Web app sedia ada | Kekal (Vue 3 + Inertia + Tailwind) |
| Backend | Laravel sedia ada → tambah REST API `/api/v1/*` |
| Auth mobile | Laravel Sanctum — token (Bearer) |
| State management | Riverpod |
| Routing | go_router |
| Networking | Dio + interceptors (token/refresh/error) |
| Codegen model | freezed + json_serializable |
| Storage token | flutter_secure_storage |
| Cache (offline-ringan) | Isar/Hive + flutter_cache_manager (imej) |
| i18n | ARB (ms + en) — port dari `resources/js/i18n.js` |
| UI parity | Konsisten brand (design system), bukan pixel-perfect |
| Offline | Online + cache ringan (bukan offline-first) |
| Push notification | FCM (Android) + APNs (iOS) |
| Service extraction | Incremental per-domain (pilihan 1) |
| Pelaksanaan | Multi-agent, 10 domain selari |

---

## 2. Prinsip Utama — PERFORMANCE FIRST

> Ini bukan "nice to have". Ini keperluan produk. Warga emas akan uninstall kalau app rasa slow.

### 2.1 Target prestasi (wajib capai)

| Metrik | Target |
|---|---|
| Cold start (buat app → skrin pertama) | < 2.0s (ideal < 1.5s) |
| Frame rate scroll | 60fps, tiada jank > 16ms/frame |
| API response (p50) | < 300ms |
| API response (p95) | < 800ms |
| Skrin pertama render (skeleton) | < 100ms (skeleton, kemudian data) |
| Perceived load (senarai) | Skeleton + data berperingkat, bukan spinner penuh |

### 2.2 Cara ukur (sejak Fasa 0)

- **Flutter DevTools** — frame chart, track jank, memory.
- **`flutter run --profile`** (bukan debug) untuk ukur sebenar.
- **k6 / Artillery** — load test API (target 1k–2k serentak masa pelancaran).
- **Flutter `Timeline` / `TimingSummary`** untuk cold start.

### 2.3 Peraturan emas (wajib ikut semua agent)

1. **Jangan `ListView(children: [...])`** untuk senarai panjang — guna `ListView.builder` / `SliverList`.
2. **`const` constructor** di mana sahaja boleh.
3. **Jangan rebuild whole tree** — guna `ref.watch` secara selektif (Riverpod `select`).
4. **Imej** — sentiasa `cached_network_image` + thumbnail resize server-side. Jangan load imej original penuh.
5. **JSON parsing besar** — guna `compute()` / isolate, dan codegen (json_serializable).
6. **Jangan init berat dalam `main()`** — defer semua kerja bukan-kritikal.
7. **Jangan bina semua tab skrin sekaligus** — lazy build (IndexedStack / lazy tab).
8. **Elak animasi berlebihan** — warga emas mahu jelas & pantas, bukan mewah.
9. **Elak heavy widget tree nesting** — flat, simple layout.

---

## 3. Arkitektur Keseluruhan

```
┌─────────────────────┐        ┌──────────────────────────────┐
│  Vue Web (Inertia)   │        │  Flutter Mobile (iOS+Android)│
│  Auth: session       │        │  Auth: Sanctum Bearer token  │
└──────────┬───────────┘        └──────────────┬───────────────┘
           │                                    │  HTTPS / JSON
           │                                    ▼
           │                    ┌──────────────────────────────┐
           └───────────────────►│      Laravel (backend)       │
                                │                              │
                                │  Service layer (logic tunggal)│
                                │  ├─ FeeService               │
                                │  ├─ DonorService             │
                                │  ├─ BayarCashService         │
                                │  ├─ DokuService              │
                                │  ├─ SenangPayService         │
                                │  ├─ OtpService               │
                                │  └─ PaymentGatewayManager    │
                                │                              │
                                │  WebController → Inertia      │
                                │  ApiController → Resource     │
                                └──────────────┬───────────────┘
                                               │
                                     ┌─────────┴─────────┐
                                     │  PostgreSQL/MySQL  │
                                     │  Redis (cache)     │
                                     └───────────────────┘
```

---

## 4. Prinsip Seni Bina Backend (elak drift)

Satu logic, dua controller nipis:

```
FormRequest (validation, kongsi) → Service (logic, TUNGGAL)
   ├─► WebController → Inertia::render()      [web]
   └─► ApiController → Resource (JSON)        [Flutter]
```

Peraturan:

1. **Logic TAK BOLEH dalam controller.** Semua logic perniagaan hidup dalam `App\Services\*`.
2. Bila Flutter mula satu domain → logic domain itu **dipindah ke Service sekaligus**, dan WebController domain itu di-refactor sama-sama. Domain lain jangan disentuh (incremental).
3. **Reuse Policies** (`App\Policies\*`) untuk authorization — jangan tulis semula.
4. **Reuse FormRequest** untuk validation — kongsi web & API.

### 4.1 Struktur fail backend

```
app/Http/Controllers/Api/V1/     # Api controller (per domain)
app/Http/Resources/              # JSON transformer (per model)
app/Services/                    # logic tunggal
routes/api.php                   # versioned /api/v1/*
```

---

## 5. Fasa 0 — Foundation (critical path, JANGAN parallel)

> Dibina oleh SATU agent (atau lead dev) dahulu. Semua agent domain kemudian **tiru template ini**. Kalau foundation tak solid, 10 agent akan hasilkan app tak seragam + slow.

### 5.1 Backend

1. **Sanctum auth**
   - `POST /api/v1/auth/login` → `{ data: { token, user } }`
   - `POST /api/v1/auth/logout`
   - `GET  /api/v1/auth/me`
   - CORS untuk origin mobile (lihat §6.6).
2. **Struktur base**
   - `app/Http/Controllers/Api/V1/AuthController.php`
   - `app/Http/Resources/UserResource.php`
   - `routes/api.php` (versioned group `prefix => 'api/v1'`)
3. **Konvensyen standard** (lihat §8) — pagination, error, envelope. Dokumentasikan.
4. **2 domain template penuh** (controller → service → resource → feature test):
   - **Member Dashboard** — `GET /api/v1/member/dashboard`
   - **Event** — `GET /api/v1/events`, `GET /api/v1/events/{id}`, `POST /api/v1/events/{id}/rsvp`
   - Ini jadi contoh rujukan untuk semua agent lain.

### 5.2 Flutter

1. **Scaffold project**
   - `flutter create --org com.mywap mywap_mobile`
   - Folder feature-first (lihat §9).
2. **Design system** (lihat §10) — `ThemeData`, `TextTheme`, `Spacing`, `Radius`, `Shadow`, warna brand.
3. **Core infra**
   - Dio + interceptor (attach Bearer, auto-refresh, error mapping).
   - `flutter_secure_storage` (simpan token).
   - go_router (routes).
   - Riverpod providers.
   - Codegen setup (build_runner, freezed, json_serializable).
   - i18n ARB (ms + en).
4. **Deliverable**: app buka → login → dashboard → senarai event, end-to-end, laju.

---

## 6. Fasa 1 — 10 Domain (parallel agents)

Setiap agent own folder + routes + skrin + API + test. **Tiada konflik fail.** Setiap domain WAJIB ikut konvensyen §8, §9, §10, §11.

| # | Domain | Skrin utama | Contoh endpoint API |
|---|---|---|---|
| 1 | Auth + Profile | login, register, forgot-password, profil, journey, complete-profile | `/auth/*`, `/profile` |
| 2 | Member core | dashboard, kad ahli, financial, pengumuman, pustaka, hub | `/member/*` |
| 3 | Events + Kehadiran | event list, RSVP, attend QR, admin event, attendance | `/events/*` |
| 4 | Infaq + Donors | infaq list/show/donate/QR/success, admin donors | `/infaq/*`, `/donors/*` |
| 5 | E-commerce | produk, kategori, pesanan, cart, checkout | `/products/*`, `/orders/*` |
| 6 | News/Artikel/Info | artikel, info terkini, komen, reaksi, video | `/articles/*`, `/news/*` |
| 7 | Admin core | admin dashboard, ahli, yuran, transaksi, usrah, broadcast | `/admin/*` |
| 8 | Superadmin | org, yuran global, banner, tetapan, template emel | `/superadmin/*` |
| 9 | Kemudahan/Usrah/Forms/Polls | tempahan ruang, usrah, borang, undian | `/facilities/*`, `/usrah/*`, `/forms/*`, `/polls/*` |
| 10 | Direktori + Kad awam + Chat | direktori, kad awam, chat | `/directory/*`, `/chat/*` |

### 6.1 Aliran kerja setiap domain (agent)

1. Pindah logic domain ke `Service` (incremental).
2. Bina `ApiController` + `Resource` + `FormRequest` (reuse Policies).
3. Tulis **feature test** Laravel (API).
4. Bina skrin Flutter (gantung pada design system Fasa 0).
5. Tulis **widget test** Flutter untuk skrin utama.
6. Lulus check prestasi (§11) — tiada jank, senarai guna builder.

---

## 7. Fasa 2 — Cross-cutting (agent khusus)

| Item | Detail |
|---|---|
| Payment | Map flow BayarCash / Doku / SenangPay (redirect → webview → callback). Guna services sedia ada. |
| Push notification | FCM + APNs + `device_tokens` table + deep-link. |
| QR + camera | Papar kad ahli (qr_flutter), scan kehadiran (mobile_scanner). |

---

## 8. Fasa 3 — Integrasi, QA, Release

1. Integrasi semua domain, smoke test.
2. `flutter test` + `php artisan test` — semua hijau.
3. Prestasi audit (DevTools, cold start < 2s, tiada jank).
4. Build + signing:
   - Android: `flutter build appbundle` (AAB) + keystore + Play Console.
   - iOS: `flutter build ipa` + App Store Connect + TestFlight.
5. Submit + review (Apple review 1–7 hari untuk app pertama).

---

## 8. Konvensyen API (standard — WAJIB ikut)

> Semua endpoint mesti seragam supaya Flutter client boleh buat satu `ApiClient` yang jangka satu bentuk response.

### 8.1 Response envelope — success

```json
{
  "data": { "id": 1, "name": "..." },
  "meta": { "cached": true }
}
```

### 8.2 Pagination (Laravel paginator)

```json
{
  "data": [ { "id": 1, "name": "..." } ],
  "meta": {
    "current_page": 1,
    "last_page": 10,
    "per_page": 25,
    "total": 240
  },
  "links": {
    "first": "/api/v1/events?page=1",
    "last": "/api/v1/events?page=10",
    "prev": null,
    "next": "/api/v1/events?page=2"
  }
}
```

- Semua senarai WAJIB `paginate()`. Sokong `?per_page=25|50|100`.

### 8.3 Error

```json
{
  "message": "Sila semak semula maklumat anda.",
  "errors": {
    "email": ["Emel diperlukan."],
    "password": ["Kata laluan minimum 8 aksara."]
  }
}
```

- Status code HTTP betul: `422` validation, `401` auth, `403` forbidden, `404` not found, `429` throttle.

### 8.4 Auth header

```
Authorization: Bearer <sanctum_token>
Accept: application/json
```

### 8.5 Versioning & konsistensi

- Semua route bawah `/api/v1/*`.
- Resource class bernama `XxxResource` (contoh `EventResource`), sentiasa return `new XxxResource($model)` / `XxxResource::collection($models)`.
- Tarikh guna ISO-8601 (`toISOString()`).

### 8.6 CORS

Benarkan origin mobile:
- Android: `http://localhost`, `https://localhost`
- iOS: `capacitor://localhost` (tiada lagi — Flutter guna `http`), `http://localhost`

> Flutter bukan WebView — kebanyakan request dibuat dari engine asli, jadi CORS biasanya tak isu. Tapi kalau guna `webview` untuk payment redirect, pastikan origin webview dibenarkan.

---

## 9. Konvensyen Flutter (folder & code)

### 9.1 Struktur folder

```
lib/
├── main.dart
├── app.dart                    # MaterialApp.router + tema
├── core/
│   ├── network/                # Dio client, interceptors, ApiClient
│   ├── storage/                # secure storage, cache
│   ├── router/                 # go_router
│   ├── constants/              # api paths, keys
│   └── utils/                  # helpers, formatters
├── shared/
│   ├── widgets/                # komponen UI guna bersama
│   ├── theme/                  # design system (lihat §10)
│   └── l10n/                   # ARB ms + en
└── features/
    ├── auth/
    ├── profile/
    ├── member/
    ├── events/
    ├── infaq/
    ├── ecommerce/
    ├── news/
    ├── admin/
    ├── superadmin/
    ├── facilities/
    └── directory/
        ├── data/               # repository, dto/model
        ├── application/        # riverpod providers
        └── presentation/       # screens + widgets
```

### 9.2 Peraturan code

- Model: freezed + json_serializable (`part 'x.freezed.dart'; part 'x.g.dart';`).
- State: Riverpod (AsyncNotifier / FutureProvider / StreamProvider).
- Repository class berasingan dari UI — UI tak tahu HTTP detail.
- Nama fail: snake_case.
- Semua string UI guna ARB (`AppLocalizations`), jangan hardcode.

---

## 10. Design System (port dari web)

> Port token ini dari `resources/css/app.css` + `tailwind.config.js`. Ini asas konsistensi brand. WAJIB siap dalam Fasa 0.

### 10.1 Warna (dari `:root` di app.css)

| Token web | Hex | Kegunaan |
|---|---|---|
| `--movement-navy` | `#071525` | Text utama |
| `--movement-dark-green` | `#123d2a` | Header / aksen gelap |
| `--movement-green` | `#2f6b32` | Primary |
| `--movement-soft-green` | `#6fbf8a` | Aksen lembut |
| `--movement-off-white` | `#f4f6f1` | Background |

### 10.2 Typography

- Font: **Figtree** (gantian: Google Fonts `Figtree`).
- Skala saiz (Flutter `TextTheme`) port dari Tailwind scale (`text-sm`, `text-base`, `text-lg`, `text-xl`, `text-2xl`...).

### 10.3 Spacing / Radius / Shadow

- Definisikan `Spacing` constants (4/8/12/16/24/32) selari Tailwind `spacing`.
- `Radius` (8/12/16/24 — port `rounded-lg/xl/2xl`).
- `Shadow` (port `shadow-sm/md/lg`).

### 10.4 Aksesibiliti warga emas (WAJIB)

- Saiz teks minimum **16sp** (boleh `textScaler` untuk besarkan).
- **Tap target minimum 48×48dp**.
- Kontras tinggi (teks gelap atas latar terang).
- Biarkan pengguna besarkan font (jangan kunci `textScaleFactor`).
- Butang utama besar & jelas.

---

## 11. Performance Guidelines (terperinci)

### 11.1 Flutter — Rendering (60fps)

```dart
// ❌ SALAH — semua item dibina sekali gus, jank bila banyak
ListView(children: items.map((e) => ItemTile(e)).toList())

// ✅ BETUL — lazy build
ListView.builder(itemCount: items.length, itemBuilder: (c, i) => ItemTile(items[i]))
```

- Guna `SliverList`/`SliverGrid` dalam `CustomScrollView` untuk skrin kompleks.
- `const` pada widget tanpa state.
- `RepaintBoundary` untuk bahagian yang kerap berubah (cth: timer, chart).
- Riverpod `select` — elak rebuild bila field lain berubah.
- Chart: `fl_chart` — guna `CustomPainter` ringan, jangan rebuild setiap frame.
- Tab: `IndexedStack` untuk kekalkan state, tapi lazy-build kandungan tab pertama kali dibuka.

### 11.2 Flutter — Imej (punca utama slow)

```dart
// ✅ BETUL — cached + placeholder + error
CachedNetworkImage(
  imageUrl: item.thumbnailUrl,   // server resize, bukan original
  placeholder: (c, _) => SkeletonBox(),
  errorWidget: (c, _, __) => Icon(Icons.broken_image),
)
```

- Server wajib sediakan **thumbnail** (resize). Jangan hantar imej 2MB ke phone.
- `flutter_cache_manager` untuk cache imej disk.
- `cacheWidth` untuk downscale imej besar kepada saiz paparan.

### 11.3 Flutter — Networking & payload

- **Payload kecil** — sparse fieldset (`?fields=id,name,status`), jangan return semua kolum.
- **Pagination** untuk setiap senarai.
- **Dio interceptor cache** untuk endpoint yang jarang berubah (announcement, banner).
- **Dedupe** — jangan fetch benda sama berulang (Riverpod autoDispose + keepAlive).

### 11.4 Flutter — Parsing & CPU

```dart
// Parsing JSON besar off main isolate
final data = await compute(parseEvents, jsonString);
```

- Guna `json_serializable` (codegen) — lebih laju dari manual `jsonDecode` map.
- Jangan `jsonDecode` dalam `build()`.

### 11.5 Flutter — Cold start

- Jangan init DB/network/semua provider dalam `main()`. Defer sehingga perlu.
- `flutter build appbundle --split-per-abi` (Android) — APK lebih kecil per ABI.
- R8/proguard aktif (default release).
- Icons: `--tree-shake-icons`.
- Splash screen native (Flutter splash) — jangan buat splash dalam Dart (tambah masa).

### 11.6 Backend — API laju

- Rujuk `PERFORMANCE.md` (Fasa 1–4 dah siap): index, eager-load, cache, Redis, OPcache, queue.
- API spesifik:
  - **Sparse fieldset** (`?fields=`) untuk kurangkan payload.
  - **Pagination** wajib.
  - **Cache response** (`Cache::remember`) untuk data statik/jarang berubah.
  - **ETag / Conditional GET** untuk data yang di-fetch kerap.
  - **gzip/brotli** aktif (Nginx).
  - **HTTP/2** (bila guna HTTPS).
  - **Load test** k6 target 1k–2k serentak sebelum pelancaran.

---

## 12. UX untuk Warga Emas

> Diintegrasikan dalam design system §10.4. Ringkasan prinsip:

1. **Jelas** — label besar, bahasa Melayu mudah, ikon + teks (bukan ikon sahaja).
2. **Pantas** — skeleton dalam < 100ms, jangan biar pengguna menunggu tanpa maklum balas.
3. **Tap besar** — 48dp minimum, ruang lega.
4. **Kontras tinggi** — teks gelap atas latar terang.
5. **Navigasi simple** — back button jelas, elak deep nesting menu.
6. **Font boleh besar** — hormati `textScaler`, jangan kunci.
7. **Error mesra** — mesej jelas dalam BM, bukan kod teknikal.
8. **Elak animasi panjang** — fokus pada respons pantas.

---

## 13. Risiko & Mitigasi

| Risiko | Mitigasi |
|---|---|
| Design system tak solid → skrin drift + tak konsisten | Fasa 0 wajib sempurna + doc token |
| Payment redirect flow paling rumit | Agent khusus + reuse services sedia ada |
| 112 skrin + 67 model, agent tak seragam | Konvensyen §8–§11 + template Fasa 0 |
| Drift web vs Flutter | Service extraction incremental + changelog design system |
| Jank / slow (warga emas) | Performance-first §2, §11 + audit setiap domain |
| Apple review lama (app pertama) | Submit TestFlight awal (Fasa 3 seawal mungkin) |
| API lambat masa 1k–2k serentak | Load test awal + Redis/OPcache/queue (PERFORMANCE.md) |

---

## 14. Senarai Perlu Sahkan (sebelum Fasa mula)

1. Payment gateway mana yang **live** (BayarCash / Doku / SenangPay — satu utama atau semua?).
2. **Bundle ID / nama app** (cth: `com.mywap.app`) + branding akhir.
3. **Font Figtree** — license untuk bundle dalam Flutter.
4. Cara agent report balik (pull request per domain?).
5. Anggaran pengguna serentak pelancaran (untuk target load test) — default 1k–2k.

---

## 15. Rujukan

- `PERFORMANCE.md` — optimization backend sedia ada (Fasa 1–4).
- `docs/roadmap.md` — keputusan arkitektur & task tracking sedia ada.
- `docs/project-status.md` — status projek terkini.
- `resources/css/app.css` — token warna & gaya asal.
- `resources/js/i18n.js` — kamus terjemahan ms/en (sumber ARB).
