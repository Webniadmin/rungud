# InZENtive Backoffice CMS — Lovable Build Package

---

## 0. HOW TO USE THIS FILE (for Luka — do not paste this section into Lovable)

Lovable degrades badly on one giant prompt. Split it:

**Before you prompt anything:**
1. Create the project, connect **Supabase**, set region **EU (Frankfurt)**.
2. Paste **PART A** into *Project Settings → Knowledge*. It stays in context for every prompt. Never repeat it in prompts.
3. Keep **Chat mode** for planning before each build prompt.

**Then build in this order, one prompt at a time, testing after each:**

| Step | Paste | Build only after |
|---|---|---|
| 1 | PART B — Foundation | — |
| 2 | PART C — Memberships & discounts | B works |
| 3 | PART D — Invoicing | Appendix questions answered by Gudrun |
| 4 | PART E — Certificates & documents | D works |
| 5 | PART F — Sync, waitlist, imports | E works |

**Rule for every prompt:** start it with *"Do not change anything outside the scope described below. Do not refactor existing tables."*

Send the **Appendix** to Gudrun and Robert now. Three of those answers block Part D.

---

# PART A — KNOWLEDGE BASE

*(paste once into Lovable → Project Settings → Knowledge)*

## A1. What this application is

An internal back-office system for **inZENtive** (Robert Steinbacher, CH-8008 Zurich), a movement and breathwork education company. It manages live events and educations (Germany, Switzerland, and international), participant registrations, instructor memberships ("Classroom"), invoicing, payments, education history and certificates.

It is **not** the public website. The public website runs on WordPress and will send registrations into this system later. This application is the single source of truth for people, events, registrations, memberships, invoices and payments.

It has exactly **three users**. Two of them are not technical and will use this system daily for years. Optimising for their confidence is more important than features.

## A2. Users and roles

**1. Robert — role `owner`**
Founder. Travels constantly, uses a phone and laptop, is not technical. He needs to look things up himself instead of calling Gudrun.

- Read access to everything: events, registrations, participants, memberships, education history, invoice and payment status, revenue totals.
- **One** write capability: he can propose an individual discount on a registration (percentage or fixed amount, with a mandatory reason). It is saved as a *proposal* and appears in Gudrun's approval queue. It never changes an invoice by itself.
- He cannot create, edit or delete events, registrations, invoices, participants or settings.
- He can never send anything.

**2. Gudrun — role `back_office`**
Secretary and bookkeeper. Not technical. Does registrations, corrections, cancellations, invoicing, payment tracking, reminders, certificates.

- Full read and write on: people, organisations, events, registrations, waitlists, memberships, attendance, certificates.
- Full access to finance: invoices, approval, sending, payments, credit notes, VAT, exports, outstanding balances.
- She is the only person who finalises and sends invoices.
- She cannot change system settings (VAT profiles, number ranges, templates, users).

**3. Webni — role `admin`**
Technical partner. Everything, including settings, templates, VAT profiles, number ranges, user management, imports, integrations.

Enforce this with Supabase **Row Level Security**, not only by hiding buttons in the UI.

## A3. Non-negotiable rules

**Money**
1. Nothing financially binding ever happens automatically. No invoice is numbered, finalised or sent without an explicit human approval click.
2. Invoice numbers are assigned **only at approval**, gapless and sequential per legal entity and per year.
3. A finalised invoice is **immutable**. It can never be edited or deleted. Corrections happen only by issuing a credit note and, if needed, a new invoice.
4. The stored invoice PDF is never regenerated. The file created at approval is the file that is served forever.
5. VAT is never hard-coded. It comes from configurable VAT profiles, is *proposed* with a visible reason, and is always manually overridable at approval with a note.
6. Every invoice snapshots the billing name, address, VAT ID and prices at the moment of approval. Later edits to the customer record must not change past invoices.

**Data**
7. Nothing is ever hard-deleted. Records are archived, cancelled or voided, with a reason and an author.
8. Every change to a registration, membership, invoice, payment or discount is written to an audit log and displayed on the record as a plain-language history ("Gudrun changed the price from €1,200.00 to €900.00 on 12.03.2026 — reason: individual discount approved by Robert").
9. A person's email is the unique key. Duplicates must be preventable and mergeable.

## A4. Language and formatting

10. **The interface language is English by default.** A German translation is available and switchable per user from the user menu; the choice is stored on the user record and persists across sessions.
11. **Full i18n from day one.** No user-visible string is hard-coded in a component. All strings live in `en.json` and `de.json` with the same keys. Adding a screen means adding both files. Retrofitting i18n later is the single most expensive mistake available here — both primary users are native German speakers and will switch.
12. **Database values are language-neutral English keys.** All enums, statuses and codes are English snake_case (`pending_approval`, `waitlist`, `partially_paid`). The UI never displays a raw enum; it renders the translated label. Use the dictionary in A6 as the seed for both files.
13. **Number and date formatting does not follow the UI language.** The company and its customers are Swiss and German. Always:
    - dates `DD.MM.YYYY` — never `MM/DD/YYYY`, in any language
    - amounts `€1,200.00` in English, `1.200,00 €` in German, `CHF 1'200.00` for Swiss francs
    - times 24-hour
14. **Documents sent to customers follow the recipient's language, not the operator's.** Invoices, emails, reminders, certificates and fact sheet cover letters render in the language on the person's or organisation's record (`de` or `en`), regardless of whether Gudrun is working in English or German at that moment. Templates exist in both.
15. The German translation must be reviewed by Gudrun before go-live. Do not ship machine-translated finance or legal terms — use the exact terms in A6.

## A5. UX rules — this is a hard requirement, not a preference

The two primary users are not technical. Build accordingly.

**Structure**
- One home screen called **Today**. It is a list of what needs attention, not a dashboard of charts. Everything important is reachable in two clicks from it.
- A single global search in the header that finds people, events, invoice numbers and email addresses in one box. No separate search per module.
- Maximum three levels of navigation. Left sidebar: Today · Events · People · Memberships · Invoices · Payments · Help.

**Interaction**
- Multi-step actions are wizards with one question per screen, a visible progress indicator, and a Back button that never loses data.
- Every button has a text label. Never icon-only for anything that changes data.
- Every irreversible action opens a plain-language confirmation that states the consequence and the recipient, e.g. *"Invoice R-2026-0147 for €900.00 will now be finalised and sent to anna@example.com. After this it can no longer be changed."* Two buttons: the action, and Cancel.
- Provide Undo (10 seconds, toast) for every reversible action.
- Forms save drafts automatically. Never lose input on navigation or error.
- Validation errors appear next to the field, in plain language, saying what to do — not "invalid input".

**Presentation**
- Status is always a coloured badge **plus** text. Never colour alone.
- No ISO date strings, no UUIDs, no slugs, no JSON, no raw enum values anywhere in the UI.
- Minimum body text 16px, table text 15px, generous row height (48px+), high contrast.
- Empty states explain what to do next, with the button to do it.
- Every list is searchable, sortable, filterable and exportable (CSV + XLSX), and has a print-friendly view.
- No feature is explained only by a tooltip. Anything non-obvious gets a short inline hint under the field.

**Help**
- A permanent **Help** page in the sidebar, available in both languages, with the eight core workflows written as numbered steps: register a participant · cancel a registration · check a membership · approve an invoice · record a payment · send a reminder · confirm attendance and issue certificates · export for the bookkeeper.
- A "?" next to every status name that explains what that status means and what happens next.

## A6. Design system

Calm, quiet, high legibility. This is a tool used for hours, not a marketing page. Light interface — do not use the brand's dark hero aesthetic for working screens.

```
Background         #F7F5F3   (page)
Surface            #FFFFFF   (cards, tables)
Border             #E2DDD9
Sidebar / header   #1E2C36   (Slate) with #E6E2DD text
Text primary       #0D0D0D
Text secondary     #646260
Accent / primary   #586C7B   (brand gradient mid-tone)
Accent hover       #1E2C36
Warm neutral       #928176   (secondary actions, tags)
```

Functional colours (the brand palette has none — add these, muted, never neon):

```
Success  bg #E8EFE8  text #3F5D45   (paid, active, completed)
Warning  bg #F6EFE2  text #7A5B24   (expiring, awaiting approval, waitlist)
Danger   bg #F4E7E5  text #7A3B33   (expired, overdue, cancelled)
Neutral  bg #EFEDEA  text #646260   (draft, no membership)
```

Typography: **Helvetica Neue LT Pro** if the licensed webfont files are supplied; otherwise **Inter**. One typeface, weights Light/Regular/Medium. No decorative fonts, no all-caps in tables.

German labels are on average 30% longer than English. Buttons, table headers and sidebar items must not truncate or wrap when the language is switched — test every screen in German before considering it done.

Layout: 8px spacing scale, 4px radius, one subtle shadow level, tables with zebra rows off and borders on. shadcn/ui components, Tailwind, no custom CSS frameworks.

## A7. Terminology dictionary — seed for `en.json` / `de.json`

Use exactly these terms. Do not substitute synonyms in either language.

| Key | English | German |
|---|---|---|
| `participant` | Participant | Teilnehmer:in |
| `billing_recipient` | Billing recipient | Rechnungsempfänger |
| `event` | Event | Veranstaltung |
| `education` | Education | Ausbildung |
| `registration` | Registration | Anmeldung |
| `waitlist` | Waiting list | Warteliste |
| `places_left` | Places left | Freie Plätze |
| `fully_booked` | Fully booked | Ausgebucht |
| `registration_open` | Registration open | Anmeldung offen |
| `registration_closed` | Registration closed | Anmeldung geschlossen |
| `membership` | Classroom membership | Classroom-Mitgliedschaft |
| `active` | Active | Aktiv |
| `expired` | Expired | Abgelaufen |
| `valid_until` | Valid until | Gültig bis |
| `discount` | Discount | Rabatt |
| `individual_discount` | Individual discount | Individueller Rabatt |
| `invoice` | Invoice | Rechnung |
| `draft` | Draft | Entwurf |
| `pending_approval` | Awaiting approval | Zur Freigabe |
| `approved` | Approved | Freigegeben |
| `sent` | Sent | Versendet |
| `partially_paid` | Partially paid | Teilweise bezahlt |
| `paid` | Paid | Bezahlt |
| `overdue` | Overdue | Überfällig |
| `cancelled` | Cancelled | Storniert |
| `credit_note` | Credit note | Gutschrift |
| `payment` | Payment | Zahlung |
| `outstanding` | Outstanding balance | Offener Betrag |
| `vat` | VAT | MwSt. |
| `reverse_charge` | Reverse charge | Reverse-Charge-Verfahren |
| `prerequisites` | Prerequisites | Voraussetzungen |
| `attendance_confirmed` | Attendance confirmed | Teilnahme bestätigt |
| `completed` | Completed | Abgeschlossen |
| `certificate` | Certificate | Zertifikat |
| `fact_sheet` | Fact sheet | Factsheet |
| `history` | History | Verlauf |
| `today` | Today | Heute |
| `approve_invoice` | Approve invoice | Rechnung freigeben |
| `record_payment` | Record payment | Zahlung erfassen |
| `send_reminder` | Send reminder | Mahnung senden |

---

# PART B — PROMPT 1: Foundation

> Build the foundation of the back-office system. Scope: authentication, roles, i18n, database schema, people, organisations, events, registrations, participant lists, and the Today dashboard. **Do not build invoicing, memberships logic, certificates or payments yet** — create the tables for them but no screens.

## Internationalisation (build this first, before any screen)

Set up `react-i18next` with `en.json` and `de.json`, English as the default, a language switcher in the user menu, and persistence of the choice on `app_users.locale`. Seed both files from the dictionary in the knowledge base. Every screen you build from here on adds keys to both files. No literal strings in JSX.

## Database (Supabase, with RLS)

```
app_users        id(auth), full_name, email, role('owner'|'back_office'|'admin'),
                 locale('en'|'de'), is_active, last_login_at

people           id, first_name, last_name, email(unique, citext), phone,
                 street, postal_code, city, country(ISO2),
                 language('de'|'en'),        -- language for documents sent to them
                 is_instructor(bool), instructor_verified_at, instructor_verified_by,
                 notes, archived_at, created_at, created_by

organisations    id, name, street, postal_code, city, country(ISO2), vat_id,
                 vat_id_checked_at, contact_person, invoice_email, language('de'|'en'),
                 payment_terms_days(default 14), notes, archived_at

billing_accounts id, type('participant'|'private_person'|'organisation'),
                 person_id(nullable), organisation_id(nullable),
                 name, street, postal_code, city, country, vat_id,
                 contact_person, invoice_email, language, is_default_for_person_id

event_templates  id, code, name_en, name_de, category('education'|'retreat'|
                 'convention'|'workshop'|'online'), description_en, description_de,
                 default_days, default_price_regular, default_price_member,
                 member_discount_percent, grants_certificate(bool),
                 certificate_template_id, fact_sheet_file_id, default_vat_profile_id

template_prerequisites  template_id, required_template_id

events           id, template_id, title_en, title_de, start_date, end_date,
                 location_name, address, city, country, is_online, online_link,
                 capacity, waitlist_enabled(bool, default true),
                 registration_opens_at, registration_closes_at,
                 status('draft'|'published'|'registration_open'|
                        'registration_closed'|'fully_booked'|'running'|
                        'completed'|'cancelled'),
                 price_regular, price_member, currency('EUR'|'CHF'),
                 vat_profile_id, fact_sheet_file_id, legal_entity_id,
                 is_public(bool), internal_notes, wp_post_id

event_trainers   event_id, person_id(nullable), external_name, role

registrations    id, event_id, participant_id → people, billing_account_id,
                 status('registered'|'waitlist'|'confirmed'|'cancelled'|
                        'attended'|'completed'|'no_show'),
                 registered_at, source('website'|'backoffice'|'import'),
                 waitlist_position,
                 membership_snapshot(jsonb), price_base, currency,
                 membership_discount_amount, individual_discount_type,
                 individual_discount_value, individual_discount_reason,
                 individual_discount_proposed_by, individual_discount_approved_by,
                 final_price,
                 prerequisites_met(bool), prerequisites_missing(text[]),
                 attendance_confirmed_at, completed_at,
                 cancelled_at, cancellation_reason, internal_note

audit_log        id, actor_user_id, entity_table, entity_id, action,
                 before(jsonb), after(jsonb), summary_en, summary_de, created_at
```

Also create empty tables now (no UI yet): `memberships`, `invoices`, `invoice_lines`, `payments`, `certificates`, `files`, `email_log`, `settings`, `legal_entities`, `vat_profiles`, `number_sequences`.

## Screens

**Login** — email + password, language switcher visible on this screen too, password reset. No sign-up.

**Today (home)** — role-aware task list, each item a card with a direct action button:
- *Robert:* next 5 events with `X of Y places taken`, registrations in the last 7 days, memberships expiring in 30 days, his open discount proposals.
- *Gudrun:* new registrations since last login, events with open registration and free places, events starting in the next 14 days, participants with unmet prerequisites, placeholders for invoice/payment counters (wired in Part D).
- Never show an empty dashboard: if nothing needs attention, say so in one calm sentence.

**Events (list)** — cards or table: title, dates, location, status badge, `X/Y booked`, `Waiting list: N`, registration open/closed, price. Filters: status, year, country, template. Search.

**Event (detail)** — tabs:
1. *Overview* — all event data, trainers, prerequisites, prices, capacity, free places, waitlist count, fact sheet, edit button (Gudrun/admin only).
2. *Participants* — the participant list. Columns exactly: Name · Email · Billing recipient · Registered on · Classroom (badge + valid until) · Discount · Final price · Invoice status · Payment status · Prerequisites · Attendance · Completed · Certificate. Sortable, filterable, searchable, exportable to CSV/XLSX, print view. Bulk actions: Confirm attendance, Mark as completed, Export.
3. *Waiting list* — ordered list with positions, move up/down, "Move to participant list" button (manual, never automatic).
4. *History* — audit log in plain language.

**Add registration (wizard, 4 steps)**
1. *Who is attending?* — search existing people by name/email, or create new inline. Show a warning if the email already exists.
2. *Prerequisites* — automatic check against the person's completed educations. If missing, show which, and allow Gudrun to tick "allow anyway" with a reason. Never block.
3. *Who receives the invoice?* — radio: *The participant* (prefilled) / *Another private person* / *A company*. If not the participant: required fields name or company name, address, country, VAT ID (optional), contact person, invoice email. Saved to `billing_accounts` and reusable next time.
4. *Price and confirmation* — shows the price breakdown (membership logic arrives in Part C; for now base price and an optional individual discount field), a **discount code** field with live validation and the "I have no code" fallback (rules in Part C), free places check, and a summary. The summary total must be computed from the same function as the price box — never two calculations. If the event is full, the wizard says so plainly and offers "Add to waiting list".

**People** — list and detail. Detail tabs: Details · Billing addresses · Registrations · Education history (placeholder) · Membership (placeholder) · Invoices (placeholder) · History.

**Cancelling a registration** — requires a reason, frees the place, shows a banner if an invoice already exists ("Invoice R-… already exists — check whether a credit note is needed"), and writes to the audit log.

## Acceptance for Part B
- Switching the interface to German changes every visible string, and no layout breaks or truncates.
- Robert's login sees no edit buttons anywhere and any direct API write is rejected by RLS.
- Creating a registration for a full event lands on the waiting list with the correct position.
- The participant list exports with every column visible on screen.
- Deleting is impossible; cancelling works and is visible in History.

---

# PART C — PROMPT 2: Memberships and discounts

> Add Classroom membership management and the discount engine. This is the highest-value part of the system: the company currently loses roughly €48,000 a year because memberships are opened manually and never expire. Expiry must be automatic and unmissable.

## Schema

```
memberships  id, person_id, type('instructor'|'instructor_plus_b2c'|'b2c'),
             status('pending_verification'|'active'|'expired'|'cancelled'),
             start_date, end_date(NOT NULL), price, currency,
             discount_percent(default 30),
             source('website'|'backoffice'|'import'|'stripe'),
             verified_by, verified_at, cancelled_at, cancellation_reason,
             previous_membership_id, internal_note

membership_events  membership_id, action, actor, note, created_at
```

## Rules

1. **`end_date` is mandatory.** A membership can never be created without one. Default: `start_date + 12 months`. There is no open-ended membership in this system.
2. A nightly job sets `status = 'expired'` for every membership where `end_date < today`. It never extends anything.
3. Instructor memberships created from the website arrive as **`pending_verification`**. Gudrun or Robert verifies instructor status, then activates. Until activated, no discount applies.
4. **Discount check at registration:** find a membership for the participant with `status = 'active'` and `start_date ≤ reference_date ≤ end_date`. The reference date is a system setting, default = registration date. Write the result into `registrations.membership_snapshot`:
   `{ active: true, type: 'instructor', valid_until: '2026-12-31', checked_at: '...' }`
5. **The snapshot never recalculates.** If the membership expires afterwards, the already-granted price stands. If Gudrun needs to change it, she does it manually with a reason, and it is logged.
6. **Display everywhere** a participant appears:
   `🟢 Classroom ACTIVE — valid until 31.12.2026` / `Classroom AKTIV — gültig bis 31.12.2026`
   `🔴 Classroom EXPIRED — since 12.02.2026` / `Classroom ABGELAUFEN — seit 12.02.2026`
   `⚪ No membership` / `Keine Mitgliedschaft`
   Gudrun must never have to open a profile to check this.
7. **Renewal reminders** (email, automatic, non-financial so no approval needed, but switchable off in settings): 30 days before, 7 days before, on expiry day, 14 days after. Sent in the member's own language. Every send is written to `email_log`.
8. **Individual discount:** percent or fixed amount, mandatory reason, mandatory "approved by". Robert can propose one; it shows as `Proposed by Robert — not yet confirmed` and appears in Gudrun's Today list. Gudrun confirms or changes it.
9. **Price calculation must be visible** as a small readable box, never a single number:
   ```
   Base price                 €1,200.00
   Classroom discount (30%)     −€300.00
   Individual discount (10%)     −€90.00
   ─────────────────────────────────────
   Final price                  €810.00
   ```
10. Only one membership discount and at most one individual discount per registration, applied in that order.

## Discount codes

Two situations produce the same need and one mechanism covers both: Robert on a stage telling a room "10% on Breath Coach", and Robert on WhatsApp telling one person "I'll give you 15%". Today both end in Excel lists and manual name-matching. A code replaces the matching entirely, because the code carries the identity.

```
discount_codes   id, code(unique, uppercase, citext), kind('campaign'|'personal'),
                 pct, applies_to(text[] of program codes), stackable(bool, default false),
                 max_uses(int; 1 for personal), used_count,
                 valid_from, valid_until,
                 created_by, created_at,
                 for_label(text, personal only: "Nadine, Sandra's friend"),
                 note(text: where they met / which event),
                 sent_via('whatsapp'|'copied'|null), sent_at,
                 status('active'|'used'|'expired'|'revoked')

code_redemptions id, code_id, registration_id, person_id, redeemed_at,
                 discount_amount, invoice_id

code_claims      id, registration_id, person_id, free_text, created_at,
                 status('open'|'confirmed'|'declined'), decided_by, decided_at,
                 resulting_discount_pct
```

11. **Campaign codes** are created in advance by Robert or Gudrun for an appearance: percentage, which programmes, valid until, maximum uses. Printed on a card or shown on the last slide, ideally as a QR that opens registration with the code prefilled.
12. **Personal codes** are created by Robert on his phone at the moment he promises somebody a discount — or by Gudrun when he calls her with one ("give Nadine 15%"). Both roles get the identical three-question flow. The code prefix and the WhatsApp signature follow the creator (`RS-` and "Robert" / `GU-` and "Gudrun, inZENtive", whose message states that Robert promised it), and `created_by` records who made it. The promise itself is always Robert's; the prefix only shows who typed it. The screen asks three things — how much, on which programme, for whom (optional free text, no name or email required) — and produces a one-use code in the form `RS-XXXX` (4 characters from an alphabet without 0/O/1/I), valid 60 days. It offers a ready WhatsApp message with the code and a "Send by WhatsApp" button (a `wa.me` link with the text prefilled). Robert is already sending that message today; now he sends it from the system.
13. **The code is validated in the CMS, not in WordPress or Stripe.** The website passes the code with the registration; the CMS decides. WooCommerce and Stripe coupons must not be used, or there would be two sources of truth for the price.
14. **Validation at registration** returns exactly one of: valid (with pct and who promised it), unknown, expired, already used, not valid for this programme. Each has its own plain-language message. Case-insensitive.
15. **Stacking rule:** by default the *better* of membership discount and code applies, never both. Robert can tick "on top of the Classroom discount" per code, and then both apply, membership first. This must be visible in the price box as separate lines.
16. **A code is Robert's decision, so it needs no separate approval from Gudrun** — but the approval screen shows, per participant, the code, who promised it, when, for whom and the note. She can still override before the invoice goes out, and the override is logged.
17. **Fallback for promises made without a code:** under the code field, a small link "Robert promised me a discount but I have no code" opens a free-text field. This creates a `code_claim`, applies **nothing** automatically, and appears on Gudrun's Today as a task and on Robert's Today as a yes/no. Only a confirmed claim produces an individual discount, with the claim text as the reason.
18. **Each code keeps its own ledger:** redemptions, revenue, and — for personal codes — whether it was sent and whether it was used. The Insights page gets a signal per campaign code ("11 registrations and €6,380 came through CONV26") so an appearance can be measured for the first time.
19. Nothing about codes is ever hard-deleted. Revoking sets `status = 'revoked'` with a reason.
20. **Campaign codes are one-per-person by default.** `code_redemptions` is keyed on `(code_id, person_id)`, so a code with `max_uses = 40` can still be used only once by any one person. Robert promised the Inside Yoga attendees "a training of their choice" — one, not one per training.
21. **List-based promises (Inside Yoga).** When Robert promises a discount to a known group whose list is incomplete, do not try to identify people from the list. Create one campaign code (`INSIDEYOGA`, 10%, all eligible programmes, `max_uses` = names on the list, one per person, valid 12 months) and send it to the people who can be identified. Everybody else identifies **themselves**: at registration they use the "I have no code" claim, which arrives with full name and email attached, and Gudrun checks the claim against the list at approval. The direction reverses — people come to the list, not the list to people. Never create a personal discount on a guessed name.

## Screens
- **Memberships** list: Name · Type · Status badge · Valid until · Days remaining · Last payment. Filters: *Expiring in 30 days*, *Expired*, *Pending verification*, *Active*. Default filter on open: *Expiring in 30 days*.
- **Today** gains three cards: `Memberships to verify (N)`, `Expiring within 30 days (N)`, `Recently expired (N)`.
- Membership tab on the person detail: current membership, full history, "Renew" button that creates a new membership starting the day after the previous end date.

---

# PART D — PROMPT 3: Invoicing, approval, payments

> Add invoicing. Read rules 1–6 in the knowledge base again before writing any code. Nothing is sent automatically; a human approves every invoice.

## Schema

```
legal_entities   id, name, street, postal_code, city, country, vat_number,
                 iban, bic, bank_name, currency_default, logo_file_id,
                 invoice_footer_en, invoice_footer_de, number_prefix

number_sequences id, legal_entity_id, doc_type('invoice'|'credit_note'),
                 year, prefix, next_number   -- gapless, transactional

vat_profiles     id, name, country, rate(numeric), treatment('standard'|
                 'reverse_charge'|'exempt'|'not_taxable'),
                 note_text_en, note_text_de, explanation_en, explanation_de

invoices         id, type('invoice'|'credit_note'),
                 kind('deposit'|'final'|'full'),   -- 50/50 split, see rules 11-14
                 parent_deposit_id,                -- final invoice points at its deposit
                 registration_total,               -- the full amount this invoice is part of
                 number(null until approved),
                 legal_entity_id, billing_snapshot(jsonb), recipient_email,
                 language('en'|'de'), issue_date, due_date, currency,
                 subtotal, discount_total, net_total, vat_profile_id, vat_rate,
                 vat_amount, gross_total,
                 status('draft'|'pending_approval'|'approved'|'sent'|
                        'partially_paid'|'paid'|'overdue'|'cancelled'),
                 approved_by, approved_at, sent_at, pdf_file_id,
                 parent_invoice_id, internal_note

invoice_lines    id, invoice_id, registration_id, participant_name,
                 description, quantity, unit_price, discount_amount,
                 discount_label, line_net, vat_rate

payments         id, invoice_id, amount, currency, paid_on,
                 method('bank_transfer'|'card'|'cash'|'other'),
                 reference, recorded_by, note

refunds          id, credit_note_id, amount, currency,
                 method('bank_transfer'|'stripe'),
                 status('prepared'|'executed'), iban, executed_on,
                 executed_by, reference, note

credit_balances  id, person_id, currency, balance     -- derived, never edited directly

credit_movements id, person_id, amount, currency, direction('in'|'out'),
                 source('rebooking'|'overpayment'|'goodwill'|'applied_to_invoice'|'refunded'),
                 credit_note_id, invoice_id, note, created_by, created_at

cancellation_terms  id, name, version, valid_from, applies_to('events'|'educations'|'all'),
                    bands(jsonb),          -- [{min_days:60, pct:0, fixed:100}, {min_days:30, pct:50}, {min_days:0, pct:100}]
                    basis('total_price'),  -- percentages are of the total ticket price, never of the amount paid
                    transfer_allowed(bool), transfer_min_days(int, 7),
                    health_declaration_hours(int, 72),
                    text_de, text_en
```

`registrations` gains: `terms_id`, `terms_version`, `terms_accepted_at`,
`cancellation_requested_at`, `cancellation_processed_at`, `cancellation_fee_pct`,
`cancellation_fee_fixed`, `cancellation_fee_amount`,
`transferred_from_registration_id`, `transferred_to_person_id`, `transferred_at`,
`health_declaration_requested_at`, `health_declaration_submitted_at`, `health_declaration_file_id`.

`events` gains: `discount_policy('all'|'none')` — see rule 25 — and `check_in_at` (timestamp; the health-declaration deadline is computed from it).

`discount_codes` gains: `one_per_person(bool, default true for campaign codes)`.

## Rules

1. When a registration is created, the system prepares a **draft** invoice automatically, filled with participant, billing recipient, event, price, discounts, currency and a proposed VAT profile. Status `draft`. **No number.**
2. **One invoice can contain several participants.** If a company books four people, Gudrun can group their registrations onto a single invoice, one line per participant, each line carrying that participant's own membership discount. This is a required feature, not an option.
3. **VAT is proposed with a visible explanation**, e.g. *"Proposed: 19% German VAT, because the event takes place in Germany."* Gudrun can change the profile at approval; if she does, she must enter a note. Never apply an irreversible tax treatment from a country rule alone.
4. **Approval screen** — a single page, two columns: left, the invoice exactly as the customer will see it, rendered in the customer's language; right, a **Discounts applied** panel listing, per participant, which discount was granted and why — for a membership discount the date the membership was checked, for an individual discount the percentage, the reason and who approved it — followed by every editable field (recipient, address, VAT ID, line items, prices, discounts, VAT profile, due date, notes). At the bottom, one primary button: **"Approve invoice"**, then a confirmation dialog listing the number that will be assigned, the total, the recipient email and the attachments.
5. On approval, in one transaction: assign the next number from the sequence, freeze the billing snapshot, render the PDF in the recipient's language, store it, set status `approved`, write the audit entry.
6. **Sending is a separate, second click.** "Approve and send" is allowed as one button but the dialog must state both consequences. The email preview is shown before sending, with the invoice PDF and the event's fact sheet attached.
7. After approval the invoice is read-only. Available actions: `Record payment`, `Send again`, `Download PDF`, `Create credit note`, `Internal note`.
8. **Credit note** copies the invoice with negative amounts, links `parent_invoice_id`, takes its own number sequence, and requires a reason. The original invoice becomes `cancelled` only when fully credited.
9. **Payments:** partial payments allowed. Status derives from the sum of payments: 0 → unchanged, >0 and < total → `partially_paid`, ≥ total → `paid`. Past due date and unpaid → `overdue`.
10. **Reminders:** a Reminders view listing overdue invoices with days overdue; Gudrun selects and sends a reminder from a template in the recipient's language. Never automatic. A reminder always uses the **outstanding** amount, never the invoice total, and shows the original invoice, what was received and on which date, what is still open, and the new due date. A partially paid invoice on an event starting within 14 days is flagged separately from a merely overdue one, because educations must be paid in full before the course starts.

## Deposits and final invoices

11. Registrations are invoiced in two parts: **50% deposit** at booking, and the balance as a **final invoice**. The deposit is half of the price *after* all discounts; the final invoice is always `total − deposit`, never an independent calculation, so the two always sum to the total.
12. The final invoice is generated **30 days before the event starts**, with the due date **seven days before the start**. Thirty days is the point at which the Terms & Conditions make 100% of the fee due — invoicing at that moment keeps the amount owed and the amount invoiced identical. Both dates are settings, not constants.
13. A registration created **less than 30 days before the start** gets a single full invoice, no deposit.
14. The final invoice references the deposit invoice by number and date and deducts the VAT already invoiced on it, as German deposit-invoice rules require. Confirm the exact wording with the tax advisor before go-live.
15. The event's fact sheet is attached to the **deposit** invoice, not repeated on the final one — participants need the hotel and travel details early.
16. An unpaid deposit after 14 days appears on Today as *"a place is held but the deposit has not been paid"*, with the waiting list count beside it. The system never cancels the registration by itself.

## Cancellation, place transfer, credit notes and refunds

17. **The fee comes from a versioned terms record, not from code.** `cancellation_terms.bands` holds `[{min_days, pct, fixed}]`; each band runs until the next one begins, so every date returns exactly one result. Bands from the Terms & Conditions Gudrun sent on 21.09.2026:

    | Days before start | Retained |
    |---|---|
    | 60 or more | fixed €100 administration fee, rest refunded |
    | 30–59 | 50% of the total ticket price |
    | 0–29 | 100% of the total ticket price, no refund, no credit |

    Two readings had to be fixed to make this computable and both must be confirmed in the T&C text itself: *"more than 60 days"* is implemented as **60 days or more** (otherwise day 60 falls between two rules), and *"100% of the total transaction value"* is implemented as **100% of the total ticket price** (consistent with band two), which means a participant who paid only the deposit still owes the final invoice.
18. **Retained amount** = `max(total × pct, fixed)`. Refund = `max(0, paid − retained)`. Still owed = `max(0, retained − paid)`, shown on screen and left standing as the open final invoice. When `fixed` exceeds the amount paid (a €117 member Convention ticket has a €58.50 deposit), the screen says so; whether the difference is claimed is a business decision, not a system default.
19. **Two documents currently disagree.** The Immersion fact sheet states 25% up to 8 weeks, 50% up to 6, 100% from 4 weeks and a €50 rebooking with medical certificate, and says these are accepted at registration. The T&C above say otherwise. The cancellation screen shows both amounts side by side in a red note until `cancellation_terms.applies_to` settles which document governs educations. Do not remove that note by deleting it — remove it by resolving the conflict.
20. Every registration stores which **version** of the terms was accepted and when. Changing the terms never changes the fee for somebody who already registered.
21. The fee is calculated from `cancellation_requested_at` — the day the participant sent the cancellation — never from the day Gudrun processes it. Both dates are stored and both are shown. With thresholds at 60 and 30 days a weekend can otherwise move somebody between bands.
22. **Cancelling** produces a credit note for the refundable part only: `paid − retained`. The original invoice is never touched and the retained amount stays as revenue. The credit note goes through the same approval as an invoice. Under 30 days there is no credit note and no credit balance.
23. **Place transfer** replaces rebooking. Until `transfer_min_days` (7) before the start, the place may go to another person for the **same** event, free of charge. The replacement must meet the event's prerequisites (checked exactly as in the wizard; an override needs a reason) and must submit the health declaration at least `health_declaration_hours` (72) before `check_in_at`. **No money moves:** invoice and payments stay with the original billing recipient, the two settle privately. Certificate and education history go to the new participant. Both registrations link to each other and both names stay in the history. The price stays as calculated for the original participant (snapshot rule); the screen flags the case where a member's discounted place goes to a non-member so Robert can decide.
24. **Rebooking to another date (€50 with medical certificate)** is not in the T&C Gudrun sent and is therefore **not built**. The `credit_balances` tables stay in the model for overpayments and goodwill. If rebooking is confirmed to still exist, it returns as a third mode on the same screen using those tables.
25. **Fixed-price events.** `events.discount_policy = 'none'` blocks every discount on that event: the membership discount, campaign and personal codes, and individual discounts. The price box shows "Fixed price — no discounts", the code field refuses any code with "stays valid for other events" and does **not** consume it, and the individual-discount field is disabled. The Summer Summit is the first such event. Nothing about a code can override this flag.
26. **Health declaration.** Every participant, not only replacements, is asked for it; the deadline is `check_in_at − 72h`. It is a document on the registration with a submitted timestamp. The participant list shows it under the "before and after" toggle; Today lists participants who have not submitted it for events starting within 14 days; the reminder template exists in both languages. The system records that it was submitted — it never evaluates its content.
27. **Refunds** are recorded, never executed by the system, and always go to the **original payment method**: for bank transfers the system prepares the instruction with amount and IBAN, Gudrun executes it in e-banking and marks it executed with date and reference; for Stripe the refund is started from the system behind a confirmation dialog and the webhook writes the status back. A refund can never exceed the sum of recorded payments and never exists without a credit note.
28. Stripe keeps its processing fee on refunds. Show this on the refund screen as an informational line; do not deduct it from the refund.

## Reconciliation

24. With a 50/50 split there are two invoices and two incoming payments per participant, so **every invoice carries a structured payment reference containing the invoice number**, and incoming bank transfers are matched against it. The current free-text reference ("Immersion 10.9.-12.09.2026") is human-readable but cannot be matched automatically.

## Screens
- **Invoices** list with status tabs: Awaiting approval · Sent · Open · Overdue · Paid · Cancelled. Columns: Number · Date · Recipient · Participants · Amount · Outstanding · Due on · Status.
- **Payments**: record a payment in three fields (Amount, Date, Reference), with the remaining balance shown live.
- **Outstanding balances** per event and overall: who has paid, who has not, totals in EUR and CHF separately (never mix currencies in a total).
- **Export for the bookkeeper**: date range → CSV with invoice number, date, recipient, country, VAT ID, net, VAT rate, VAT amount, gross, currency, payment date, plus a ZIP of the PDFs.
- **Today** gains: `Invoices awaiting approval (N)` · `Overdue payments (N)` · `Received this month (amount)`.

---

# PART E — PROMPT 4: Documents, certificates, education history

> Add document handling, automatic attachments, certificates and the education history.

1. **Files**: Supabase Storage. Upload fact sheets, manuals, certificate templates, logos. Each document can exist in an English and a German version; the correct one is chosen by the recipient's language. Assign a fact sheet to an event template (inherited) or to a single event (override).
2. **Automatic email after invoice approval**: to the invoice email, in the recipient's language, invoice PDF + event fact sheet attached, from a template Gudrun can edit in settings (both languages side by side). Preview before send. Logged.
3. **Customer area link**: the fact sheet and invoice remain available on the registration record and are exposed via API for the future customer portal.
4. **Attendance and completion**: on the participant list, bulk "Confirm attendance" and "Mark as completed", with date.
5. **Certificates**: generated only for registrations marked `completed` on events whose template has `grants_certificate`. Each certificate gets its own number, issue date, PDF from an A4 template with name, education, dates, location, trainer and signature image, rendered in the participant's language. Bulk-generate for an event, preview one before generating all, then bulk-send or download as ZIP. Certificates can be revoked with a reason, never deleted.
6. **Education history** on the person page: a table — Education · Date · Location · Trainer · Status · Certificate (download). Grouped into *Basic educations* and *Advanced / continuing educations*, newest last. Exportable as PDF for the participant, in their language.
7. **Prerequisites** now read from this history automatically and show on every new registration.

---

# PART F — PROMPT 5: Website connection, waitlist, imports

> The full architecture — who owns which data, the WordPress plugin, the flows — is in `inzentive-website-integration.md`. This part is only the CMS side. Build it against that document; do not invent a second sync design.

## Schema

```
people           + wp_user_id, woo_customer_id
events           + woo_product_id, invoice_policy('manual_approval'|'auto'), check_in_at
programs         + ld_course_id, ld_rewatch_course_id, ld_group_id, is_credential(bool)
memberships      + woo_subscription_id, origin('woo'|'manual')
invoices         + woo_order_id
payments         + stripe_payment_intent_id, woo_order_id
registrations    + health_declaration_token

sync_log         id, direction('in'|'out'), system('woo'|'learndash'|'wp'),
                 event_type, entity_table, entity_id, external_id, event_id(unique),
                 payload(jsonb), summary_en, summary_de, status('ok'|'failed'|'ignored'),
                 error, created_at

sync_outbox      id, target, endpoint, method, payload(jsonb), event_id(unique),
                 attempts, next_attempt_at, last_error, status('pending'|'sent'|'failed'),
                 created_at, sent_at
```

## Rules

1. **Ownership is fixed, not negotiated per record.** The CMS owns events (dates, prices, capacity, VAT, discount policy), registrations, memberships' verification and manual memberships, invoices/payments, codes, certificates and course-access decisions. The website owns logins, digital purchases, paid-subscription lifecycles, content, and course completions. The ownership map in the integration document is the specification; the *Website connection* screen displays it verbatim.
2. **Inbound webhooks** land on one edge function `POST /webhooks/wordpress`. Verify the HMAC signature, deduplicate on the event ID (a repeat returns 200 and does nothing), write a `sync_log` row **before** processing, then process. Every inbound event produces a plain-language `summary_en/de` ("Membership renewed · Beata Tothova · until 07.09.2027").
3. **Outbound pushes** never call the website directly from business logic. They insert a `sync_outbox` row; a worker sends with exponential backoff for 24 hours, sets `X-Inzentive-Origin: cms` and an event ID on every request, and writes a `sync_log` row per attempt. After 24 hours the row is `failed` and appears on Today as *"a change did not reach the website"*. Nothing is silent, nothing is dropped.
4. **No loops.** Ignore an inbound event whose payload hash equals the last value the CMS pushed for that entity.
5. **Field-level pushes only.** `PUT /events/{woo_product_id}` sends price, stock, open/closed, dates, fact-sheet URL, discount policy — never title, description, images or slug.
6. **`invoice_policy`.** Orders for products marked `auto` (digital products, memberships) create an invoice that is approved automatically — same sequence, same PDF, same export — and logged as "issued automatically (digital product, no review needed)". Products marked `manual_approval` follow the existing draft → approve flow. The setting lives on the product type, not on the invoice.
7. **Course access** is granted and revoked only through the outbox (`POST /inzentive/v1/access`), with a reason and optional `expires_at`. The nightly membership-expiry job enqueues a revoke for every membership that expired that day. Completing a live education enqueues a grant for the programme's `ld_rewatch_course_id`, permanent.
8. **Credential completions** arriving from LearnDash (`programs.is_credential = true`) create the education-history row, the certificate and the licence exactly as a live education would. Content-course completions are stored for Insights only.
9. **Public API for the website** (server-to-server, shared secret): `GET /api/events`, `POST /api/pricing`, `POST /api/registrations`, `POST /api/codes/validate`, `POST /api/waitlist`, `GET /api/me`, `POST /api/health-declaration/{token}`, `GET /api/verify/{number}`. `POST /api/pricing` is the single pricing function — the same one the wizard and the approval screen use. Never a second implementation.
10. **Health declaration**: 14 and 7 days before an event, enqueue signed-link emails to every registration without `health_declaration_submitted_at`; the deadline shown is `check_in_at − 72h`.

## Screens

- **Website connection** (sidebar group *System*, badge = failed pushes): header with status badge ("Everything arrived" / "N changes did not arrive") and last-72h counts; tab *Log* — newest first, arrow for direction, system, plain-language summary and detail, status badge, *Retry now* on failures; tab *What lives where* — the ownership map with owner badge and "the other side gets". Read-only for Robert.
- **Today**: card *"N changes did not reach the website"* linking to the log.
- **Help**: section *Where do I do what — website or back office*.

## Also in this part

11. **Waitlist**: registrations beyond capacity are placed on the list automatically with a position. When a place frees up, the system does **not** promote anyone automatically — it creates a task on Today: *"Place available: Somatic Breathing Berlin — first on the waiting list is Anna Smith. Confirm now?"*
12. **Initial load**: importers for the Wix membership CSV, and pulls from the Woo REST API (customers, orders of the last 24 months, subscriptions) and LearnDash (enrolments, completions). Column mapping, dry run with a preview of created/updated/skipped, duplicate detection by email, rollback of the last import.
13. **Duplicate merge tool**: same email or very similar names, side by side, merge with a full audit trail; the surviving `wp_user_id` is pushed to the website, no WP user is ever deleted.
14. **Backups**: an "Export everything" button producing a ZIP of all tables as CSV plus all PDFs.

---

# PART G — ACCEPTANCE TESTS

Run these with Gudrun before go-live. Each must pass exactly.

1. **The company case.** Smith Fitness GmbH registers four employees for one education. Anna has an active Classroom membership, Peter none, Maria's expired last month, Lisa's is active. Result: Anna and Lisa get the member price, Peter and Maria the regular price, all four appear as separate lines on **one** invoice addressed to Smith Fitness GmbH, and the participant list shows the correct membership badge for each.
2. **Expired membership.** A participant whose membership expired yesterday registers today and receives the regular price, with `Classroom EXPIRED — since …` shown at every step.
3. **No silent expiry.** A membership with `end_date` in the past can never show as active anywhere in the system.
4. **No auto-send.** No invoice can reach a customer without a human clicking approve. Verify by creating ten registrations and confirming nothing left the system.
5. **Immutability.** After approval, no field of the invoice can be changed by any role, including admin. The only path is a credit note.
6. **Gapless numbering.** Twenty invoices approved in sequence produce twenty consecutive numbers with no gaps, even with two approvals at the same second.
7. **Address snapshot.** Change a company's address after an invoice was approved; the old PDF and the stored data are unchanged.
8. **Robert's limits.** Logged in as Robert, no create/edit/delete/send is possible anywhere; a discount proposal is possible and appears in Gudrun's queue.
9. **Partial payment.** A €900 invoice paid €400 shows `partially paid`, an open balance of €500, and appears in Outstanding balances.
10. **Waitlist.** A full event still accepts registrations, places them on the list with positions, and a cancellation creates a task rather than promoting silently.
11. **Prerequisites.** Registering someone without the required basic education flags it, names the missing education, and still allows an override with a reason.
12. **Language independence.** Gudrun works with the interface in German while an invoice for an English-speaking participant renders, sends and archives in English — and the same test in reverse.
13. **German layout.** Every screen switched to German shows no truncated buttons, no wrapped table headers, no untranslated strings.
14. **Deposit arithmetic.** A €408.17 registration produces a €204.09 deposit and a €204.08 final invoice that sum exactly to €408.17, with the final invoice referencing the deposit's number and date.
15. **Late booking.** A registration created three weeks before the start produces one full invoice and no deposit.
16. **Fee bands.** Cancelling a €408.17 booking for a 10.09 event with only the €204.09 deposit paid returns: 11.07 and 12.07 (61 and 60 days) → €100 retained, €104.09 refund; 13.07 (59 days) → €204.09 retained, €0 refund; 11.08 (30 days) → same; 12.08 (29 days) → €408.17 retained, €0 refund, **€204.08 still owed** on the final invoice. No date falls between two bands.
17. **Received, not processed.** A cancellation received on Friday and processed on Monday is charged at Friday's band.
18. **Place transfer.** Nine days before the start, Beata's place goes to Stephanie: no credit note, no refund, invoice unchanged, Stephanie receives the fact sheet and a health-declaration request due 72h before check-in, the certificate later goes to Stephanie, and the history shows both names. The same transfer five days before is refused; a transfer to somebody missing the prerequisite is refused without an override reason.
18a. **Fixed price.** On the Summer Summit an active member pays full price, any code is refused with "stays valid for other events" and is not consumed, and the individual-discount field is disabled.
18b. **One per person.** The same person redeeming `INSIDEYOGA` on a second registration is refused; a different person on the same code is accepted while `used_count < max_uses`.
18c. **Two documents.** With both terms records loaded, the cancellation screen for the Immersion shows both amounts for 27.07 (€204.09 under the T&C, €102.04 under the fact sheet) until `applies_to` resolves it.
19. **Refund ceiling.** A refund larger than the sum of recorded payments is rejected, and no refund can be recorded without a credit note.
20. **Personal code round trip.** Robert creates a 15% code for "Nadine, Sandra's friend" on Breath Coach Basic. A registration for Basic with that code gets 15%, the approval screen shows "promised by Robert on DD.MM.YYYY, for Nadine, Sandra's friend", and the code is marked used. A second registration with the same code is rejected as already used.
21. **Wrong programme.** The same code entered on an Immersion registration is rejected with "applies to Breath Coach Basic, not to Immersion" and the price is unchanged.
22. **Stacking.** An active member enters a non-stackable 10% code and receives 30%, not 40%. The same member with a stackable 10% code receives both, shown as two separate lines.
23. **Claim never auto-applies.** A registration with "Robert promised me a discount" and no code produces a full-price draft invoice and one open claim on both Today screens; confirming the claim adds an individual discount with the claim text as the reason.
24. **Campaign ledger.** After eleven redemptions the campaign code shows 11/40 used and the sum of the discounted line totals as revenue.
25. **Website round trip.** A registration submitted on the website appears in the CMS within 60 seconds with a draft invoice and one `sync_log` in-row; confirming it pushes the new free-places count and produces one out-row and zero in-rows (no loop).
26. **Failed push is visible.** With the website endpoint down, a price change shows on Today as "did not reach the website" within 15 minutes, and the log shows the retries.
27. **Auto invoice.** A €20 digital purchase produces one numbered invoice in the same sequence without any approval click, logged as issued automatically.
28. **One-question test.** Gudrun, without help, can answer "Is Anna registered for anything, has she paid, and is her membership still valid?" in under 30 seconds from the home screen.

---

# APPENDIX — Questions for Robert and Gudrun

*Send now. Items 1–5 block Part D; do not build invoicing before they are answered.*

**Legal and tax**
1. Which legal entity issues which invoices? inZENtive is registered in Zurich, but German educations carry 19% German VAT. Is there a German VAT registration or a second entity? This decides whether we need one or two invoice number ranges.
2. Do you need to continue an existing invoice number sequence, or start fresh? What format (e.g. `2026-0001`, `RE-2026-0001`)?
3. Which cases of reverse charge actually occur in practice — EU companies with a VAT ID booking a German event?
4. Which bank accounts and currencies appear on invoices (EUR and CHF separately)?
5. Which software does the bookkeeper use — bexio, DATEV, Lexoffice, something else? That decides the export format.

**Commercial**
6. Is the Classroom discount based on the membership being valid on the **registration date** or on the **event date**? What happens if it expires in between?
7. Are deposits or instalments used for the larger educations (e.g. €2,000 Breath Coach)? If yes, what is the standard split?
8. What are the cancellation terms — free until when, what fee after, and does a cancellation produce a credit note or a partial one?
9. Standard payment term — 14 days, 30 days, before the event starts?
10. Standard Classroom discount — is it always 30%, or does it differ per event?

**Operational**
11. Who may cancel a registration, and does Robert need to approve it?
12. Where does the current membership data live and can it be exported (CSV)? Does every record have a real end date, or do we need to set them during import?
13. What does the WordPress site currently use for registrations — a plugin, WooCommerce, a plain form?
14. Who reviews the German translation of the interface before go-live — Gudrun? It should not go live machine-translated.
15. Do certificates need a specific number format and a scanned signature?
