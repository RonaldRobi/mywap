# Product

<!-- impeccable:product-schema 1 -->

## Platform

adaptive

One product, two clients: a Flutter app shipping on iOS and Android, and a Laravel/Inertia web app for public pages, the member area, and admin/superadmin operations. Mobile uses one Material 3 language across both OSes, not per-OS native styling.

## Users

Members of three Malaysian Islamic NGOs, PKPIM, ABIM and WADAH, who move through the tiers as they age:

- PKPIM — under 20, students and junior members
- ABIM — 20 to 43, youth members
- WADAH — 44 and above, senior and veteran members

They use the product to check the member card and fee status, register for programmes, join usrah groups, read news and the library, donate to infaq campaigns, buy organisation merchandise, fill forms, refer new members, and keep profile details current. The mobile plan explicitly names older, less tech-confident users as the hardest audience to serve.

Operators: organisation admins manage members, fees, programmes, the information hub and transactions for their own organisation; superadmins manage organisations, system settings, mail, banners, infaq campaigns and cross-organisation views.

## Product Purpose

Official digital membership platform for PKPIM, ABIM and WADAH. It replaces fragmented manual administration with one system for member identity, dues, programmes and communication, so members have one place to act and organisations have one source of truth.

Success: members complete core tasks (fee payment, programme registration, member card, infaq) without help, and admins run their organisation without spreadsheets or scattered channels.

## Positioning

One member identity that survives an entire life in the movement. A person's original member number is kept for life; when age moves them into the next NGO tier, the system migrates them to that organisation and stacks a new prefix (for example P → AP → WAP), preserving history. A single-organisation tool cannot truthfully copy this cross-tier, multi-organisation lifecycle.

It is also the one-stop centre: membership, fees, infaq, events and attendance, usrah, forms, directory, news and merchandise in one product.

## Operating Context

- Three organisations with configured age ranges; a daily scheduled command (`app:process-age-transitions`) moves members whose age no longer matches their tier and records transition history.
- Registration and first login use the Malaysian IC number: IC infers date of birth, gender, likely organisation and fee; first login is IC → email → 6-digit OTP → set password.
- Payments run through BayarCash (FPX and direct debit); infaq campaigns and recurring donations are part of the member experience.
- Members are scoped to their current organisation; admins see their own organisation, superadmins see across organisations.
- Two clients share one Laravel backend and one service layer; the backend is the single source of truth.
- Interface language is Malay first, with English available.

## Capabilities and Constraints

Confirmed capabilities:

- Member card, profile and journey/timeline, opt-in member directory, referral links with QR and stats.
- Annual membership fees with statuses (unpaid, paid, exempted, life member), receipt/PDF, CSV import, manual payments, void/reversal, reconciliation and activity log.
- Programmes and events with RSVP, QR attendance scanning and reports.
- Usrah groups, announcements, news, articles, digital library, videos, polls, facilities and bookings.
- Infaq campaigns with public listings, anonymous option and recurring donations; donor tracking and deduplication.
- E-commerce/mall with member pricing, stock and orders.
- Form builder with public share links and response export.
- Push notifications and in-app notifications.
- Admin and superadmin dashboards with org scoping, exports and system settings (mail, logo, splash, organisations, fees, banners).

Constraints:

- Apple Guideline 3.2.2(iv): the mobile app must not collect charity donation amounts or donor details in-app. Donation buttons must open the campaign website in the default browser or SFSafariViewController; the app may show campaign information only.
- Mobile performance is a product requirement, not polish: fast cold start, smooth scrolling, small API payloads and skeleton-first loading.
- Touch targets on mobile must stay comfortably large (at least 48px); no dense desktop-style controls.
- Organisation-scoped authorization is enforced throughout; cross-organisation access must stay blocked.
- First-time member access depends on a registered email for OTP.
- Public/guest browsing exists, but member-only surfaces (card, fees, programme registration, usrah) require login.

## Brand Commitments

- Name: myWAP (also referred to as MyMarhalah).
- Official platform of PKPIM, ABIM and WADAH; each organisation keeps its own identity and membership base inside one product.
- Malay-first member voice; respectful, community-oriented tone.
- Existing logo asset at `public/images/logomywaphorizontal.png`; per-organisation logos are stored in the application storage.

## Evidence on Hand

- The repository contains working web and mobile clients, the Laravel backend and a full service layer, not a prototype.
- Seeded demo data exists for members, fees, events, infaq, products, articles, library, polls, usrah and forms; it is demo content, not real member or donor data.
- Organisation logos and the myWAP logo exist; no real testimonials, customer names, benchmarks, press or case studies are in the repository, so future work must not fabricate them.
- Real production data lives in the deployed database, not in this repository.

## Product Principles

1. Speed and simplicity are product requirements; a slow or crowded screen causes members to drop out.
2. One backend truth serves both clients; never fork member, fee or organisation logic per client.
3. Membership is a lifecycle, not a record: identity, number and history follow the member across age tiers.
4. Read publicly, act securely: guests can explore; money, registration and personal data require an authenticated, organisation-scoped member.
5. Compliance and trust are not optional: donation handling follows Apple's rule, and every admin action is scoped and auditable.

## Accessibility & Inclusion

- Members span students through seniors; the oldest tier is the least forgiving audience, so large text, large tap targets, clear labels and short flows are required.
- Malay is the primary language, with English available; all member-facing copy must work in both.
- The donation path must stay browser-based on iOS; no in-app charity payment or donor capture.
