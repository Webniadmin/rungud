# rungud

Back office for inZENtive. Two deliverables:

| Folder | What | Runs where |
|---|---|---|
| `plugin/` | WordPress plugin `rungud-cms` — tables, REST API `/wp-json/rungud/v1`, business rules, PDFs, Stripe webhook, audit | inside the inZENtive WordPress site |
| `web/` | React UI, renders only | Vercel |

Rules: see [CLAUDE.md](CLAUDE.md). Build instructions: [docs/PROMPT.md](docs/PROMPT.md). **Current status and next steps: [docs/STATUS.md](docs/STATUS.md).**

## Plugin

```sh
cd plugin
composer install
composer test               # php -l + unit tests (no WordPress needed)
npm install && npx wp-env start   # needs Docker: WordPress 6.8 + WooCommerce, PHP 8.1
composer test:integration
```

Configuration: constants in `wp-config.php` (see `plugin/.env.example` for the list). Secrets never go in the repo.

LearnDash is a commercial plugin and is not installed by wp-env. Tests that need it extend `LocalSiteTestCase`, carry `@group local-site`, and are excluded in CI. To run them, create `plugin/.wp-env.override.json` (gitignored) that adds the site team's LearnDash and inzentive plugins to `plugins`, set `RUNGUD_TEST_SITE_PLUGINS` to the site plugins' main files if they are not auto-detected, then `composer test:local-site`.

### Install on a WordPress site

1. Upload the release zip, activate. Activation creates the `wp_rungud_*` tables, seeds the legal entity, VAT profiles and the invoice sequence (next number 100649), and adds two roles: **rungud Owner** (Robert) and **rungud Back office** (Gudrun). Administrators get every rungud capability. Deactivating never drops tables.
2. In `wp-config.php`: `define( 'RUNGUD_JWT_SECRET', '<64 random characters>' );` — login stays disabled until this is set (at least 32 bytes).
3. **Robert's account must have only the rungud Owner role.** If he is an administrator or shop manager on the site today, give him a separate account for rungud; otherwise the widest role wins and he gets Gudrun's buttons.
4. Settings → rungud: allowed origins (Vercel prod + preview pattern, e.g. `https://rungud-*-webniadmin.vercel.app`), Stripe keys, SMTP, legal entity, VAT profiles, next invoice number. Values set in `wp-config.php` override the page and are shown read-only.
5. Apache/CGI strips the `Authorization` header on some hosts. If login works but every request afterwards returns 401, add to `.htaccess` above the WordPress block:
   ```
   RewriteRule .* - [E=HTTP_AUTHORIZATION:%{HTTP:Authorization}]
   ```

### REST API (phase 1)

| Route | Who | |
|---|---|---|
| `POST /auth/login` `{username, password}` | public | → `access_token` (15 min), `refresh_token` (30 days, rotating), `user` |
| `POST /auth/refresh` `{refresh_token}` | public | rotates; reusing an old refresh token ends the whole session |
| `POST /auth/logout` | any rungud role | ends the session at once |
| `GET /auth/me` | any rungud role | user, role, capability flags, language |
| `GET /today` | any rungud role | role-aware task list |

Five failed logins for one account (or twenty from one IP) lock login for 15 minutes.

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
