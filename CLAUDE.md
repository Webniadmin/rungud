# rungud CMS — rules

These rules are copied verbatim from `docs/PROMPT.md` §7. They apply to every change in this repository.


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

## Where the work stands

Read `docs/STATUS.md` first: phase status, decisions taken, what is waiting on the site team, and the plan for the next session. Update it at the end of every phase.

## Repo layout

- `plugin/` — WordPress plugin `rungud-cms` (PHP 8.1+). Owns tables, REST API `/wp-json/rungud/v1/*`, capabilities, PDFs, Stripe webhook, cron, audit.
- `web/` — React + Vite + TypeScript. UI only; talks only to the plugin's REST API.
- `docs/` — PROMPT.md (build instructions), prototype.html (approved UI), website-integration.md, build-package.md, analysis.md.

## Commands

- Plugin unit tests (no WordPress): `cd plugin && composer test:unit`
- Plugin integration tests (needs Docker): `cd plugin && npx wp-env start && composer test:integration`
- Frontend: `cd web && npm run lint && npm test && npm run build`
