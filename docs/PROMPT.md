# rungud CMS — build instructions for Claude Code

You are building **rungud CMS**, the back office for inZENtive (Robert Steinbacher, Zurich — breathwork and movement education). Three users: Gudrun (bookkeeper/secretary, does everything), Robert (founder, mostly reads, promises discounts), Webni (admin). Both primary users are non-technical Germans. The product name is *rungud*.

Read this file fully before writing any code. Then read the four reference documents in `/docs` in this order: `prototype.html` (the approved UI — open it in a browser, click through every screen in both languages and both roles), `website-integration.md` (v2.1 — ownership rules and the site's endpoints), `build-package.md` (domain rules: memberships, invoices, cancellation, codes, certificates — **the architecture part of it is superseded by this file**), `analysis.md` (data findings).

---

## 1. Architecture — decided, do not revisit

**No separate database. The CMS lives inside the existing WordPress site.**

The site already holds everything that matters: users, WooCommerce orders and products, LearnDash memberships (billed through LearnDash's built-in Stripe), licences (`_inzentive_licenses` user meta), redeem codes (Woo coupons), course access. The CMS **reads those directly and sends commands through the site's own functions** — no copies, no webhooks between systems, no sync layer.

Two deliverables in one monorepo:

```
rungud/
  plugin/          WordPress plugin "rungud-cms" (PHP 8.1+, WP 6.x, WooCommerce, LearnDash 5.1.9)
  web/             React + Vite + TypeScript frontend, deployed to Vercel, talks only to the plugin's REST API
  docs/            the four reference documents
  CLAUDE.md        the rules in §7 of this file, copied verbatim
```

The plugin owns: custom tables, REST API (`/wp-json/rungud/v1/*`), capabilities, PDF generation, Stripe webhook receiver, cron jobs, audit log. The frontend owns: nothing but UI. **Every business rule is in PHP, once.** The frontend never computes a price, a fee or a status.

The site team is separately building `/wp-json/inzentive/v1/*` (licences, membership cancel/refund, access grant, access overview, **and read endpoints `GET /programs`, `GET /plans`, `GET /events`**). Read catalogue data from those; never hardcode programmes, plans or events. Where those exist, call them; where the plugin needs the same logic, call the site's PHP functions directly (`certification_reconcile()`, `membership_plans()`) — never reimplement, never write to LearnDash tables or user meta directly.

## 2. Data the plugin owns (custom tables, prefix `wp_rungud_`)

Only what the site does not have:

```
invoices          hand-issued invoices and credit notes: number (gapless, continues the
                  existing sequence from 100648+, per year not required), kind
                  (invoice|credit_note|deposit|final), status, billing_snapshot JSON,
                  lines JSON, vat_profile, totals, currency, due_date, pdf_path,
                  woo_order_id NULL, stripe_invoice_id NULL, parent_id NULL, approved_by/at
payments          for hand-issued invoices only: amount, paid_on, method, reference
refunds           prepared/executed, method(bank|stripe|woo), amount, reference, executed_by/at
certificates      number (IZ-{PROG}-{YYYY}-{SEQ}), licence_number ({PREFIX}-{SEQ}),
                  user_id, program_id, completed_on, days, place, trainer, valid_until,
                  status(valid|superseded|revoked), pdf_path
education_history user_id, program_id, event/product ref, completed_on, source(live|learndash)
discount_codes    mirror + intent of Woo coupons the CMS created: code, woo_coupon_id,
                  kind(campaign|personal|redeem), pct, program_ids, one_per_person,
                  stackable, for_label, note, made_by, sent_via
code_claims       "Robert promised me a discount, no code": user/email, text, status, decided_by
document_sends    doc_type, doc_ref, to_email, subject, body, sent_by, sent_at, status
attendees         (only if the site does not already expose line-item meta editing)
audit             actor, entity, entity_id, action, before JSON, after JSON, summary_de, summary_en
vat_profiles, legal_entity, settings, cancellation_terms (versioned bands JSON)
```

Everything else is read live: `WP_User`, `wc_get_orders()`, `wc_get_product()`, LearnDash group/course access, `_inzentive_licenses`, Woo coupons with `_inzentive_*` meta, Stripe (invoices, subscriptions) via the Stripe PHP SDK with a restricted key.

## 3. REST API (`/wp-json/rungud/v1/`)

Auth: JWT issued by the plugin on login (`POST /auth/login` with WP credentials → token; refresh; logout). Roles map to WP capabilities: `rungud_owner` (Robert), `rungud_backoffice` (Gudrun), `administrator`. Every endpoint checks a capability. CORS allow-list from settings (the Vercel preview and prod domains).

Resources (each with list/detail; writes only where the ownership map allows):
`people`, `people/{id}/access` (proxies `inzentive/v1/access`), `people/{id}/documents` (Woo PDFs, Stripe PDFs, CMS PDFs, certificates), `events` (from `inzentive/v1/events`: `event_id, product_id, title, date, price, member_price, seats_left`; the Woo product is recognised by `_inzentive_event_id` meta on the product and on order line items — Woo REST shows only the full price, the member price is applied by the site's filter), `events/{id}/participants` (orders of that product + line-item attendees + health declaration + certificate status), `orders`, `orders/{id}/attendees` (write line-item meta), `orders/{id}/refund`, `memberships` (from LearnDash + Stripe; commands proxy to `inzentive/v1`), `licences` (proxy), `codes` (creates Woo coupons), `claims`, `invoices` (hand-issued; approve = assign number + freeze + PDF), `payments`, `refunds`, `certificates`, `documents/send`, `today` (the task list for the current role), `insights`, `audit`, `settings`.

Stripe webhook: `POST /wp-json/rungud/v1/stripe` — the CMS's **own** endpoint (never LearnDash's), signature-verified, idempotent on event id.

## 4. Frontend

Rebuild `docs/prototype.html` faithfully as a React app: same layout, same design tokens (`#F7F5F3` page, `#1E2C36` sidebar, `#586C7B` accent, Inter, tabular numbers, badges = colour + text), same navigation groups (Today · Operations · Money · Reach · System · Help), same 21 screens, same wording. i18n with `react-i18next`, English default, German switch in the user menu, both dictionaries seeded from the prototype's `T` object and its inline strings. Dates `DD.MM.YYYY` always; money `€1,200.00` / `1.200,00 €` / `CHF 1'200.00`.

Keep the prototype's hard rules: one calc function per price box (never two totals), every destructive action behind a confirmation dialog that states the consequence in one sentence, undo toast for reversible actions, tooltips (`?`) with the prototype's texts, Today as a sentence list not a dashboard, Help page with the workflows and the "where do I do what" table.

Robert's role sees no edit controls except *Promise a discount* and confirming claims; enforce on the API, not only in the UI.

## 5. Build order — one phase per session, tests green before the next

0. **Repo + tooling.** Monorepo, `plugin/` with Composer (stripe/stripe-php, dompdf or mpdf for PDFs, phpunit + wp-env), `web/` with Vite/TS/Tailwind/shadcn, ESLint, Vitest. GitHub Actions: PHP tests + frontend build. `web/` deploys to Vercel from `main` (prod) and every branch (preview). `.env.example` for both.
1. **Plugin core.** Activation creates tables; capabilities; JWT auth; audit helper; settings page in WP admin (only for API keys, CORS, legal entity, VAT profiles, number sequence). `today` endpoint returning static structure.
2. **Read-only screens.** People (search, detail with Overview tab), Events with participant list, Orders, Memberships (LearnDash + Stripe read), Website connection log (= audit of commands). Login. Robert's view. **Ship to Vercel here and give Gudrun the link.**
3. **Commands.** Person → Access tab: licences add/verify/reject/revoke, membership cancel-at-period-end / now / refund+cancel, grant free access, checkout link, redeem code (Woo coupon with `_inzentive_valid_card`, `_inzentive_card_days`, `_inzentive_card_scope`, allowed email). Attendees on line items. Send document to email (Woo PDF URL, Stripe `invoice_pdf`, CMS PDF) with per-language cover text. Woo refund.
4. **Discount codes + claims.** Campaign and personal codes as Woo coupons (product restriction, usage limits, one-per-person), Robert's three-question *Promise a discount* screen with WhatsApp text, claims queue, discount policy respected (fixed-price products refuse codes without consuming them).
5. **Hand-issued invoices.** Draft → approval screen (customer view left, editable right, discounts panel) → gapless number in a transaction (`SELECT … FOR UPDATE` on the sequence row) → frozen snapshot → PDF stored → immutable (any UPDATE on an approved invoice throws). Credit notes, payments, refunds, cancellation calculator (bands from `cancellation_terms`, both the T&C and the fact-sheet rules shown while `applies_to` is unresolved), place transfer. Deposit/final pairs.
6. **Certificates + education history.** Templates, licence numbering by programme prefix, valid_until per programme, reissue keeps licence number, revoke, bulk generate/send, public `/verify/{number}` page served by the plugin, LearnDash credential-course completion → history + certificate.
7. **Today, Insights, Messages.** Role-aware Today from real queries; Insights signals from LearnDash activity joined to membership/licence state; message drafts with approval (sending through the site's mail/SMTP).
8. **Import.** Wix membership CSV (`docs/` has the analysis: 11 product names → 3 types, dates `M/D/YYYY`, no active rows in the sample), past educations CSV, dry-run + rollback.

## 6. Environment

```
plugin (wp-config or settings page): RUNGUD_JWT_SECRET, RUNGUD_SMTP_* (same server/sender as the site), STRIPE_SECRET_KEY (restricted: read invoices,
  cancel subscriptions, refund), STRIPE_WEBHOOK_SECRET, RUNGUD_CORS_ORIGINS
web (.env): VITE_API_BASE=https://<site>/wp-json/rungud/v1
```
Staging: a WordPress staging copy + Stripe **test mode** + Vercel preview. Never point a preview at production.

## 7. Rules — copy into CLAUDE.md

1. Business rules live in PHP once. The frontend renders; it never calculates.
2. Never write to LearnDash tables, Woo tables or user meta directly. Use `inzentive/v1` endpoints or the site's PHP functions. If one is missing, stop and list it.
3. Nothing financially binding happens without a human click: no invoice numbered, no refund executed, no subscription cancelled, no message sent. Confirmation dialogs state amount, person, date.
4. An approved invoice is immutable. Corrections only via credit note. Numbers are gapless and assigned inside a transaction.
5. Every command and every document send writes an audit row with a plain-language German and English summary. Failures surface on Today. Nothing is silent.
6. Nothing is hard-deleted. Archive, cancel, revoke — with reason and actor.
7. Every user-visible string is in both `en.json` and `de.json`. German finance terms come from the glossary in `build-package.md` §A7, never machine-translated.
8. Dates `DD.MM.YYYY`, 24h; money formatted per language; CHF with apostrophes. Never `MM/DD`.
9. Robert's role is enforced by capabilities on every write endpoint.
10. Do not build a second database, a sync layer, webhooks between the site and the CMS, or a copy of Woo/LearnDash data. If a design seems to need one, stop and say so.
11. Before each phase: read the matching prototype screens and the matching section of `build-package.md`. After each phase: run the acceptance tests for it (`build-package.md` Part G + `website-integration.md` §7), then stop and report.

## 8. Things to confirm — proceed with the default, flag in the report

| # | Item | Default until confirmed |
|---|---|---|
| 1 | Woo PDF invoice plugin — none locally, live being checked | assume none; recommend "PDF Invoices & Packing Slips for WooCommerce"; read the PDF URL from that plugin's meta; show "PDF not available" until it exists |
| 2 | Programmes — **`GET inzentive/v1/programs`** returns `id, slug, title, audience(b2c\|b2b), licence_prefix, certification_numbers` | **key on `slug`, never on `id`** (ids differ between local and live). Licence prefix is being added on the site; licences exist only for B2B programmes. Validity months and external issuer (HeartMath, bodyART) are CMS settings per slug |
| 3 | Plans — **`GET inzentive/v1/plans`** | read, never hardcode. For orientation: `membership-monthly` €20/mo b2c · `membership-annual` €192/yr b2c · `instructor-annual` €200/yr b2b · `instructor-practice-annual` €250/yr b2b (also opens B2C) |
| 4 | Events — **`GET inzentive/v1/events`**; product recognised by `_inzentive_event_id` meta | "fixed price / no discounts" does not exist on the site — it is a CMS concept: store `discount_policy` per event **in the CMS settings keyed on event slug/id**, and enforce it only where the CMS acts (codes it creates, hand-issued invoices). The site's member price filter is untouched |
| 5 | TODO(confirm) Which cancellation terms govern educations: fact sheet (8/6/4 weeks, €50 rebooking) or T&C (60/30 days, €100 fee) | store both as versions; the calculator shows both until `applies_to` is set |
| 6 | Robert's signature image and legal block — from the client, not from the site (Impressum is not published yet) | placeholder assets; legal block from `docs/analysis.md` as a settings default, editable |
| 7 | Mail — the site sends as `info@inzentive.online` "inZENtive" via `vega.mysafeservers.com:587` STARTTLS with SMTP auth | the CMS sends **from the same address and server** so SPF/DKIM hold and the customer sees one sender; credentials come out of band into wp-config/settings, never into the repo. Email template: dark background, Helvetica, no rounded corners, light button with black text — the site team will supply a sample HTML |

Start with phase 0. Report at the end of each phase: what was built, what tests ran, what is in the TODO table above that you had to assume.
