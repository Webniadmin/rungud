# InZENtive — Website integration (v2.1, 24.09.2026)

*v2.1 = v2 with the seven corrections from the website side (checked against the site's code), adopted verbatim.*

*v2.2 (24.09.2026): the CMS is a plugin inside the site (docs/PROMPT.md §1). No outbox, no automatic retries, no copy tables — §3, §4, §5 and test §7.9 updated accordingly.*

*Rewritten after the answers from the website side. This version replaces v1 entirely. The build package's Part F must be read against this document.*

---

## 0. What changed, and the principle that follows

In v1 I made the back office the owner of events, prices, memberships and invoices. The website side has since explained how the site actually works, and the honest conclusion is that **the back office should own nothing that lives on the site.** It reads, it displays, it sends commands through endpoints, and it keeps the audit trail. That is less code, less risk, and it matches what Gudrun needs: one screen per person where she can see everything and press the buttons a bookkeeper presses.

The principle stays: every fact has one owner; the other side gets a copy or a command; every command and every read that crosses the boundary is logged and visible.

**Three corrections carried over from the website side, adopted as written:**

- **Licence ≠ access.** B2B content needs *two* conditions: a verified licence for that programme **and** an active membership (or a valid card). Either one alone shows the programme with the lessons locked. The site enforces this in LearnDash groups (`certification_reconcile()`); the CMS only writes the licence and never derives access from the number.
- **Instructor discount on events is not tied to the licence.** The member price applies to anyone with an active membership on any plan. Both prices sit on the Woo product; the site applies the member price via a price filter. The CMS does not compute event prices.
- **Redeem codes are WooCommerce coupons** with meta (`_inzentive_valid_card = yes`, `_inzentive_card_days`, `_inzentive_card_scope = b2c | all`). One use per user is Woo's usage limit; the site refuses a new code while an old one runs; expiry is the site's daily cron. The CMS creates them through `/wc/v3/coupons` — it does not keep its own table.

---

## 1. Ownership map

| Data | Owner | The CMS does | Endpoint / source |
|---|---|---|---|
| Events, courses, lessons, programmes, prices, member prices, capacity (= Woo stock), page content | **WordPress / WooCommerce** | reads: remaining places, price, member price, dates | Woo REST `/wc/v3/products` |
| Event and course purchases, their invoices | **WooCommerce** | reads orders and invoice PDFs; sends a PDF to any address with a cover text; refunds through Woo | Woo REST `/wc/v3/orders`, `/orders/{id}/refunds`, PDF plugin URL |
| Named participants on a company order | **CMS writes, Woo stores** | Gudrun types the names; the CMS writes `_inzentive_attendees` = `[{name,email}]` (length = quantity) as meta on the **order line item**, not the order — one order can hold tickets for several events, and the site's participant export reads per line item. A participant needs no account; the buyer does | Woo REST line-item meta |
| Membership plans (4, defined in code), subscription billing, renewals, membership invoices | **LearnDash + Stripe** | reads plan and status; reads Stripe invoices; cancels at period end / immediately / refund+cancel; sends a checkout link for a new paid plan | site endpoints + Stripe API |
| Free or manual access with an end date ("valid card") | **Site** | grants with `expires_at`; the site expires it itself | site endpoint |
| Licences per programme (org, number, `pending/verified/rejected`, `valid_until`) — **keyed on (user, programme), no own id** | **Site (user meta `_inzentive_licenses`)** | adds, verifies, rejects, revokes; picks the programme(s) explicitly; the `BW-…` prefix is a readable label from which the CMS *suggests* the programme — the site never reads access from the number | site endpoint |
| Redeem codes (timed access) | **WooCommerce coupons** | creates with meta, lists, disables | Woo REST `/wc/v3/coupons` |
| Discount codes on events (personal / campaign) | **WooCommerce coupons** | creates per-person or per-campaign coupons restricted to product, with usage limits | Woo REST `/wc/v3/coupons` |
| Access overview per person (plan, groups, licences, valid card, courses) | **Site** | reads only | site endpoint |
| People: email, login, profile | **WordPress** | reads; changes email through WP REST (the site emails the old address). **The CMS never creates WP accounts** — account and role arise from registration on the site. A licence or grant for somebody without an account = the CMS sends a registration invitation (audit row); once the account exists, Today shows *"account created — add the licence/grant now"* and Gudrun adds it with one click. Nothing is written automatically | `/wp/v2/users/{id}` |
| Instructor verification | **Site**, expressed as a licence | Gudrun verifies the licence in the CMS → site | site endpoint |
| Certificates (PDF, verify page), education history | **CMS** | issues; the licence number on the site is the same number | CMS |
| Audit log of everything above | **CMS** | every read of a PDF, every send, every command, with who/when/result | CMS `wp_rungud_audit` |

Two things the CMS **stops** doing compared to v1: it does not generate invoices for site sales, and it does not push prices or stock to Woo. The CMS invoice engine (Part D) remains for what the site does not sell: anything invoiced by hand — company billing corrections, credit notes for bank-transfer refunds, and whatever Gudrun issues outside the shop. Whether that is a lot or almost nothing is a question for her (see §6).

---

## 2. Gudrun's screen: one person, everything

This is the deliverable she asked for. On the person page, a tab **Access, memberships and licences** with these blocks — every button calls one endpoint and writes one audit row.

**Membership**
- plan, status, current period end, Stripe customer link
- *Cancel at period end* → `POST /membership/cancel-at-period-end` (Stripe `cancel_at_period_end`; access stays until the date; the site's webhook and daily cron close it)
- *Cancel now* → `POST /membership/cancel-now` (Stripe cancel + LearnDash group removal)
- *Refund and cancel now* → `POST /membership/refund-cancel` (amount ≤ last Stripe charge; confirmation dialog states the amount and the card)
- *Send checkout link for a plan* → the CMS cannot charge somebody's card; it emails the link to the plan's pricing page, logged as sent. The site requires login (no guest sales) and refuses a B2C plan on a professional account — so for a professional the CMS offers only the instructor plan
- *Grant free access until …* → `POST /access/grant {scope: b2c|all, expires_at, reason}` — the site's valid-card mechanism, no coupon; the site expires it. If an active grant already exists, a new one **extends** it to the later `expires_at`; it never creates a second

**Licences**
- list from `_inzentive_licenses`: programme, organisation, number, status, valid until
- *Add licence* → programme picker (explicit, multi-select), organisation, number (CMS proposes the next `PREFIX-SEQ` and suggests the programme from the prefix; both editable), valid until → `POST /licences {status: verified}`
- *Verify* / *Reject* on pending ones the user self-reported on the site → `POST /licences/{user_id}/{program_id}/verify|reject`
- *Revoke* with reason → `POST /licences/{user_id}/{program_id}/revoke`
- the note under the block, verbatim: *"A licence alone does not open the lessons. The person also needs an active membership or free access. The site checks both."*

**Redeem codes**
- *Create timed-access code* → days (7 / 30 / custom), scope (B2C only / all programmes the person is licensed for), optional restriction to this person's email (Woo *Allowed emails* — the site adds the check at redeem, it did not exist), note → `POST /wc/v3/coupons` with the meta above; the code is shown once with a copy button and a WhatsApp/email text
- list of codes created for this person with used/unused and expiry

**Access overview (read-only)** → `GET /access/{user}`: plan, groups, licences, valid card, enrolled courses with completion — the same data the site uses, so what Gudrun sees is what the person sees.

**Invoices and orders** (also on the person page, and in the global Invoices list)
- Woo orders: number, date, items, total, status, invoice PDF (from the PDF plugin's URL) — *Download*, *Send to…* (address field + cover text, defaults per language, logged), *Refund* (full or partial through `/orders/{id}/refunds`; the site removes access itself unless another paid order covers it)
- Stripe invoices for the membership: number, date, amount, PDF (`invoice_pdf` URL) — *Download*, *Send to…*
- CMS invoices (hand-issued) — as in Part D

**Participants on a company order**
- on a Woo order line with quantity > 1: a small table where Gudrun types name + email per seat → written to `_inzentive_attendees` on that line item (array length = quantity); each seat then appears in the event's participant list with its own health-declaration and certificate row. Per-person discounts on such orders are Woo coupons created from the CMS.

---

## 3. Transport and safety

- **Stripe webhooks:** the CMS registers **its own** Stripe webhook endpoint for `customer.subscription.*` and `invoice.paid/payment_failed`. It never edits LearnDash's endpoint — LD rewrites its own event list on reconnect.
- **Auth:** none between CMS and site — the CMS is a plugin inside the same WordPress. It calls the `inzentive/v1` routes internally (`rest_do_request`) or the site's PHP functions directly, and Woo through its PHP API. No Application Passwords, no Woo REST keys. Stripe: a restricted key (read invoices, cancel subscriptions, refund).
- **Every command runs synchronously on a human click and writes one `wp_rungud_audit` row** with the result (ok / failed + error) and a plain-language German and English summary; every read that produces a document (PDF fetched, email sent) writes an audit row too. **There is no outbox and no automatic retry.** A failed command appears on Today as *"did not reach the website"* with a *Retry* button; Gudrun retries by hand, and the retry is its own audit row. Nothing is silent.
- **Every destructive command has a confirmation dialog** that states the consequence in one sentence with the amount, the date and the person, in the operator's language.
- **The CMS never writes to LearnDash tables, Woo tables or user meta directly** — only through the site's endpoints and the two REST APIs. If an endpoint is missing, the site side adds it; the CMS does not go around it.
- **Idempotency:** every command carries an event ID; the site returns 200 on a repeat.

### Endpoints the site provides (`/wp-json/inzentive/v1/`)

| Endpoint | Action |
|---|---|
| `GET /access/{user_id}` | plan, groups, licences, valid card, courses + completion, **`stripe_customer_id`, `stripe_subscription_id`** — the CMS reads membership invoices straight from Stripe with those (LearnDash does not record Stripe renewals) |
| `POST /licences {user_id, program_id, organisation, number, valid_until}` · `POST /licences/{user_id}/{program_id}/verify` · `/reject` · `/revoke` | licence lifecycle — key is (user, programme); revoke of a verified licence is new on the site and built together with the endpoint |
| `POST /membership/cancel-at-period-end` · `/cancel-now` · `/refund-cancel` | subscription lifecycle (Stripe + LD) |
| `POST /access/grant` `{user_id, scope, expires_at, reason}` | valid card without a coupon |

Everything else is Woo REST (`products`, `orders`, `refunds`, `coupons`) and WP REST (`users`).

---

## 4. Flows

1. **Event or course bought on the site.** Woo order → the CMS reads it live (`wc_get_orders()`) under the person and under the event; participant list of the event = orders of that product (+ attendee meta). No CMS invoice. Health declaration and certificate work per seat as before.
2. **Company buys four seats.** One order, quantity 4, billing = company. Gudrun opens the order in the CMS, types four names on the Immersion line → line-item meta → four rows in the participant list. Participants do not need accounts. Per-person discount → a Woo coupon made from the CMS before purchase, or a partial refund after.
3. **Membership.** Bought on the site through LearnDash's Stripe. The CMS reads plan/status/period end live (`GET /access/{user}`, Stripe API) and lists the Stripe invoices; its own Stripe webhook only feeds audit rows and Today (e.g. `invoice.payment_failed`), it stores no copy. Cancel/refund from the CMS through the site endpoints. Expiry is the site's job; the CMS only reflects it.
4. **Instructor arrives.** Self-reports a licence on the site → `pending` → shows on Gudrun's Today → she verifies in the CMS (or rejects) → site reconciles groups. Or she adds a licence herself for someone who never self-reported.
5. **Free trial / goodwill.** Gudrun creates a 7-day redeem code (Woo coupon with meta) and sends it, or grants a valid card directly with an end date. Both expire on the site.
6. **Refund.** Woo order → Woo refund API (Stripe under it); membership → `/refund-cancel`. The CMS records who, when, how much, why. Bank-transfer refunds stay in the CMS refund flow (Part D).
7. **Send an invoice.** Gudrun opens any order/invoice, presses *Send to…*, enters an address (defaults to the customer), keeps or edits the cover text, sends. The PDF comes from the Woo PDF plugin or from Stripe; the CMS never re-renders it. Logged.
8. **Email change.** CMS → WordPress user update (the site emails the old address). The CMS has no person record of its own; it reads `WP_User` live.

---

## 5. Schema on the CMS side (replaces the v1 and v2 additions)

**No copy tables.** People, orders, memberships, Stripe invoices, licences, grants and coupons are read live from `WP_User`, WooCommerce, the `inzentive/v1` endpoints and Stripe. The plugin's own tables (prefix `wp_rungud_`) hold only what the site does not have — see `docs/PROMPT.md` §2:

```
invoices, payments, refunds         hand-issued invoices and their money (Part D)
certificates, education_history     issued by the CMS
discount_codes, code_claims         intent behind Woo coupons the CMS created; promises without a code
document_sends                      doc_type('woo_invoice'|'stripe_invoice'|'cms_invoice'|'certificate'),
                                    doc_ref, to_email, subject, body, sent_by, sent_at, status
audit                               every command and send: actor, entity, action, before, after,
                                    status, error, summary_de, summary_en
vat_profiles, legal_entity, settings, cancellation_terms, number_sequences
```

Removed: `site_orders`, `site_memberships`, `stripe_invoices`, `site_licences`, `site_grants`, `site_coupons`, `sync_log`, `sync_outbox`, the `people` additions.

---

## 6. Decisions still open — two for Gudrun and the bookkeeper, one for the site side

1. **Three number sequences** (Woo, Stripe, CMS) are normally permitted in Germany as long as each is unique and consecutive. Two things for the bookkeeper to confirm: that three sequences are acceptable for them, and **whether Stripe's invoices meet the formal requirements** (customer address, VAT rate and amount, inZENtive's tax number). If Stripe's do not, membership invoices have to be re-issued by the CMS from Stripe data — decide before Part D is touched.
2. **Woo PDF invoice plugin** — check on the live site. If none: install one standard plugin that numbers gaplessly and exposes a PDF URL/REST. Without it there is no PDF to send.
3. ~~`_inzentive_attendees` shape~~ — confirmed: `[{name, email}]` on the line item, length = quantity.

---

## 7. Acceptance tests (integration)

1. Gudrun cancels a membership at period end: Stripe shows `cancel_at_period_end`, the person keeps access until the date, the CMS shows the date, and the audit log shows one command.
2. Gudrun cancels now: Stripe subscription cancelled, LearnDash group removed within one minute, access overview reflects it.
3. Refund and cancel: Stripe refund for the stated amount, subscription cancelled, one audit row with amount and reason.
4. Add a verified licence for programme X: `_inzentive_licenses` shows it; the person with an active membership sees X unlocked; the same person after membership expiry sees X locked with the lessons visible.
5. Create a 7-day B2C redeem code: a Woo coupon with the three meta fields exists; redeemed once, refused the second time and refused while active; access closes on day 8.
6. Send invoice to a third address: the PDF from the Woo plugin (or Stripe) arrives with the cover text; `document_sends` has the row.
7. Company order with four seats: four attendee rows appear in the event's participant list and in the site's participant export.
8. Refund a Woo order from the CMS: Woo refund exists, Stripe refund exists, the site removed course access, the CMS shows the refund on the order.
9. Site endpoint down: a cancel command fails, writes an audit row with the error and shows on Today as "did not reach the website"; once the endpoint is back, a retry click succeeds and writes its own audit row.
