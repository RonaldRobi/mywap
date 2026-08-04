# Project Status — myWAP (MyMarhalah)

> Last updated: 2026-08-04 (Session 7: Transaction UX + Donor Tracking + Form Builder + Mail Config)
> Stack: Laravel 12 + Vue 3 (Inertia) + TailwindCSS 3 + SQLite (dev)

---

## Tech Stack

| Layer | Technology |
|---|---|
| Backend | Laravel 12, PHP 8.2+ |
| Frontend | Vue 3 + Inertia.js v2, TailwindCSS 3 |
| Database | SQLite (dev), MySQL ready |
| Auth | Laravel Breeze (Inertia Vue), Spatie Laravel Permission |
| Assets | Vite 7, Ziggy (client routes) |
| Queue | Database driver |

---

## ✅ Features Siap

### Authentication & User Management
- Single login form: auto-detect email (admin) vs IC number (member) — buang role selection
- Public registration form (guest access) — step 1: isi maklumat, step 2: bayaran
- IC auto-detect: parse DOB + gender dari IC, infer organisasi (PKPIM/ABIM/WADAH), papar yuran
- Cawangan dropdown — filter ikut organisasi dikesan, fallback "Tidak Berkenaan"
- Auto-generate member_no masa daftar (prefix ikut org: P → PKPIM, A → ABIM, W → WADAH)
- No password semasa daftar — password ditetapkan semasa log masuk kali pertama
- First-time login flow: IC → email → OTP → set password → auto login
- Forgot password + forgot ID digabung: IC → verify DOB → tunjuk masked email + member_no → hantar reset link
- OTP via Resend.com, guna DB template (editable dari superadmin)
- Profile CRUD + onboarding (complete-profile) flow — gender, marital_status, emergency_contact, position, topics added
- Age-based auto-transition between NGO tiers (daily cron: `app:process-age-transitions`) — update prefix `member_no` bila transit
- Member directory (opt-in, searchable)
- Profile journey/timeline page

### Dashboard
- Landing page (public: banners + infaq + articles)
- Member dashboard (banners, fee status, events, library, news, usrah)
- Admin dashboard (analytics, charts, CSV export)
- Superadmin settings (system logo, splash screen)

### Information Hub
- Announcements (pinned, CRUD)
- Digital library (file upload, cover image, categories)
- News system (categories, reactions "like", comments)
- Articles (public listing/detail, reactions, comments)

### Events
- CRUD events (admin) + RSVP (member)
- QR code attendance scanning
- Attendance report (admin) + print
- Google Calendar link integration

### Infaq (Donations)
- Campaign CRUD (superadmin)
- Public listing with calendar-date URLs
- Donation form: BayarCash FPX redirect, anonymous toggle, prayer message
- Recurring donations via BayarCash Direct Debit (bulanan/mingguan/tahunan)
- Success/thank-you page
- Donor tracking & deduplication (lihat seksyen Donor Tracking di atas)

### Referral System (Ahli Jemput)
- Public referral link: `/daftar?ref={member_no}` — auto-fill "Dirujuk Oleh" dalam registration form
- Referral page untuk ahli (`/member/referral`) — share link + QR code (SVG) + stats
- Share buttons: WhatsApp, Telegram, Facebook, X (Twitter), Email, Salin Pautan
- QR code generation via `simplesoftwareio/simple-qrcode` (SVG format, no Imagick needed)
- Stats tracking: jumlah dijemput, aktif, pending
- Senarai ahli yang dijemput (nama, no ahli, organisasi, tarikh daftar, status)
- `referred_by_user_id` FK pada users table — track siapa rujuk siapa

### Email Notifications (Registration)
- 4 email templates guna DB `email_templates` (editable dari superadmin):
  - `registration_received` — hantar ke user lepas submit form (sebelum bayar)
  - `registration_activated` — hantar ke user lepas payment success (welcome + login link)
  - `new_member_alert` — hantar ke admin contact lepas ahli baru daftar + bayar
  - `form_invitation` — hantar jemputan borang ke emel penerima (dengan button "Buka Borang")
- Guna `AppServiceProvider::configureMailFromSettings()` — mail runtime config (resend/smtp/log) dari DB

### E-Commerce
- Products CRUD (categories, images, search, stock)
- Order management (checkout, status tracking)
- Policies: member buy, admin manage

### Finance (Fee Module — One-Stop Center ✅)
- **FeeService v2** — perpetual life_member/exempted, cleanup unpaid on status change
- **FeeStatus Enum** — PHP 8.1 backed enum, cast in MembershipFee model
- **CSV Import Yuran** — upload → preview → process → summary report. Match by IC + (member_no OR original_member_no)
- **Export (CSV/Excel/PDF)** — export filtered fee list in 3 formats
- **Manual Payment Entry** — admin form with proof file (wajib untuk anti-ketirisan)
- **Receipt PDF** — downloadable receipt per payment
- **Activity Log (Audit Trail)** — log every action: life_member toggle, exempt, CSV import, manual payment, fee update
- **Payment Source Display** — history shows source: "Import CSV", "Manual (Admin)", "Dummy Payment"
- **Duplicate Reference Detection** — reject manual payment with existing reference
- **Generate Fees Button** — UI trigger for `fees:generate` artisan command
- **Void/Reverse Payment** — admin can void payment → sets `payment.status=voided`, resets fee to unpaid
- **Reconciliation Report** — expected vs collected vs outstanding with collection rate
- **Monthly Collection Chart** — bar chart of monthly fee collection
- **Clickable Stats Cards** — click stats to auto-filter table by status
- **ToastNotification Component** — reusable, success/error/info, auto-dismiss
- **ConfirmDialog Component** — reusable, replace browser `confirm()`
- **Esc Key** — close all modals/panels with Escape key
- **Loading Skeleton** — animated skeleton bars replace "Memuatkan..."
- **Yuran Seumur Hidup** — label rename, Superadmin-only access, hidden in dropdown
- **Pengecualian Yuran** — Superadmin-only, hidden in dropdown
- **Superadmin Org Filter** — dropdown to scope view by organization
- **Org-Scoped Security** — `authorizeOrg()` helper in all methods, cross-org access blocked

### Admin Finance Dashboard ✅
- **Route:** `/admin/finance` — comprehensive financial overview
- **Summary Cards** — total revenue + breakdown by source (Yuran Keahlian, Infaq)
- **Chart.js Bar Chart** — monthly revenue trend
- **Chart.js Donut Chart** — revenue breakdown by source
- **Merged Transaction Table** — unified view of payments + infaq_donations
- **Clickable Names** — opens slide-over with member's complete financial history
- **Export (PDF/Excel/CSV)** — comprehensive PDF report with cover + stats + table + signature
- **Filter by Year + Organization** — superadmin scope

### Transaction Detection & Management ✅
- **Search box** — cari transaksi ikut nama ahli, emel, rujukan, atau description (Admin & Superadmin)
- **Date range filter** — Dari/Hingga untuk sempitkan julat tarikh
- **Type badges berwarna** — Yuran (biru), Infaq (hijau), Pesanan (ungu) untuk kenal pasti jenis transaksi sepintas lalu
- **Status label BM** — Menunggu / Berjaya / Gagal / Dipulangkan (ganti value raw)
- **Export CSV** — button pada kedua-dua halaman (`admin.transactions.export.csv`, `superadmin.transactions.export.csv`), hormat semua filter aktif
- **BayarCash callback idempotency** — callback pendua tidak diproses semula jika payment sudah `successful`/`failed`/`refunded` (elak double-count, duplikasi donasi)

### Donor Tracking & Management ✅
- **Donor model + table** — rekod penderma unik merentas semua kempen infaq
- **Auto-deduplikasi** — `DonorService::findOrCreate()` padankan guna `user_id` → `email` → `phone`
- **Statistik penderma** — `total_donated`, `donation_count`, `last_donated_at` di-increment setiap donation disahkan (termasuk kitaran recurring)
- **Halaman Senarai Penderma** (`/admin/donors`) — search (nama/emel/telefon), sort (tertinggi/terkini/kerap), 4 summary cards
- **Halaman Detail Penderma** (`/admin/donors/{donor}`) — profil, statistik (jumlah/disahkan/kerap/berkala), sejarah sumbangan penuh dengan pautan ke kempen
- **Backfill command** — `php artisan app:backfill-donors` untuk populate donor dari data InfaqDonation sedia ada
- **Integrasi** — `InfaqController`, `BayarCashController` (authorization, recurring transaction) auto-link `donor_id` ke setiap donation

### Form Builder (Borang) ✅
- **Pengganti Google Forms** — bina borang tersuai terus dalam sistem (selesaikan aduan "GForm terlampau banyak")
- **9 jenis soalan** — text, textarea, number, email, phone, date, select, radio, checkbox
- **Konfigurasi soalan** — options, required, placeholder, help_text, sort order (move up/down), add/remove
- **Attach ke Program** — `event_id` pada form untuk kaitkan borang dengan event/program tertentu
- **Attach ke Pertubuhan** — `organization_id` (Superadmin boleh assign, Admin auto-scoped)
- **Public link** — `/borang/{token}` (share_token 32-char rawak), tak perlu login untuk isi
- **Emel penerima** — senarai `recipient_emails` (JSON), hantar borang terus ke emel AJK/penyertaan via `FormInvitationNotification` (Resend/SMTP)
- **Response viewer** — `/admin/forms/{form}/responses` — jadual semua jawapan paginated
- **Export CSV** respons — `admin.forms.export`
- **UI** — `Admin/Forms/Index` (senarai + Jawapan/Hantar/Edit/Padam), `Admin/Forms/Builder` (drag-reorder, dynamic fields), `Admin/Forms/Responses`, `Forms/Public` (public submission)
- **Sidebar** — link "Borang" bawah seksyen Kandungan (Admin/Superadmin)

### Mail Configuration from Superadmin Panel ✅
- **Kaedah penghantaran** — dropdown `mail_mailer`: `resend` | `smtp` | `log` (ganti `MAIL_MAILER` dalam `.env`)
- **SMTP fields** — host, port, username, password (encrypted cast), encryption (TLS/SSL/Tiada)
- **Resend API key** — password field, kekal jika dikosongkan
- **From address/name** — `mail_from_address` / `mail_from_name`
- **Test email button** — hantar emel ujian ke sebarang emel untuk sahkan konfigurasi
- **Dynamic config** — `AppServiceProvider::configureMailFromSettings()` baca dari DB setiap boot — terpakai untuk request + queued mail (tak perlu restart worker)

### Superadmin Tools

### Facilities Booking
- Facility CRUD (admin)
- Member browse + book time slots
- Admin approve/reject bookings

### Usrah Groups ✅
- Admin create/edit/delete groups + assign members (max 1 naqib per group)
- Member view own group + attendance history
- Naqib log attendance (hadir/tidak_hadir/uzur) via modal with date picker
- Meeting day select (dropdown — Isnin–Ahad), is_active toggle
- Attendance persisted in `usrah_attendances` table (unique per user per session date)
- Duplicate usrah loading logic extracted to `LoadUsrahForUser` action
- Authorization via `UsrahGroupPolicy` (viewAny, create, update, delete, assignMembers, logAttendance)
- Full test coverage (10 tests) for CRUD, assign, attendance

### Broadcast Messages
- Admin compose + send targeted broadcasts
- Targets: all members, unpaid fees, specific usrah group
- Queued delivery via `SendBroadcastJob`

### Superadmin Tools
- Organization CRUD (name, slug, theme, logo, age range, sort order)
- Fee management per organization
- System-wide settings (logo, splash screen config)
- Dashboard banner management (with display order)
- Transaction viewer + status update (cross-organization)
- Member import (Excel/CSV chunked processing) — header mapping, auto DOB+gender dari IC, validation & error reporting
- Infaq management across orgs

### Public / Shared
- Article listing + detail (public)
- Infaq listing + donate (public)
- Social share previews (OG tags for news, articles, infaq, events)
- PWA: service worker, manifest, install prompt
- Splash screen (configurable via superadmin)
- Dynamic NGO theming (PKPIM/ABIM/WADAH/management)

---

## 🔶 Features Separuh Siap

| Feature | Status | Notes |
|---|---|---|
| E-Commerce Payment | Basic flow done, no payment gateway | Orders stored & status tracked, but payment redirect not connected (unlike infaq which has ToyyibPay) |
| Member Import UI | Import endpoints exist (`importStart/Chunk/Finish`), but front-end integration minimal | Excel import works backend-side |
| Notification UI | Backend DB notifications + `markAllRead` done, but member dashboard notification display may be incomplete | NotificationController exists |
| Profile Timeline | Page exists, content is basic | Shows transition history + basic info |
| E-Commerce Categories | CRUD exists, integration with product listing functional | Products assignable to categories |

---

## 🔌 API / Service Integration

| Service | Status | Details |
|---|---|---|
| ToyyibPay | ✅ Integrated | Infaq donation + fee payment redirect |
| Maatwebsite Excel | ✅ Integrated | Member import, CSV export |
| Spatie Permission | ✅ Integrated | 3 roles: Superadmin, Admin, Member |
| Laravel Sanctum | ✅ Integrated | API token auth available |
| Laravel Queue (DB) | ✅ Integrated | Broadcast, import, notifications |
| Ziggy | ✅ Integrated | Client-side route helper |
| Simple QRCodes | ✅ Integrated | Event attendance QR generation |
| html2canvas | ✅ Integrated | Member card download as image |
| VueQuill | ✅ Integrated | Rich text editor (news, info hub) |
| Amazon S3 | 🔧 Configured (inactive) | `.env` has AWS placeholders |
| Redis | 🔧 Configured (inactive) | `.env` has Redis config |
| Mail | ✅ Resend (`resend.com`) | API key + from address configurable via Superadmin dashboard. Templates editable from panel. |

---

## 🗄️ Database Schema (133 migrations)

### Core
- `users` — name, email, ic_number, password, phone, dob, gender, marital_status, current_organization_id, branch_id, referred_by_user_id, profile_completed_at, education_level, profession, industry, member_no, wadah_state, address fields, postcode, city, state, emergency_contact_name, emergency_contact_phone, position, topics, legacy fields
- `organizations` — name, slug, color_theme, logo_path, fee_amount, min_age, max_age, sort_order
- `branches` — organization_id, name, state, address, contact, logo, is_active

### Auth
- `permissions`, `roles`, `model_has_roles`, `model_has_permissions`, `role_has_permissions`
- `personal_access_tokens` (Sanctum)

### Content
- `announcements` — org-scoped, is_pinned, published_at
- `library_items` — file_path, cover_image_path, category
- `news_categories` — name, slug, icon, display_order, is_active
- `news_posts` — author, org, category, title, slug, content, cover, is_published, published_at
- `news_post_reactions`, `news_post_comments`
- `articles`, `article_comments`, `article_reactions`

### Events
- `events` — org, title, slug, type, location/link, start/end time, featured_image, attendance_token (soft deletes)
- `event_rsvps` — user, event, status, attended_at

### Infaq
- `infaq` — org, title, slug, type, target_amount, collected_amount, is_active, display_order
- `infaq_donations` — infaq_id, user_id, **donor_id**, amount, status, donor info, prayer_message, is_anonymous, wants_updates

### Donors
- `donors` — name, email, phone, user_id, total_donated, donation_count, last_donated_at

### Forms (Borang)
- `forms` — organization_id, event_id, title, slug, description, is_active, allow_public, share_token, header_image_path, **recipient_emails** (JSON, soft deletes)
- `form_questions` — form_id, label, type, options (JSON), required, placeholder, help_text, sort_order
- `form_responses` — form_id, user_id (nullable), respondent_name/email/phone, submitted_at
- `form_answers` — form_response_id, form_question_id, value (unique per response+question)

### E-Commerce
- `categories` — name, description
- `products` — name, desc, price, stock, category_id, organisasi_id, image, status
- `orders` — user_id, organisasi_id, total, status, tracking_no
- `order_items` — order_id, product_id, quantity, price

### Finance
- `payments` (polymorphic) — user_id, payable_type, payable_id, amount, status, reference, description, **proof_path**, **uploaded_by** (anti-ketirisan)
- `campaigns` — org, title, slug, target_amount, current_amount, status
- `membership_fees` — user_id, organization_id, year, amount, status (FeeStatus enum: unpaid/paid/exempted/life_member), paid_at, payment_id, notes
- `fee_imports` — user_id, year, total_rows, success_count, skip_count, csv_file, proof_file, errors (json) — track CSV import batches
- `activity_logs` — user_id, **organization_id**, target_type, target_id, action, description, old_values, new_values — audit trail untuk semua tindakan kewangan

### Facilities
- `facilities` — org, name, description, location, type, price_per_unit, capacity, image, is_active
- `facility_bookings` — facility_id, user_id, start/end datetime, total_price, booking_status, payment_status, admin_remarks

### Groups & Comms
- `usrah_groups` — org, name, meeting_day, meeting_time
- `usrah_group_user` — pivot: is_naqib, joined_at
- `broadcast_messages` — org, title, content, target_criteria, usrah_group_id, sent_at

### Postcode
- `postcodes` — postcode (PK), city, state, country (auto-detect lokasi dari poskod)

### System
- `app_settings` — singleton: system_logo, splash, admin_contact_email/phone, resend_api_key, gemini_api_key, mail_from_address/name, **mail_mailer**, **mail_smtp_host/port/username/password/encryption**
- `otp_codes` — user_id, code (6-digit), purpose (login|email_verify), expires_at, used_at
- `email_templates` — key (unique), subject, body (with {{name}} {{code}} {{purpose}} placeholders)
- `membership_fees` — user_id, organization_id, year, amount, status (paid/unpaid/exempted/life_member), paid_at, payment_id, notes (tracking yuran tahunan)
- `dashboard_banners` — org, title, image, is_active, display_order
- `user_transition_histories` — user, from_org, to_org, transitioned_at
- `organization_positions` — org, name, display_order
- `notifications` (Laravel native), `cache`, `jobs`, `sessions`

---

## 📁 Components / Pages

### Layouts (3)
- `AppLayout.vue` — Main shell, dynamic NGO theming, sidebar, bottom nav, splash, PWA
- `AuthenticatedLayout.vue` — Breeze default
- `GuestLayout.vue` — Minimal guest layout

### Components (21)
- `ApplicationLogo`, `AppSplashScreen`, `PwaInstallPrompt`
- `CampaignInfaqCard`, `LibraryGrid`, `SocialShareButtons`, `ExportCsvButton`, `AnnouncementsBoard`
- UI: `Modal`, `Dropdown`, `Checkbox`, `TextInput`, `InputLabel`, `InputError`, `PrimaryButton`, `SecondaryButton`, `DangerButton`, `NavLink`, `ResponsiveNavLink`, `DropdownLink`
- `ui/`: `AccernityBackground`, `AccernityCard`, `AuroraBackground`, `RainbowButton`

### Pages (~50)

| Group | Pages |
|---|---|---|
| Auth | Landing (single login form + first-time + forgot-id inline), Register, RegisterPayment, ForgotPassword, ResetPassword, ConfirmPassword, VerifyEmail |
| Member | Dashboard, CompleteProfile, Card, Announcements, Library, InformationHub, InfaqShow, Financial/Overview, Referral |
| Admin | Dashboard, BranchManage, Broadcasts, FacilitiesManage, FacilityBookings, InfoManage, InformationHubManage (stats bar, simplified table, slide-over panel), LibraryManage, Transactions, ArticleManage |
| Superadmin | SystemSettings (mailer config + Resend/SMTP + test email + admin contact), Transactions, InfaqManage, BannerManage, LogoSettings, Fees, OrganizationManage |
| Admin | EmailTemplates (subject + body + placeholder guide) |
| Admin | **Forms/Index**, **Forms/Builder**, **Forms/Responses**, **Donors/Index**, **Donors/Show** |
| Public | Welcome, Landing, Dashboard, **Forms/Public** (borang submission) |
| Ecommerce | Products/Index, Products/Show, Products/Create, Products/Edit, Categories/Index, Categories/Create, Categories/Edit, Orders/Index, Orders/Show |
| Events | Index, AdminAttendance, ShowQr, AttendanceSuccess |
| Articles | Index, Show |
| Infaq | Index, Show, Donate, Success |
| Facilities | Index, Show |
| Info | Index, Show |
| Directory | Index |
| Profile | Edit, Show, Partials/(3) |
| Usrah | AdminManage, MyGroup |

---

## 📂 App Structure

| Layer | Count |
|---|---|
| Controllers (main) | **42** | (AdminFinanceController, MemberFeeController, DonorController, FormController added) |
| Auth Controllers | 9 |
| Models | **63** | (Donor, Form, FormQuestion, FormResponse, FormAnswer added) |
| Policies | 3 (Product, Category, Order) |
| Middleware | 2 (HandleInertiaRequests, EnsureProfileIsComplete) |
| Jobs | 2 (ProcessMembersImport, SendBroadcastJob) |
| Notifications | **9** | (AnnouncementPublished, FeeReminder, FormInvitation, GeneralBroadcast, MemberTransition, NewMemberAlert, OtpEmail, RegistrationActivated, RegistrationReceived) |
| Events | 1 (UserOrganizationTransitioned) |
| Listeners | 1 (LogTransitionAndNotify) |
| Console Commands | **7** | (ProcessAgeTransitions, GenerateFees, BackfillDonors, ImportAbim, ImportPkpim, NotifyAnnualFees, ResetSequences) |
| Form Requests | 2 (ProfileUpdate, Login) |
| Imports | **2** | (MembersImport, **FeeImport**) |
| Services | **4** | (FeeService, OtpService, DonorService, BayarCashService) |
| Seeders | **25** | (+ **FeeDemoSeeder**) |
| Sample CSVs | 1 |
| Blade Views | **11** | (7 emails incl. form-invitation, 4 exports) |
| **Enums** | **1** | (**FeeStatus**) |
| Vue Components | **31** | (**ToastNotification**, **ConfirmDialog** added) |
| Vue Pages | **100** | (Forms/Index, Forms/Builder, Forms/Responses, Donors/Index, Donors/Show, Forms/Public added) |

---

## 📝 Known TODO/FIXME Comments

| File | Line | Note |
|---|---|---|
| `Admin/BranchManage.vue` | 246 | Phone input placeholder `"03-XXXX XXXX"` — bukan TODO sebenar |

Codebase bersih — tiada TODO/FIXME/HACK aktif dalam source code.

### Planned (Next)
- Payment Gateway architecture per org (Bayarcash/ToyyibPay/Billplz — resolver interface)
- Dual-role popup: bila user ada Admin + Member roles, bagi pilih nak log masuk sebagai mana
- Form Builder enhancements: conditional logic (tunjuk/sembunyi soalan), file upload soalan, drag-and-drop builder

---

## ⚙️ Roles & Permissions

| Role | Permissions |
|---|---|
| Superadmin | All (gate bypass) |
| Admin | Member permissions + create/edit/delete members, events, payments, documents, settings |
| Member | Read-only: view members, events, payments, documents |

---

## Architecture Notes

- **Multi-tenancy:** `OrganizationScope` global scope on User, UsrahGroup, Announcement, BroadcastMessage, LibraryItem — scoped by `current_organization_id`
- **Age transition:** Daily cron moves members between NGO tiers based on age ranges configured in `organizations.min_age / max_age`
- **Gate before():** Superadmin granted all abilities via `Gate::before()` in `AppServiceProvider`
- **File storage:** Local disk, paths normalized via `NormalizesStoragePath` trait
- **InformationHubManage UX:** Stats bar (4 cards), simplified table (4 columns), row actions dropdown (⋮), slide-over panel (tab-based, edit mode, Escape to close)
- **Payment flow:** ToyyibPay redirect-based — user sent to ToyyibPay, callback updates payment status
- **Import ahli:** Header mapping (`nama`, `ic`, dll) — auto DOB + gender dari IC. Validation: IC wajib, minimum 6 digit. Skipped rows dilaporkan sebagai error. Auto-generate `member_no` dengan prefix ikut org.
- **No keahlian stacking:** `original_member_no` kekal seumur hidup. `member_no` di-prepend prefix org baru bila transit (P00001 → AP00001 → WAP00001). Ahli tanpa transit kekal asal.
- **Stats Ahli:** `profile_completed_at` tentukan aktif/tidak aktif.
- **OTP auth flow:** Hanya untuk first-time setup. Guna `otp_codes` table (6-digit, 5 min expiry). Hantar via Resend. Bukan untuk login harian — lepas setup, guna password macam biasa.
- **Single login form:** Satu input identifier (auto-detect email vs IC). Backend `LoginRequest::authenticate()` try email dulu, then IC/member_no. First-time user yang belum login dapat error khas "Guna Log Masuk Kali Pertama".
- **CSRF dengan fetch():** `HandleInertiaRequests` share `csrf_token` dalam props. Vue baca dari `usePage().props.csrf_token` dan hantar sebagai `X-CSRF-TOKEN` header.
- **Dynamic mail config:** `AppServiceProvider::configureMailFromSettings()` baca `app_settings` (resend_api_key, mail_from_*, **mail_mailer**, **mail_smtp_\***) dan set config runtime setiap boot. Menyokong mailer: `resend` / `smtp` / `log`. Terpakai untuk request biasa dan queued mail.
- **FeeService pattern:** Service layer pisahkan logic yuran dari controller. `getStatus()` cached by year, `isLifeMember()`/`isExempted()` check across ALL years (perpetual). `markAsPaid/lifeMember/exempted` cleanup unpaid records. `getDueCount()` exclude life_member + exempted across years.
- **FeeStatus Enum:** PHP 8.1 backed string enum (`unpaid/paid/exempted/life_member`). Cast dalam MembershipFee model. Pencegahan data corruption via type safety.
- **CSV Import Flow:** Upload → preview (check each user: not_found/exempted/already_paid/ready) → confirm → process in batches. Setiap row match guna IC + (member_no OR original_member_no). Org-scoped.
- **Anti-Ketirisan:** Setiap Payment ada `proof_path` + `uploaded_by`. Manual payment WAJIB upload proof. CSV import optional proof. Slide-over tunjuk amaran ⚠ kalau tiada bukti.
- **Activity Log (Audit Trail):** `activity_logs` table with `organization_id`. Log setiap action dengan `old_values` + `new_values`. Track "siapa buat apa, bila, terhadap siapa".
- **Org-Scoped Security:** All controller methods use `authorizeOrg()` helper — Superadmin bypass, Admin scoped to `current_organization_id`. 6 security gaps fixed (downloadReceipt, feeDetail, updateMemberFee, reversePayment, previewImport, processImport).
- **Reusable Vue Components:** `ToastNotification.vue` — stacked toasts with auto-dismiss + animations. `ConfirmDialog.vue` — promise-based modal confirm with variant support (danger/primary). Kedua-dua boleh guna di mana-mana component.
- **Admin Finance Dashboard:** Merged view of `payments` + `infaq_donations` → unified revenue dashboard. Chart.js untuk monthly bar + source donut charts. Comprehensive PDF report (cover → stats → table → signature). Clickable member names open financial history slide-over.
- **Email templates:** Table `email_templates` dengan key unik. Subject + body stored. Support placeholders `{{name}}`, `{{code}}`, `{{purpose}}`. Boleh edit dari superadmin panel.
- **BayarCash idempotency:** Callback & direct-debit authorization semak status terminal (`successful`/`failed`/`refunded`) sebelum proses — callback pendua dari gateway tidak akan double-count atau duplikasi donasi/fee.
- **Donor deduplication:** `DonorService::findOrCreate()` padankan `user_id` → `email` → `phone` (prioriti berurutan). `incrementDonor()` kemas kini total_donated/donation_count/last_donated_at bila donation disahkan.
- **Form Builder:** Borang guna `share_token` (32-char rawak) untuk public access — URL `/borang/{token}`. Responses disimpan secara berasingan (`form_responses` + `form_answers`) untuk fleksibiliti query & export. `recipient_emails` (JSON) untuk hantar jemputan borang via email.
- **Mailer config dari DB:** `app_settings.mail_mailer` menentukan driver aktif (resend/smtp/log). SMTP credentials disimpan encrypted cast. Butang test email guna `Mail::raw()` untuk sahkan konfigurasi sebenar.
