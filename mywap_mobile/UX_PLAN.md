# Pelan Penambahbaikan UI/UX — myWAP Mobile

> **Arahan untuk sesi akan datang:** Baca dokumen ini penuh sebelum mula kerja.
> Setiap fasa boleh dibuat berasingan (tak saling bergantung kecuali
> dinyatakan). Tandakan `[x]` bila siap dan kemas kini fail ini selepas
> setiap fasa. Jalankan `flutter analyze && flutter test` selepas setiap
> fasa sebelum beralih ke fasa seterusnya.
>
> Skop: **mobile app sahaja** (`mywap_mobile/`). Web (`resources/js/`,
> Blade/Inertia) tidak disentuh oleh mana-mana fasa dalam dokumen ini,
> **kecuali** Fasa 3 yang membaca (bukan mengubah) kod web sebagai rujukan
> design.

## Status semasa repo (pada masa pelan ini ditulis)

- `pubspec.yaml` version: `1.4.0+20` (selepas Fasa 1, 2, 3, 5, 6
  dilaksanakan — lihat "Log pelaksanaan" di hujung dokumen).
- **WAJIB** bump build number (`+N` dalam `pubspec.yaml`) sebelum build
  release — rujuk `AGENTS.md` repo root.
- Backend (`php artisan test`) **tidak terjejas** oleh mana-mana fasa dalam
  dokumen ini. Fasa 3 (kad ahli) khususnya — backend **sudah siap 100%**,
  tiada perubahan PHP/Laravel diperlukan sama sekali.

---

## FASA 0 — Simpan pelan ini ✅

- [x] Tulis dokumen ini sebagai `mywap_mobile/UX_PLAN.md`

---

## FASA 1 — Quick fixes (kos rendah, risiko rendah)

### 1.1 Dark theme — TIADA KERJA DIPERLUKAN ✅ (disahkan semasa audit)

**Dapatan:** App **sudah** single-theme. `lib/app.dart:72` hanya set
`theme: AppTheme.light`, tiada `darkTheme:`/`themeMode:` langsung.
`ColorScheme.fromSeed()` di `lib/shared/theme/app_theme.dart:73-79` tiada
parameter `brightness`. Tiada toggle UI, tiada provider theme-mode wujud di
mana-mana. Flutter behaviour: bila `darkTheme` null pada `MaterialApp`,
`theme:` dipakai tak kira brightness sistem peranti.

- [x] Tiada tindakan diperlukan — tandakan selesai.

### 1.2 Padding greeting "Assalamualaikum, {nama}"

**Fail:** `lib/features/member/presentation/member_dashboard_screen.dart`

**Punca:** `SliverAppBar` (~baris 76) set `titleSpacing: 0` secara eksplisit.
Widget `_Greeting` (kelas ~baris 203-244) langsung tiada `Padding`/
`Container` pembalut — teks mula pada x=0 (selepas safe-area sahaja),
berbeza daripada padding standard app `Spacing.lg` (16px) yang dipakai
hampir semua skrin lain.

**Pembetulan (scoped, tak sentuh `titleSpacing` supaya `NotificationBell` di
kanan tak bergeser):**

```dart
// Dalam class _Greeting, build() — bungkus Column sedia ada:
return Padding(
  padding: const EdgeInsets.only(left: Spacing.lg), // 16
  child: Column(
    crossAxisAlignment: CrossAxisAlignment.start,
    mainAxisSize: MainAxisSize.min,
    children: [
      Text('Assalamualaikum, $_firstName', ...),
      Text(greeting, ...),
    ],
  ),
);
```

**Isu sampingan ditemui (TIDAK termasuk dalam skop asal, pilihan optional):**
`_MembershipCard` (~baris 455-479, fail sama) guna radius hardcode
`circular(28)` dan padding dalaman `Spacing.md` (12) — tak konsisten dengan
`AppRadius.hero` (32) dan `Spacing.lg` (16) yang dipakai kad lain. Biarkan
dulu melainkan diminta.

- [x] Bungkus `_Greeting` dengan `Padding(left: Spacing.lg)`
- [x] `flutter analyze && flutter test`

### 1.3 Radius hero banner — 3 widget sahaja (BUKAN token global)

**Keputusan kau (disahkan semasa perbincangan):** Hanya widget **banner**,
bukan token `AppRadius.hero` (yang turut dipakai kad ahli lama — dibina
semula di Fasa 3 — dan CTA/header Menu hub).

**Nilai cadangan:** 32 → **24** (kurangkan ~25%). Boleh dilaraskan semula
bila dilihat di device sebenar.

| Widget | Fail:Baris | Ubah |
|---|---|---|
| `_Banner` (dashboard) | `member_dashboard_screen.dart:255` | `borderRadius: AppRadius.hero` → `borderRadius: const BorderRadius.all(Radius.circular(24))` |
| `_TopCarousel` (public home) | `public_home_screen.dart:203` | sama |
| `_BrandHero` fallback (public home) | `public_home_screen.dart:284` | sama |

**Jangan** ubah token `AppRadius.hero` sendiri (`app_theme.dart:24`) — ia
dipakai di tempat lain (`_MemberCta`, header Menu hub `PatternBackdrop`,
event list card `_EventCard`) yang tidak dalam skop perubahan ini.

Opsyen kemas (optional, bukan wajib): tambah token baru
`AppRadius.banner = BorderRadius.all(Radius.circular(24))` dalam
`app_theme.dart` dan guna `AppRadius.banner` di 3 tempat atas supaya tiada
angka hardcode berulang.

- [x] `_Banner` (dashboard) — radius 24
- [x] `_TopCarousel` (public home) — radius 24
- [x] `_BrandHero` (public home) — radius 24
- [x] `flutter analyze && flutter test`

---

## FASA 2 — Pembetulan pemotongan imej (poster 1080×1350, ratio 0.8)

**Punca akar:** `AppImage` (`lib/shared/widgets/app_image.dart:20`) default
`fit: BoxFit.cover`, **tiada parameter `aspectRatio`**. Setiap caller kena
sediakan sendiri `AspectRatio` yang padan 0.8 — kebanyakan tempat guna
`height` tetap atau ratio lain (16:9, 1:1), menyebabkan crop teruk.

**Pola pembetulan seragam:** Ganti `height: X` tetap → bungkus dalam
`AspectRatio(aspectRatio: 4/5, child: AppImage(...))`. `4/5 = 0.8` padan
**tepat** dengan 1080:1350 — rujukan contoh betul sedia ada:
`news_list_screen.dart:111`, `news_detail_screen.dart:100`,
`infaq_detail_screen.dart:133`.

### 2.1 Event detail — featured image (PALING TERUK, contoh eksplisit)

**Fail:** `lib/features/events/presentation/event_detail_screen.dart:198-203`

Sebelum:

```dart
AppImage(
  event?.featured_image_url,
  height: 220,
  width: double.infinity,
  borderRadius: BorderRadius.zero,
),
```

Lepas:

```dart
AspectRatio(
  aspectRatio: 4 / 5,
  child: AppImage(
    event?.featured_image_url,
    width: double.infinity,
    borderRadius: BorderRadius.zero,
  ),
),
```

**Jangan lupa** skeleton loading sama fail (~baris 387):
`SkeletonBox(height: 220, radius: 0)` → bungkus
`AspectRatio(aspectRatio: 4/5, child: SkeletonBox(radius: 0))` juga (biar
konsisten semasa loading vs loaded).

- [x] `event_detail_screen.dart` featured image → `AspectRatio(4/5)`
- [x] `event_detail_screen.dart` skeleton loading → `AspectRatio(4/5)` juga

### 2.2 Event list card (thumbnail)

**Fail:** `lib/features/events/presentation/events_screen.dart:123-145`
(class `_EventCard`)

Sebelum:

```dart
SizedBox(
  height: 176,
  child: Stack(
    fit: StackFit.expand,
    children: [
      if (imageUrl != null && imageUrl.isNotEmpty)
        AppImage(imageUrl, fit: BoxFit.cover)
      else
        Container(color: AppColors.paleGreen),
      ...
```

Lepas:

```dart
AspectRatio(
  aspectRatio: 4 / 5,
  child: Stack(
    fit: StackFit.expand,
    children: [
      if (imageUrl != null && imageUrl.isNotEmpty)
        AppImage(imageUrl, fit: BoxFit.cover)
      else
        Container(color: AppColors.paleGreen),
      ...
```

**Amaran:** Kad ini guna `borderRadius: AppRadius.hero` (32) pada
`Material`/`InkWell`/`ClipRRect` (3 tempat, ~baris 114-120) — **ini BUKAN
banner widget** (tak disenaraikan Fasa 1.3), jadi **kekalkan 32** kecuali
diminta berubah juga.

Lebih tinggi (176 → lebar×1.25) bermakna kad jadi lebih tinggi dari
sekarang — semak visual di device, mungkin perlu kurangkan padding/spacing
sekitar supaya senarai tak jadi terlalu panjang menatal.

- [x] `events_screen.dart` `_EventCard` thumbnail → `AspectRatio(4/5)`
- [ ] Semak visual height senarai selepas perubahan (device sebenar)

### 2.3 Dashboard event card (carousel horizontal)

**Fail:** `member_dashboard_screen.dart:985-990` (class `_EventCard`,
`SizedBox` width:175)

Sama pola — ganti `height` tetap/`SizedBox` imej dengan
`AspectRatio(aspectRatio: 4/5, ...)` dalam `Column` kad (lebar kad kekal
175, tinggi imej auto-kira).

- [x] `member_dashboard_screen.dart` `_EventCard` → `AspectRatio(4/5)`
  (tinggi senarai mendatar dinaikkan 230 → 320 untuk elak limpahan)

### 2.4 Articles (list + detail + gallery) — modul SAMA pattern, ratio salah

**Fail 1:** `lib/features/news/presentation/articles_list_screen.dart:111-119`

```dart
// SEBELUM
AspectRatio(
  aspectRatio: 16 / 9,   // ← salah
  child: AppImage(article.coverImage, width: double.infinity, fit: BoxFit.cover, borderRadius: BorderRadius.zero),
),
// LEPAS — tukar 16/9 → 4/5
```

**Fail 2:** `lib/features/news/presentation/article_detail_screen.dart:101-105`
(featured image)

```dart
// SEBELUM
AppImage(article.coverImagePath, height: 200, borderRadius: BorderRadius.circular(12)),
// LEPAS
AspectRatio(
  aspectRatio: 4 / 5,
  child: AppImage(article.coverImagePath, width: double.infinity, borderRadius: BorderRadius.circular(12)),
),
```

**Fail 2 (sama), galeri:** `article_detail_screen.dart:126-140`

```dart
// SEBELUM — SizedBox(height:180) + SizedBox(width:260) → ratio 1.44
// LEPAS — kekalkan height 180 (ketinggian strip galeri), width dikira
// auto daripada AspectRatio:
SizedBox(
  height: 180,
  child: ListView.separated(
    ...
    itemBuilder: (_, i) => AspectRatio(
      aspectRatio: 4 / 5,
      child: AppImage(article.gallery[i].path, borderRadius: BorderRadius.circular(12)),
    ),
  ),
),
```

Nota: `AspectRatio` dalam `ListView` horizontal mesti dibungkus dalam
sesuatu yang bagi height eksplisit (height:180 pada `SizedBox` luar sudah
cukup — `AspectRatio` akan auto-kira width = 180/0.8 = 144px setiap item,
gantikan `width:260` hardcode).

- [x] `articles_list_screen.dart` — 16/9 → 4/5
- [x] `article_detail_screen.dart` featured image → `AspectRatio(4/5)`
- [x] `article_detail_screen.dart` galeri → `AspectRatio(4/5)` per-item

### 2.5 Produk (ecommerce) — pemotongan sederhana, pilihan optional

**Fail:** `lib/features/ecommerce/presentation/product_detail_screen.dart:188-194`
(hero image, height:280 tetap)

```dart
// Jika admin selalu upload poster 1080×1350 utk produk juga:
AspectRatio(
  aspectRatio: 4 / 5,
  child: AppImage(product.displayImage, width: double.infinity, fit: BoxFit.cover, borderRadius: BorderRadius.zero),
),
```

Thumbnail katalog (`products_screen.dart:397`, ratio 1:1) — **biarkan**,
crop ringan (~20%) biasanya boleh diterima untuk foto produk segiempat.
Hanya betulkan hero jika produk pun kena crop teruk di device — **tanya
user dulu** sebelum ubah, ini di luar skop eksplisit asal.

- [ ] (Optional, tanya dulu) `product_detail_screen.dart` hero image

### 2.6 TAK PERLU DIUBAH (dah betul / sesuai)

- `news_list_screen.dart`, `news_detail_screen.dart`,
  `infaq_detail_screen.dart` — dah guna 4/5 ✅
- `videos_screen.dart:336` (16:9) — thumbnail video memang 16:9 dari
  sumber, betul ✅
- Library book cover (3:4 di `member_dashboard_screen.dart:1455` &
  `public_home_screen.dart:586`) — hampir tiada crop (~6%), biarkan ✅

- [x] `flutter analyze && flutter test` selepas semua perubahan Fasa 2

---

## FASA 3 — Kad Ahli (replikasi design web, TANPA ubah web)

### Penemuan kritikal (jimat masa sesi akan datang — JANGAN ulang audit ini)

**Backend SUDAH SEDIA 100%.** `app/Services/MemberCoreService.php::card()`
(baris 30-71) **sudah** hantar dalam response `/member/card`:

```php
'card_front_path' => ...,   // baris 58 — imej background custom per-organisasi
'card_back_path'  => ...,   // baris 59 — imej muka belakang custom
```

**TIADA kerja backend/PHP diperlukan.** Punca design lama tak match: model
Flutter `MemberCardInfo.fromJson()` langsung tak parse 2 field ini, dan
widget `member_card_screen.dart` reka bentuk sepenuhnya berbeza (bukan
ikut koordinat SVG web).

### Spec tepat dari web

Rujukan sumber (baca, JANGAN ubah):
- `resources/js/Components/MemberCard/layout.js` — semua konstanta +
  logik auto-fit teks
- `resources/js/Components/MemberCard/MemberCardFront.vue` — struktur SVG
  muka depan
- `resources/js/Components/MemberCard/MemberCardFlip.vue` — animasi flip

**Kanvas design:** 638 × 1004 px (nisbah kad TNG 54×85.6mm menegak =
0.635:1)

| Elemen | Koordinat/Nilai (dalam kanvas 638×1004) |
|---|---|
| Foto ahli (bulatan) | `cx:320, cy:428, r:130` — diameter 260px, clip bulat, fallback warna `#dfeee5` + inisial 72px/w800 warna `#03553e` |
| Nama | `centerX:319, maxWidth:520`, auto-shrink 30→20px, auto-wrap 1-2 baris, UPPERCASE, w700, warna `#00291a`, baseline baris-1 = y:600, lineHeight:36 |
| Kotak organisasi (pill hijau) | `x:185, w:272, h:40, radius:5`, warna `#03553e`; teks UPPERCASE letter-spacing:1, auto-shrink 28→12px, w800, putih; posisi `y` = nameBottom+50 |
| No. Ahli | `centerX:319`, auto-shrink 20→12px, w700, warna `#012c10`; posisi = orgBoxBottom+50 |
| QR | `x:480, y:845, size:95, padding:3`, kotak putih `rx:3`, **sudut kanan-bawah** (bukan tengah) |
| Background | `card_front_path` (imej custom, `preserveAspectRatio="xMidYMid slice"` = setara `BoxFit.cover`) ATAU fallback gradient `#1a6b4f → #0f5a3e` (atas→bawah) |
| Radius luar kad | `4.6%/2.9%` elliptical (bukan radius tetap) |
| Shadow | `0 18px 40px -12px rgba(0,60,40,0.45)` |
| Font | `Figtree` (web) |
| Muka belakang | `card_back_path` (imej custom, `object-cover`) ATAU fallback gradient `#e6f2ea→#0f5a3e` + teks "Design belakang kad belum dimuat naik." |
| Flip animation | 3D `rotateY(180deg)`, `cubic-bezier(0.4,0.2,0.2,1)` 0.7s, tap untuk toggle |

**ELEMEN YANG DIBUANG** (ada di mobile lama, TIADA di web baru): grid info
Email/Telefon/Cawangan/Profesion/Lokasi (`member_card_screen.dart:190-211`,
class `_Detail`). Web baru hanya: background + foto + nama + organisasi +
no.ahli + QR.

### Keputusan teknikal (dah dibuat semasa perbincangan — jangan tanya lagi)

1. **QR:** Guna `qr_flutter` client-side (`QrImageView` + `card?.qrValue`)
   — **BUKAN** parse `qrPrivate`/`qrPublic` SVG mentah dari server. Sebab:
   `qr_flutter` sudah jadi dependency, hasil visual QR sama (payload URL
   sama), **tiada dependency baru diperlukan** (`flutter_svg` TIDAK ada
   dalam `pubspec.yaml` — disahkan via grep). Kalau nak parity 100%
   pixel-perfect dengan server SVG, boleh tambah `flutter_svg` kemudian —
   tapi tak perlu untuk fungsi.
2. **Font:** Guna `Inter` sedia ada (app punya font bundled), **BUKAN**
   `Figtree`. Alasan: tiada Figtree di-bundle, muat turun+bundle font baru
   perlukan kerja tambahan (asset, pubspec, lesen) untuk kesan visual
   kecil. Kalau nak Figtree tepat, boleh tambah kemudian.
3. **Kaedah render:** `Stack` + `Positioned` berskala (bukan
   `CustomPainter` mentah) — guna `LayoutBuilder` untuk kira skala
   `canvasWidth/638`, kemudian setiap elemen
   `Positioned(left: x*scale, top: y*scale, ...)`. Lebih mudah
   diselenggara berbanding `CustomPainter` manual untuk teks+imej+bentuk
   bercampur.
4. **Auto-fit teks (nama/organisasi/no.ahli):** Port logik
   `fitSingleLine()`/`wrap()` dari `layout.js` ke Dart — guna
   `TextPainter` untuk ukur lebar teks, loop turunkan `fontSize` sehingga
   muat, sama logik dengan JS punya `measureText()`.

### Fail yang perlu diubah

**1. `lib/features/member/data/models/member_card_data.dart`** — tambah
field pada `MemberCardInfo`:

```dart
final String? card_front_path;
final String? card_back_path;
// dalam fromJson:
card_front_path: json['card_front_path'] as String?,
card_back_path: json['card_back_path'] as String?,
```

**2. Fail baru: `lib/features/member/presentation/widgets/member_card_layout.dart`**
— port pure Dart punya `layout.js`:

```dart
class CardLayout {
  static const cardW = 638.0, cardH = 1004.0;
  static const photoCx = 320.0, photoCy = 428.0, photoR = 130.0;
  // ... semua konstanta dari layout.js

  static double fitSingleLine(String text, {required double fontSize, required double minFontSize, required FontWeight weight, required double maxWidth}) {
    var size = fontSize;
    while (size > minFontSize && _measure(text, size, weight) > maxWidth) size -= 1;
    return size;
  }

  static double _measure(String text, double size, FontWeight weight) {
    final painter = TextPainter(
      text: TextSpan(text: text, style: TextStyle(fontSize: size, fontWeight: weight)),
      textDirection: TextDirection.ltr,
    )..layout();
    return painter.width;
  }

  // layoutName() — port wrap()+fitSingleLine gabungan, pulangkan {fontSize, lines: [{text, baselineY}]}
  // layoutOrganization() — port fitSingleLine, pulangkan {fontSize, boxTop}
  // layoutMemberNo() — port fitSingleLine, pulangkan {fontSize, topY}
}
```

**3. `lib/features/member/presentation/member_card_screen.dart`** —
REWRITE PENUH `_MemberCard` widget:

```dart
class _MemberCard extends StatefulWidget {
  // ... flip state via AnimationController
}

class _MemberCardState extends State<_MemberCard> with SingleTickerProviderStateMixin {
  late final AnimationController _flipController;
  bool _showBack = false;

  @override
  void initState() {
    super.initState();
    _flipController = AnimationController(vsync: this, duration: const Duration(milliseconds: 700));
  }

  void _toggle() {
    setState(() => _showBack = !_showBack);
    if (_showBack) {
      _flipController.forward();
    } else {
      _flipController.reverse();
    }
  }

  @override
  Widget build(BuildContext context) {
    return GestureDetector(
      onTap: _toggle,
      child: AspectRatio(
        aspectRatio: CardLayout.cardW / CardLayout.cardH, // 638/1004
        child: AnimatedBuilder(
          animation: _flipController,
          builder: (context, child) {
            final angle = _flipController.value * math.pi; // 0 → pi
            final isBack = angle > math.pi / 2;
            return Transform(
              alignment: Alignment.center,
              transform: Matrix4.identity()
                ..setEntry(3, 2, 0.0015) // perspective
                ..rotateY(angle),
              child: isBack
                  ? Transform(
                      alignment: Alignment.center,
                      transform: Matrix4.identity()..rotateY(math.pi), // flip balik supaya tak oglang
                      child: _CardBack(path: card?.card_back_path),
                    )
                  : _CardFront(card: card, qrValue: qrValue),
            );
          },
        ),
      ),
    );
  }
}

class _CardFront extends StatelessWidget {
  // ClipRRect(borderRadius: elliptical ~4.6%/2.9% dari saiz sebenar)
  // Stack:
  //   - background: card.card_front_path != null ? AppImage(fit: cover) : gradient fallback
  //   - LayoutBuilder untuk skala: scale = constraints.maxWidth / CardLayout.cardW
  //   - Positioned foto (bulat, clip)
  //   - Positioned nama (auto-fit, 1-2 baris)
  //   - Positioned kotak organisasi + teks
  //   - Positioned no.ahli
  //   - Positioned QR (qr_flutter QrImageView, size: 95*scale, posisi kanan-bawah)
}

class _CardBack extends StatelessWidget {
  // card_back_path != null ? Image.network(fit: cover) : gradient + teks placeholder
}
```

**Radius elliptical `4.6%/2.9%`:** Flutter `BorderRadius.all(Radius.elliptical(...))`
wujud dalam SDK — guna terus:

```dart
BorderRadius.all(Radius.elliptical(constraints.maxWidth * 0.046, constraints.maxHeight * 0.029))
```

**Buang:** `class _Detail` (grid info) dan `GridView.count` (baris ~190-211
lama) — tiada lagi dalam design baru.

### Checklist Fasa 3

- [x] Tambah `card_front_path`/`card_back_path` ke model
      `member_card_data.dart`
- [x] Buat `member_card_layout.dart` (port `layout.js`)
- [x] Rewrite `_MemberCard` jadi flip widget dgn `Stack`+`Positioned`
      berskala
- [x] Buang grid info lama (`_Detail`, `GridView.count`)
- [x] Ujian widget: flip berfungsi, design background paths diterima
- [ ] Test pada device sebenar dengan organisasi yang ADA
      `card_front_path`/`card_back_path` DAN yang TIADA (fallback gradient)
      — admin panel web boleh set nilai ini untuk test
- [x] `flutter analyze && flutter test`

**Cadangan:** Jangan gabung fasa ini dengan fasa lain dalam satu sesi — ia
paling berisiko (text-measurement logic, animation state, scaling math)
dan patut dapat tumpuan penuh + testing teliti berasingan.

---

## ~~FASA 4 — Bahasa Inggeris~~ — DIGUGURKAN (keputusan user)

Keputusan: abaikan dulu bahasa Inggeris. Infra `AppLocalizations`
(`lib/shared/l10n/`, 4 key sahaja: `appTitle`, `login`, `logout`, `retry`)
kekal tak dipakai — tiada tindakan. Jika kelak nak dihidupkan semula,
rujuk audit asal: ~250-400+ string unik merentasi 60-70+ fail perlu
di-extract — kerja besar yang perlu dipecah berperingkat merentasi
beberapa sesi.

---

## FASA 5 — Latar organic (canvas color + bentuk gradient)

### Keputusan skop (PENTING — baca sebelum mula)

**Pendekatan global (wrap `MaterialApp.builder`) TIDAK BOLEH dipakai** —
disahkan via grep: **27+ skrin** (`referral_screen.dart`,
`products_screen.dart`, `member_dashboard_screen.dart`,
`public_home_screen.dart`, `menu_screen.dart`, dll) set
`Scaffold(backgroundColor: AppColors.pageBackground)` **eksplisit**. Kalau
cuma letak latar organic di belakang `MaterialApp`, ia akan **ditutup**
(occluded) oleh background pekat setiap Scaffold individu.

**Pendekatan betul (scoped, selaras dengan keperluan "especially
frontpage"):** Bina **SATU** widget reusable, pasang terus dalam `body:` 2
skrin yang dikenal pasti "dull" — **dashboard ahli** dan **public home**.
Tak sentuh 25+ skrin lain.

### 1. Tukar token warna (satu baris, kesan global — selamat)

**Fail:** `lib/shared/theme/app_colors.dart:43`

```dart
// SEBELUM
static const Color pageBackground = Color(0xFFF7F9F5);
// LEPAS
static const Color pageBackground = Color(0xFFF2FBF6);
```

Ini automatik kemas kini `scaffoldBackgroundColor`
(`app_theme.dart:86`) dan semua 27+ skrin yang rujuk token — tiada kerja
tambahan.

- [x] Tukar `AppColors.pageBackground` → `#F2FBF6`

### 2. Widget baru: `OrganicCanvasBackground`

**Fail baru:** `lib/shared/widgets/organic_canvas_background.dart`

Keperluan asal yang perlu dipenuhi: canvas #F2FBF6, overlay gradient
mint/aqua lut-sinar, bentuk organik besar lengkung lembut, opacity rendah,
transisi gradient lembut, ringan & performant, berfungsi pada pelbagai
saiz skrin, elak raster image besar, guna CustomPainter/native Flutter
drawing, hormat SafeArea/scrolling sedia ada, kekalkan warna jenama & semua
fungsi UI.

```dart
import 'dart:ui' as ui;
import 'package:flutter/material.dart';
import '../theme/app_colors.dart';

/// Latar hiasan bentuk organik lut-sinar atas kanvas #F2FBF6.
///
/// CustomPainter (bukan raster) — ringan, berskala ikut saiz skrin,
/// tiada interaksi (IgnorePointer dibungkus di sini), opacity rendah supaya
/// tak ganggu kebolehbacaan kandungan di atasnya.
class OrganicCanvasBackground extends StatelessWidget {
  const OrganicCanvasBackground({super.key});

  @override
  Widget build(BuildContext context) {
    return IgnorePointer(
      child: RepaintBoundary(
        child: CustomPaint(
          painter: _OrganicBlobPainter(),
          size: Size.infinite,
        ),
      ),
    );
  }
}

class _OrganicBlobPainter extends CustomPainter {
  @override
  void paint(Canvas canvas, Size size) {
    // Blob 1 — mint, kanan-atas, besar
    _paintBlob(
      canvas, size,
      center: Offset(size.width * 0.85, size.height * 0.08),
      radius: size.width * 0.55,
      colors: [AppColors.movementSoftGreen.withValues(alpha: .10), Colors.transparent],
    );
    // Blob 2 — aqua, kiri-tengah
    _paintBlob(
      canvas, size,
      center: Offset(size.width * 0.05, size.height * 0.40),
      radius: size.width * 0.45,
      colors: [const Color(0xFF8FE0D6).withValues(alpha: .08), Colors.transparent],
    );
    // Blob 3 — hijau jenama, kanan-bawah, halus
    _paintBlob(
      canvas, size,
      center: Offset(size.width * 0.90, size.height * 0.75),
      radius: size.width * 0.40,
      colors: [AppColors.movementGreen.withValues(alpha: .06), Colors.transparent],
    );
  }

  void _paintBlob(Canvas canvas, Size size, {required Offset center, required double radius, required List<Color> colors}) {
    final paint = Paint()
      ..shader = ui.Gradient.radial(center, radius, colors)
      ..maskFilter = const MaskFilter.blur(BlurStyle.normal, 40); // kelembutan tepi
    canvas.drawCircle(center, radius, paint);
  }

  @override
  bool shouldRepaint(_OrganicBlobPainter oldDelegate) => false; // statik, tak perlu redraw
}
```

**Nota prestasi:** `shouldRepaint: false` + `RepaintBoundary` pastikan ia
dilukis SEKALI sahaja dan tak repaint semasa scroll/animasi lain.

- [x] Buat `organic_canvas_background.dart`

### 3. Pasang di 2 skrin

**Fail:** `lib/features/member/presentation/member_dashboard_screen.dart`
— cari `Scaffold(` root (~baris 64), bungkus `body:`:

```dart
Scaffold(
  backgroundColor: AppColors.pageBackground, // kekal, base fallback
  body: Stack(
    children: [
      const Positioned.fill(child: OrganicCanvasBackground()),
      CustomScrollView(/* ...kandungan sedia ada... */),
    ],
  ),
  ...
)
```

**Fail:** `lib/features/public/presentation/public_home_screen.dart` —
sama pola pada `Scaffold.body`.

**Hormat SafeArea/scrolling:** Kerana `OrganicCanvasBackground` cuma
lapisan **paling bawah** dalam `Stack` dan `CustomScrollView`/`ListView`
kekal sebagai child **atas**nya, semua `SafeArea`, `SliverAppBar`, padding
sedia ada **tidak terjejas langsung** — ia purely visual backdrop.

- [x] Pasang di `member_dashboard_screen.dart`
- [x] Pasang di `public_home_screen.dart`
- [ ] Test di pelbagai saiz skrin (iPhone SE kecil, tablet/iPad besar) —
      blob guna peratus `size.width`/`size.height` jadi automatik skala
- [x] `flutter analyze && flutter test`

---

## FASA 6 — Shimmer loading skeleton

Satu-satunya item "frontpage dull/boring" yang dipilih daripada senarai
cadangan (lain: runtuhkan palet shortcut pelangi, satukan 3 implementasi
`SectionHeader`, ganti `_EmptyHint`, animasi fade+slide — semua TIDAK
dipilih, boleh dipertimbang semula kelak jika diminta).

**Fail:** `lib/shared/widgets/skeleton_box.dart` (komen sendiri: "Static
skeleton placeholder... §11.5" — sengaja statik sebelum ini, perlu
diubah).

**Pendekatan:** Guna `AnimatedBuilder` + `LinearGradient` bergerak (tiada
package baharu diperlukan — tak perlu `shimmer` pub package, native
Flutter cukup):

```dart
class SkeletonBox extends StatefulWidget {
  const SkeletonBox({super.key, this.width, this.height, this.radius = 12});
  final double? width;
  final double? height;
  final double radius;

  @override
  State<SkeletonBox> createState() => _SkeletonBoxState();
}

class _SkeletonBoxState extends State<SkeletonBox> with SingleTickerProviderStateMixin {
  late final AnimationController _controller;

  @override
  void initState() {
    super.initState();
    _controller = AnimationController(vsync: this, duration: const Duration(milliseconds: 1400))..repeat();
  }

  @override
  void dispose() {
    _controller.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return AnimatedBuilder(
      animation: _controller,
      builder: (context, child) {
        return Container(
          width: widget.width,
          height: widget.height,
          decoration: BoxDecoration(
            borderRadius: BorderRadius.circular(widget.radius),
            gradient: LinearGradient(
              begin: Alignment(-1 + _controller.value * 3, 0),
              end: Alignment(_controller.value * 3, 0),
              colors: [
                AppColors.surfaceMuted,
                AppColors.paleGreen.withValues(alpha: .5),
                AppColors.surfaceMuted,
              ],
            ),
          ),
        );
      },
    );
  }
}
```

**Nota penting:** `SkeletonBox` dipakai **meluas** (placeholder dalam
`AppImage`, semua skeleton loading state). Perubahan ini **satu fail
sahaja** tapi kesan global — naikkan SEMUA loading state app sekaligus.
Risiko rendah (pure visual), tapi test semua skrin loading (dashboard,
public home, event list, dll) untuk pastikan tiada jank/lag prestasi
(patut okay — hanya 1 `AnimationController` per instance, ringan).

### Checklist Fasa 6

- [x] Rewrite `skeleton_box.dart` dengan animated shimmer
- [x] Hormati `MediaQuery.disableAnimations` (berhenti berdenyut)
- [ ] Test loading state di device sebenar di minima 3-4 skrin berbeza
      (dashboard, public home, event detail, member card)
- [x] `flutter analyze && flutter test`

---

## FASA 7 — Biometric login (TIADA KOD — user test dulu)

**Dapatan audit:** Logik **sudah lengkap 100%** (toggle, auto-login,
prompt one-time, reset on logout). Fail terlibat:

- `lib/core/biometric/biometric_service.dart` — service wrapper
- `lib/features/auth/presentation/widgets/biometric_prompt.dart` — dialog
  "offer enable" selepas login
- `lib/features/auth/application/auth_controller.dart` — providers +
  logic (`biometricSupportedProvider`, `biometricAvailableProvider`,
  `loginWithBiometrics()`, `setBiometricEnabled()`)
- `lib/features/auth/presentation/login_screen.dart` (baris 241-300) — UI
  butang login biometrik
- `lib/features/profile/presentation/profile_screen.dart`
  (`_BiometricSetting`, baris 486-554) — toggle setting

Butang "Log Masuk dengan Face ID/Cap Jari" **sengaja** disorok
(`SizedBox.shrink()`) sehingga:

1. User log masuk **manual** (email+password) sekali
2. Dialog "Aktifkan Face ID?" (`BiometricPrompt.offerEnable`) muncul —
   **terima**
3. Log keluar
4. Kembali ke skrin log masuk → butang sepatutnya **muncul**

**Status:** User akan test urutan tepat di atas pada device sebenar
(simulator kadang tak sokong biometric prompt penuh — guna device fizikal
jika boleh).

**Jika SELEPAS test urutan penuh ia TETAP tak muncul**, kumpul maklumat
ini untuk sesi akan datang:

- Device/OS yang ditest (iOS versi berapa / Android versi berapa)
- Adakah dialog "Aktifkan Face ID?" di langkah 2 muncul langsung?
- Ada error di console/log semasa test?

Punca paling berkemungkinan jika masih gagal:
`BiometricService.isDeviceSupported()` (`biometric_service.dart:40-48`)
**senyap** `catch` semua exception → `return false`. Kalau platform-channel
error (contoh: entitlement Face ID tak di-set di `Info.plist` iOS, atau
config Android tertentu), ia silently disable tanpa log — ini akan jadi
fokus siasatan sesi depan jika perlu.

- [ ] User test urutan penuh di device sebenar
- [ ] Jika masih gagal — catat maklumat di atas, bawa ke sesi akan datang

---

## Susunan cadangan sesi (ikut effort)

| Sesi | Fasa | Anggaran kerja |
|---|---|---|
| 1 | Fasa 1 (quick fix) + Fasa 2 (image crop) | Kecil-sederhana, kebanyakan copy-paste pattern berulang |
| 2 | Fasa 5 (organic background) + Fasa 6 (shimmer) | Sederhana, 2 fail baru + pasang 2 tempat |
| 3 | Fasa 3 (kad ahli) — **BERASINGAN**, paling kompleks | Besar — port logik layout.js, flip animation, auto-fit teks |
| — | Fasa 7 (biometric) | User test dulu, tiada kerja kod melainkan jumpa bug sebenar |

**Jangan gabung Fasa 3 (kad ahli) dengan fasa lain dalam satu sesi.**

---

## Rujukan cepat — fail yang terlibat merentasi semua fasa

```
lib/app.dart                                                    (Fasa 1.1 - rujukan sahaja)
lib/shared/theme/app_theme.dart                                 (Fasa 1.3 optional, Fasa 5)
lib/shared/theme/app_colors.dart                                (Fasa 5)
lib/shared/widgets/app_image.dart                                (Fasa 2 - rujukan pattern)
lib/shared/widgets/skeleton_box.dart                             (Fasa 6)
lib/shared/widgets/organic_canvas_background.dart                (Fasa 5 - fail baru)
lib/features/member/presentation/member_dashboard_screen.dart   (Fasa 1.2, 1.3, 2.3, 5)
lib/features/public/presentation/public_home_screen.dart        (Fasa 1.3, 5)
lib/features/events/presentation/event_detail_screen.dart       (Fasa 2.1)
lib/features/events/presentation/events_screen.dart             (Fasa 2.2)
lib/features/news/presentation/articles_list_screen.dart        (Fasa 2.4)
lib/features/news/presentation/article_detail_screen.dart       (Fasa 2.4)
lib/features/ecommerce/presentation/product_detail_screen.dart  (Fasa 2.5 - optional)
lib/features/member/data/models/member_card_data.dart            (Fasa 3)
lib/features/member/presentation/member_card_screen.dart        (Fasa 3)
lib/features/member/presentation/widgets/member_card_layout.dart (Fasa 3 - fail baru)
lib/core/biometric/biometric_service.dart                        (Fasa 7 - rujukan sahaja)
lib/features/auth/presentation/login_screen.dart                (Fasa 7 - rujukan sahaja)
```

## Rujukan web (baca sahaja, JANGAN ubah)

```
resources/js/Components/MemberCard/layout.js
resources/js/Components/MemberCard/MemberCardFront.vue
resources/js/Components/MemberCard/MemberCardFlip.vue
app/Services/MemberCoreService.php                               (backend sudah siap, tiada ubah)
```

---

## Log pelaksanaan

### Sesi 2 — Fasa 1, 2, 3, 5, 6 (kod siap; tinggal ujian device)

Semua fasa kod telah dilaksanakan dan `flutter analyze` + `flutter test`
lulus (98+ ujian). Yang tinggal hanyalah pengesahan visual pada device
sebenar (ditanda `[ ]` di atas).

**Penyimpangan / keputusan yang dibuat semasa pelaksanaan:**

1. **Fasa 1.3** — Daripada hardcode `Radius.circular(24)` di 3 tempat,
   ditambah token baharu `AppRadius.banner` (`app_theme.dart`) dan digunakan
   di ketiga-tiga banner. `AppRadius.hero` (32) tidak diubah, jadi kad
   program/CTA/menu kekal seperti asal.

2. **Fasa 2.3** — Imej kad program dashboard bertukar daripada `Expanded`
   (mengisi tinggi kad) kepada `AspectRatio(4/5)`. Ini menambah tinggi
   kad secara semula jadi, jadi tinggi senarai mendatar dinaikkan
   **230 → 320** untuk mengelakkan limpahan (overflow). Jika nampak terlalu
   tinggi di device, laras nilai 320 atau kecilkan blok teks di bawah kad.

3. **Fasa 2.4** — Galeri artikel: `SizedBox(width: 260)` diganti dengan
   `AspectRatio(4/5)`. Lebar setiap item kini dikira daripada tinggi strip
   (180 → ~144px), jadi item kelihatan lebih sempit tetapi poster tidak
   terpotong.

4. **Fasa 5** — Widget `OrganicCanvasBackground` menggunakan gradient
   radial ke alfa 0 (bukan `MaskFilter.blur`) kerana lebih murah pada
   peranti lama dan tiada perbezaan visual ketara. Warna aqua ditambah
   sebagai token `AppColors.organicAqua` (peraturan repo: tiada `Color()`
   hardcode di luar `app_colors.dart`). Latar dipasang pada **kedua-dua
   keadaan loading/error** juga (bukan hanya `data`) supaya tiada "kilat"
   latar putih semasa memuat.

5. **Fasa 6** — `SkeletonBox` kini animated. **Nota penting untuk ujian:**
   `AnimationController.repeat()` menyebabkan `tester.pumpAndSettle()`
   tidak akan reda jika skeleton kelihatan. Dalam ujian baharu, guna
   `tester.pump(Duration(...))` berulang, bukan `pumpAndSettle`, apabila
   skrin berpotensi menunjukkan skeleton/imej rangkaian.

6. **Fasa 3 (kad ahli)** — Warna design web ditambah sebagai token di
   `app_colors.dart` (`cardNameInk`, `cardOrgPill`, `cardMemberNoInk`,
   `cardPhotoPlaceholder`, `cardFallbackGradient`, `cardBackGradient`,
   `cardShadow`). Bayang diletakkan **di luar** `ClipRRect` (jika di dalam,
   ia dipotong). Kad dihadkan kepada lebar maksimum 420px dan ditengahkan
   supaya kekal berkad pada tablet.

   Ujian lama `MemberCardScreen renders card details and QR code` dikemas
   kini mengikut design baharu: nama UPPERCASE (`AHMAD BIN ALI`), nombor
   ahli tanpa label, dan grid butiran dibuang (sebagaimana design web).
   Dua ujian baharu ditambah: flip ke muka belakang, dan penerimaan
   `card_front_path`/`card_back_path`.

7. **Fasa 2.5 (produk)** — Tidak dilaksanakan kerana ditanda "optional,
   tanya dulu". Beritahu jika mahu hero imej produk turut ditukar ke 4:5.

**Fail baharu:**
- `lib/shared/widgets/organic_canvas_background.dart`
- `lib/features/member/presentation/widgets/member_card_layout.dart`

**Fail diubah (utama):**
- `lib/shared/theme/app_colors.dart` (token latar, pattern, kad)
- `lib/shared/theme/app_theme.dart` (`AppRadius.banner`, `bottomNavHeight`)
- `lib/shared/widgets/skeleton_box.dart` (shimmer)
- `lib/features/member/presentation/member_dashboard_screen.dart`
- `lib/features/public/presentation/public_home_screen.dart`
- `lib/features/events/presentation/event_detail_screen.dart`
- `lib/features/events/presentation/events_screen.dart`
- `lib/features/news/presentation/articles_list_screen.dart`
- `lib/features/news/presentation/article_detail_screen.dart`
- `lib/features/member/data/models/member_card_data.dart`
- `lib/features/member/presentation/member_card_screen.dart`
- `test/features/member_core_test.dart`
