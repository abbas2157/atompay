# Deploying AtomPay

Production: `atompay.shop`, served from `/var/www/atompay.shop`, deployed by
`git pull` on the same server that runs AtomShop.

## Two things that make this app unusual

Read these before your first deploy; they shape every step below.

**1. The database is shared with AtomShop.** Both apps use the `atomshop`
database. AtomPay owns only its `atompay_*` tables and tracks them in its own
`atompay_migrations` table. `php artisan migrate --force` is safe — it only runs
pending migrations. The destructive commands are **not** safe here: see
[Never run these](#never-run-these).

**2. Front-end assets are built locally, not on the server.** The server has
Node 18; Vite 7 needs 20.19+. So `public/build/` is committed to git and arrives
with the `git pull`. There is no `npm` step on the server, and `node_modules/`
need not exist there.

---

## Every deploy

```bash
cd /var/www/atompay.shop

# 1. Take the site down only if this deploy includes migrations or slow steps.
php artisan down --render="errors::503" --retry=60

# 2. Pull. --ff-only refuses to merge, so a dirty server tree fails loudly
#    instead of creating a silent merge commit.
git pull --ff-only origin main

# 3. PHP dependencies (skip if composer.lock did not change).
composer install --no-dev --optimize-autoloader --no-interaction

# 4. Database. Safe: runs only migrations not yet in atompay_migrations.
php artisan migrate --force

# 5. Rebuild the caches. optimize = config + routes + views + events.
php artisan optimize:clear
php artisan optimize

# 6. Confirm the shared document store is still reachable.
php artisan atompay:kyc-check

php artisan up
```

If the deploy touched only Blade or CSS, steps 1, 3, 4 and the `down`/`up` pair
can be skipped — `optimize:clear && optimize` is enough.

### Before you push, on your own machine

Because the server cannot build assets, **any change under `resources/` must be
compiled and committed with it**:

```bash
npm run build
git add public/build
git commit
git push origin main
```

Forgetting this is the cause of `ViteManifestNotFoundException` and of the
server serving yesterday's CSS. If you are unsure whether a push needs it:
`git diff --name-only origin/main -- resources/` — any output means run the build.

---

## First deploy on a new server (one time)

```bash
# Code
cd /var/www && git clone https://github.com/abbas2157/atompay.git atompay.shop
cd atompay.shop
composer install --no-dev --optimize-autoloader

# Environment. .env is NOT in git - copy and fill it in.
cp .env.example .env
php artisan key:generate          # ONLY on a brand-new install, never again
```

Then edit `.env`:

```ini
APP_ENV=production
APP_DEBUG=false
APP_URL=https://atompay.shop
ASSET_URL=https://atompay.shop

DB_DATABASE=atomshop             # the shared database
DB_USERNAME=...
DB_PASSWORD=...

ATOMSHOP_URL=https://atomshop.pk
ATOMPAY_ASSET_URL=https://atomshop.pk

# Must be byte-identical to AtomShop's value - see docs/shared-storage.md
ATOMPAY_KYC_ROOT=/var/www/shared/atompay

# Google Analytics 4. Empty = no tag and no Google hosts in the CSP.
GOOGLE_ANALYTICS_ID=G-PB9VT5QFML

# Email - REQUIRED: password-reset codes go out by email. Use the same SMTP
# account as AtomShop (copy its MAIL_* values). Never leave MAIL_MAILER=log
# in production: codes would be written to the log file instead of sent.
MAIL_MAILER=smtp
MAIL_HOST=...
MAIL_PORT=587
MAIL_USERNAME=...
MAIL_PASSWORD=...
MAIL_FROM_ADDRESS=no-reply@atomshop.pk
MAIL_FROM_NAME="AtomPay"

# WhatsApp codes (sign-up + password reset by mobile number) - AtomShop's
# Cloud API number (same token and phone number id as AtomShop). Without these
# two lines customers can still sign up and reset with an EMAIL; anyone who
# types a mobile number is asked to use their email instead.
WHATSAPP_TOKEN=...
WHATSAPP_PHONE_NUMBER_ID=...

# Mobile app. Push stays off until FCM_CREDENTIALS points at the Firebase
# service-account JSON (keep it outside the web root, readable by www-data).
FCM_CREDENTIALS=/var/www/shared/atompay/firebase-service-account.json
ATOMPAY_APP_MIN_ANDROID=1.0.0
ATOMPAY_APP_MIN_IOS=1.0.0
ATOMPAY_APP_STORE_ANDROID=https://play.google.com/store/apps/details?id=...
ATOMPAY_APP_STORE_IOS=https://apps.apple.com/app/id...
ATOMPAY_SUPPORT_PHONE=...
ATOMPAY_SUPPORT_WHATSAPP=...
ATOMPAY_SUPPORT_EMAIL=...
```

Writable directories and the shared document store:

```bash
sudo chown -R www-data:www-data storage bootstrap/cache
sudo chmod -R ug+rwX storage bootstrap/cache
```

Then follow [docs/shared-storage.md](shared-storage.md) to create
`/var/www/shared/atompay`, move any existing KYC files into it, and set the same
`ATOMPAY_KYC_ROOT` in **both** apps' `.env`.

Finally run the migrations and caches:

```bash
php artisan migrate --force
php artisan optimize
php artisan atompay:kyc-check
```

### Web server

The document root must be **`/var/www/atompay.shop/public`**, not the project
root. The `.htaccess` in the project root is a development shim for XAMPP's
subfolder layout; pointing a production docroot at the project root would expose
`.env`, `storage/` and `vendor/`.

`storage:link` is not needed — this app serves no files from the public disk.

### Scheduler (required for the mobile app)

The mobile app's notifications (limit decisions, KYC outcomes, instalment
reminders) and the daily cleanup of expired app sign-ins run from Laravel's
scheduler. Add one cron entry for the web-server user:

```bash
sudo crontab -u www-data -e
# then add:
* * * * * cd /var/www/atompay.shop && php artisan schedule:run >> /dev/null 2>&1
```

Check it with `php artisan schedule:list`. You should see `atompay:notify` every 10 minutes and
`sanctum:prune-expired` daily. Every notification is announced once only (a unique
key per event), so a missed or doubled run does no harm.

---

## Verifying a deploy

```bash
curl -sS -o /dev/null -w '%{http_code}\n' https://atompay.shop/up     # expect 200
curl -sS https://atompay.shop | grep -o 'build/assets/app-[^"]*'      # hashed CSS/JS, not a 404
php artisan atompay:kyc-check                                          # expect all green
php artisan about --only=environment                                   # APP_ENV=production, debug false
```

Then click through once as a human: the landing page calculator, `/login`, and
`/my` for a customer whose KYC has documents — that last one exercises the
shared disk end to end.

---

## Rolling back

```bash
cd /var/www/atompay.shop
git log --oneline -5                 # find the last good commit
git reset --hard <commit>
composer install --no-dev --optimize-autoloader
php artisan optimize:clear && php artisan optimize
```

`public/build` is committed, so a code rollback rolls the assets back with it.

A rollback **does not undo a migration**, and you should not try to: reversing
`atompay_kyc_profiles` drops every KYC record. If a migration is the problem,
write a new forward migration that corrects it.

---

## Never run these

On this server they damage AtomShop, not just AtomPay, because both apps share
one database.

| Command | What it actually does here |
|---|---|
| `php artisan migrate:fresh` | Drops **every table in the `atomshop` database** — orders, users, products, all of it. Not just AtomPay's. |
| `php artisan db:wipe` | Same. |
| `php artisan migrate:refresh` / `migrate:reset` / `migrate:rollback` | Runs AtomPay's `down()` migrations, dropping `atompay_kyc_profiles` and `atompay_credit_assessments` — every KYC record and credit decision. |
| `php artisan key:generate` on a live install | Invalidates existing sessions and anything encrypted with the old key. Only ever on a brand-new install. |
| `git clean -xfd` | Deletes `.env`, and — until `ATOMPAY_KYC_ROOT` points outside the project — the customers' CNIC scans. |
| `npm run build` on the server | Fails (Node 18). Build locally and commit instead. |

`php artisan migrate --force` is the only migration command that belongs in a
deploy.

---

## Troubleshooting

**`ViteManifestNotFoundException`** — `public/build/manifest.json` is missing.
Someone pushed a `resources/` change without running `npm run build`. Build and
commit it locally, then pull again.

**Stale CSS after a deploy** — the same cause, or `php artisan optimize:clear`
was skipped. Assets are content-hashed, so a correct deploy never needs a
browser cache purge.

**`atompay:kyc-check` reports missing documents** — AtomShop and AtomPay have
different `ATOMPAY_KYC_ROOT` values, or files were left behind in the old
project-local folder. Compare both `.env` files; see
[docs/shared-storage.md](shared-storage.md).

**500 with a blank page** — `tail -50 storage/logs/laravel.log`. Most often
`storage/` is not writable by `www-data` after a deploy that ran as root.

**Config changes appear to do nothing** — config is cached in production. Run
`php artisan optimize:clear && php artisan optimize` after editing `.env`.
