# rungud

Back office for inZENtive. Two deliverables:

| Folder | What | Runs where |
|---|---|---|
| `plugin/` | WordPress plugin `rungud-cms` — tables, REST API `/wp-json/rungud/v1`, business rules, PDFs, Stripe webhook, audit | inside the inZENtive WordPress site |
| `web/` | React UI, renders only | Vercel |

Rules: see [CLAUDE.md](CLAUDE.md). Build instructions: [docs/PROMPT.md](docs/PROMPT.md).

## Plugin

```sh
cd plugin
composer install
composer test               # php -l + unit tests (no WordPress needed)
npm install && npx wp-env start   # needs Docker: WordPress 6.8 + WooCommerce, PHP 8.1
composer test:integration
```

Configuration: constants in `wp-config.php` (see `plugin/.env.example` for the list). Secrets never go in the repo.

LearnDash is a commercial plugin and is not installed by wp-env. Tests that need it run against the staging copy.

Release zip: `composer install --no-dev -o`, then zip `rungud-cms.php`, `src/`, `vendor/` as folder `rungud-cms/`.

## Web

```sh
cd web
cp .env.example .env.local   # point VITE_API_BASE at local wp-env or staging
npm install
npm run dev
npm run lint && npm test && npm run build
```

### Vercel

Project root directory: `web`. Production branch: `main`. Every other branch gets a preview deployment.

Environment variable `VITE_API_BASE`:
- **Production** → `https://<live site>/wp-json/rungud/v1`
- **Preview** → `https://<staging site>/wp-json/rungud/v1` — never production.

Add both Vercel domains to `RUNGUD_CORS_ORIGINS` on the matching WordPress install.
