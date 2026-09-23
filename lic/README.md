# Central License Manager (standalone)

The vendor's control centre for selling and validating the SaaS script + its
add-on modules. Dependency-free PHP 8+. Runs on its own domain
(`https://license.example.com`), separate from any SaaS install.

## Pieces

| Path | Auth | Purpose |
|---|---|---|
| `POST /api/v1/license/verify`   | `license_key` (public) | Check a key for a product + domain. |
| `POST /api/v1/license/issue`    | `X-Server-Secret` | Issue a key after a paid purchase (idempotent on `payment.reference`). |
| `POST /api/v1/module/download`  | `license_key` | Verify a key, then stream that product's package ZIP. **Module source files live only here.** |
| `GET  /api/v1/catalog`          | public | Active products for the SaaS "Buy module" list. |
| `GET  /buy.php`                 | public | Hosted checkout — operator pays, a key is issued and pushed to their site. |
| `/admin/`                       | session login | Licenses, Products (+ package upload), Payments, Redeem CodeCanyon codes, Settings. |

## Module packages

Each module's source is a ZIP (`module.json` at its root, exactly as the SaaS
`ModulePackageService` expects). Upload it on **Products** → per-product
*Module package (.zip)*. It is stored under `storage/packages/<slug>.zip`,
**denied to the web**, and only ever sent through `POST /api/v1/module/download`
after the caller's license key verifies for that product + domain. The SaaS
extracts and installs it on the client server. `core` needs no package.

Wire contract: `docs/LICENSE_SERVER_CONTRACT.md` in the SaaS repo.

## Install

1. Upload the ZIP contents to the subdomain's document root.
2. Create a database + user.
3. Edit `config/config.php` — DB creds, a long random `SERVER_SECRET`, `ADMIN_USER`.
4. Admin password: `php bin/hash-password.php 'pw'` → paste into `ADMIN_PASS_HASH`.
5. Create the tables — `php bin/install.php`, **or** open `/setup.php` in a
   browser and click **Create tables** (then delete `setup.php`), **or** import
   `database/schema.sql` manually.
6. Sign in at `/admin/`, open **Settings**, choose the validation mode and enter
   your payment gateway credentials. Add your **Products** (core + modules) with
   prices.
7. In each SaaS install's `.env` — the server URL is hardcoded in the SaaS
   (`config/services.php`); only the shared secret is set:
   ```
   LICENSE_SERVER_SECRET=<the same SERVER_SECRET as this config/config.php>
   ```
   then `php artisan config:clear` on the SaaS.

## Validation modes (Settings)

- **native** — keys are issued and checked entirely here.
- **codecanyon** — the **Redeem** screen (and `issue`) verify an Envato purchase
  code via the Envato API, then mint a native key bound to the buyer's domain.
  Needs an Envato API personal token in Settings.

## Web server routing

The SaaS calls the PHP files **directly** — `/api/verify.php`, `/api/issue.php`,
`/api/catalog.php`, `/api/download.php` — so **no URL rewriting is required** on
any server. The `/api/v1/...` pretty paths in `.htaccess` are an Apache-only
convenience.

**You DO need to deny web access to the private folders** — `.htaccess` in
`config/ lib/ bin/ database/ storage/` handles Apache, but **nginx / CloudPanel
/ LiteSpeed ignore `.htaccess`**, so add this to the vhost:

```nginx
# CloudPanel: Site → Vhost → paste inside the server { } block
location ~ ^/(config|lib|bin|database|storage)/ { deny all; return 404; }
```

Without it, `https://license.example.com/storage/packages/…` and
`…/database/schema.sql` would be downloadable. (Uploaded packages get a random
filename as a second layer, but still add the rule.)

## Security notes

- HTTPS only — the shared secret, admin session and gateway keys ride on it.
- Add the nginx `deny` rule above (Apache is covered by the bundled `.htaccess`).
- Rotate `SERVER_SECRET` here and in every SaaS `.env` together.
- The admin panel has a login gate but no rate limiting — add an IP allowlist if
  it is internet-facing.
- **Delete `setup.php`** after setup.