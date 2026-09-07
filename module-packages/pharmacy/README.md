# Pharmacy — packaged module

A fully self-contained ZIP module for the ZoomNearby platform's Perfex-style
plugin engine (`App\Services\Modular\ModulePackageService`).

## What it adds

| Piece | Detail |
|---|---|
| Navigation | A **Pharmacy** drawer section → *Pharmacy Dashboard*, *Drug Batches & Expiry*, *Prescriptions* |
| Tables | `pharmacy_mod_drug_batches`, `pharmacy_mod_prescriptions`, `pharmacy_mod_prescription_items` |
| API | `GET|POST /api/tenant/pharmacy-module/...` (see `routes.php`) |
| Namespace | `Modules\pharmacy\...` → `modules/pharmacy/...` at runtime |

The tables are `pharmacy_mod_`-prefixed so the package never collides with a
host that also ships a built-in pharmacy vertical.

## Layout

```
module.json                         manifest (key, navigation, features)
routes.php                          loaded while the module is active
Database/Migrations/*.php           run on Activate, rolled back on Uninstall(drop data)
Models/*.php                        Modules\pharmacy\Models\*
Http/Controllers/*.php              Modules\pharmacy\Http\Controllers\*
```

## Install & test (Super Admin)

1. **Super Admin → Modules → Upload** `pharmacy.zip`. It installs **inactive**.
2. **Activate.** Migrations run; the three `pharmacy_mod_*` tables are created.
3. Sign in to a tenant. The **Pharmacy** section appears in the drawer.
4. *Drug Batches & Expiry* → fill the form → **Save Batch** → it lists below.
5. *Prescriptions* → fill patient + drugs (one per line `name | dosage | qty`)
   → **Create Prescription** → open it → **Mark Dispensed**.
6. *Pharmacy Dashboard* counters reflect the above.
7. **Uninstall (drop data)** removes the row, the `modules/pharmacy/` folder,
   and drops the three tables.

> If the host has route caching enabled, run `php artisan route:clear` (or
> `route:cache`) after activating so the module routes resolve.
