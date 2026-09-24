# rungud — status and next steps

*Last updated 24.09.2026, end of day. Read this first when you pick the work up again.*

## Where we are

| Phase (PROMPT.md §5) | State |
|---|---|
| 0 — Repo + tooling | ✅ done |
| 1 — Plugin core (tables, roles, JWT, audit, settings page, Today) | ✅ done |
| 2 — Read-only screens | ✅ done locally · ⏳ Vercel + link for Gudrun wait for the public staging URL |
| 3 — Commands | ✅ built and tested · ⏳ membership cancel/refund blocked on the site (see "Waiting on the site team") |
| 4 — Discount codes + claims | ⬜ next |
| 5 — Hand-issued invoices | ⬜ |
| 6 — Certificates + education history | ⬜ |
| 7 — Today, Insights, Messages, Help | ⬜ |
| 8 — Import (Wix CSV, past educations) | ⬜ |

CI (GitHub Actions, `main`): plugin lint + unit tests (PHP 8.1, 8.3), integration tests in wp-env (WordPress 6.9 + WooCommerce 11.0.1, site double), frontend lint + tests + build. Last green: 59 integration, 26+ unit, 258 frontend tests.

## What exists

**Plugin (`plugin/`)** — REST `/wp-json/rungud/v1`:

- Auth: `POST /auth/login|refresh|logout`, `GET /auth/me`. Access token 15 min, rotating refresh token 30 days, 60 s reuse grace (reloads, two tabs), lockout after 5 failures.
- Reads: `/today`, `/people` (+ `/{id}`, `/{id}/access`, `/{id}/documents`, `/{id}/redeem-codes`), `/events` (+ `/{id}` with participants and names per place), `/orders` (+ `/{id}`), `/memberships`, `/audit`, `/programs` (with next licence number), `/plans`, `/site/capabilities`.
- Commands (one audit row each, `request_id` per dialog, ok / refused / failed, manual Retry): licences add / verify / reject / revoke, free access grant, membership cancel-at-period-end / cancel-now / refund-cancel (route discovery — disabled until the site has them), checkout link, redeem codes (Woo coupons with the site's meta), names per place (`_inzentive_attendees` on the line item), Woo refund, send document, `POST /audit/{id}/retry`.
- WP admin → Settings → rungud: connection (Stripe, CORS, SMTP), website links (pricing page), legal entity, VAT profiles, invoice number sequence (next 100649, raise only).
- Mail: from info@inzentive.online; `RUNGUD_MAIL_MODE=log` outside production — nothing is sent, the send is logged.

**Frontend (`web/`)** — the prototype's stylesheet and icons ported verbatim; EN default, DE switch; Robert (owner) sees everything and no command anywhere (enforced by the API, tested per route).

Screens built: Login, Today, People, Person (Overview · Access, membership, licences · Invoices and orders), Events, Event (Overview · Participants · Waiting list · History), Invoices (website orders tab), Memberships, Website connection (Log · What lives where). The other prototype screens show which phase fills them.

## Decisions taken (keep)

1. **No outbox, no automatic retries** (rule 10). A command that fails is an audit row with the error, shows on Today as "did not reach the website", and Gudrun retries by hand. `website-integration.md` v2.2 says so.
2. **In-process, not HTTP.** The plugin calls `inzentive/v1` with `rest_do_request()` and Woo through its PHP objects (WC_Coupon, WC_Order_Item, `wc_create_refund`). No Application Passwords, no Woo REST keys.
3. **The site's API capability is widened only for the duration of our own call** (`inzentive/api/capability`), after our route checked the rungud capability. Gudrun never gets `edit_users`.
4. **Refused vs failed.** The site said "no" (4xx) → refused: shown in the dialog, logged, not on Today. The site did not answer / broke / route missing / permission → failed: Today + Retry. Retries send a fresh idempotency key (the site stores refusals for 48 h).
5. **Summaries tell the truth**: rows that were not done start with "Not done:" / "Nicht ausgeführt:".
6. **Participant amounts are gross** (what the customer paid), same as orders; the site's `event_attendees()` row is net.
7. **People list excludes staff** (administrators and the two rungud roles).
8. **Licence number proposal** = highest used number with that prefix + 1 (site licences except rejected, programme certification numbers, CMS certificates); Gudrun can change it.
9. **Refusal texts are translated in the UI** from reason keys (`reasons.*`), including the site's `inzentive_api_*` codes.
10. **UI fidelity over shadcn**: prototype CSS ported as is; shadcn is configured but not used for the look.
11. **Help page deferred to phase 7** — the prototype's text describes the old model (prices changed in the back office) and must be rewritten.
12. **WordPress 6.9 + WooCommerce 11.0.1 in CI** (WooCommerce 11.1 needs WP 7.0).

## Waiting on the site team

| # | What | Blocks |
|---|---|---|
| 1 | `POST inzentive/v1/membership/cancel-at-period-end`, `/cancel-now`, `/refund-cancel` (`{user_id, amount?, reason}`) | Phase 3 membership buttons (they switch on by themselves once the routes exist) |
| 2 | Commit `app/api.php` (untracked) and the `functions.php` line that loads it; deploy | Everything on live/staging |
| 3 | Read `_inzentive_attendees` in `event_attendees()` / the participant export | Site export shows names per place |
| 4 | Health declaration storage per seat | Participant list column, Today item |
| 5 | Waiting list names (only `waitlist_count()` exists) | Event → Waiting list tab |
| 6 | Stored Stripe customer id (API only guesses `get_gateway_customer_id()`) | Stripe customer link |
| 7 | WooCommerce PDF invoice plugin ("PDF Invoices & Packing Slips for WooCommerce") | Sending website invoices; PDF links |
| 8 | A distinct `revoked` licence state (revoke is stored as `rejected`) | Clear history for Gudrun |
| 9 | LearnDash on the staging copy | Memberships list, free access, course progress |

## Waiting on you / Gudrun

- **Public staging URL** (WordPress copy + Stripe test mode) → then: Vercel `VITE_API_BASE` for Preview, add the Vercel domains to CORS, give Gudrun the link.
- **Live CMS domain** — recommendation `backoffice.inzentive.online` (CNAME to Vercel); confirm the live site is `inzentive.online`.
- **Pricing page URL** for the checkout-link e-mail (WP → Settings → rungud → Website links).
- **Stripe restricted test key** + webhook secret on staging.
- Robert's WP account must hold **only** the rungud Owner role (separate account if he is admin/shop manager today).
- Open questions from PROMPT §8: #5 cancellation terms (fact sheet vs T&C), #6 signature image + legal block (only a placeholder today), #7 the site team's e-mail HTML sample.
- Invoice numbering: confirm 100648 is the last used number (next = 100649) and whether credit notes share the sequence (phase 5).

## Next session — phase 4 (discount codes + claims)

Read first: prototype screens `codes` and `promise`, `build-package.md` Part C (discount codes, rules 11–21) and D rule 25, `website-integration.md` §1 (codes are Woo coupons).

1. Discount codes list (prototype "Discount codes"): campaign + personal, used / revenue per code (from Woo coupon usage), filter chips.
2. **Promise a discount** (Robert and Gudrun, three questions: how much, which programme, for whom) → one-use Woo coupon `RS-XXXX` / `GU-XXXX`, 60 days, product restriction from the programme's products, WhatsApp text + `wa.me` link. Robert's role needs the `promise` capability only.
3. Campaign codes: percentage, programmes, valid until, max uses, one per person (Woo `usage_limit_per_user = 1`).
4. Claims queue ("Robert promised me a discount, no code") — `wp_rungud_code_claims`; Robert's Today yes/no, Gudrun's Today task; confirming creates a personal code for that person.
5. Discount policy per event (CMS setting keyed on event slug): fixed-price events refuse codes the CMS creates.
6. Today: `promises_unused`, `code_claims_open`, `code_claims_to_decide`.
7. Tests: Part G 18a, 18b, 20, 21, 23, 24 (as far as Woo coupons allow); Robert can promise but nothing else.

Then phase 5 (hand-issued invoices) — needs the answers on numbering and VAT first.

## Local development

- Site copy: `projects/inzentive`, served by `php -S 127.0.0.1:8080` + proxy on `localhost:4000/inzentive`. Plugin is a symlink `wp-content/plugins/rungud-cms → rungud/plugin`; config block "rungud CMS (local only)" in its `wp-config.php` (JWT secret, CORS `localhost:5173`, `RUNGUD_MAIL_MODE=log`).
- Test logins (local DB only): `gudrun.test` (back office), `robert.test` (owner). Passwords are not in the repo — ask Webni or reset with `wp user update gudrun.test --user_pass=… --skip-email`.
- Frontend: `cd web && npm run dev` (uses `web/.env.local` → `http://localhost:4000/inzentive/wp-json/rungud/v1`).
- The site team's `tools/seed-local.php` recreates their test data (IDs change); it does not touch `wp_rungud_*` or the two logins.
- Test data changed during phase 3 testing (local only): Jonas Weber's BW-2210 verified; redeem codes TRY-YETT, TRY-K5L7; names on order #130; €5.00 refund on order #124; failed free-access rows on Today (expected without LearnDash).
