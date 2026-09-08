# Installation & Deployment

Zoom POS SaaS is a standard Laravel 13 application. It ships with a browser
installer (`/install`) and can also be installed from the command line.

## 1. Server requirements

| Requirement | Minimum |
|---|---|
| PHP | 8.3 (8.4 recommended) |
| PHP extensions | `bcmath`, `ctype`, `curl`, `dom`, `fileinfo`, `json`, `mbstring`, `openssl`, `pcre`, `pdo`, `pdo_mysql`, `tokenizer`, `xml`, and one of `gd` / `imagick` |
| PHP memory_limit | 256M |
| Database | MySQL 8 / MariaDB 10.6+ (PostgreSQL and SQLite also supported) |
| Node.js | 20+ (only to build front-end assets) |
| Composer | 2.x |
| Web server | Nginx or Apache with the document root pointed at `public/` |

## 2. Extract & configure

```bash
unzip zoom-pos-saas-<version>.zip
cd zoom-pos-saas

composer install --no-dev --optimize-autoloader
cp .env.example .env
php artisan key:generate
```

Edit `.env`:

- `APP_URL` – the public HTTPS URL of the install.
- `APP_ENV=production`, `APP_DEBUG=false`.
- `DB_*` – database connection.
- `MAIL_*` – outgoing mail (password resets, tenant invites).
- `LICENSE_SERVER_SECRET` – the shared secret from your License Manager
  (`config/config.php` → `SERVER_SECRET`). Required only to buy/activate
  add-on modules; the core script runs without it. The license server URL is
  fixed in `config/services.php` and is not an environment variable.
- Payment gateway keys (`STRIPE_*`, `PAYPAL_*`, `RAZORPAY_*`,
  `MERCADOPAGO_*`) – for platform subscription billing; optional.
- Set `DEMO_MODE=false` for a real deployment.

## 3. Build assets

```bash
npm ci
npm run build
```

## 4. Install the database

**Browser installer** – visit `https://yourdomain.com/install` and follow the
requirements → environment → migrate → admin-account → finish steps. This
creates the schema, seeds platform defaults, and creates the first
super-admin account.

**CLI** – for a clean production database (no demo tenants):

```bash
php artisan migrate --force
php artisan db:seed --class=PlatformDefaultsSeeder --force
php artisan db:seed --class=PermissionsTableSeeder --force
php artisan db:seed --class=LandingPageSeeder --force
php artisan storage:link
```

`php artisan db:seed --force` (no `--class`) additionally seeds sample
Retail and Restaurant tenants — useful for evaluation, skip it for a real
deployment. Then create the super-admin through the `/install` admin step.

## 5. Production cache & permissions

```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

Make `storage/` and `bootstrap/cache/` writable by the web-server user.

## 6. Scheduler & queue

Add the Laravel scheduler to cron:

```
* * * * * cd /path/to/zoom-pos-saas && php artisan schedule:run >> /dev/null 2>&1
```

Run a queue worker (`QUEUE_CONNECTION=database` by default):

```
php artisan queue:work --sleep=3 --tries=3
```

The scheduler runs `license:check-status` daily to re-verify installed
add-on modules.

## 7. Add-on modules

The script ships with two native verticals — **Retail** and **Restaurant**.
Additional verticals (Pharmacy, Salon & Bookings, Repair Technician, and any
future module) are installed at runtime from **Super Admin → Modules**:

1. Enter the module's license key.
2. The script downloads the package from the License Manager, extracts it to
   `modules/<slug>/`, runs its migrations, and activates it.

No redeployment or Flutter release is needed — mobile clients pick up a new
module on their next bootstrap sync. See `docs/MODULE_PACKAGES.md` and
`docs/FLUTTER_MODULE_ARCHITECTURE.md`.

## 8. Upgrading

Replace the application files (keep `.env`, `storage/`, and `modules/`), then:

```bash
composer install --no-dev --optimize-autoloader
php artisan migrate --force
npm ci && npm run build
php artisan optimize
```
