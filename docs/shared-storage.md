# Shared KYC storage (AtomPay ↔ AtomShop)

## Why this exists

AtomPay and AtomShop share one MySQL database but live in two project folders. A
file column in `atompay_kyc_profiles` holds a *relative* path such as
`kyc/4772/cnic_front-20260917124715.png` — meaningful only against some root
directory. Each app has its own root, so a shared database gives the two apps
shared **references**, never shared **bytes**.

Both apps therefore point one Laravel disk at the **same directory**. The file
is written once and read by both. Nothing is copied, nothing can go stale.

| File kind | How it is shared | Why |
|---|---|---|
| AtomShop product pictures | Not shared at all — AtomPay builds a URL onto AtomShop's domain (`ATOMPAY_ASSET_URL`, see `app/Models/Product.php`) | They are public; the browser fetches them directly |
| AtomPay KYC documents (CNIC, selfie, signed form) | Shared directory, this document | They are private; they must never sit at a guessable public URL |

## Configuration

AtomPay: disk `kyc` in `config/filesystems.php`, selected by
`config('atompay.kyc.disk')`. Root comes from `ATOMPAY_KYC_ROOT`, falling back to
`storage/app/private` so local development needs no setup.

AtomShop: an identical disk named `atompay_kyc` with **the same**
`ATOMPAY_KYC_ROOT` value. If the two values differ, each app writes into its own
folder and documents appear to vanish from the other.

The disk is deliberately not public: no `url`, `serve => false`, and it must
never appear in `filesystems.links` or behind `storage:link`.
`Account\DocumentController` is the only route by which these bytes reach a
browser, and it checks owner-or-staff first.

## Production setup (one time, both apps on one server)

Where things live on the production box:

| | Path |
|---|---|
| AtomPay project root | `/var/www/atompay.shop` |
| AtomShop project root | `/var/www/html` |
| Shared KYC documents | `/var/www/shared/atompay` |

"Project root" means the directory holding `artisan` and `.env` — not the
`public/` docroot. `/var/www/html` is also Apache's default docroot, so confirm
with `ls /var/www/html/artisan` before running the commands below; if AtomShop's
`.env` sits a level up, adjust the paths.

Keep the shared directory **outside both project folders** so a redeploy or a
stray `git clean -xfd` can never delete an identity document.

```bash
# 1. Create the shared root and give it to the web user.
sudo mkdir -p /var/www/shared/atompay/kyc
sudo chown -R www-data:www-data /var/www/shared/atompay
sudo chmod -R 2770 /var/www/shared/atompay   # setgid: new files inherit the group

# 2. Move the documents that already exist in the AtomPay project folder.
sudo rsync -a /var/www/atompay.shop/storage/app/private/kyc/ /var/www/shared/atompay/kyc/
sudo chown -R www-data:www-data /var/www/shared/atompay

# 3. Point BOTH apps at it - the value must be byte-identical in each .env.
echo 'ATOMPAY_KYC_ROOT=/var/www/shared/atompay' | sudo tee -a /var/www/atompay.shop/.env
echo 'ATOMPAY_KYC_ROOT=/var/www/shared/atompay' | sudo tee -a /var/www/html/.env

# 4. Reload config and verify from both sides.
cd /var/www/atompay.shop && php artisan config:clear && php artisan atompay:kyc-check
cd /var/www/html         && php artisan config:clear && php artisan atomshop:kyc-check

# 5. Only once both checks pass, remove the old copy.
sudo rm -rf /var/www/atompay.shop/storage/app/private/kyc
```

If the two apps run as *different* PHP users, put both users in a shared group
and use that group in step 1 instead of `www-data:www-data`. The disk's
`permissions` block already writes files `0660` and directories `0770`, so group
membership is enough — no world-readable ID scans.

## Verifying

`php artisan atompay:kyc-check` reports the resolved root, whether it is
readable and writable, a write-then-read round trip, and — the part that
actually catches drift — every path the database promises that is not on disk.
`--prune-check` adds the reverse: files on disk no profile references.

Run it **in both apps**. Matching output is the proof that the wiring is right.
A document listed as missing means the other app wrote it to a different root,
or it was lost in a deploy.

## What this does not cover

- **Sessions.** Same `users` table, but each app keeps its own file-based
  sessions on its own domain, so signing into AtomPay does not sign you into
  AtomShop. Single sign-on needs a shared session store plus a parent cookie
  domain — a separate job.
- **Compiled assets.** `public/build/` is per-app and committed; see the note in
  `.gitignore`.

## If the apps are ever split across servers

A shared directory stops being possible. The replacement is shared object
storage (S3 / Cloudflare R2 / Spaces): both apps point the same disk name at the
same bucket with the same credentials, the paths in the database stay
byte-identical, and private documents are served through short-lived signed
URLs. Only the disk definition changes — application code reads
`config('atompay.kyc.disk')` and never touches a local path directly.

Do **not** solve it with rsync or a cron copy: it doubles storage, is always
stale, and means every customer's CNIC exists in two places to secure and two
places to delete from when they ask.
