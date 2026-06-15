# Roadmap — myWAP (MyMarhalah)

> Last updated: 2026-06-13 (Session 5: Finance One-Stop Center)
> Use this file to record architecture decisions, task status, and future plans.

---

## Status Semasa

- ✅ Fee module complete — lihat `docs/project-status.md`

## Architecture Decisions (Session 5: Fee Module)

- **FeeStatus Enum**: PHP 8.1 backed string enum (`unpaid/paid/exempted/life_member`). Cast dalam MembershipFee. Ganti raw string `status` column.
- **Exempted & Life Member perpetual**: Bukan tahunan. `getStatus()` check `isLifeMember()`/`isExempted()` across ALL years first, baru check per-year. `markAsExempted()` tak guna parameter `year`.
- **Cleanup unpaid on status change**: Bila admin toggle life_member/exempted, semua `unpaid` records auto-deleted untuk user tersebut. Elak legacy records.
- **CSV Import matching**: Match guna `ic_number` AND (`member_no` OR `original_member_no`). Untuk handle age transition stacking.
- **Payment Source Detection**: Guna reference prefix untuk detect sumber: `DUMMY-` = dummy, `CSV-` = import CSV, `MANUAL-` = manual admin.
- **authorizeOrg() helper**: Standard method untuk org-scoping. Superadmin bypass, Admin scoped. Digunakan oleh 8+ controller methods.
- **ActivityLog organization_id**: Setiap log ada `organization_id` untuk filter by org.
- **Finance Dashboard merge**: `payments` (Yuran) + `infaq_donations` (Infaq) → unified view. Chart.js untuk charts.
- **ToastNotification + ConfirmDialog**: Reusable components — boleh guna di mana-mana page lain.
- **Life Member label**: Hanya Superadmin yang boleh toggle life_member/exempted. Admin tak nampak button. Label "Yuran Seumur Hidup" ganti "Life Member".
- **Table column order**: Prioritise member_no → name → ic for quick identification.
- **Clickable names**: Dalam fee table dan finance dashboard, nama member boleh klik → buka slide-over financial history.

- **DOB auto-fill from IC**: `User::parseDobFromIc()` extract YYMMDD from IC number. Jantina (last digit IC: ganjil=lelaki, genap=perempuan).
- **Postcode auto-detect**: Table `postcodes` with seed data (1299 rows). Client-side watch triggers API call. Falls back gracefully if not found.
- **Membership fee pisah dari Payment polymorphic**: Table `membership_fees` untuk track status yuran tahunan. Payment sedia ada kekal untuk transaksi kewangan. FeeService sebagai middleware logic.
- **Life member**: Status `life_member` di `membership_fees` bypass semua cek tahunan. Admin set manually.
- **Jawatan ahli**: Column `position` (string) — beza dari Spatie roles. Untuk display/title sahaja, bukan authorization.
- **Emergency contact**: Guna nama `emergency_contact_name` / `emergency_contact_phone` (neutral, bukan "waris").
- **Import ahli guna header mapping**: Column CSV guna nama (contoh: `nama`, `ic`) instead of index angka. Auto-detect DOB + gender dari IC. Validation skip row kalau IC tak sah.
- **No keahlian stacking**: Nombor asal kekal seumur hidup (`original_member_no`). Bila transit, `member_no` di-prepend prefix org baru (P→AP→WAP). Ahli sedia ada tanpa transit kekal macam biasa (W1234).
- **Stats Ahli**: Guna `profile_completed_at` — bukan status yuran — untuk tentukan aktif/tidak aktif.
- **Pagination per_page**: Support 25/50/100, dengan page number smart truncation + jump to page.
- **Slide-over panel UI**: Setiap data group dalam card (rounded, border, header bar). Avatar gradient ikut org color. Tab guna icon + text. Horizontal timeline untuk transit.

## Architecture Decisions (Session 4)

- **OTP only for first-time setup**: Bukan untuk login harian. Guna Resend.com sebagai mail transport.
- **Single login form**: Satu input field (auto-detect email vs IC). Buang role selection (Admin/Ahli).
- **Dual role handling**: `is_dual_role` flag — user pilih nak log masuk sebagai Admin atau Ahli (future: popup choice).
- **Lupa Kata Laluan / ID Ahli digabung**: Satu flow → IC → verify DOB → tunjuk masked email + member_no → hantar reset link.
- **Email templates from DB**: `email_templates` table — editable via Superadmin panel. Placeholders `{{name}}`, `{{code}}`, `{{purpose}}`.
- **Resend API key from DB**: `app_settings.resend_api_key` — encrypted cast. Boleh ubah dari dashboard, tak perlu `.env`.
- **Mail from address from DB**: `app_settings.mail_from_address` / `mail_from_name` — boleh config dari dashboard.
- **CSRF token via Inertia shared props**: `HandleInertiaRequests` tambah `csrf_token` untuk fetch() calls.
- **First-time login detection**: `users.first_login_at` column. Kalau null, kena guna "Log Masuk Kali Pertama" flow.

## Task Tracking

| Task | Status | Priority | Notes |
|---|---|---|---|
| Session 4: OTP Auth + Single Login + Email Templates | | | |
| Migration: `otp_codes` table + `first_login_at` | ✅ Done | High | 6-digit OTP, 5 minit expiry |
| `OtpCode` model + `OtpService` | ✅ Done | High | generate/send/verify/invalidate |
| `OtpEmail` notification via Resend | ✅ Done | High | Dengan DB template fallback |
| CSRF fix: Inertia shared props + fetch headers | ✅ Done | High | `HandleInertiaRequests` + `csrfHeaders()` helper |
| Single login form (buang role selection) | ✅ Done | High | Auto-detect email vs IC |
| First-time login flow (inline) | ✅ Done | High | IC → email → OTP → login |
| Forgot ID flow (inline, verify DOB) | ✅ Done | High | Masuk IC + DOB → tunjuk masked info |
| Gabung Lupa Password + Lupa ID | ✅ Done | Medium | Satu link "Lupa Kata Laluan / ID Ahli" |
| `forgotId` API endpoint | ✅ Done | High | Dengan DOB verification |
| Migration: `email_templates` table + seed | ✅ Done | High | Default `otp_login` template |
| `EmailTemplate` model + render helpers | ✅ Done | High | `renderSubject()`, `renderBody()` guna placeholders |
| Admin Vue: Email template editor | ✅ Done | High | Tab切换, edit subject + body, placeholder guide |
| Migration: `resend_api_key` + `mail_from_*` to `app_settings` | ✅ Done | High | Encrypted cast |
| `AppServiceProvider`: dynamic mail config from DB | ✅ Done | High | Baca API key + from address |
| Superadmin dashboard: Resend settings + mail from | ✅ Done | High | API key + from address + from name |
| Clean up: buang `otp_email_verify` template | ✅ Done | Medium | Tinggal satu template je |
| Fix: ProfileController missing `use App\Models\User` | ✅ Done | High | Bug pre-existing |
| Postcode table + seed + lookup | ✅ Done | High | 1299 postcodes seeded |
| Profile fields (gender, marital, emergency, position, topics) | ✅ Done | High | Migration + DOB auto-fill |
| Membership fee table + service | ✅ Done | High | `membership_fees`, `FeeService` |
| Update controllers (Payment, Dashboard, MemberDashboard, Financial, Broadcast) | ✅ Done | High | Semua guna FeeService |
| Update Vue pages (CompleteProfile, Profile/Edit) | ✅ Done | High | Form + auto-detect postcode |
| Migrations run + verify | ✅ Done | High | All 57 migrations OK |
| Gender auto-detect from IC | ✅ Done | High | `User::guessGenderFromIc()` |
| Organization positions table + CRUD | ✅ Done | High | `organization_positions`, admin page, sidebar link |
| Artisan command fees:generate | ✅ Done | High | `php artisan fees:generate {year}` |
| Admin UI: member fee list + toggle life_member/exempted | ✅ Done | High | `admin.fees.members` route, sidebar, modal |
| Backend: pass all profile fields to frontend | ✅ Done | High | HandleInertiaRequests, ProfileController, InfoHubAdminController |
| Frontend: display new fields in InfoHubManage + Profile/Show | ✅ Done | High | gender, marital, address, emergency, fee_status, etc. |
| Slide-over panel for member profile | ✅ Done | High | Tab system (Peribadi/Kerjaya/Alamat/Lain), Edit mode, Escape key |
| Simplify InfoHubManage table + stats bar | ✅ Done | High | 4 columns, stats cards, actions dropdown |
| Import ahli rewrite: header mapping + auto DOB/gender | ✅ Done | High | CSV guna column name, validation, error reporting |
| Sample CSV for import | ✅ Done | Medium | `database/seeders/data/import-ahli-contoh.csv` |
| Fix "button tindakan" bugs | ✅ Done | High | Missing route + dead button handler |
| No keahlian stacking + original_member_no | ✅ Done | High | P→AP→WAP stacking. `original_member_no` for search & sentimental |
| Transit process: prepend prefix on age transition | ✅ Done | High | `ProcessAgeTransitions` prepend org prefix to `member_no` |
| Update MembersImport: auto-generate no_ahli per org | ✅ Done | High | Prefix + padding + original_member_no |
| DemoActivitySeeder: transit + attended programs | ✅ Done | Medium | Transitions for Ahmad, RSVPs attended |
| Stats bar: total/aktif/tidak_aktif | ✅ Done | High | 3 cards, guna profile_completed_at |
| Pagination: per_page, page number, jump to | ✅ Done | High | 25/50/100 options, smart truncated pages |
| Slide-over UI improve (cards, gradient avatar, timeline) | ✅ Done | High | Card sections, tabs with icons, horizontal timeline |
| Program filter by year | ✅ Done | Medium | Dropdown year filter, loads all attended |
| Horizontal timeline for transition history | ✅ Done | Medium | Dot + line + arrow visual flow |
| **Session 5: Finance One-Stop Center** | | | |
| FeeStatus Enum + cast di MembershipFee | ✅ Done | High | PHP 8.1 backed string enum |
| FeeService v2 (perpetual life_member/exempted, cleanup unpaid) | ✅ Done | High | isLifeMember/isExempted cross-year |
| CSV Import Yuran (preview → process → report) | ✅ Done | High | Match by IC + member_no | 
| Export filtered list (CSV/Excel/PDF) | ✅ Done | High | dompdf + maatwebsite |
| Manual Payment Entry (proof wajib) | ✅ Done | High | Anti-ketirisan |
| Receipt PDF generation per payment | ✅ Done | High | dompdf template |
| Activity Log (Audit Trail) model + table | ✅ Done | High | organization_id included |
| Void/Reverse Payment | ✅ Done | High | payment.status=voided, fee.reset |
| Duplicate Reference Detection | ✅ Done | Medium | manualPayment reject duplicates |
| Reconciliation Report | ✅ Done | Medium | Expected vs collected vs rate |
| Monthly Collection Chart | ✅ Done | Medium | Simple bar chart |
| Clickable Stats Cards | ✅ Done | Medium | Auto-filter table by status |
| ToastNotification reusable component | ✅ Done | Medium | success/error/info auto-dismiss |
| ConfirmDialog reusable component | ✅ Done | Medium | variant support (danger/primary) |
| Esc key + loading skeleton UX | ✅ Done | Medium | Keyboard shortcuts + skeleton bars |
| Rename Life Member → Yuran Seumur Hidup | ✅ Done | Low | Labels + Superadmin-only |
| Superadmin-only life_member + exempt | ✅ Done | High | Frontend + backend restriction |
| Superadmin org filter dropdown | ✅ Done | Medium | Scope stats + table by org |
| authorizeOrg() security helper | ✅ Done | High | 6 security gaps fixed |
| Payment source display (CSV/Manual/Dummy) | ✅ Done | Medium | source field in history |
| Admin Finance Dashboard (chart.js) | ✅ Done | High | Aggregates payments + infaq_donations |
| Infaq data integration in Finance Dashboard | ✅ Done | High | Merge infaq_donations + payments |
| Clickable names → member finance slide-over | ✅ Done | Medium | Financial history overlay |
| Table column reorder (No Ahli → Nama → IC) | ✅ Done | Low | UX improvement |
| FeeDemoSeeder (12 members, various status) | ✅ Done | High | Realistic demo data |
| 4 migrations baru | ✅ Done | High | proof_to_payments, fee_imports, activity_logs, org_id_activity_logs |

## Next Steps

- Auto-cron: `fees:generate` schedule every Jan 1
- Payment Gateway architecture per org (interface + resolver designed, implement bila sedia)
- Infaq + Ecommerce flow — create Payment records for single source of truth (Option B)
- Bulk reminder notification (WhatsApp/email) for unpaid fees
- Finance Role (`role:Finance`) — separation of duties
- Fee reminder notification — auto hantar reminder ke ahli tertunggak
- Year-End Closing — lock tahun lepas untuk elak edit
- Receipt/invoice generation for fee payments — auto-send via email
- Monthly Collection Chart — line/bar trend (current chart is simple)
- Payment Proof Watermark — overlay sistem authenticity
