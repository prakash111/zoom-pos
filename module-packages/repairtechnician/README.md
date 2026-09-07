# Repair Technician — packaged module

A fully self-contained ZIP module for the ZoomNearby platform's Perfex-style
plugin engine (`App\Services\Modular\ModulePackageService`).

## What it adds

| Piece | Detail |
|---|---|
| Navigation | A **Repair** drawer section → *Repair Dashboard*, *Repair Tickets*, *Device Categories* |
| Tables | `repair_mod_device_categories`, `repair_mod_tickets`, `repair_mod_ticket_items` |
| API | `GET|POST /api/tenant/repair-module/...` (see `routes.php`) |
| Namespace | `Modules\repairtechnician\...` → `modules/repairtechnician/...` at runtime |

The tables are `repair_mod_`-prefixed so the package never collides with a
host that also ships a built-in repair vertical.

## Layout

```
module.json                         manifest (key, navigation, features)
routes.php                          loaded while the module is active
Database/Migrations/*.php           run on Activate, rolled back on Uninstall(drop data)
Models/*.php                        Modules\repairtechnician\Models\*
Http/Controllers/*.php              Modules\repairtechnician\Http\Controllers\*
```

## Install & test (Super Admin)

1. **Super Admin → Modules → Upload** `repairtechnician.zip`. It installs **inactive**.
2. **Activate.** Migrations run; the three `repair_mod_*` tables are created.
3. Sign in to a tenant. The **Repair** section appears in the drawer.
4. *Device Categories* → add e.g. `Smartphone` with checklist points
   (`Power`, `Display`, `Charging`, …) → **Save Category**.
5. *Repair Tickets* → fill customer + device, pick the category → **Create
   Ticket**. It appears in the list; the category's checklist points are
   copied onto the ticket.
6. Open the ticket → **Change Ticket Status** → step it through
   `diagnosing → in_progress → ready → delivered`.
7. *Repair Dashboard* counters reflect the above.
8. **Uninstall (drop data)** removes the row, the `modules/repairtechnician/`
   folder, and drops the three tables.

> If the host has route caching enabled, run `php artisan route:clear` (or
> `route:cache`) after activating so the module routes resolve.
