# Standalone vertical module packages

`module-packages/` holds the source for **installable ZIP modules** that run
on this platform's Perfex-style plugin engine
(`App\Services\Modular\ModulePackageService`, Super Admin → Modules).

Two are shipped here, extracted as self-contained verticals:

| Key | Name | Tables | API prefix |
|---|---|---|---|
| `pharmacy` | Pharmacy | `pharmacy_mod_drug_batches`, `pharmacy_mod_prescriptions`, `pharmacy_mod_prescription_items` | `/api/tenant/pharmacy-module` |
| `repairtechnician` | Repair Technician | `repair_mod_device_categories`, `repair_mod_tickets`, `repair_mod_ticket_items` | `/api/tenant/repair-module` |

They are **additive** — the core built-in `pharmacy` / `repair_technician`
operating modes are untouched. The package tables are `*_mod_*`-prefixed so a
package installs cleanly whether or not the host also has the built-in
vertical.

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

## Adding another packaged vertical

1. `mkdir module-packages/<key>` with the layout above.
2. Add `<key>` to the `MODULES=(…)` array in
   `scripts/build_module_packages.sh` (and its `reset` table list).
3. Rebuild.
