# License System — Implementation & Operations Guide

How the two halves fit together:

```
  ┌─────────────────────────────┐         ┌──────────────────────────────────────┐
  │  SaaS install (the script    │  HTTPS  │  License Manager (standalone)         │
  │  you sell — many copies)     │◀───────▶│  license.yourdomain.com — one copy    │
  │                              │         │                                      │
  │  LicenseService (custom drv) │         │  /api/v1/license/verify              │
  │  CustomLicenseServerClient   │         │  /api/v1/license/issue               │
  │  ModulePackageService        │         │  /api/v1/catalog                    │
  │  license:check-status (daily)│         │  /buy.php   (hosted checkout)        │
  │  /api/license/activate  ◀────┼─push────┤  /admin/    (issue, revoke, redeem)  │
  └─────────────────────────────┘         └──────────────────────────────────────┘
```

- The **License Manager** is *your* control centre. You run **one** copy on its
  own subdomain. It issues keys, verifies them, sells modules, and manages
  CodeCanyon redemptions.
- Each **SaaS install** is a copy of the script a customer bought. It never
  stores gateway secrets or driver config — it only knows the License Manager
  URL + shared secret, and the license keys its operator has entered.

Every wire message carries `X-Server-Secret: <shared secret>`. The checkout
callback is additionally HMAC-signed with the same secret.

---

## Part A — Deploy the License Manager

1. **Get the package** — run `php scripts/build_license_server.php` in the SaaS
   repo → `storage/app/license-dist/license-manager-standalone.zip`.

2. **Upload** its contents to a dedicated subdomain's document root
   (e.g. `license.yourdomain.com`). It is plain PHP 8+ — no Composer, no build.

3. **Database** — create a MySQL database + user (any name; the reference uses
   `license_manager`).

4. **`config/config.php`** — edit:
   | Constant | Set to |
   |---|---|
   | `DB_HOST` / `DB_PORT` / `DB_NAME` / `DB_USER` / `DB_PASS` | your DB |
   | `SERVER_SECRET` | a long random string — **this exact value goes in every SaaS `.env`** |
   | `ADMIN_USER` | admin login name |
   | `ADMIN_PASS_HASH` | output of `php bin/hash-password.php 'your-password'` |
   | `DEFAULT_LICENSE_TTL_DAYS` | `0` = perpetual keys; `365` = annual, etc. |

5. **Create the tables** — pick one:
   - shell: `php bin/install.php`
   - browser: open `/setup.php`, click **Create tables**, then **delete
     `setup.php`**
   - manual: import `database/schema.sql` in phpMyAdmin

6. **Web-server routing** — the three `/api/v1/...` paths must map to the PHP
   files:
   - Apache: the bundled `.htaccess` handles it (needs `mod_rewrite`).
   - nginx: add the `location` blocks from the package `README.md`, and
     `location ~ ^/(config|lib|bin|database)/ { deny all; }`.

7. **Sign in** at `/admin/` → **Settings**:
   - **Validation mode** — `native` (issue + check keys here) or `codecanyon`
     (also verify Envato purchase codes; needs an Envato token).
   - **Envato API token** (only if you also sell on CodeCanyon).
   - **Payment gateway** — Razorpay or Stripe — plus its keys and default
     currency. Used by `/buy.php`.

8. **Products** → add every sellable item:
   - `core` — the main script (used at install time).
   - one row per module, `slug` **exactly** matching the SaaS module key
     (`pharmacy`, `salon`, `repairtechnician`, …).
   - price, currency, description, and *Active* (unticked = hidden from the
     "Buy" list).

---

## Part B — Configure each SaaS install

In the SaaS `.env` (set these before you ship the build, or per customer):

```dotenv
LICENSE_DRIVER=custom
LICENSE_SERVER_URL=https://license.yourdomain.com
LICENSE_SERVER_SECRET=<the SERVER_SECRET from config/config.php>
LICENSE_SERVER_TIMEOUT=10
MODULE_STORE_URL=https://license.yourdomain.com/buy.php
```

- `LICENSE_DRIVER=codecanyon` instead routes verification to the Envato API
  (`ENVATO_API_TOKEN`) — use only if that install was sold on CodeCanyon.
- If `LICENSE_SERVER_URL` is empty the `custom` driver only *format-checks*
  keys (any well-formed 16+ char string passes) — fine for local dev, not for
  production.
- There is **no in-app licensing screen**. Operators only ever type license
  keys.

---

## Part C — The three ways a customer gets a key

SuperAdmin → Modules always shows an **"Available modules"** card listing every
sellable vertical — the ones bundled in the build (`module-packages/`) plus
anything extra from the vendor catalog. Each card has:

- **Install** — for a bundled module: registers it from the build with no upload.
  The row then drops into *Installed Modules* needing a license key.
- **Buy module ↗** — opens the hosted checkout (see below).

### 1. Buy through the hosted checkout (self-serve, gateway)

- **Core:** installer step 4 shows **"Buy a license ↗"** → `/buy.php?product=core&domain=<their-host>`.
- **Module:** the *Available modules* card's **"Buy module ↗"** → `/buy.php?product=<slug>&domain=<their-host>&return=<modules-url>`.

Flow: `/buy.php` collects an email → creates a gateway order (Razorpay/Stripe)
→ on payment it issues a key **bound to that domain**, records the payment,
**pushes the key to the buyer's site** (`POST https://<domain>/api/license/activate`,
HMAC-signed) so the module/core self-activates, and shows the key on a receipt
as a fallback. Idempotent on the gateway payment reference.

### 2. You issue a key manually and send it

License Manager → **Licenses** → *Issue a license*: pick the product, enter the
buyer email, optionally pre-bind a domain / set an expiry. Copy the generated
key and send it to the customer. They paste it:
- **Core** → installer step 4, or the SuperAdmin **dashboard banner** later.
- **Module** → SuperAdmin → Modules → **Install** the module (or upload its
  ZIP), then paste the key in its *license key* field → *Verify & Activate*.

### 3. Redeem a CodeCanyon purchase code

License Manager → **Redeem**: paste the buyer's Envato purchase code + choose
the product. It calls the Envato API (token from Settings), and on success mints
a **native** key (expiry taken from `supported_until`). From then on that key
behaves like any other — verified against the License Manager, not Envato.

---

## Part D — How activation works on the SaaS

### Core script

1. Installer step 4: `LicenseService::verify($key, 'core', <domain>)`.
2. On success the key + metadata land in `storage/installed`, and
   `platform_system.core_license_status = ok`.
3. If the daily check later fails, `core_license_status` flips to `warn` → an
   amber banner on the SuperAdmin **dashboard** with a field to paste a fresh
   key. **The platform is never disabled by a core-license failure.**

### Modules

`sdui_modules` gains license columns. The rule enforced everywhere:

> `sdui_modules.is_active = true` ⇒ the module holds a currently-valid license.

- **Activate** (`ModulePackageService::activate()`) throws unless
  `license_status = 'active'`.
- **`verifyAndRecordLicense()`** calls the License Manager, and on success
  stores `license_status=active`, an encrypted copy of the key, its hash/prefix,
  driver, buyer and expiry.
- Entering a key in SuperAdmin → Modules runs verify → record → activate.
- The **checkout callback** (`/api/license/activate`) does the same
  automatically. If the module ZIP is not uploaded yet, the key is stashed in
  `platform_system.license_pending_<slug>` and applied by
  `ModulePackageService::install()` when the ZIP arrives.
- Because `ModuleRegistry` and every nav/route/SDUI reader already filter on
  `is_active`, a de-licensed module drops out of the drawer, routes, bootstrap
  and registration modes with no extra code.

---

## Part E — Daily re-check & revocation

`php artisan license:check-status` (scheduled `->daily()`), also runnable with
`--sync`, `--module=<slug>`, `--core-only`, `--grace-days=N`:

- Re-verifies every `requires_license` module using its stored key.
- **Definitive "no"** from the server (revoked / wrong domain) → module
  `license_status=revoked`, deactivated, dropped from
  `allowed_registration_modes`, audit-logged.
- **Expired past the grace window** → `license_status=expired`, deactivated.
- **Transport failure** (server unreachable) → tolerated for **3 consecutive
  daily runs** (`license_module_<slug>_fail_streak`) before the module is
  pulled.
- **Core** → writes `core_license_status` (`ok` / `warn`) +
  `core_license_message` + `core_license_last_checked`; never disables anything.

To revoke: License Manager → **Licenses** → *Revoke* (or *Suspend*). The change
takes effect on the SaaS at its next verify (immediately on the next module
activate / re-check, otherwise within a day).

---

## Part F — API reference (implemented by the License Manager)

All POST unless noted. Header on every call:
`X-Server-Secret: <shared secret>`; `Content-Type` / `Accept: application/json`.

### `POST /api/v1/license/verify`
Body `{license_key, product_slug, domain}` →
`200 {status:bool, expires_at:?ISO8601, message, plan:?}`.
`status:false` (still HTTP 200) = unknown / revoked / expired / bound elsewhere.
`401` bad secret, `422` bad body. On first successful verify the key is bound to
`domain`.

### `POST /api/v1/license/issue`
Body `{payment:{gateway,reference,amount,currency,payer_email}, product_slug, domain, item_id?}` →
`200 {status, license_key, expires_at, message, plan}`. **Idempotent on
`payment.reference`.**

### `GET /api/v1/catalog`
→ `200 {status:true, products:[{slug,name,description,price,currency}]}` —
active products only. The SaaS caches this ~30 min; `core` is filtered out of
the "Buy module" list.

### `POST https://<saas-domain>/api/license/activate`  *(License Manager → SaaS)*
Header `X-License-Signature: hex hmac_sha256(raw_body, shared secret)`.
Body `{product_slug, license_key, domain, expires_at?, plan?}`. The SaaS
verifies the HMAC + domain, then activates core or the module (or stashes a
pending key).

Full contract with response bodies: `docs/LICENSE_SERVER_CONTRACT.md`.

---

## Part G — Operations

| Task | How |
|---|---|
| **Rotate the shared secret** | Change `SERVER_SECRET` in `config/config.php` **and** `LICENSE_SERVER_SECRET` in every SaaS `.env` at the same time. Old secret stops verifying immediately. |
| **Move a license to a new domain** | License Manager → Licenses → *Reset domain* on that row. Next verify from the new host re-binds it. |
| **Give a refund / kill an install** | *Revoke* the license. Modules auto-deactivate within a day; core shows the warn banner. |
| **Change a module's price** | License Manager → Products. The SaaS picks it up within ~30 min (catalog cache). |
| **Per-install price/URL override on the SaaS** | `platform_system` key `module_catalog` = `{"<slug>":{"price":…,"buy_url":…,"buy_enabled":false}}`. |
| **APP_KEY rotated on a SaaS install** | The encrypted stored module keys become unreadable → the daily check deactivates those modules → operator re-enters the keys. Same caveat as any Laravel encrypted setting. |
| **"Database not initialised" on the LM** | Run `php bin/install.php` or open `/setup.php`. |
| **Checkout shows "gateway not configured"** | Fill the gateway keys in License Manager → Settings. |

### Security checklist

- HTTPS only on the License Manager subdomain.
- `config/`, `lib/`, `bin/`, `database/` are denied to the web (`.htaccess` /
  nginx `location`). Verify `https://license.yourdomain.com/config/config.php`
  returns 403.
- Delete `setup.php` after setup.
- Consider an IP allowlist on `/admin/` — it has a login gate but no rate
  limiting.

---

## File & route map

### License Manager (inside the ZIP)
| Path | Purpose |
|---|---|
| `config/config.php` | DB creds, `SERVER_SECRET`, admin login |
| `lib/helpers.php` | DB, settings, Envato verify, HMAC, `issue_license()`, `notify_saas_activation()` |
| `api/verify.php` · `api/issue.php` · `api/catalog.php` | the three endpoints |
| `buy.php` | public hosted checkout (Razorpay + Stripe) |
| `admin/index.php` | Licenses list + issue + filters + status actions |
| `admin/products.php` | product catalog editor |
| `admin/settings.php` | validation mode, Envato token, gateway keys |
| `admin/redeem.php` | CodeCanyon purchase-code → native key |
| `admin/payments.php` | payment ledger |
| `database/schema.sql` | `settings`, `products`, `licenses`, `payments` |
| `bin/hash-password.php` · `bin/install.php` · `setup.php` | setup helpers |

### SaaS (this repo)
| Path | Purpose |
|---|---|
| `app/Services/License/LicenseService.php` | driver dispatch: `verify()`, `issue()`, `catalog()`, `currentDomain()` |
| `app/Services/License/CustomLicenseServerClient.php` | HTTP client for the License Manager |
| `app/Services/License/EnvatoLicenseVerificationService.php` | the `codecanyon` driver (unused unless `LICENSE_DRIVER=codecanyon`) |
| `app/Services/Modular/ModulePackageService.php` | `activate()` gate, `verifyAndRecordLicense()`, `clearLicense()`, pending-license apply |
| `app/Services/Modular/ModuleCatalog.php` | merge vendor catalog + manifest + override; `storeLink()` |
| `app/Http/Controllers/Api/LicenseActivationController.php` | `POST /api/license/activate` (signed callback) |
| `app/Console/Commands/CheckLicenseStatusCommand.php` | `license:check-status` (scheduled in `routes/console.php`) |
| `app/Livewire/Installer/AdminAccountStep.php` | install-time license-key requirement |
| `app/Livewire/SuperAdmin/Modules/Index.php` | the module manager (activate / buy / re-check) |
| `app/Livewire/SuperAdmin/Dashboard.php` | core-license warn banner + re-enter key |
| `config/services.php` → `license_server`, `envato` | env-backed config |
