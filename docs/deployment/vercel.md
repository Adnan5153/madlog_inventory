# Deploying Madlog Store on Vercel

This guide walks through shipping the **Laravel admin + staff panels** to
Vercel so a client can poke at the live application and leave feedback.
The static marketing landing page (`index.html`) is **not** part of this
deployment — it lives in the repo for GitHub Pages and is excluded via
`.vercelignore`.

## How the deployment works

`vercel.json` glues three things together:

1. **Build step** — Composer installs PHP deps, `npm ci && npm run build`
   compiles Vite assets into `public/build/`, and `php artisan
   storage:link` creates the public storage symlink.
2. **Runtime** — `builds[0]` points the PHP runtime (`vercel-php@0.7.4`)
   at `api/index.php`, which `require()`s `public/index.php`. Laravel
   then owns all routing, sessions, CSRF, etc.
3. **Routing** — every URL is rewritten to `api/index.php`; static
   `/build/*` and `/storage/*` paths are served directly from `public/`
   with a 1-year cache header.

```
Request → /admin
  ↓
vercel.json route  /(.*)  →  api/index.php
  ↓
api/index.php  →  require public/index.php
  ↓
Laravel router  →  AdminDashboardController@index
```

## One-time setup in the Vercel dashboard

### 1. Create the Vercel project

- Go to <https://vercel.com/new> and import the GitHub repo.
- Framework preset: **Other** (we set `framework: null` in `vercel.json`).
- Root directory: leave blank — the repo root is the project root.

### 2. Add a Postgres database

- In the project, open the **Storage** tab → **Create Database** →
  **Postgres**.
- Vercel will provision the store and inject `POSTGRES_*` env vars into
  every deployment. Copy the full **`.env.local` snippet** from the
  connect dialog — you'll want the `DATABASE_URL` line for step 3.

### 3. Set environment variables

Project → **Settings** → **Environment Variables**. Add each of these
for the **Production** environment (mirror to Preview if you want
clients to see a per-PR URL):

| Variable                  | Value / Notes                                                              |
|---------------------------|----------------------------------------------------------------------------|
| `APP_KEY`                 | Run `php -r "echo 'base64:'.base64_encode(random_bytes(32));"` locally      |
| `APP_URL`                 | `https://<your-project>.vercel.app`                                        |
| `APP_NAME`                | `Madlog Store`                                                             |
| `APP_ENV`                 | `production`                                                               |
| `APP_DEBUG`               | `false`                                                                    |
| `DB_CONNECTION`           | `pgsql`                                                                    |
| `DATABASE_URL`            | Paste the full connection string from Vercel Postgres connect dialog      |
| `SESSION_DRIVER`          | `database`                                                                 |
| `SESSION_SECURE_COOKIE`   | `true`                                                                     |
| `CACHE_STORE`             | `database`                                                                 |
| `QUEUE_CONNECTION`        | `sync` (no queue worker on Vercel)                                         |
| `LOG_CHANNEL`             | `stderr` (Vercel log explorer picks it up)                                 |
| `MAIL_MAILER`             | `log` until you wire up Resend / Postmark / SES                            |
| `MAIL_FROM_ADDRESS`       | e.g. `hello@madlogstore.test`                                              |
| `BCRYPT_ROUNDS`           | `12` (matches local default)                                               |

> The `env` block in `vercel.json` already forces the safe-production
> defaults above, but explicit env vars in the dashboard take
> precedence, so set them there anyway — that way you can flip values
> without re-deploying.

### 4. Trigger the first build

Push to `main`. The first build will:

1. `composer install --no-dev --no-interaction --optimize-autoloader`
2. `npm ci && npm run build`
3. `php artisan storage:link || true`
4. `php artisan migrate --force || true` (best-effort — covered below)

If the database wasn't ready yet, step 4 will fail loudly but the deploy
artifact still ships. Watch the Vercel **Build Logs** tab to confirm.

### 5. Run the initial migrations

If step 4 above failed or you want a clean re-run:

```bash
# Locally with the same DATABASE_URL the deployment uses:
vercel env pull .env.production
php artisan migrate --force --env=production
```

Or run them from the Vercel **Shell** (Settings → Functions → open a
shell in the production deployment):

```bash
php artisan migrate --force
php artisan db:seed --class=RolesAndPermissionsSeeder
php artisan db:seed --class=SettingsSeeder
```

> If you have a Lot/Part/InventoryItem seed, run those too — they're
> the ones that fill the dashboard charts.

## Troubleshooting

### "Can't resolve '../../vendor/livewire/flux/dist/flux.css'"

Means the Vite build ran *before* `composer install` finished and
couldn't find `vendor/`. The fix is the order in our `buildCommand`:

```
composer install && npm ci && npm run build
```

If you copy the vercel.json block, keep that order. Re-deploy.

### `APP_KEY` is empty / 500s everywhere

Every Laravel session needs `APP_KEY`. If you see decryption errors in
the logs, regenerate:

```bash
php -r "echo 'base64:'.base64_encode(random_bytes(32));"
```

Paste the output into the Vercel env var and redeploy.

### Session cookie not sticking (login loops)

`SESSION_SECURE_COOKIE` must be `true` on Vercel because the project is
HTTPS-only. The `env` block sets this for you, but if you override it
in the dashboard, leave it `true`.

### Build succeeds but `/admin` 404s

Two common causes:

1. **Migrations didn't run** — the `users` table doesn't exist, so the
   login form throws. Run `php artisan migrate --force` via Vercel
   Shell.
2. **Storage symlink missing** — `php artisan storage:link` creates
   `public/storage`. The buildCommand already runs this; if you see
   broken images, run it again from Vercel Shell.

### First request is slow (~5s)

Vercel runs PHP in a serverless function that cold-starts on the first
request of the day. Subsequent requests are fast. There's no fix for
this — it's the trade-off for not running a long-lived PHP-FPM process.

## Environment variables reference

Anything not listed in `vercel.json` `env` block or the table above
comes from the dashboard. If you want to set per-environment values
(production vs. preview), use the Environment Variables UI — Vercel
copies the value into the runtime as-is.

## Local sanity check before pushing

```bash
# Confirm composer.json is healthy
composer validate --strict

# Confirm vercel.json is valid JSON
python -c "import json; json.load(open('vercel.json'))"

# Confirm the PHP entry point loads
php -l api/index.php

# Run the test suite — must be green before sharing with client
php artisan test
```
