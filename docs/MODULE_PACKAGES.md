# Standalone vertical module packages

`module-packages/` holds the source for **installable ZIP modules** that run
on this platform's Perfex-style plugin engine
(`App\Services\Modular\ModulePackageService`, Super Admin → Modules).

Three are shipped here, extracted as self-contained verticals:

| Key | Name | Tables | API prefix |
|---|---|---|---|
| `pharmacy` | Pharmacy | `pharmacy_mod_drug_batches`, `pharmacy_mod_prescriptions`, `pharmacy_mod_prescription_items` | `/api/tenant/pharmacy-module` |
| `repairtechnician` | Repair Technician | `repair_mod_device_categories`, `repair_mod_tickets`, `repair_mod_ticket_items` | `/api/tenant/repair-module` |
| `salon` | Salon & Bookings | `salon_mod_services`, `salon_mod_stylists`, `salon_mod_appointments` | `/api/tenant/salon-module` |

They are **additive** — the core built-in `pharmacy` / `repair_technician` /
`service_booking` operating modes are untouched. The package tables are
`*_mod_*`-prefixed so a package installs cleanly whether or not the host
also has the built-in vertical.

## Package layout

```
module-packages/<key>/
  module.json                 manifest: key, name, version, navigation[], features{}
  routes.php                  required by ModuleServiceProvider while the module is active
  Database/Migrations/*.php    run on Activate  (migrate --path)
                              rolled back on Uninstall + "drop data"
  Models/*.php                Modules\<key>\Models\*          (lowercase namespace segment)
  Http/Controllers/*.php      Modules\<key>\Http\Controllers\*
```

`<key>` is the lowercase, separator-free slug. It is simultaneously the
`sdui_modules.slug`, the `modules/<key>/` runtime directory, and the
`Modules\<key>` PHP namespace segment — the three must stay identical
(enforced by `ModulePackageService::normalizeKey()`).

## Build the ZIPs

```bash
scripts/build_module_packages.sh
```

Produces (all git-ignored):

```
storage/app/module-dist/pharmacy.zip
storage/app/module-dist/repairtechnician.zip
storage/app/module-dist/salon.zip
storage/app/module-dist/all-modules-clean.zip    # master bundle of the above
```

Each `<key>.zip` has `module.json` at its root, exactly as `install()`
expects.

## Fresh install & manual test (Super Admin)

1. **Purge first (optional).** If a previous copy is installed on this box:
   `scripts/build_module_packages.sh reset` — drops the `*_mod_*` tables,
   deletes the `sdui_modules` rows, and removes `modules/<key>/`.
2. **Super Admin → Modules → Upload** `pharmacy.zip`. Installs **inactive**.
3. **Activate** — migrations run, tables are created, routes load.
   *(If route caching is on: `php artisan route:clear`.)*
4. Sign in to a tenant; the module's section appears in the drawer.
5. Walk each screen (per the module's own `README.md`): create a record,
   confirm it lists, exercise the status / dispense action.
6. Repeat 2–5 for `repairtechnician.zip`.
7. **Uninstall → "drop data"** to return to a clean state.

## Lifecycle & cleanup

`ModulePackageService` (Super Admin → Modules) is the single entry point.
Every transition below also runs `php artisan optimize:clear`, so a
route/config/view-cached deployment reflects the change on the very next
request instead of after the next deploy.

| Action | `sdui_modules` row | `modules/<key>/` files | Own tables | Registry / drawer / governance card |
|---|---|---|---|---|
| **Install** | created, `is_active = false` | written | — | hidden (inactive) |
| **Activate** | `is_active = true` | kept | `migrate --path` | shown |
| **Deactivate** | `is_active = false` | kept | kept | **hidden** |
| **Uninstall** | deleted | `File::deleteDirectory()` | kept | hidden |
| **Uninstall + "drop data"** | deleted | deleted | `migrate:rollback --path` | hidden |

Uninstall also:

- drops the key from `allowed_registration_modes` so it can't linger as a
  selectable store type;
- on **drop data**, sweeps the `migrations` table of this module's exact
  migration filenames (scoped, not `LIKE '%name%'`) so a later re-install
  re-runs its migrations instead of skipping them or hitting *table
  already exists*;
- deletes `permissions` rows whose `module` equals the slug (a module that
  registered none simply has none), plus anything the manifest names under
  `features.cleanup` (below);
- if `File::deleteDirectory()` fails (e.g. the FPM user can't write the
  tree), falls back to `rm -rf` so the directory is never left behind.

### Optional `module.json` → `features.cleanup`

```json
"features": {
    "cleanup": {
        "permission_modules": ["pharmacy", "pharmacy_batches"],
        "config_key_prefixes": ["pharmacy_mod."],
        "platform_system_keys": ["pharmacy_promo_enabled"]
    }
}
```

On uninstall this deletes `permissions` rows for those extra `module`
values, `configurations` rows whose `key` starts with each prefix, and
`platform_system` rows for those exact keys. Everything is opt-in — the
purge only ever removes what the manifest lists (plus permission rows
matching the slug).

### Not applicable here (this is not nwidart/laravel-modules)

The engine is custom. There is **no** `module:migrate-rollback` command, **no**
`modules_statuses.json`, and **no** Composer namespace mapping — so
`composer dump-autoload` is never needed (see the `spl_autoload_register`
note above). The built-in verticals (`retail`, `restaurant`, `pharmacy`,
`service_booking`, `repair_technician`) are compiled into
`ModuleRegistry::allModules()` and are **not** removable — they always show
in the SuperAdmin "Module Governance" card; the checkboxes there control
whether each is offered at tenant registration, nothing more.

There is **no composer step** — module classes load through a runtime
`spl_autoload_register` in `App\Providers\ModuleServiceProvider`
(`Modules\<key>\Foo\Bar` → `modules/<key>/Foo/Bar.php`), so nothing needs
`composer dump-autoload`.

### How an active module is wired in (`ModuleServiceProvider::bootModule()`)

For every `sdui_modules` row that is `source_type = package` and
`is_active = true`, the provider injects — without touching any core file:

| In `modules/<key>/` | Effect |
|---|---|
| `Providers/ModuleProvider.php` (`Modules\<key>\Providers\ModuleProvider`) | `$app->register()`ed like any Laravel provider |
| `routes.php` | loaded (flat file — the simple case) |
| `routes/api.php`, `routes/web.php` | loaded if present (split files) |
| `Resources/views/` | published as the `module-<key>::` view namespace |

Migrations are **not** auto-run on boot — they run once, on Activate.

## Gating module-specific UI

Anything that should only render while a module is usable checks the
registry rather than hard-coding the vertical:

```blade
@if (\App\Services\Modular\ModuleRegistry::isActive('pharmacy'))
    {{-- pharmacy-only settings card / menu entry / form --}}
@endif
```

- `ModuleRegistry::isActive($key)` — built-in vertical, or a package module
  whose row exists **and** `is_active`. A deactivated / uninstalled package
  is `false`.
- `ModuleRegistry::isInstalled($key)` — present at all (active or not);
  `false` once fully uninstalled.

`ModuleRegistry::allModules()`, `TenantNavRegistry` (drawer), and the
SuperAdmin "Module Governance" card already filter on `is_active`, so
deactivating or uninstalling a package removes it from all of them with no
per-screen code.

## Adding another packaged vertical

1. `mkdir module-packages/<key>` with the layout above.
2. Add `<key>` to the `MODULES=(…)` array in
   `scripts/build_module_packages.sh` (and its `reset` table list).
3. Rebuild.
