# Salon & Bookings — packaged module

A fully self-contained ZIP module for the ZoomNearby platform's Perfex-style
plugin engine (`App\Services\Modular\ModulePackageService`).

## What it adds

| Piece | Detail |
|---|---|
| Navigation | A **Salon** drawer section → *Salon Dashboard*, *Appointments*, *Service Catalogue*, *Stylists & Specialists* |
| Tables | `salon_mod_services`, `salon_mod_stylists`, `salon_mod_appointments` |
| API | `GET|POST /api/tenant/salon-module/...` (see `routes.php`) |
| Namespace | `Modules\salon\...` → `modules/salon/...` at runtime |

The tables are `salon_mod_`-prefixed so the package never collides with a
host that also ships a built-in salon / service-booking vertical.

## Layout

```
module.json                         manifest (key, navigation, features)
routes.php                          loaded while the module is active
Database/Migrations/*.php           run on Activate, rolled back on Uninstall(drop data)
Models/*.php                        Modules\salon\Models\*
Http/Controllers/*.php              Modules\salon\Http\Controllers\*
```

## Install & test (Super Admin)

1. **Super Admin → Modules → Upload** `salon.zip`. It installs **inactive**.
2. **Activate.** Migrations run; the three `salon_mod_*` tables are created.
3. Sign in to a tenant. The **Salon** section appears in the drawer.
4. *Service Catalogue* → add e.g. `Haircut & Style`, 30 min, price → **Save Service**.
5. *Stylists & Specialists* → add a stylist with specialties → **Save Stylist**.
6. *Appointments* → pick the service + stylist, set a date/time → **Book
   Appointment**. It appears in the list.
7. Open the appointment → **Change Appointment Status** → step it through
   `confirmed → in_service → completed`.
8. *Salon Dashboard* counters reflect the above.
9. **Uninstall (drop data)** removes the row, the `modules/salon/` folder,
   and drops the three tables.

> If the host has route caching enabled, run `php artisan route:clear` (or
> `route:cache`) after activating so the module routes resolve.
