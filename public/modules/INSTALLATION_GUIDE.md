# Zoom Sales CRM & Inventory — Module Installation Guide

This guide explains how to install and activate add-on vertical and extension modules in **Zoom Sales CRM & Inventory**.

---

## 📦 Available Module Packages

| Module Name | Package ZIP | Type | Capabilities & Description |
| :--- | :--- | :--- | :--- |
| **Lead Management System** | `leadmanagement.zip` | Extension | Complete sales pipeline CRM, Kanban board, prospect activities, automated customer conversion, quotation generator integration, and follow-up reminders. |
| **Pharmacy POS** | `pharmacy.zip` | Core Vertical | Drug batch numbers, expiry date tracking, patient prescription intake & dispensing workflow, batch-level inventory control. |
| **Repair Technician** | `repairtechnician.zip` | Core Vertical | Device intake service tickets, diagnostic checklists, technician labor & replacement parts tracking, customer claim receipts, repair lifecycle status. |
| **Salon & Spa Bookings** | `salon.zip` | Core Vertical | Service menu catalog, specialist & stylist management, appointment calendar booking, chair/service duration timers, and client loyalty. |

---

## 🚀 How to Install a Module (SuperAdmin Panel)

1. **Log in to SuperAdmin Portal**:
   - Access `https://yourdomain.com/login` using your SuperAdmin credentials.
2. **Navigate to Modules**:
   - In the left sidebar, click on **Modules**.
3. **Upload the Module ZIP**:
   - Click the **Upload Module** (or **Install Module**) button at the top right.
   - Select the target `.zip` file (e.g. `leadmanagement.zip`, `pharmacy.zip`, etc.).
   - Click **Upload & Install**.
4. **Automatic Processing**:
   - The platform automatically extracts the package into `modules/{slug}/`.
   - Database migrations in `Database/Migrations/` are executed automatically.
   - The module is registered into the system's `sdui_modules` catalog.
5. **Activate the Module**:
   - Locate the newly installed module in the list and click **Activate**.

---

## ⚙️ Enabling Modules for Tenants / Stores

### For Business Verticals (Pharmacy, Repair, Salon):
- Once activated in SuperAdmin, the store operating mode becomes selectable.
- Tenants can register directly into that vertical, or SuperAdmin can switch existing tenants under **Tenants > Edit Tenant > Store Operating Mode**.
- The tenant backoffice and Flutter POS client automatically adapt their UI layouts to match the vertical.

### For Extensions (Lead Management CRM):
- Extension modules add capabilities on top of any existing operating mode (Retail, Restaurant, etc.).
- In SuperAdmin, go to **Tenants > Select Tenant > Tenant Detail**.
- Scroll to **Optional Extensions**.
- Check **Lead Management System** and click **Save Changes**.
- The tenant backoffice will immediately show the **Leads** section in their navigation drawer.

---

## 🔧 Troubleshooting & Tips

- **Route Caching**: If module routes return 404 after activation, run:
  ```bash
  php artisan route:clear
  ```
- **File Permissions**: Ensure your server's web process can write to the `modules/` directory:
  ```bash
  chmod -R 775 modules storage bootstrap/cache
  ```
- **Uninstalling**: Clicking **Uninstall** in SuperAdmin safely removes the module folder, rolls back migrations, and deactivates the module.
