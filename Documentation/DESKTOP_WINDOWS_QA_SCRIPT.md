# Windows Desktop QA Script

Manual verification for the NativePHP desktop build. Everything here needs a real
Windows machine, real hardware, and a real network you can disconnect — none of it
can be executed from the Linux sandbox this was built in. Each step names the exact
code path it's exercising, so a failure points straight back to the relevant file
instead of a vague "printing doesn't work."

Run steps in order — later steps assume earlier ones passed (e.g. you need to be
logged in before testing POS).

## 0. Build the installer

Not testable locally without `wine` (a hard `electron-builder` requirement for
building Windows targets from Linux). Either:
- Push a `v*` tag — `.github/workflows/release-desktop.yml` builds it on a real
  `windows-latest` GitHub Actions runner and attaches `Zoom-POS-Setup-<version>.exe`
  to the release, or
- Run `php artisan native:build win x64` directly on a Windows machine with PHP/
  Composer/Node installed.

Confirm the output is a **single .exe**, not a zip with loose files/DLLs.

## 1–2. Install and launch

1. Run `Zoom-POS-Setup.exe` on a clean Windows machine (no PHP/Node/Composer
   installed on the target — the point of NativePHP is that none of that is needed).
2. Confirm: Desktop shortcut created, Start Menu entry created, an uninstaller is
   registered in "Add or remove programs".
   — `config/nativephp.php` → `nsis` block controls all of this.
3. Launch from the Desktop shortcut. First boot runs migrations against a fresh
   per-user SQLite database and should land on the login screen.
   — `app/Providers/NativeAppServiceProvider.php::ensureApplicationInitialized()`

## 3. Login (first time on this device, online)

4. Log in with a real tenant account's email/password while connected to the
   internet.
   — Since the local database is empty on a fresh install, this must go through
     the online bootstrap path: `App\Services\Auth\DesktopAuthBootstrapService`.
     It calls the real `/api/v1/pos/auth/login` endpoint, then provisions local
     Plan/Company/User rows with a **freshly-local-hashed password** and configures
     the device's sync credentials in the same step.
   - Expected: login succeeds, lands on `/tenant` dashboard.
   - Verify: the sync status pill in the top header (next to the language switcher)
     shows **Online** shortly after, then **Syncing**, then **Synced**.
     — `App\Livewire\Tenant\DesktopSyncStatus`

## 4. Web-connected mode — spot-check modules

Click through each of these while online and confirm normal web-app behavior (same
UI, same forms, same validation) — this is the *same Laravel/Livewire app* as the
web version, so if one page works they structurally all do, but check a
representative spread:

5. Dashboard, Products, Categories, Customers, Suppliers, Sales history, Quotes,
   Cash register, Receivables, Payables, Reports, Users & Roles, Settings, Restaurant
   POS/Tables/KDS (if pos_mode is restaurant), Consignments, Service Orders, Sales
   Targets, Devices, Public catalog admin.
6. In Settings → Notifications, confirm the **Desktop Printers** card appears (it
   only renders inside the desktop app) and lists real printers installed on this
   Windows machine.
   — `App\Livewire\Tenant\DesktopPrinterSettings`, backed by NativePHP's
     `System::printers()`.
7. Pick a printer for 58mm, 80mm, and A4, and save.

## 5. POS full lifecycle (online)

8. Open POS. Search a product by name, then by barcode (type it manually first).
9. Plug in a USB/Bluetooth barcode scanner (keyboard-wedge type) and scan a product
   **with no field focused** (click somewhere neutral on the product grid first) —
   it should still add to cart.
   — `resources/js/hardware-barcode-listener.js`, dispatches the same
     `barcode-scanned` Livewire event the camera scanner uses.
10. Add multiple items, change quantities, apply a per-item price override if your
    role permits it, apply a cart discount, select a customer, select a
    salesperson, add a note.
11. Checkout with cash, then repeat with card, then split cash+card, then (if a
    customer is selected) credit/khata.
12. On the success screen, click **Print Receipt**. If a printer is configured for
    this format, it should print **silently — no OS print dialog**.
    — `App\Services\Printing\DesktopPrintService::printReceiptAuto()`
    If no printer is configured, it should instead open the existing browser
    print-dialog preview in a new window — confirm that fallback also works.
13. Click **WhatsApp** and **Send Email** on the same success screen. Without
    WhatsApp Cloud API credentials configured (Settings → Notifications →
    Automated Sending), WhatsApp should open a `wa.me` link. With credentials
    configured, it should send directly and show "sent via WhatsApp".
14. Confirm stock decremented on the Products page after checkout.
15. Confirm the sale appears in Sales history and in Reports.

## 6. Disconnect the network completely

16. Disable Wi-Fi/Ethernet on the machine (airplane mode, or physically unplug —
    don't just close the app). Give the sync status pill ~20s to show **Offline**.

## 7. Offline core operations

Everything below must work with **zero network** — this is reading/writing the
local SQLite database directly, no API calls in the hot path:

17. Open POS, search products (including a product created in an earlier step),
    scan a barcode, complete a full checkout (cash and credit/khata) exactly like
    step 11.
18. Create a new product from scratch. Create a new customer from scratch.
19. Create a quotation for a customer, save it as draft.
20. Adjust stock on an existing product (add/subtract/set).
21. Record a payment against an existing customer's outstanding balance (Khata).
22. Open Reports — cached/local figures should render (may not reflect the very
    latest server-side numbers from other devices, which is expected offline).
23. Click **Print Receipt** on the offline sale from step 17 — silent print must
    still work with no network (it's local HTML rendering, no API call).
24. Try **Send Email** on that offline sale.
    — Expected: it does **not** claim "sent". It should show a "queued, will send
      automatically once you're back online" message.
      — `App\Services\Delivery\MessageQueueService::sendOrQueueEmail()` catches the
        connection failure and writes a row to the local `message_queue` table
        instead of throwing a hard error.
25. Close the app window (the X button). **Correction from an earlier version
    of this doc**: on Windows, this vendor version of NativePHP does NOT hide
    to the tray — `window-all-closed` calls `app.quit()` unconditionally on
    non-macOS (see `vendor/nativephp/desktop/resources/electron/out/main/
    index.js`), which triggers `before-quit` and kills every child PHP
    process (the embedded server, the queue worker). Closing the window is a
    full quit, same as quitting from the tray icon. Reopen from the Desktop
    shortcut or tray icon and confirm you're still logged in (the local
    session isn't cleared by a same-process resume — see step 32 for when it
    *should* clear) and the POS state is intact. If your build ever adds true
    hide-to-tray behavior, this step must be re-verified and this note
    updated — right now the tray icon exists for quick relaunch/status only,
    not for keeping a hidden window alive.

## 8. Reconnect

26. Re-enable the network. Watch the sync status pill: **Offline → Online →
    Syncing → Synced**, within roughly 10–20 seconds.
    — `App\Jobs\RunDesktopSyncCycle`, self-rescheduling via the queue worker
      NativePHP auto-starts; `App\Services\Sync\DesktopSyncClient::runCycle()`.
27. Confirm the queued email from step 24 actually sends now, and its status
    updates from "queued" to genuinely sent (check for a success indicator; there's
    no dedicated "queue inbox" UI yet, so confirm via the recipient inbox and/or
    `message_queue` table status if you have DB access).
28. On the **web app** (a different browser/machine, logged into the same tenant),
    confirm: the offline sale from step 17, the new product and customer from step
    18, the quotation from step 19, the stock adjustment from step 20, and the
    customer payment from step 21 **all appear** — this is the actual proof the
    sync engine round-tripped correctly, not just that it "ran".
29. Reverse direction: on the web app, create a new sale, edit an existing
    product's name/price, and add a new customer. Back on the desktop app, wait for
    the next sync cycle (or watch the status pill cycle once) and confirm all three
    show up locally.
29b. Also reverse-direction, but starting FROM the desktop app this time:
    create a new category, a new brand, and a new supplier while the desktop
    app is online. Confirm all three show up on the **web app** within one
    sync cycle. — Categories/brands/suppliers/units used to be pulled from
    the server but never pushed back (`App\Services\Sync\DesktopSyncClient`
    only listed products/customers in `legacyPushableModels`); they're now
    included, wired through `created_categories`/`created_brands`/
    `created_suppliers`/`created_units` in `PosSyncApiController::syncBatch()`.
29c. Edit the tenant's address, currency symbol, or invoice/quote terms in
    **Settings** on the web app. Confirm the change appears on the desktop
    app after its next sync cycle (previously these settings were only ever
    written to the desktop's local `Company` row once, at first-login
    bootstrap, and never refreshed again — see the `company` block now
    included in `GET /api/v1/pos/sync-catalog`).
30. Check for duplicates anywhere in steps 28–29c — none of the synced records
    should appear twice on either side. This is what `desktop_sync_receipts` and
    the `(company_id, external_id)` uniqueness constraints exist to guarantee;
    a duplicate here is a real bug, not a flake.

## 9. Session security & restart reliability

31. Fully quit the app (tray icon **Quit**, or the window's X button — per the
    step 25 correction, both are a full quit in this build).
32. Reopen from the Desktop shortcut.
    — Expected: **login screen**, not the dashboard. This is
      `App\Services\Auth\DesktopSessionGuard::clearStaleSessionsOnBoot()`, which
      runs once per cold start in `NativeAppServiceProvider::boot()`.
    — Also confirm the app actually opens at all, promptly, every time. Two
      previously-fatal boot bugs are fixed and covered by
      `tests/Feature/DesktopStartupAndErrorRecoveryTest.php` and
      `tests/Feature/Jobs/RunDesktopSyncCycleTest.php`, but both need real
      Windows hardware to fully confirm: (a) `RunDesktopSyncCycle` was
      missing the `Queueable` trait, so scheduling it threw an uncaught
      `Error` on literally every boot, right after the window opened; (b) a
      cached config from a build machine froze `nativephp-internal.running`
      to `false`, silently pointing every local write at the build server's
      own MySQL instead of the per-device SQLite file. Repeat this
      close-then-reopen cycle at least 5 times in a row, and again after a
      full Windows restart, to rule out any process/lock-file leftover the
      Linux sandbox this was built in cannot reproduce.
33. Log in again with the **same account** — this time it should be fully offline-
    capable immediately if the network is still off (no bootstrap round-trip
    needed, since this device already has a local password hash from step 3).
33b. **Switching accounts on the same device**: while still signed in as the
    account from step 33, log out and log in as a *different* tenant's
    account on this same device (a second real account, or a second
    registration from step 2 of a fresh account). Immediately after that
    second login, confirm every page (Products, Customers, POS, Reports)
    shows ONLY the second account's data — none of the first account's rows
    anywhere. This exercises a serious fixed bug in `ResolveTenantContext`
    (the global tenant-scoping middleware, runs on every single web/desktop
    page load): it used to only bind the current tenant into the app
    container if nothing was bound yet. That's invisible on a normal
    stateless web server (fresh process per request), but NativePHP keeps
    ONE PHP process alive for the entire app session — so switching accounts
    without fully quitting the app used to leave the FIRST account's tenant
    id bound forever, silently serving its data to the second account.
    Regression-tested in `tests/Feature/TenantIsolationInvestigationTest.php`
    (`test_switching_tenants_on_the_same_process_does_not_leak_the_previous_tenants_data`),
    but a real device is the only way to confirm the Electron/NativePHP
    process genuinely behaves the same way as the PHPUnit reproduction.

## 10. Uninstall / reinstall

34. Uninstall via "Add or remove programs". Confirm the app is removed cleanly.
    By default `NATIVEPHP_NSIS_DELETE_APP_DATA` is **off**
    (`config/nativephp.php` → `nsis.delete_app_data_on_uninstall`), so local
    business data should survive an uninstall unless that flag was explicitly
    enabled at build time.
35. Reinstall (or install a newer version over an existing one). Confirm all the
    business data created during this test script (products, customers, sales,
    quotations, stock adjustments) is still present — this validates the "preserve
    user data during updates" requirement.

## Known gaps to expect, not bugs

- **Cash drawer auto-kick**: not implemented. `System::print()` only accepts HTML,
  not raw ESC/POS bytes, so a real drawer-kick would need a bundled native helper
  this session had no hardware to build/verify against. Many thermal printers with
  a drawer cable kick the drawer automatically on any print job via their own
  Windows driver — worth checking if your printer already does this for free
  before treating it as missing.
- **Cash register Z-report/movement slip printing**: still uses the browser
  print-dialog path only (fully functional), not yet wired to the silent
  `DesktopPrintService` path that receipts/invoices/quotations use.
- **WhatsApp**: requires a Meta WhatsApp Cloud API account and credentials
  (Settings → Notifications) to send automatically. Without them, it correctly
  falls back to the manual `wa.me` link flow rather than silently failing.
