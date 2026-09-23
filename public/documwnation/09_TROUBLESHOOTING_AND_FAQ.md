# Troubleshooting, API Reference and Complete Coverage Inventory

## Help & Support

For help with Zoom Sales CRM & Inventory, contact:

- **WhatsApp:** [+91 85350 75196](https://wa.me/918535075196)
- **Telegram:** [@cloudonext](https://t.me/cloudonext)
- **Email:** [support@zoomnearby.com](mailto:support@zoomnearby.com)

Include the app version, affected screen and a brief description of the problem.


## Common problems and resolution

| Symptom | Check and instruction |
| --- | --- |
| Installer says PHP 8.2 is acceptable | Its requirements-screen check is older than Composer; follow the actual PHP `^8.3` requirement and platform check |
| Installer returns instead of dashboard | Complete administrator/core-license and finalization; migrations alone do not mark installation complete |
| Missing styles or images | Build Vite assets; verify public document root, storage link and upload permissions |
| Wrong tenant or branch | Verify authenticated company, selected store, headers/query context and staff assignment |
| Missing module menu | Check installed/active package, license, tenant selection, plan features, permissions and refreshed bootstrap |
| Client sign-in blocked | Inspect URL, TLS, license result, verification status, app build and token/session validity |
| Queue/reminder not delivered | Check configured channel, cron, due time/timezone, queue name, worker and failed-job/provider response |
| API responds 401/403 | Check a current user-bound credential, permission and branch access; do not retry with another tenant's identifier |
| Overdue results include future balances | Current filter prioritizes all unpaid balances; it is not an exclusively past-due filter |
| Date range does nothing | Use `filter=custom_date` with both dates; it filters creation date |
| Pending offline sale | Preserve local outbox; inspect connectivity/auth/error/conflict status; reconnect and retry |
| Printer is unreachable | Test the device's Bluetooth/LAN connection, supported platform, paper width and printer selection |
| Fiscal response contains an identifier | Inspect driver behavior; a locally generated payload/reference is not tax-authority acceptance |
| Snapshot cannot be restored in UI | Current backup tool exports JSON tables in ZIP; use a separately verified infrastructure recovery procedure |

## API usage and response examples



Use HTTPS with `Authorization: Bearer <tenant-user-token>` and `Accept: application/json`. JSON requests also need `Content-Type: application/json`. These examples require an authenticated user with the relevant permissions; a company-only integration credential is not a substitute for every user operation.

### 9.1 Switch active store

`POST /api/v1/tenant/stores/switch`

```json
{"store_id": 2}
```

Illustrative response excerpt; the actual resource includes additional branch fields and a `store` alias:

```json
{
  "success": true,
  "message": "Switched to Zoom Sales CRM & Inventory North successfully",
  "current_store": {
    "id": 2,
    "name": "Zoom Sales CRM & Inventory North",
    "branch_code": "BLR-02"
  },
  "current_store_id": 2
}
```

The controller checks `stores.view`, tenant ownership, staff assignment and active status, then persists `current_store_id` on the user. Unauthorized, missing or inactive branches produce errors rather than a successful context switch.

### 9.2 List sales

`GET /api/v1/tenant/sales`

| Parameter | Meaning |
| --- | --- |
| `store_id` | Authorized branch ID; must be consistent with resolved store context |
| `filter` | One of the values in section 7 |
| `start_date`, `end_date` | Both required to apply `custom_date`; use `YYYY-MM-DD` |
| `query` | Search sale number, tracking code, customer or stored items |
| `page`, `per_page` | Pagination; default page size is 20 |

Example: `/api/v1/tenant/sales?store_id=2&filter=custom_date&start_date=2026-09-01&end_date=2026-09-23`.

Illustrative response excerpt; `sales` aliases `data`, and additional pagination and sale fields are returned:

```json
{
  "success": true,
  "data": [
    {
      "id": "1048",
      "server_id": 1048,
      "invoice_number": "POS-82F054EF",
      "customer_name": "Example Customer",
      "total_amount": 12.98,
      "due_amount": 0,
      "payment_status": "paid",
      "created_at": "2026-09-23T00:05:00+00:00"
    }
  ],
  "meta": {"total": 1, "current_page": 1, "last_page": 1, "per_page": 20}
}
```

`id` is serialized as a string and can be the external sale ID; `server_id` is the database ID. Monetary fields shown here are JSON numbers. Example records are fictional.



## Coverage and how to use this inventory

The route catalog below is generated from `php artisan route:list --json` for this installation, including compatibility aliases, public endpoints and framework/vendor routes. Methods and middleware are copied from registered metadata. Route presence is not a live endpoint test or a complete payload specification.

For each operation: locate its controller action, inspect validation and middleware, use the appropriate session or user-bound API credential, select the intended store, supply the required fields, submit once and inspect the response. Web mutations also require the applicable CSRF/session handling. For the detailed customer workflows, follow chapters 00 and 05.

Public actions and UI fields are separately inventoried so that actions performed through Livewire or package code are not omitted just because they lack an individual HTTP route. Lifecycle/helper methods and state fields are labeled as source references, not advertised as independent customer features. All Flutter Dart surfaces are inventoried in chapter 06.

**Coverage snapshot:** 1,355 registered route entries; 598 distinct route actions; 273 Flutter Dart source files.

## Registered route catalog

### Routes: App / Http / Controllers / Api / AppPreferenceController@updatePreferences

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| GET\|POST\|PUT\|HEAD | `/api/app/app-preferences` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications |
| GET\|POST\|PUT\|HEAD | `/api/app/preferences` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications |
| GET\|POST\|PUT\|HEAD | `/api/app/settings/app-preferences/drawer` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications |
| GET\|POST\|PUT\|HEAD | `/api/tenant/app-preferences` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications |
| GET\|POST\|PUT\|HEAD | `/api/tenant/preferences` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications |
| GET\|POST\|PUT\|HEAD | `/api/tenant/settings/app-preferences/drawer` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications |
| GET\|POST\|PUT\|HEAD | `/api/v1/tenant/app-preferences` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications |
| GET\|POST\|PUT\|HEAD | `/api/v1/tenant/preferences` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications |
| GET\|POST\|PUT\|HEAD | `/api/v1/tenant/settings/app-preferences/drawer` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications |

### Routes: App / Http / Controllers / Api / CustomerController@search

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| GET\|HEAD | `/api/app/customers/search` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:customers,view |
| GET\|HEAD | `/api/customers/search` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:customers,view |
| GET\|HEAD | `/api/tenant/customers/search` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:customers,view |

### Routes: App / Http / Controllers / Api / DashboardController@salesChart

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| GET\|HEAD | `/api/dashboard/sales-chart` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications |
| GET\|HEAD | `/api/tenant/dashboard/sales-chart` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications |
| GET\|HEAD | `/api/v1/dashboard/sales-chart` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications |
| GET\|HEAD | `/api/v1/pos/dashboard/sales-chart` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications |
| GET\|HEAD | `/api/v1/tenant/dashboard/sales-chart` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications |

### Routes: App / Http / Controllers / Api / DashboardController@show

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| GET\|HEAD | `/api/app/views/dashboard` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications |
| GET\|HEAD | `/api/tenant/views/dashboard` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications |
| GET\|HEAD | `/api/v1/tenant/views/dashboard` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications |

### Routes: App / Http / Controllers / Api / DashboardController@summary

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| GET\|HEAD | `/api/dashboard/summary` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications |
| GET\|HEAD | `/api/tenant/dashboard/summary` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications |
| GET\|HEAD | `/api/v1/dashboard/summary` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications |
| GET\|HEAD | `/api/v1/pos/dashboard/summary` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications |
| GET\|HEAD | `/api/v1/tenant/dashboard/summary` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications |

### Routes: App / Http / Controllers / Api / DispatchController@dispatchDocument

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| POST | `/api/dispatch/{type}/{id}` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:pos,create |
| POST | `/api/tenant/dispatch/{type}/{id}` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:pos,create |
| POST | `/api/v1/pos/dispatch/{type}/{id}` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:pos,create |
| POST | `/api/v1/tenant/dispatch/{type}/{id}` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:pos,create |
| POST | `/tenant/dispatch/{type}/{id}` | tenant.dispatch.document | web, App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, Illuminate\Auth\Middleware\Authenticate:web, App\Http\Middleware\ResolveTenantContext, App\Http\Middleware\EnsureTenantEmailIsVerified, App\Http\Middleware\EnsureTenantSubscriptionActive |

### Routes: App / Http / Controllers / Api / DispatchController@dispatchEmail

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| POST | `/api/tenant/dispatch/email` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:pos,create |
| POST | `/api/v1/tenant/dispatch/email` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:pos,create |
| POST | `/tenant/dispatch/email` | tenant.dispatch.email | web, App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, Illuminate\Auth\Middleware\Authenticate:web, App\Http\Middleware\ResolveTenantContext, App\Http\Middleware\EnsureTenantEmailIsVerified, App\Http\Middleware\EnsureTenantSubscriptionActive |

### Routes: App / Http / Controllers / Api / DispatchController@dispatchSms

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| POST | `/api/tenant/dispatch/sms` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:pos,create |
| POST | `/api/v1/tenant/dispatch/sms` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:pos,create |
| POST | `/tenant/dispatch/sms` | tenant.dispatch.sms | web, App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, Illuminate\Auth\Middleware\Authenticate:web, App\Http\Middleware\ResolveTenantContext, App\Http\Middleware\EnsureTenantEmailIsVerified, App\Http\Middleware\EnsureTenantSubscriptionActive |

### Routes: App / Http / Controllers / Api / DocumentActionController@actionsSheet

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| GET\|HEAD | `/api/tenant/documents/{type}/actions-sheet/{id?}` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications |
| GET\|HEAD | `/api/tenant/documents/{type}/{id}/actions-sheet` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications |
| GET\|HEAD | `/api/v1/tenant/documents/{type}/actions-sheet/{id?}` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications |
| GET\|HEAD | `/api/v1/tenant/documents/{type}/{id}/actions-sheet` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications |
| GET\|HEAD | `/tenant/documents/{type}/{id}/actions-sheet` | tenant.documents.actions-sheet | web, App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, Illuminate\Auth\Middleware\Authenticate:web, App\Http\Middleware\ResolveTenantContext, App\Http\Middleware\EnsureTenantEmailIsVerified, App\Http\Middleware\EnsureTenantSubscriptionActive, App\Http\Middleware\CheckTenantPermission:sales,view |

### Routes: App / Http / Controllers / Api / DocumentDispatchController@dispatchDocument

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| POST | `/api/documents/dispatch` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications |
| POST | `/api/tenant/documents/dispatch` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications |
| POST | `/api/v1/documents/dispatch` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications |
| POST | `/api/v1/tenant/documents/dispatch` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications |
| POST | `/tenant/documents/dispatch` | tenant.documents.dispatch | web, App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, Illuminate\Auth\Middleware\Authenticate:web, App\Http\Middleware\ResolveTenantContext, App\Http\Middleware\EnsureTenantEmailIsVerified, App\Http\Middleware\EnsureTenantSubscriptionActive, App\Http\Middleware\CheckTenantPermission:pos,create |

### Routes: App / Http / Controllers / Api / DocumentDispatchController@getDispatchOptions

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| GET\|HEAD | `/api/documents/{type}/{id}/dispatch-options` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications |
| GET\|HEAD | `/api/tenant/documents/{type}/{id}/dispatch-options` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications |
| GET\|HEAD | `/api/v1/documents/{type}/{id}/dispatch-options` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications |
| GET\|HEAD | `/api/v1/tenant/documents/{type}/{id}/dispatch-options` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications |
| GET\|HEAD | `/tenant/documents/{type}/{id}/dispatch-options` | tenant.documents.dispatch-options | web, App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, Illuminate\Auth\Middleware\Authenticate:web, App\Http\Middleware\ResolveTenantContext, App\Http\Middleware\EnsureTenantEmailIsVerified, App\Http\Middleware\EnsureTenantSubscriptionActive, App\Http\Middleware\CheckTenantPermission:sales,view |

### Routes: App / Http / Controllers / Api / DocumentDispatchController@getEnabledChannels

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| GET\|HEAD | `/api/documents/channels` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications |
| GET\|HEAD | `/api/tenant/dispatch/channels` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications |
| GET\|HEAD | `/api/v1/documents/channels` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications |
| GET\|HEAD | `/api/v1/tenant/dispatch/channels` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications |

### Routes: App / Http / Controllers / Api / DocumentPreviewController@previewModal

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| GET\|HEAD | `/api/tenant/documents/{type}/{id}/preview-modal` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications |
| GET\|HEAD | `/api/v1/tenant/documents/{type}/{id}/preview-modal` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications |
| GET\|HEAD | `/tenant/documents/{type}/{id}/preview-modal` | tenant.documents.preview-modal | web, App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, Illuminate\Auth\Middleware\Authenticate:web, App\Http\Middleware\ResolveTenantContext, App\Http\Middleware\EnsureTenantEmailIsVerified, App\Http\Middleware\EnsureTenantSubscriptionActive |

### Routes: App / Http / Controllers / Api / DocumentPreviewController@renderHtml

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| GET\|HEAD | `/api/tenant/documents/{type}/{id}/render-html` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications |
| GET\|HEAD | `/api/v1/tenant/documents/{type}/{id}/render-html` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications |
| GET\|HEAD | `/tenant/documents/{type}/{id}/render-html` | tenant.documents.render-html | web, App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, Illuminate\Auth\Middleware\Authenticate:web, App\Http\Middleware\ResolveTenantContext, App\Http\Middleware\EnsureTenantEmailIsVerified, App\Http\Middleware\EnsureTenantSubscriptionActive |

### Routes: App / Http / Controllers / Api / InvoiceController@actionsSheet

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| GET\|HEAD | `/api/invoices/actions-sheet/{id?}` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:sales,view |
| GET\|HEAD | `/api/invoices/{id}/actions-sheet` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:sales,view |
| GET\|HEAD | `/api/tenant/invoices/actions-sheet/{id?}` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:sales,view |
| GET\|HEAD | `/api/tenant/invoices/{id}/actions-sheet` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:sales,view |
| GET\|HEAD | `/api/tenant/views/invoices/actions-sheet/{id?}` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:sales,view |
| GET\|HEAD | `/api/tenant/views/invoices/{id}/actions-sheet` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:sales,view |
| GET\|HEAD | `/api/v1/tenant/invoices/actions-sheet/{id?}` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:sales,view |
| GET\|HEAD | `/api/v1/tenant/invoices/{id}/actions-sheet` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:sales,view |
| GET\|HEAD | `/api/v1/tenant/views/invoices/actions-sheet/{id?}` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:sales,view |
| GET\|HEAD | `/api/v1/tenant/views/invoices/{id}/actions-sheet` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:sales,view |

### Routes: App / Http / Controllers / Api / InvoiceController@createSchema

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| GET\|HEAD | `/api/invoices/create` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications |
| GET\|HEAD | `/api/tenant/invoices/create` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications |
| GET\|HEAD | `/api/tenant/views/invoices/create` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications |
| GET\|HEAD | `/api/v1/tenant/invoices/create` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications |
| GET\|HEAD | `/api/v1/tenant/views/invoices/create` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications |

### Routes: App / Http / Controllers / Api / InvoiceController@index

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| GET\|HEAD | `/api/invoices` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:sales,view |
| GET\|HEAD | `/api/sales/invoices` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:sales,view |
| GET\|HEAD | `/api/tenant/invoices` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:sales,view |
| GET\|HEAD | `/api/tenant/receivables` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:sales,view |
| GET\|HEAD | `/api/v1/pos/invoices` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:sales,view |
| GET\|HEAD | `/api/v1/pos/sales/invoices` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:sales,view |
| GET\|HEAD | `/api/v1/sales/invoices` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:sales,view |
| GET\|HEAD | `/api/v1/tenant/invoices` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:sales,view |
| GET\|HEAD | `/api/v1/tenant/receivables` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:sales,view |

### Routes: App / Http / Controllers / Api / InvoiceController@store

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| POST | `/api/tenant/invoices` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications |
| POST | `/api/v1/tenant/invoices` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications |

### Routes: App / Http / Controllers / Api / InvoicePreviewController@previewSheet

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| GET\|HEAD | `/api/invoices/preview-sheet/{id?}` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications |
| GET\|HEAD | `/api/invoices/{id}/preview-sheet` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications |
| GET\|HEAD | `/api/tenant/invoices/preview-sheet/{id?}` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications |
| GET\|HEAD | `/api/tenant/invoices/{id}/preview-sheet` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications |
| GET\|HEAD | `/api/v1/tenant/invoices/preview-sheet/{id?}` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications |
| GET\|HEAD | `/api/v1/tenant/invoices/{id}/preview-sheet` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications |

### Routes: App / Http / Controllers / Api / LeadController@createSchema

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| GET\|HEAD | `/api/leads/create` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\EnsureTenantExtension:leadmanagement |
| GET\|HEAD | `/api/tenant/leads/create` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\EnsureTenantExtension:leadmanagement |
| GET\|HEAD | `/api/tenant/views/create-lead` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\EnsureTenantExtension:leadmanagement |
| GET\|HEAD | `/api/v1/tenant/leads/create` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\EnsureTenantExtension:leadmanagement |
| GET\|HEAD | `/api/v1/tenant/views/create-lead` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\EnsureTenantExtension:leadmanagement |

### Routes: App / Http / Controllers / Api / LeadController@dashboard

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| GET\|HEAD | `/api/tenant/views/lead-management` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:leads,view, App\Http\Middleware\EnsureTenantExtension:leadmanagement |
| GET\|HEAD | `/api/tenant/views/leads` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:leads,view, App\Http\Middleware\EnsureTenantExtension:leadmanagement |
| GET\|HEAD | `/api/v1/tenant/views/lead-management` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:leads,view, App\Http\Middleware\EnsureTenantExtension:leadmanagement |
| GET\|HEAD | `/api/v1/tenant/views/leads` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:leads,view, App\Http\Middleware\EnsureTenantExtension:leadmanagement |

### Routes: App / Http / Controllers / Api / LeadController@followups

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| GET\|HEAD | `/api/tenant/leads/followups` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:leads,view, App\Http\Middleware\EnsureTenantExtension:leadmanagement |
| GET\|HEAD | `/api/v1/tenant/leads/followups` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:leads,view, App\Http\Middleware\EnsureTenantExtension:leadmanagement |

### Routes: App / Http / Controllers / Api / LeadController@leadsList

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| GET\|HEAD | `/api/tenant/leads/list` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:leads,view, App\Http\Middleware\EnsureTenantExtension:leadmanagement |
| GET\|HEAD | `/api/v1/tenant/leads/list` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:leads,view, App\Http\Middleware\EnsureTenantExtension:leadmanagement |

### Routes: App / Http / Controllers / Api / LicenseActivationController@activate

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| POST | `/api/license/activate` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext |

### Routes: App / Http / Controllers / Api / NavigationController@getDrawerMenu

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| GET\|HEAD | `/api/app/drawer/menu` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications |
| GET\|HEAD | `/api/app/navigation/menu` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications |
| GET\|HEAD | `/api/drawer-menu` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications |
| GET\|HEAD | `/api/drawer/menu` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications |
| GET\|HEAD | `/api/menu` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications |
| GET\|HEAD | `/api/navigation/menu` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications |
| GET\|HEAD | `/api/tenant/drawer/menu` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications |
| GET\|HEAD | `/api/tenant/navigation/menu` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications |
| GET\|HEAD | `/api/v1/navigation/menu` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications |
| GET\|HEAD | `/api/v1/pos/drawer-menu` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications |
| GET\|HEAD | `/api/v1/pos/drawer/menu` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications |
| GET\|HEAD | `/api/v1/pos/menu` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications |
| GET\|HEAD | `/api/v1/pos/navigation/menu` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications |
| GET\|HEAD | `/api/v1/tenant/navigation/menu` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications |

### Routes: App / Http / Controllers / Api / NavigationMenuController@saveMenuSettings

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| POST | `/api/app/navigation/menu` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications |
| POST | `/api/drawer-menu` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications |
| POST | `/api/drawer/menu` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications |
| POST | `/api/menu` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications |
| POST | `/api/navigation/menu` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications |
| POST | `/api/settings/navigation-menu` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications |
| POST | `/api/tenant/navigation/menu` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications |
| POST | `/api/tenant/settings/navigation-menu` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications |

### Routes: App / Http / Controllers / Api / NotificationController@clearAll

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| POST | `/api/notifications/clear-all` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications |
| POST | `/api/tenant/notifications/clear-all` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications |
| POST | `/api/v1/tenant/notifications/clear-all` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications |
| POST | `/tenant/notifications/clear-all` | tenant.notifications.clear-all | web, App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, Illuminate\Auth\Middleware\Authenticate:web, App\Http\Middleware\ResolveTenantContext, App\Http\Middleware\EnsureTenantEmailIsVerified, App\Http\Middleware\EnsureTenantSubscriptionActive |

### Routes: App / Http / Controllers / Api / NotificationController@dismiss

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| POST | `/api/notifications/dismiss` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications |
| DELETE | `/api/notifications/{id}` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications |
| POST | `/api/notifications/{id}/dismiss` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications |
| POST | `/api/notifications/{type}/{id}/dismiss` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications |
| POST | `/api/tenant/notifications/dismiss` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications |
| DELETE | `/api/tenant/notifications/{id}` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications |
| POST | `/api/tenant/notifications/{id}/dismiss` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications |
| POST | `/api/tenant/notifications/{type}/{id}/dismiss` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications |
| POST | `/api/v1/tenant/notifications/dismiss` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications |
| DELETE | `/api/v1/tenant/notifications/{id}` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications |
| POST | `/api/v1/tenant/notifications/{id}/dismiss` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications |
| POST | `/api/v1/tenant/notifications/{type}/{id}/dismiss` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications |
| POST | `/tenant/notifications/{id}/dismiss` | tenant.notifications.dismiss.simple | web, App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, Illuminate\Auth\Middleware\Authenticate:web, App\Http\Middleware\ResolveTenantContext, App\Http\Middleware\EnsureTenantEmailIsVerified, App\Http\Middleware\EnsureTenantSubscriptionActive |
| POST | `/tenant/notifications/{type}/{id}/dismiss` | tenant.notifications.dismiss | web, App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, Illuminate\Auth\Middleware\Authenticate:web, App\Http\Middleware\ResolveTenantContext, App\Http\Middleware\EnsureTenantEmailIsVerified, App\Http\Middleware\EnsureTenantSubscriptionActive |

### Routes: App / Http / Controllers / Api / NotificationController@feed

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| GET\|HEAD | `/api/tenant/notifications/feed` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications |
| GET\|HEAD | `/api/v1/tenant/notifications/feed` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications |
| GET\|HEAD | `/tenant/notifications/feed` | tenant.notifications.feed | web, App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, Illuminate\Auth\Middleware\Authenticate:web, App\Http\Middleware\ResolveTenantContext, App\Http\Middleware\EnsureTenantEmailIsVerified, App\Http\Middleware\EnsureTenantSubscriptionActive |

### Routes: App / Http / Controllers / Api / NotificationController@unreadCount

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| GET\|HEAD | `/api/notifications/unread-count` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications |
| GET\|HEAD | `/api/tenant/notifications/unread-count` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications |
| GET\|HEAD | `/api/v1/tenant/notifications/unread-count` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications |

### Routes: App / Http / Controllers / Api / QuotationController@actionsSheet

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| GET\|HEAD | `/api/quotations/actions-sheet/{id?}` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:quotes,view |
| GET\|HEAD | `/api/quotations/{id}/actions-sheet` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:quotes,view |
| GET\|HEAD | `/api/tenant/quotations/actions-sheet/{id?}` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:quotes,view |
| GET\|HEAD | `/api/tenant/quotations/{id}/actions-sheet` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:quotes,view |
| GET\|HEAD | `/api/tenant/views/quotations/actions-sheet/{id?}` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:quotes,view |
| GET\|HEAD | `/api/tenant/views/quotations/{id}/actions-sheet` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:quotes,view |
| GET\|HEAD | `/api/v1/tenant/quotations/actions-sheet/{id?}` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:quotes,view |
| GET\|HEAD | `/api/v1/tenant/quotations/{id}/actions-sheet` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:quotes,view |
| GET\|HEAD | `/api/v1/tenant/views/quotations/actions-sheet/{id?}` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:quotes,view |
| GET\|HEAD | `/api/v1/tenant/views/quotations/{id}/actions-sheet` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:quotes,view |

### Routes: App / Http / Controllers / Api / QuotationController@createModal

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| GET\|HEAD | `/api/quotations/create-modal` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:quotes,view |
| GET\|HEAD | `/api/tenant/quotations/create-modal` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:quotes,view |
| GET\|HEAD | `/api/tenant/views/quotations/create-modal` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:quotes,view |
| GET\|HEAD | `/api/v1/tenant/quotations/create-modal` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:quotes,view |
| GET\|HEAD | `/api/v1/tenant/views/quotations/create-modal` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:quotes,view |

### Routes: App / Http / Controllers / Api / QuotationController@createSchema

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| GET\|HEAD | `/api/quotations/create` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:quotes,view |
| GET\|HEAD | `/api/tenant/quotations/create` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:quotes,view |
| GET\|HEAD | `/api/tenant/views/quotations/create` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:quotes,view |
| GET\|HEAD | `/api/v1/pos/quotations/create` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:quotes,view |
| GET\|HEAD | `/api/v1/tenant/quotations/create` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:quotes,view |
| GET\|HEAD | `/api/v1/tenant/views/quotations/create` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:quotes,view |

### Routes: App / Http / Controllers / Api / QuotationController@dispatchQuotation

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| POST | `/api/quotations/{id}/dispatch` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:quotes,create |
| POST | `/api/tenant/quotations/{id}/dispatch` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:quotes,create |
| POST | `/api/v1/tenant/quotations/{id}/dispatch` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:quotes,create |

### Routes: App / Http / Controllers / Api / QuotationController@previewSheet

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| GET\|HEAD | `/api/quotations/preview-sheet/{id?}` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:quotes,view |
| GET\|HEAD | `/api/quotations/{id}/preview-sheet` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:quotes,view |
| GET\|HEAD | `/api/tenant/quotations/preview-sheet/{id?}` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:quotes,view |
| GET\|HEAD | `/api/tenant/quotations/{id}/preview-sheet` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:quotes,view |
| GET\|HEAD | `/api/tenant/views/quotations/preview-sheet/{id?}` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:quotes,view |
| GET\|HEAD | `/api/tenant/views/quotations/{id}/preview-sheet` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:quotes,view |
| GET\|HEAD | `/api/v1/tenant/quotations/preview-sheet/{id?}` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:quotes,view |
| GET\|HEAD | `/api/v1/tenant/quotations/{id}/preview-sheet` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:quotes,view |
| GET\|HEAD | `/api/v1/tenant/views/quotations/preview-sheet/{id?}` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:quotes,view |
| GET\|HEAD | `/api/v1/tenant/views/quotations/{id}/preview-sheet` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:quotes,view |

### Routes: App / Http / Controllers / Api / QuotationController@previewView

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| GET\|HEAD | `/api/quotations/{id}/preview` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:quotes,view |
| GET\|HEAD | `/api/tenant/quotations/{id}/preview` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:quotes,view |
| GET\|HEAD | `/api/tenant/views/quotations/{id}/preview` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:quotes,view |
| GET\|HEAD | `/api/v1/tenant/quotations/{id}/preview` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:quotes,view |
| GET\|HEAD | `/api/v1/tenant/views/quotations/{id}/preview` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:quotes,view |

### Routes: App / Http / Controllers / Api / QuotationController@sendSheet

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| GET\|HEAD | `/api/quotations/send-sheet/{id?}` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:quotes,view |
| GET\|HEAD | `/api/quotations/{id}/send-sheet` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:quotes,view |
| GET\|HEAD | `/api/tenant/quotations/send-sheet/{id?}` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:quotes,view |
| GET\|HEAD | `/api/tenant/quotations/{id}/send-sheet` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:quotes,view |
| GET\|HEAD | `/api/tenant/views/quotations/send-sheet/{id?}` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:quotes,view |
| GET\|HEAD | `/api/tenant/views/quotations/{id}/send-sheet` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:quotes,view |
| GET\|HEAD | `/api/v1/tenant/quotations/send-sheet/{id?}` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:quotes,view |
| GET\|HEAD | `/api/v1/tenant/quotations/{id}/send-sheet` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:quotes,view |
| GET\|HEAD | `/api/v1/tenant/views/quotations/send-sheet/{id?}` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:quotes,view |
| GET\|HEAD | `/api/v1/tenant/views/quotations/{id}/send-sheet` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:quotes,view |

### Routes: App / Http / Controllers / Api / QuotationController@sendView

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| GET\|HEAD | `/api/quotations/{id}/send` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:quotes,view |
| GET\|HEAD | `/api/tenant/quotations/{id}/send` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:quotes,view |
| GET\|HEAD | `/api/tenant/views/quotations/{id}/send` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:quotes,view |
| GET\|HEAD | `/api/v1/tenant/quotations/{id}/send` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:quotes,view |
| GET\|HEAD | `/api/v1/tenant/views/quotations/{id}/send` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:quotes,view |

### Routes: App / Http / Controllers / Api / QuotationController@showSchema

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| GET\|HEAD | `/api/quotations/{id}` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:quotes,view |
| GET\|HEAD | `/api/tenant/quotations/{id}` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:quotes,view |
| GET\|HEAD | `/api/tenant/views/quotations/{id}` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:quotes,view |
| GET\|HEAD | `/api/v1/tenant/quotations/{id}` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:quotes,view |
| GET\|HEAD | `/api/v1/tenant/views/quotations/{id}` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:quotes,view |

### Routes: App / Http / Controllers / Api / QuotationController@store

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| POST | `/api/tenant/quotations` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:quotes,create |
| POST | `/api/v1/tenant/quotations` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:quotes,create |

### Routes: App / Http / Controllers / Api / ReceivablesController@reminderSheet

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| GET\|HEAD | `/api/receivables/{id}/reminder-sheet` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:customers,view |
| GET\|HEAD | `/api/tenant/receivables/{id}/reminder-sheet` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:customers,view |
| GET\|HEAD | `/api/v1/pos/receivables/{sale}/reminder-sheet` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:customers,view |
| GET\|HEAD | `/api/v1/tenant/receivables/{id}/reminder-sheet` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:customers,view |

### Routes: App / Http / Controllers / Api / RolePermissionController@getPermissionsSchema

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| GET\|HEAD | `/api/permissions` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:users,view |
| GET\|HEAD | `/api/roles/schema` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:users,view |
| GET\|HEAD | `/api/tenant/permissions` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:users,view |
| GET\|HEAD | `/api/tenant/roles/schema` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:users,view |
| GET\|HEAD | `/api/v1/pos/permissions` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:users,view |
| GET\|HEAD | `/api/v1/pos/roles/schema` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:users,view |
| GET\|HEAD | `/api/v1/tenant/permissions` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:users,view |
| GET\|HEAD | `/api/v1/tenant/roles/schema` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:users,view |

### Routes: App / Http / Controllers / Api / TenantSettingsController@saveSmsCredentials

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| POST | `/api/app/settings/sms-gateway` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:settings,edit |
| POST | `/api/tenant/settings/sms-gateway` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, Illuminate\Auth\Middleware\Authenticate:sanctum, App\Http\Middleware\AuthenticateTenantApi |
| POST | `/api/v1/tenant/settings/sms-gateway` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, Illuminate\Auth\Middleware\Authenticate:sanctum, App\Http\Middleware\AuthenticateTenantApi |

### Routes: App / Http / Controllers / Api / TenantSettingsController@sendTestSms

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| POST | `/api/app/settings/sms-gateway/test` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:settings,edit |
| POST | `/api/tenant/settings/sms-gateway/test` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, Illuminate\Auth\Middleware\Authenticate:sanctum, App\Http\Middleware\AuthenticateTenantApi |
| POST | `/api/v1/tenant/settings/sms-gateway/test` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, Illuminate\Auth\Middleware\Authenticate:sanctum, App\Http\Middleware\AuthenticateTenantApi |

### Routes: App / Http / Controllers / Api / Tenant / CouponApiController@destroy

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| DELETE | `/api/tenant/coupons/{id}` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications |
| POST | `/api/tenant/coupons/{id}/delete` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications |
| DELETE | `/api/v1/pos/coupons/{id}` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:settings,edit |
| POST | `/api/v1/pos/coupons/{id}/delete` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:settings,edit |
| DELETE | `/api/v1/tenant/coupons/{id}` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications |
| POST | `/api/v1/tenant/coupons/{id}/delete` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications |

### Routes: App / Http / Controllers / Api / Tenant / CouponApiController@index

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| GET\|HEAD | `/api/tenant/coupons` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications |
| GET\|HEAD | `/api/v1/pos/coupons` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:settings,view |
| GET\|HEAD | `/api/v1/tenant/coupons` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications |

### Routes: App / Http / Controllers / Api / Tenant / CouponApiController@show

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| GET\|HEAD | `/api/tenant/coupons/{id}` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications |
| GET\|HEAD | `/api/v1/pos/coupons/{id}` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:settings,view |
| GET\|HEAD | `/api/v1/tenant/coupons/{id}` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications |

### Routes: App / Http / Controllers / Api / Tenant / CouponApiController@store

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| POST | `/api/tenant/coupons` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications |
| POST | `/api/v1/pos/coupons` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:settings,edit |
| POST | `/api/v1/tenant/coupons` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications |

### Routes: App / Http / Controllers / Api / Tenant / CouponApiController@update

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| PUT\|POST | `/api/tenant/coupons/{id}` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications |
| PUT\|POST | `/api/v1/pos/coupons/{id}` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:settings,edit |
| PUT\|POST | `/api/v1/tenant/coupons/{id}` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications |

### Routes: App / Http / Controllers / Api / Tenant / FaqApiController@destroy

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| DELETE | `/api/tenant/faqs/{id}` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications |
| POST | `/api/tenant/faqs/{id}/delete` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications |
| DELETE | `/api/v1/pos/faqs/{id}` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:settings,edit |
| POST | `/api/v1/pos/faqs/{id}/delete` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:settings,edit |
| DELETE | `/api/v1/tenant/faqs/{id}` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications |
| POST | `/api/v1/tenant/faqs/{id}/delete` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications |

### Routes: App / Http / Controllers / Api / Tenant / FaqApiController@index

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| GET\|HEAD | `/api/tenant/faqs` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications |
| GET\|HEAD | `/api/v1/pos/faqs` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:settings,view |
| GET\|HEAD | `/api/v1/tenant/faqs` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications |

### Routes: App / Http / Controllers / Api / Tenant / FaqApiController@show

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| GET\|HEAD | `/api/tenant/faqs/{id}` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications |
| GET\|HEAD | `/api/v1/pos/faqs/{id}` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:settings,view |
| GET\|HEAD | `/api/v1/tenant/faqs/{id}` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications |

### Routes: App / Http / Controllers / Api / Tenant / FaqApiController@store

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| POST | `/api/tenant/faqs` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications |
| POST | `/api/v1/pos/faqs` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:settings,edit |
| POST | `/api/v1/tenant/faqs` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications |

### Routes: App / Http / Controllers / Api / Tenant / FaqApiController@update

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| PUT\|POST | `/api/tenant/faqs/{id}` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications |
| PUT\|POST | `/api/v1/pos/faqs/{id}` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:settings,edit |
| PUT\|POST | `/api/v1/tenant/faqs/{id}` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications |

### Routes: App / Http / Controllers / Api / Tenant / SalesController@index

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| GET\|HEAD | `/api/tenant/sales` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:sales,view |
| GET\|HEAD | `/api/v1/pos/sales` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:sales,view |
| GET\|HEAD | `/api/v1/tenant/sales` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:sales,view |

### Routes: App / Http / Controllers / Api / Tenant / StoreController@assignStaff

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| POST | `/api/v1/tenant/stores/{id}/staff` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications |

### Routes: App / Http / Controllers / Api / Tenant / StoreController@index

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| GET\|HEAD | `/api/v1/tenant/stores` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications |

### Routes: App / Http / Controllers / Api / Tenant / StoreController@management

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| GET\|HEAD | `/tenant/settings/stores` | tenant.settings.stores | web, App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, Illuminate\Auth\Middleware\Authenticate:web, App\Http\Middleware\ResolveTenantContext, App\Http\Middleware\EnsureTenantEmailIsVerified |

### Routes: App / Http / Controllers / Api / Tenant / StoreController@staff

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| GET\|HEAD | `/api/v1/tenant/stores/{id}/staff` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications |

### Routes: App / Http / Controllers / Api / Tenant / StoreController@store

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| POST | `/api/v1/tenant/stores` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications |

### Routes: App / Http / Controllers / Api / Tenant / StoreController@switch

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| POST | `/api/v1/tenant/stores/switch` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications |
| POST | `/api/v1/tenant/stores/{id}/switch` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications |

### Routes: App / Http / Controllers / Api / Tenant / StoreController@update

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| PUT | `/api/v1/tenant/stores/{id}` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications |

### Routes: App / Http / Controllers / Api / Tenant / StoreController@webStore

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| POST | `/tenant/settings/stores` | tenant.stores.create | web, App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, Illuminate\Auth\Middleware\Authenticate:web, App\Http\Middleware\ResolveTenantContext, App\Http\Middleware\EnsureTenantEmailIsVerified, App\Http\Middleware\PreventDemoModifications |

### Routes: App / Http / Controllers / Api / Tenant / StoreController@webSwitch

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| POST | `/tenant/settings/stores/{id}/switch` | tenant.stores.switch | web, App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, Illuminate\Auth\Middleware\Authenticate:web, App\Http\Middleware\ResolveTenantContext, App\Http\Middleware\EnsureTenantEmailIsVerified |

### Routes: App / Http / Controllers / Api / Tenant / StoreController@webUpdate

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| PUT | `/tenant/settings/stores/{id}` | tenant.stores.update | web, App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, Illuminate\Auth\Middleware\Authenticate:web, App\Http\Middleware\ResolveTenantContext, App\Http\Middleware\EnsureTenantEmailIsVerified, App\Http\Middleware\PreventDemoModifications |

### Routes: App / Http / Controllers / Api / Tenant / StorefrontSettingsController@getBannerAuth

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| GET\|HEAD | `/api/tenant/storefront/banner-auth` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications |
| GET\|HEAD | `/api/v1/pos/storefront/banner-auth` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\EnsureTenantExtension:ecommerce_storefront, App\Http\Middleware\CheckTenantApiUserPermission:storefront,view |
| GET\|HEAD | `/api/v1/tenant/storefront/banner-auth` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications |

### Routes: App / Http / Controllers / Api / Tenant / StorefrontSettingsController@getDomainConfig

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| GET\|HEAD | `/api/tenant/storefront/domain-config` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications |
| GET\|HEAD | `/api/v1/pos/storefront/domain-config` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\EnsureTenantExtension:ecommerce_storefront, App\Http\Middleware\CheckTenantApiUserPermission:storefront,view |
| GET\|HEAD | `/api/v1/tenant/storefront/domain-config` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications |
| GET\|HEAD | `/tenant/storefront/domain-config` |  | web, App\Http\Middleware\AuthenticateTenantApi |

### Routes: App / Http / Controllers / Api / Tenant / StorefrontSettingsController@getPaymentGateways

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| GET\|HEAD | `/api/tenant/storefront/payment-gateways` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications |
| GET\|HEAD | `/api/v1/pos/storefront/payment-gateways` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\EnsureTenantExtension:ecommerce_storefront, App\Http\Middleware\CheckTenantApiUserPermission:gateways,view |
| GET\|HEAD | `/api/v1/tenant/storefront/payment-gateways` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications |

### Routes: App / Http / Controllers / Api / Tenant / StorefrontSettingsController@updateBannerAuth

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| POST\|PUT | `/api/tenant/storefront/banner-auth` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications |
| POST\|PUT | `/api/v1/pos/storefront/banner-auth` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\EnsureTenantExtension:ecommerce_storefront, App\Http\Middleware\CheckTenantApiUserPermission:storefront,edit |
| POST\|PUT | `/api/v1/tenant/storefront/banner-auth` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications |

### Routes: App / Http / Controllers / Api / Tenant / StorefrontSettingsController@updateDomainConfig

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| POST\|PUT | `/api/tenant/storefront/domain-config` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications |
| POST\|PUT | `/api/v1/pos/storefront/domain-config` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\EnsureTenantExtension:ecommerce_storefront, App\Http\Middleware\CheckTenantApiUserPermission:storefront,edit |
| POST\|PUT | `/api/v1/tenant/storefront/domain-config` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications |
| PUT\|POST | `/tenant/storefront/domain-config` |  | web, App\Http\Middleware\AuthenticateTenantApi |

### Routes: App / Http / Controllers / Api / Tenant / StorefrontSettingsController@updatePaymentGateways

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| POST\|PUT | `/api/tenant/storefront/payment-gateways` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications |
| POST\|PUT | `/api/v1/pos/storefront/payment-gateways` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\EnsureTenantExtension:ecommerce_storefront, App\Http\Middleware\CheckTenantApiUserPermission:gateways,edit |
| POST\|PUT | `/api/v1/tenant/storefront/payment-gateways` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications |

### Routes: App / Http / Controllers / Api / UnifiedDispatchController@batchDispatch

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| POST | `/api/tenant/dispatch/batch` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, Illuminate\Auth\Middleware\Authenticate:sanctum, App\Http\Middleware\AuthenticateTenantApi |
| POST | `/api/tenant/dispatch/batch-send` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, Illuminate\Auth\Middleware\Authenticate:sanctum, App\Http\Middleware\AuthenticateTenantApi |
| POST | `/api/v1/tenant/dispatch/batch` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, Illuminate\Auth\Middleware\Authenticate:sanctum, App\Http\Middleware\AuthenticateTenantApi |
| POST | `/api/v1/tenant/dispatch/batch-send` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, Illuminate\Auth\Middleware\Authenticate:sanctum, App\Http\Middleware\AuthenticateTenantApi |
| POST | `/tenant/dispatch/batch` | tenant.dispatch.batch | web, App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, Illuminate\Auth\Middleware\Authenticate:web, App\Http\Middleware\ResolveTenantContext, App\Http\Middleware\EnsureTenantEmailIsVerified, App\Http\Middleware\EnsureTenantSubscriptionActive, App\Http\Middleware\CheckTenantPermission:pos,create |
| POST | `/tenant/dispatch/batch-send` | tenant.dispatch.batch-send | web, App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, Illuminate\Auth\Middleware\Authenticate:web, App\Http\Middleware\ResolveTenantContext, App\Http\Middleware\EnsureTenantEmailIsVerified, App\Http\Middleware\EnsureTenantSubscriptionActive, App\Http\Middleware\CheckTenantPermission:pos,create |

### Routes: App / Http / Controllers / Api / UnifiedDispatchController@dispatch

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| POST | `/api/dispatch/send` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications |
| POST | `/api/tenant/dispatch/send` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, Illuminate\Auth\Middleware\Authenticate:sanctum, App\Http\Middleware\AuthenticateTenantApi |
| POST | `/api/v1/tenant/dispatch/send` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, Illuminate\Auth\Middleware\Authenticate:sanctum, App\Http\Middleware\AuthenticateTenantApi |
| POST | `/tenant/dispatch/send` | tenant.dispatch.send | web, App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, Illuminate\Auth\Middleware\Authenticate:web, App\Http\Middleware\ResolveTenantContext, App\Http\Middleware\EnsureTenantEmailIsVerified, App\Http\Middleware\EnsureTenantSubscriptionActive, App\Http\Middleware\CheckTenantPermission:pos,create |

### Routes: App / Http / Controllers / Api / V1 / AiImageApiController@availability

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| GET\|HEAD | `/api/v1/pos/ai-image/availability` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:products,view |

### Routes: App / Http / Controllers / Api / V1 / AiImageApiController@generate

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| POST | `/api/v1/pos/ai-image/generate` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:products,edit |

### Routes: App / Http / Controllers / Api / V1 / ApiIntegrationsController@dispatchDocument

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| POST | `/api/app/notifications/dispatch` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:pos,create |
| POST | `/api/tenant/notifications/dispatch` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:pos,create |
| POST | `/api/v1/tenant/notifications/dispatch` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:pos,create |

### Routes: App / Http / Controllers / Api / V1 / ApiIntegrationsController@index

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| GET\|HEAD | `/api/app/api-integrations` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:settings,view |
| GET\|HEAD | `/api/tenant/api-integrations` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:settings,view |
| GET\|HEAD | `/api/v1/tenant/api-integrations` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:settings,view |

### Routes: App / Http / Controllers / Api / V1 / ApiIntegrationsController@regenerateApiKey

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| POST | `/api/app/api-keys/regenerate` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:settings,edit |
| POST | `/api/tenant/api-keys/regenerate` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:settings,edit |
| POST | `/api/v1/tenant/api-keys/regenerate` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:settings,edit |

### Routes: App / Http / Controllers / Api / V1 / ApiIntegrationsController@saveChannel

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| POST\|PUT | `/api/app/api-integrations/{channel}` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:settings,edit |
| POST\|PUT | `/api/tenant/api-integrations/{channel}` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:settings,edit |
| POST\|PUT | `/api/v1/tenant/api-integrations/{channel}` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:settings,edit |

### Routes: App / Http / Controllers / Api / V1 / ApiIntegrationsController@testChannel

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| POST | `/api/app/api-integrations/{channel}/test` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:settings,edit |
| POST | `/api/tenant/api-integrations/{channel}/test` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:settings,edit |
| POST | `/api/v1/tenant/api-integrations/{channel}/test` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:settings,edit |

### Routes: App / Http / Controllers / Api / V1 / AppBootstrapController@bootstrap

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| GET\|HEAD | `/api/app/bootstrap` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications |
| GET\|HEAD | `/api/bootstrap` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications |
| GET\|HEAD | `/api/tenant/bootstrap` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications |
| GET\|HEAD | `/api/v1/bootstrap` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications |
| GET\|HEAD | `/api/v1/pos/app/bootstrap` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications |
| GET\|HEAD | `/api/v1/tenant/bootstrap` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications |

### Routes: App / Http / Controllers / Api / V1 / AppBootstrapController@switchMode

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| POST | `/api/app/mode` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:settings,edit |
| POST | `/api/v1/pos/app/mode` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:settings,edit |

### Routes: App / Http / Controllers / Api / V1 / AppBootstrapController@updateNav

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| POST | `/api/v1/pos/settings/nav-config` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:settings,edit |

### Routes: App / Http / Controllers / Api / V1 / AuthApiController@checkSubdomain

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| GET\|HEAD | `/api/auth/check-subdomain` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext |
| GET\|HEAD | `/api/public/check-subdomain` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext |
| GET\|HEAD | `/api/v1/auth/check-subdomain` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext |
| GET\|HEAD | `/api/v1/pos/auth/check-subdomain` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext |
| GET\|HEAD | `/api/v1/pos/check-subdomain` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext |
| GET\|HEAD | `/api/v1/pos/public/check-subdomain` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext |
| GET\|HEAD | `/api/v1/public/check-subdomain` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext |

### Routes: App / Http / Controllers / Api / V1 / AuthApiController@register

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| POST | `/api/auth/register` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext |
| POST | `/api/register` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext |
| POST | `/api/v1/pos/auth/register` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext |
| POST | `/api/v1/pos/register` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext |

### Routes: App / Http / Controllers / Api / V1 / AuthApiController@resendOtp

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| POST | `/api/api/app/resend-otp` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext |
| POST | `/api/app/resend-otp` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext |
| POST | `/api/auth/resend-otp` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext |
| POST | `/api/v1/pos/app/resend-otp` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext |
| POST | `/api/v1/pos/auth/resend-otp` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext |

### Routes: App / Http / Controllers / Api / V1 / AuthApiController@verifyEmailOtp

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| POST | `/api/api/app/verify-otp` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext |
| POST | `/api/api/auth/verify-email-otp` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext |
| POST | `/api/api/auth/verify-otp` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext |
| POST | `/api/app/verify-otp` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext |
| POST | `/api/auth/verify-email-otp` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext |
| POST | `/api/auth/verify-otp` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext |
| POST | `/api/v1/pos/app/verify-otp` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext |
| POST | `/api/v1/pos/auth/verify-email-otp` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext |
| POST | `/api/v1/pos/auth/verify-otp` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext |

### Routes: App / Http / Controllers / Api / V1 / CashRegisterApiController@close

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| POST | `/api/v1/pos/cash-register/{id}/close` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:cash_register,edit |

### Routes: App / Http / Controllers / Api / V1 / CashRegisterApiController@closeRegister

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| POST | `/api/tenant/cash-register/close` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:cash_register,edit |

### Routes: App / Http / Controllers / Api / V1 / CashRegisterApiController@current

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| GET\|HEAD | `/api/v1/pos/cash-register/current` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:cash_register,view |

### Routes: App / Http / Controllers / Api / V1 / CashRegisterApiController@history

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| GET\|HEAD | `/api/tenant/cash-register/history` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:cash_register,view |
| GET\|HEAD | `/api/v1/pos/cash-register/history` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:cash_register,view |

### Routes: App / Http / Controllers / Api / V1 / CashRegisterApiController@open

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| POST | `/api/v1/pos/cash-register/open` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:cash_register,create |

### Routes: App / Http / Controllers / Api / V1 / CashRegisterApiController@openRegister

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| POST | `/api/tenant/cash-register/open` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:cash_register,create |

### Routes: App / Http / Controllers / Api / V1 / CashRegisterApiController@recordTransaction

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| POST | `/api/tenant/cash-register/{id}/transaction` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:cash_register,edit |
| POST | `/api/v1/pos/cash-register/{id}/transaction` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:cash_register,edit |

### Routes: App / Http / Controllers / Api / V1 / CashRegisterApiController@show

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| GET\|HEAD | `/api/v1/pos/cash-register/{id}` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:cash_register,view |

### Routes: App / Http / Controllers / Api / V1 / CashRegisterApiController@status

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| GET\|HEAD | `/api/tenant/cash-register/status` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:cash_register,view |

### Routes: App / Http / Controllers / Api / V1 / CatalogAdminApiController@brandsDestroy

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| DELETE | `/api/v1/pos/brands/{id}` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:categories,edit |

### Routes: App / Http / Controllers / Api / V1 / CatalogAdminApiController@brandsIndex

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| GET\|HEAD | `/api/v1/pos/brands` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:categories,view |

### Routes: App / Http / Controllers / Api / V1 / CatalogAdminApiController@brandsStore

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| POST | `/api/v1/pos/brands` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:categories,create |

### Routes: App / Http / Controllers / Api / V1 / CatalogAdminApiController@brandsUpdate

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| PUT | `/api/v1/pos/brands/{id}` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:categories,edit |

### Routes: App / Http / Controllers / Api / V1 / CatalogAdminApiController@categoriesDestroy

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| DELETE | `/api/app/categories/{id}` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:categories,edit |
| DELETE | `/api/tenant/categories/{id}` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:categories,edit |
| DELETE | `/api/v1/pos/categories/{id}` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:categories,edit |

### Routes: App / Http / Controllers / Api / V1 / CatalogAdminApiController@categoriesIndex

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| GET\|HEAD | `/api/app/categories` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:categories,view |
| GET\|HEAD | `/api/tenant/categories` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:categories,view |
| GET\|HEAD | `/api/v1/pos/categories` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:categories,view |

### Routes: App / Http / Controllers / Api / V1 / CatalogAdminApiController@categoriesStore

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| POST | `/api/app/categories` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:categories,create |
| POST | `/api/tenant/categories` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:categories,create |
| POST | `/api/v1/pos/categories` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:categories,create |

### Routes: App / Http / Controllers / Api / V1 / CatalogAdminApiController@categoriesUpdate

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| PUT\|PATCH\|POST | `/api/app/categories/{id}` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:categories,edit |
| PUT\|PATCH\|POST | `/api/tenant/categories/{id}` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:categories,edit |
| PUT | `/api/v1/pos/categories/{id}` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:categories,edit |

### Routes: App / Http / Controllers / Api / V1 / CatalogAdminApiController@suppliersDestroy

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| DELETE | `/api/v1/pos/suppliers/{id}` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:suppliers,edit |

### Routes: App / Http / Controllers / Api / V1 / CatalogAdminApiController@suppliersIndex

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| GET\|HEAD | `/api/v1/pos/suppliers` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:suppliers,view |

### Routes: App / Http / Controllers / Api / V1 / CatalogAdminApiController@suppliersStore

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| POST | `/api/v1/pos/suppliers` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:suppliers,create |

### Routes: App / Http / Controllers / Api / V1 / CatalogAdminApiController@suppliersUpdate

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| PUT | `/api/v1/pos/suppliers/{id}` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:suppliers,edit |

### Routes: App / Http / Controllers / Api / V1 / CatalogAdminApiController@unitsDestroy

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| DELETE | `/api/v1/pos/units/{id}` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:units,edit |

### Routes: App / Http / Controllers / Api / V1 / CatalogAdminApiController@unitsIndex

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| GET\|HEAD | `/api/v1/pos/units` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:units,view |

### Routes: App / Http / Controllers / Api / V1 / CatalogAdminApiController@unitsStore

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| POST | `/api/v1/pos/units` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:units,create |

### Routes: App / Http / Controllers / Api / V1 / CatalogAdminApiController@unitsUpdate

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| PUT | `/api/v1/pos/units/{id}` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:units,edit |

### Routes: App / Http / Controllers / Api / V1 / CatalogApiController@destroy

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| DELETE | `/api/v1/pos/catalog/{id}` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:catalog,edit |

### Routes: App / Http / Controllers / Api / V1 / CatalogApiController@index

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| GET\|HEAD | `/api/v1/pos/catalog` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:catalog,view |

### Routes: App / Http / Controllers / Api / V1 / CatalogApiController@store

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| POST | `/api/v1/pos/catalog` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:catalog,create |

### Routes: App / Http / Controllers / Api / V1 / ConsignmentApiController@destroy

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| DELETE | `/api/v1/pos/consignments/{id}` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:consignments,edit |

### Routes: App / Http / Controllers / Api / V1 / ConsignmentApiController@dispatch

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| POST | `/api/v1/pos/consignments/{id}/dispatch` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:consignments,edit |

### Routes: App / Http / Controllers / Api / V1 / ConsignmentApiController@finalize

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| POST | `/api/v1/pos/consignments/{id}/finalize` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:consignments,edit |

### Routes: App / Http / Controllers / Api / V1 / ConsignmentApiController@index

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| GET\|HEAD | `/api/v1/pos/consignments` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:consignments,view |

### Routes: App / Http / Controllers / Api / V1 / ConsignmentApiController@reconcile

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| POST | `/api/v1/pos/consignments/{id}/reconcile` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:consignments,edit |

### Routes: App / Http / Controllers / Api / V1 / ConsignmentApiController@show

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| GET\|HEAD | `/api/v1/pos/consignments/{id}` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:consignments,view |

### Routes: App / Http / Controllers / Api / V1 / ConsignmentApiController@store

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| POST | `/api/v1/pos/consignments` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:consignments,create |

### Routes: App / Http / Controllers / Api / V1 / DeviceApiController@index

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| GET\|HEAD | `/api/devices` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications |
| GET\|HEAD | `/api/tenant/devices` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications |
| GET\|HEAD | `/api/v1/pos/device-sessions` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications |
| GET\|HEAD | `/api/v1/pos/devices` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications |

### Routes: App / Http / Controllers / Api / V1 / DeviceApiController@revoke

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| POST | `/api/devices/{token}/revoke` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications |
| POST | `/api/tenant/devices/{token}/revoke` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications |
| POST | `/api/v1/pos/device-sessions/{token}/revoke` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications |
| POST | `/api/v1/pos/devices/{token}/revoke` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications |

### Routes: App / Http / Controllers / Api / V1 / EcommerceWebhookController@handleOrders

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| POST | `/api/integrations/webhooks/{tenant_uuid}/orders` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext |
| POST | `/api/v1/integrations/webhooks/{tenant_uuid}/orders` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext |

### Routes: App / Http / Controllers / Api / V1 / LandingApiController@planDetail

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| GET\|HEAD | `/api/pricing-plans/{id}` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext |
| GET\|HEAD | `/api/v1/pricing-plans/{id}` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext |

### Routes: App / Http / Controllers / Api / V1 / LandingApiController@plans

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| GET\|HEAD | `/api/pricing-plans` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext |
| GET\|HEAD | `/api/subscription/plans` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext |
| GET\|HEAD | `/api/v1/pos/public/plans` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext |
| GET\|HEAD | `/api/v1/pricing-plans` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext |
| GET\|HEAD | `/api/v1/subscription/plans` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext |

### Routes: App / Http / Controllers / Api / V1 / LandingApiController@show

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| GET\|HEAD | `/api/public/landing` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext |
| GET\|HEAD | `/api/public/landing-config` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext |
| GET\|HEAD | `/api/v1/pos/auth/landing` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext |
| GET\|HEAD | `/api/v1/pos/public/landing` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext |
| GET\|HEAD | `/api/v1/pos/public/landing-config` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext |
| GET\|HEAD | `/api/v1/public/landing-config` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext |

### Routes: App / Http / Controllers / Api / V1 / LandingApiController@submitContact

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| POST | `/api/public/contact` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext |
| POST | `/api/public/contact-us` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext |
| POST | `/api/v1/pos/public/contact` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext |
| POST | `/api/v1/pos/public/contact-us` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext |
| POST | `/api/v1/public/contact` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext |
| POST | `/api/v1/public/contact-us` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext |

### Routes: App / Http / Controllers / Api / V1 / LanguageApiController@appTranslations

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| GET\|HEAD | `/api/app/translations` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications |

### Routes: App / Http / Controllers / Api / V1 / LanguageApiController@index

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| GET\|HEAD | `/api/v1/pos/languages` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:settings,view |

### Routes: App / Http / Controllers / Api / V1 / LanguageApiController@setDefault

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| PUT | `/api/v1/pos/languages/default` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:settings,edit |

### Routes: App / Http / Controllers / Api / V1 / LanguageApiController@translations

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| GET\|HEAD | `/api/v1/pos/languages/translations/{locale}` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications |

### Routes: App / Http / Controllers / Api / V1 / PayablesApiController@destroy

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| DELETE | `/api/v1/pos/payables/{id}` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:finance,edit |

### Routes: App / Http / Controllers / Api / V1 / PayablesApiController@index

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| GET\|HEAD | `/api/v1/pos/payables` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:finance,view |

### Routes: App / Http / Controllers / Api / V1 / PayablesApiController@pay

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| POST | `/api/v1/pos/payables/{id}/pay` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:finance,edit |

### Routes: App / Http / Controllers / Api / V1 / PayablesApiController@store

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| POST | `/api/v1/pos/payables` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:finance,create |

### Routes: App / Http / Controllers / Api / V1 / PayablesApiController@update

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| PUT | `/api/v1/pos/payables/{id}` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:finance,edit |

### Routes: App / Http / Controllers / Api / V1 / PermissionApiController@show

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| GET\|HEAD | `/api/v1/pos/users/{id}/permissions` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:users,view |

### Routes: App / Http / Controllers / Api / V1 / PermissionApiController@update

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| PUT | `/api/v1/pos/users/{id}/permissions` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:users,edit |

### Routes: App / Http / Controllers / Api / V1 / PharmacyApiController@batchBarcode

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| GET\|HEAD | `/api/tenant/pharmacy/batches/{id}/barcode` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:products,view |
| GET\|HEAD | `/api/v1/pos/pharmacy/batches/{id}/barcode` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:products,view |

### Routes: App / Http / Controllers / Api / V1 / PharmacyApiController@batchSheet

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| GET\|HEAD | `/api/tenant/pharmacy/batch-sheet` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:products,view |
| GET\|HEAD | `/api/v1/pos/pharmacy/batch-sheet` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:products,view |

### Routes: App / Http / Controllers / Api / V1 / PharmacyApiController@batchesAdjust

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| POST | `/api/tenant/pharmacy/batches/adjust` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:products,edit |
| POST | `/api/tenant/pharmacy/batches/{id}/adjust` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:products,edit |
| POST | `/api/v1/pos/pharmacy/batches/adjust` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:products,edit |
| POST | `/api/v1/pos/pharmacy/batches/{id}/adjust` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:products,edit |

### Routes: App / Http / Controllers / Api / V1 / PharmacyApiController@batchesIndex

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| GET\|HEAD | `/api/tenant/pharmacy/batches` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:products,view |
| GET\|HEAD | `/api/v1/pos/pharmacy/batches` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:products,view |

### Routes: App / Http / Controllers / Api / V1 / PharmacyApiController@batchesReturn

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| POST | `/api/tenant/pharmacy/batches/return` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:products,edit |
| POST | `/api/tenant/pharmacy/batches/{id}/return` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:products,edit |
| POST | `/api/v1/pos/pharmacy/batches/return` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:products,edit |
| POST | `/api/v1/pos/pharmacy/batches/{id}/return` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:products,edit |

### Routes: App / Http / Controllers / Api / V1 / PharmacyApiController@batchesStore

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| POST | `/api/tenant/pharmacy/batches` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:products,create |
| POST | `/api/v1/pos/pharmacy/batches` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:products,create |

### Routes: App / Http / Controllers / Api / V1 / PharmacyApiController@checkout

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| POST | `/api/tenant/pharmacy/checkout` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:pos,create |
| POST | `/api/v1/pos/pharmacy/checkout` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:pos,create |

### Routes: App / Http / Controllers / Api / V1 / PharmacyApiController@checkoutSheet

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| GET\|HEAD | `/api/tenant/pharmacy/checkout-sheet` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:pos,view |
| GET\|HEAD | `/api/v1/pos/pharmacy/checkout-sheet` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:pos,view |

### Routes: App / Http / Controllers / Api / V1 / PharmacyApiController@prescriptionCheckout

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| POST | `/api/tenant/pharmacy/prescriptions/{id}/checkout` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:pos,create |
| POST | `/api/v1/pos/pharmacy/prescriptions/{id}/checkout` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:pos,create |

### Routes: App / Http / Controllers / Api / V1 / PharmacyApiController@prescriptionCheckoutSheet

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| GET\|HEAD | `/api/tenant/pharmacy/prescriptions/{id}/checkout-sheet` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:pos,view |
| GET\|HEAD | `/api/v1/pos/pharmacy/prescriptions/{id}/checkout-sheet` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:pos,view |

### Routes: App / Http / Controllers / Api / V1 / PharmacyApiController@prescriptionsDispense

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| POST | `/api/tenant/pharmacy/prescriptions/{id}/dispense` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:pos,edit |
| POST | `/api/v1/pos/pharmacy/prescriptions/{id}/dispense` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:pos,edit |

### Routes: App / Http / Controllers / Api / V1 / PharmacyApiController@prescriptionsIndex

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| GET\|HEAD | `/api/tenant/pharmacy/prescriptions` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:sales,view |
| GET\|HEAD | `/api/v1/pos/pharmacy/prescriptions` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:sales,view |

### Routes: App / Http / Controllers / Api / V1 / PharmacyApiController@prescriptionsStore

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| POST | `/api/tenant/pharmacy/prescriptions` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:sales,create |
| POST | `/api/v1/pos/pharmacy/prescriptions` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:sales,create |

### Routes: App / Http / Controllers / Api / V1 / PharmacyApiController@search

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| GET\|HEAD | `/api/tenant/pharmacy/search` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:products,view |
| GET\|HEAD | `/api/v1/pos/pharmacy/search` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:products,view |

### Routes: App / Http / Controllers / Api / V1 / PosDesktopSyncController@pull

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| GET\|HEAD | `/api/v1/pos/desktop-sync/pull` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications |

### Routes: App / Http / Controllers / Api / V1 / PosDesktopSyncController@push

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| POST | `/api/v1/pos/desktop-sync/push` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:pos,create |

### Routes: App / Http / Controllers / Api / V1 / PosSyncApiController@analytics

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| GET\|HEAD | `/api/v1/pos/analytics` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:reports,view |

### Routes: App / Http / Controllers / Api / V1 / PosSyncApiController@authConfig

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| GET\|HEAD | `/api/api/app/auth-config` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext |
| GET\|HEAD | `/api/app/auth-config` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext |
| GET\|HEAD | `/api/v1/pos/app/auth-config` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext |
| GET\|HEAD | `/api/v1/pos/auth-config` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext |
| GET\|HEAD | `/api/v1/pos/auth/auth-config` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext |

### Routes: App / Http / Controllers / Api / V1 / PosSyncApiController@branding

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| GET\|HEAD | `/api/v1/pos/auth/branding` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext |

### Routes: App / Http / Controllers / Api / V1 / PosSyncApiController@customerLedger

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| GET\|HEAD | `/api/app/customers/{id}/ledger` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:customers,view |
| GET\|HEAD | `/api/tenant/customers/{id}/ledger` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:customers,view |
| GET\|HEAD | `/api/v1/pos/customers/{id}/ledger` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:customers,view |

### Routes: App / Http / Controllers / Api / V1 / PosSyncApiController@customerRecordPayment

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| POST | `/api/app/customers/{id}/payment` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:finance,edit |
| POST | `/api/tenant/customers/{id}/payment` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:finance,edit |
| POST | `/api/v1/pos/customers/{id}/payment` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:finance,edit |

### Routes: App / Http / Controllers / Api / V1 / PosSyncApiController@customersIndex

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| GET\|HEAD | `/api/app/customers` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:customers,view |
| GET\|HEAD | `/api/tenant/customers` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:customers,view |
| GET\|HEAD | `/api/v1/pos/customers` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:customers,view |

### Routes: App / Http / Controllers / Api / V1 / PosSyncApiController@customersSearch

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| GET\|HEAD | `/api/v1/pos/customers/search` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:customers,view |
| GET\|HEAD | `/api/v1/tenant/customers/search` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:customers,view |

### Routes: App / Http / Controllers / Api / V1 / PosSyncApiController@customersStore

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| POST | `/api/app/customers` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:customers,create |
| POST | `/api/tenant/customers` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:customers,create |
| POST | `/api/v1/pos/customers` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:customers,create |

### Routes: App / Http / Controllers / Api / V1 / PosSyncApiController@desktopWebSession

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| POST | `/api/v1/pos/auth/desktop-session` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications |

### Routes: App / Http / Controllers / Api / V1 / PosSyncApiController@dueReceivables

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| GET\|HEAD | `/api/v1/pos/receivables/due` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:customers,view |

### Routes: App / Http / Controllers / Api / V1 / PosSyncApiController@inventoryAdjustStock

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| POST | `/api/v1/pos/inventory/adjust` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:products,edit |

### Routes: App / Http / Controllers / Api / V1 / PosSyncApiController@inventoryBulkImport

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| POST | `/api/v1/pos/inventory/import` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:products,create |

### Routes: App / Http / Controllers / Api / V1 / PosSyncApiController@inventoryDestroyProduct

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| DELETE | `/api/app/products/{id}` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:products,edit |
| DELETE | `/api/tenant/products/{id}` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:products,edit |
| DELETE | `/api/v1/pos/inventory/product/{id}` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:products,edit |
| DELETE | `/api/v1/pos/inventory/products/{id}` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:products,edit |
| DELETE | `/api/v1/pos/products/{id}` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:products,edit |

### Routes: App / Http / Controllers / Api / V1 / PosSyncApiController@inventoryIndex

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| GET\|HEAD | `/api/v1/pos/inventory` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:products,view |

### Routes: App / Http / Controllers / Api / V1 / PosSyncApiController@inventoryStoreProduct

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| POST | `/api/v1/pos/inventory/product` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:products,create |

### Routes: App / Http / Controllers / Api / V1 / PosSyncApiController@inventoryUploadProductImage

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| POST | `/api/v1/pos/inventory/product/{id}/image` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:products,edit |

### Routes: App / Http / Controllers / Api / V1 / PosSyncApiController@login

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| POST | `/api/v1/pos/auth/login` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext |

### Routes: App / Http / Controllers / Api / V1 / PosSyncApiController@publicSettings

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| GET\|HEAD | `/api/v1/pos/app/public-settings` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext |
| GET\|HEAD | `/api/v1/pos/auth/public-settings` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext |
| GET\|HEAD | `/api/v1/pos/public/settings` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext |

### Routes: App / Http / Controllers / Api / V1 / PosSyncApiController@registrationConfig

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| GET\|HEAD | `/api/v1/pos/auth/registration-config` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext |

### Routes: App / Http / Controllers / Api / V1 / PosSyncApiController@registrationMeta

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| GET\|HEAD | `/api/app/registration-meta` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext |
| GET\|HEAD | `/api/v1/pos/app/registration-meta` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext |
| GET\|HEAD | `/api/v1/pos/auth/registration-meta` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext |

### Routes: App / Http / Controllers / Api / V1 / PosSyncApiController@remindReceivable

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| GET\|POST\|HEAD | `/api/v1/pos/receivables/{sale}/remind` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:finance,edit |

### Routes: App / Http / Controllers / Api / V1 / PosSyncApiController@saleDetails

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| GET\|HEAD | `/api/v1/pos/sales/{id}` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:sales,view |

### Routes: App / Http / Controllers / Api / V1 / PosSyncApiController@salePdf

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| GET\|HEAD | `/api/v1/pos/sales/{id}/pdf` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:sales,view |

### Routes: App / Http / Controllers / Api / V1 / PosSyncApiController@scheduleReceivableReminder

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| PUT | `/api/v1/pos/receivables/{sale}/reminder` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:finance,edit |

### Routes: App / Http / Controllers / Api / V1 / PosSyncApiController@sendDelivery

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| POST | `/api/v1/pos/send-delivery` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:pos,create |

### Routes: App / Http / Controllers / Api / V1 / PosSyncApiController@session

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| GET\|HEAD | `/api/v1/pos/auth/me` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications |
| GET\|HEAD | `/api/v1/pos/auth/session` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications |
| GET\|HEAD | `/api/v1/pos/v1/auth/me` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications |

### Routes: App / Http / Controllers / Api / V1 / PosSyncApiController@status

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| GET\|HEAD | `/api/v1/pos/status` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications |

### Routes: App / Http / Controllers / Api / V1 / PosSyncApiController@subscription

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| GET\|HEAD | `/api/v1/pos/subscription` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications |

### Routes: App / Http / Controllers / Api / V1 / PosSyncApiController@subscriptionActivateFree

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| POST | `/api/v1/pos/subscription/plans/{plan}/activate-free` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications |

### Routes: App / Http / Controllers / Api / V1 / PosSyncApiController@subscriptionEntitlements

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| GET\|HEAD | `/api/v1/pos/subscription/entitlements` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications |

### Routes: App / Http / Controllers / Api / V1 / PosSyncApiController@subscriptionFeatures

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| GET\|HEAD | `/api/v1/pos/subscription/features` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications |

### Routes: App / Http / Controllers / Api / V1 / PosSyncApiController@subscriptionMercadoPagoPreference

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| POST | `/api/v1/pos/subscription/plans/{plan}/mercadopago/preference` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications |

### Routes: App / Http / Controllers / Api / V1 / PosSyncApiController@subscriptionMercadoPagoVerify

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| POST | `/api/v1/pos/subscription/plans/{plan}/mercadopago/verify` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications |

### Routes: App / Http / Controllers / Api / V1 / PosSyncApiController@subscriptionRazorpayOrder

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| POST | `/api/v1/pos/subscription/plans/{plan}/razorpay/order` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications |

### Routes: App / Http / Controllers / Api / V1 / PosSyncApiController@subscriptionRazorpayVerify

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| POST | `/api/v1/pos/subscription/plans/{plan}/razorpay/verify` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications |

### Routes: App / Http / Controllers / Api / V1 / PosSyncApiController@subscriptionRedeem

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| POST | `/api/v1/pos/subscription/redeem` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications |

### Routes: App / Http / Controllers / Api / V1 / PosSyncApiController@syncBatch

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| POST | `/api/v1/pos/sync-batch` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:pos,create |

### Routes: App / Http / Controllers / Api / V1 / PosSyncApiController@syncPull

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| GET\|HEAD | `/api/v1/pos/sync-catalog` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:products,view |
| GET\|HEAD | `/api/v1/pos/sync-pull` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:products,view |

### Routes: App / Http / Controllers / Api / V1 / PosSyncApiController@syncPush

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| POST | `/api/v1/pos/sync-push` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:pos,create |
| POST | `/api/v1/pos/sync-sales` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:pos,create |

### Routes: App / Http / Controllers / Api / V1 / PosSyncApiController@taxRulesDestroy

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| DELETE | `/api/tenant/settings/tax-rules/{id}` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:settings,edit |
| POST | `/api/tenant/settings/tax-rules/{id}/delete` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:settings,edit |
| DELETE | `/api/v1/pos/taxes/{id}` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:settings,edit |
| POST | `/api/v1/pos/taxes/{id}/delete` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:settings,edit |

### Routes: App / Http / Controllers / Api / V1 / PosSyncApiController@taxRulesEditSheet

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| GET\|HEAD | `/api/tenant/settings/tax-rules/{id}/edit-sheet` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:settings,view |
| GET\|HEAD | `/api/v1/pos/taxes/{id}/edit-sheet` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:settings,view |

### Routes: App / Http / Controllers / Api / V1 / PosSyncApiController@taxRulesIndex

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| GET\|HEAD | `/api/tenant/settings/tax-rules` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:settings,view |
| GET\|HEAD | `/api/v1/pos/taxes` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications |

### Routes: App / Http / Controllers / Api / V1 / PosSyncApiController@taxRulesSeedCountry

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| POST | `/api/tenant/settings/tax-rules/seed-country` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:settings,edit |
| POST | `/api/v1/pos/taxes/seed-country` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:settings,edit |

### Routes: App / Http / Controllers / Api / V1 / PosSyncApiController@taxRulesSetDefault

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| POST | `/api/tenant/settings/tax-rules/{id}/set-default` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:settings,edit |
| POST | `/api/v1/pos/taxes/{id}/set-default` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:settings,edit |

### Routes: App / Http / Controllers / Api / V1 / PosSyncApiController@taxRulesStore

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| POST | `/api/tenant/settings/tax-rules` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:settings,edit |
| POST | `/api/v1/pos/taxes` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:settings,view |

### Routes: App / Http / Controllers / Api / V1 / PosSyncApiController@taxRulesToggle

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| POST | `/api/tenant/settings/tax-rules/{id}/toggle` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:settings,edit |
| POST | `/api/v1/pos/taxes/{id}/toggle` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:settings,edit |

### Routes: App / Http / Controllers / Api / V1 / PosSyncApiController@taxRulesUpdate

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| PUT\|POST | `/api/tenant/settings/tax-rules/{id}` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:settings,edit |
| PUT\|POST | `/api/v1/pos/taxes/{id}` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:settings,edit |

### Routes: App / Http / Controllers / Api / V1 / PushDeviceApiController@config

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| GET\|HEAD | `/api/v1/pos/auth/push-config` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext |

### Routes: App / Http / Controllers / Api / V1 / PushDeviceApiController@destroy

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| DELETE | `/api/v1/pos/push-devices` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications |

### Routes: App / Http / Controllers / Api / V1 / PushDeviceApiController@store

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| POST | `/api/v1/pos/push-devices` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications |

### Routes: App / Http / Controllers / Api / V1 / PushDeviceApiController@test

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| POST | `/api/v1/pos/push-devices/test` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications |

### Routes: App / Http / Controllers / Api / V1 / QuotationApiController@convert

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| POST | `/api/v1/pos/quotations/{id}/convert` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:quotes,edit |

### Routes: App / Http / Controllers / Api / V1 / QuotationApiController@defaults

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| GET\|HEAD | `/api/v1/pos/quotations/defaults` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:quotes,view |

### Routes: App / Http / Controllers / Api / V1 / QuotationApiController@destroy

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| DELETE | `/api/v1/pos/quotations/{id}` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:quotes,edit |

### Routes: App / Http / Controllers / Api / V1 / QuotationApiController@index

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| GET\|HEAD | `/api/v1/pos/quotations` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:quotes,view |

### Routes: App / Http / Controllers / Api / V1 / QuotationApiController@pdf

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| GET\|HEAD | `/api/tenant/quotations/{id}/pdf` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:quotes,view |
| GET\|HEAD | `/api/tenant/views/quotations/{id}/pdf` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:quotes,view |
| GET\|HEAD | `/api/v1/pos/quotations/{id}/pdf` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:quotes,view |
| GET\|HEAD | `/api/v1/tenant/quotations/{id}/pdf` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:quotes,view |

### Routes: App / Http / Controllers / Api / V1 / QuotationApiController@show

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| GET\|HEAD | `/api/v1/pos/quotations/{id}` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:quotes,view |

### Routes: App / Http / Controllers / Api / V1 / QuotationApiController@store

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| POST | `/api/v1/pos/quotations` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:quotes,create |

### Routes: App / Http / Controllers / Api / V1 / QuotationApiController@update

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| PUT | `/api/v1/pos/quotations/{id}` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:quotes,edit |

### Routes: App / Http / Controllers / Api / V1 / RepairApiController@categoriesDestroy

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| DELETE | `/api/tenant/repair/categories/{id}` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:repair,delete |
| DELETE | `/api/v1/pos/repair/categories/{id}` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:repair,delete |

### Routes: App / Http / Controllers / Api / V1 / RepairApiController@categoriesIndex

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| GET\|HEAD | `/api/tenant/repair/categories` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:repair,view |
| GET\|HEAD | `/api/v1/pos/repair/categories` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:repair,view |

### Routes: App / Http / Controllers / Api / V1 / RepairApiController@categoriesStore

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| POST | `/api/tenant/repair/categories` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:repair,create |
| POST | `/api/v1/pos/repair/categories` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:repair,create |

### Routes: App / Http / Controllers / Api / V1 / RepairApiController@categoriesUpdate

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| PUT\|PATCH\|POST | `/api/tenant/repair/categories/{id}` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:repair,diagnose |
| PUT\|PATCH\|POST | `/api/v1/pos/repair/categories/{id}` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:repair,diagnose |

### Routes: App / Http / Controllers / Api / V1 / RepairApiController@checkoutSheet

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| GET\|HEAD | `/api/tenant/repair/checkout-sheet` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:repair,view |
| GET\|HEAD | `/api/v1/pos/repair/checkout-sheet` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:repair,view |

### Routes: App / Http / Controllers / Api / V1 / RepairApiController@posCheckout

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| POST | `/api/tenant/repair/checkout` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:repair,checkout |
| POST | `/api/tenant/repair/pos-checkout` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:repair,checkout |
| POST | `/api/v1/pos/repair/checkout` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:repair,checkout |
| POST | `/api/v1/pos/repair/pos-checkout` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:repair,checkout |

### Routes: App / Http / Controllers / Api / V1 / RepairApiController@stats

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| GET\|HEAD | `/api/tenant/repair/stats` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:repair,view |
| GET\|HEAD | `/api/v1/pos/repair/stats` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:repair,view |

### Routes: App / Http / Controllers / Api / V1 / RepairApiController@ticketCheckoutSheet

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| GET\|HEAD | `/api/tenant/repair/tickets/{id}/checkout-sheet` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:repair,view |
| GET\|HEAD | `/api/v1/pos/repair/tickets/{id}/checkout-sheet` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:repair,view |

### Routes: App / Http / Controllers / Api / V1 / RepairApiController@ticketIntakeSheet

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| GET\|HEAD | `/api/tenant/repair/tickets/{id}/intake-sheet` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:repair,view |
| GET\|HEAD | `/api/v1/pos/repair/tickets/{id}/intake-sheet` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:repair,view |

### Routes: App / Http / Controllers / Api / V1 / RepairApiController@ticketsAddPart

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| POST | `/api/tenant/repair/tickets/{id}/parts` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:repair,diagnose |
| POST | `/api/v1/pos/repair/tickets/{id}/parts` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:repair,diagnose |

### Routes: App / Http / Controllers / Api / V1 / RepairApiController@ticketsAssign

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| POST | `/api/tenant/repair/tickets/{id}/assign` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:repair,assign |
| POST | `/api/v1/pos/repair/tickets/{id}/assign` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:repair,assign |

### Routes: App / Http / Controllers / Api / V1 / RepairApiController@ticketsDestroy

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| DELETE | `/api/tenant/repair/tickets/{id}` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:repair,delete |
| DELETE | `/api/v1/pos/repair/tickets/{id}` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:repair,delete |

### Routes: App / Http / Controllers / Api / V1 / RepairApiController@ticketsDispatch

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| POST | `/api/tenant/repair/tickets/{id}/dispatch` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:repair,view |
| POST | `/api/v1/pos/repair/tickets/{id}/dispatch` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:repair,view |
| POST | `/tenant/repair/tickets/{ticket}/dispatch` | tenant.repair.ticket.dispatch | web, App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, Illuminate\Auth\Middleware\Authenticate:web, App\Http\Middleware\ResolveTenantContext, App\Http\Middleware\EnsureTenantEmailIsVerified, App\Http\Middleware\EnsureTenantSubscriptionActive, App\Http\Middleware\EnsureTenantVertical:repair_technician, App\Http\Middleware\CheckTenantPermission:repair,view |

### Routes: App / Http / Controllers / Api / V1 / RepairApiController@ticketsIndex

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| GET\|HEAD | `/api/tenant/repair/tickets` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:repair,view |
| GET\|HEAD | `/api/v1/pos/repair/tickets` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:repair,view |

### Routes: App / Http / Controllers / Api / V1 / RepairApiController@ticketsRemovePart

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| DELETE | `/api/tenant/repair/tickets/{ticketId}/parts/{partId}` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:repair,diagnose |
| DELETE | `/api/v1/pos/repair/tickets/{ticketId}/parts/{partId}` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:repair,diagnose |

### Routes: App / Http / Controllers / Api / V1 / RepairApiController@ticketsSetLabor

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| POST | `/api/tenant/repair/tickets/{id}/labor` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:repair,diagnose |
| POST | `/api/v1/pos/repair/tickets/{id}/labor` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:repair,diagnose |

### Routes: App / Http / Controllers / Api / V1 / RepairApiController@ticketsSettle

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| POST | `/api/tenant/repair/tickets/{id}/settle` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:repair,checkout |
| POST | `/api/v1/pos/repair/tickets/{id}/settle` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:repair,checkout |

### Routes: App / Http / Controllers / Api / V1 / RepairApiController@ticketsShareDispatchSheet

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| GET\|HEAD | `/api/tenant/repair/tickets/{id}/share-sheet` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:repair,view |
| GET\|HEAD | `/api/v1/pos/repair/tickets/{id}/share-sheet` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:repair,view |
| GET\|HEAD | `/tenant/repair/tickets/{ticket}/share-sheet` | tenant.repair.ticket.share-sheet | web, App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, Illuminate\Auth\Middleware\Authenticate:web, App\Http\Middleware\ResolveTenantContext, App\Http\Middleware\EnsureTenantEmailIsVerified, App\Http\Middleware\EnsureTenantSubscriptionActive, App\Http\Middleware\EnsureTenantVertical:repair_technician, App\Http\Middleware\CheckTenantPermission:repair,view |

### Routes: App / Http / Controllers / Api / V1 / RepairApiController@ticketsShareSheet

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| GET\|POST\|HEAD | `/api/tenant/repair/tickets/{id}/share` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:repair,view |
| GET\|POST\|HEAD | `/api/v1/pos/repair/tickets/{id}/share` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:repair,view |

### Routes: App / Http / Controllers / Api / V1 / RepairApiController@ticketsShow

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| GET\|HEAD | `/api/tenant/repair/tickets/{id}` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:repair,view |
| GET\|HEAD | `/api/v1/pos/repair/tickets/{id}` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:repair,view |

### Routes: App / Http / Controllers / Api / V1 / RepairApiController@ticketsStore

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| POST | `/api/tenant/repair/tickets` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:repair,create |
| POST | `/api/v1/pos/repair/tickets` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:repair,create |

### Routes: App / Http / Controllers / Api / V1 / RepairApiController@ticketsUpdateChecklist

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| POST | `/api/tenant/repair/tickets/{id}/checklist` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:repair,diagnose |
| POST | `/api/v1/pos/repair/tickets/{id}/checklist` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:repair,diagnose |

### Routes: App / Http / Controllers / Api / V1 / RepairApiController@ticketsUpdateStatus

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| POST | `/api/tenant/repair/tickets/{id}/status` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:repair,diagnose |
| POST | `/api/v1/pos/repair/tickets/{id}/status` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:repair,diagnose |

### Routes: App / Http / Controllers / Api / V1 / ReportsApiController@aging

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| GET\|HEAD | `/api/v1/pos/reports/aging` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:reports,view |

### Routes: App / Http / Controllers / Api / V1 / ReportsApiController@commissions

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| GET\|HEAD | `/api/v1/pos/reports/commissions` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:reports,view |

### Routes: App / Http / Controllers / Api / V1 / ReportsApiController@export

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| GET\|HEAD | `/api/v1/pos/reports/export` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:reports,view |

### Routes: App / Http / Controllers / Api / V1 / ReportsApiController@paymentMethods

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| GET\|HEAD | `/api/v1/pos/reports/payment-methods` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:reports,view |

### Routes: App / Http / Controllers / Api / V1 / ReportsApiController@profitLoss

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| GET\|HEAD | `/api/v1/pos/reports/profit-loss` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:reports,view |

### Routes: App / Http / Controllers / Api / V1 / ReportsApiController@summary

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| GET\|HEAD | `/api/v1/pos/reports/summary` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:reports,view |

### Routes: App / Http / Controllers / Api / V1 / ReportsApiController@tillClosings

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| GET\|HEAD | `/api/v1/pos/reports/till-closings` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:reports,view |

### Routes: App / Http / Controllers / Api / V1 / RestaurantApiController@floorsDestroy

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| DELETE | `/api/tenant/restaurant/floors/{id}` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:pos,edit |
| DELETE | `/api/v1/pos/restaurant/floors/{id}` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:pos,edit |

### Routes: App / Http / Controllers / Api / V1 / RestaurantApiController@floorsIndex

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| GET\|HEAD | `/api/tenant/restaurant/floors` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:pos,view |
| GET\|HEAD | `/api/v1/pos/restaurant/floors` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:pos,view |

### Routes: App / Http / Controllers / Api / V1 / RestaurantApiController@floorsStore

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| POST | `/api/tenant/restaurant/floors` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:pos,edit |
| POST | `/api/v1/pos/restaurant/floors` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:pos,edit |

### Routes: App / Http / Controllers / Api / V1 / RestaurantApiController@floorsUpdate

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| PUT | `/api/tenant/restaurant/floors/{id}` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:pos,edit |
| PUT | `/api/v1/pos/restaurant/floors/{id}` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:pos,edit |

### Routes: App / Http / Controllers / Api / V1 / RestaurantApiController@kotDismissAlarm

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| POST | `/api/tenant/restaurant/kot/{id}/dismiss-alarm` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:pos,edit |
| POST | `/api/v1/pos/restaurant/kot/{id}/dismiss-alarm` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:pos,edit |

### Routes: App / Http / Controllers / Api / V1 / RestaurantApiController@kotIndex

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| GET\|HEAD | `/api/tenant/restaurant/kot` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:pos,view |
| GET\|HEAD | `/api/v1/pos/restaurant/kot` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:pos,view |

### Routes: App / Http / Controllers / Api / V1 / RestaurantApiController@kotPrint

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| POST | `/api/tenant/restaurant/kot/{id}/print` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:pos,view |
| POST | `/api/v1/pos/kot/{id}/print` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:pos,view |
| POST | `/api/v1/pos/restaurant/kot/{id}/print` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:pos,view |

### Routes: App / Http / Controllers / Api / V1 / RestaurantApiController@kotUpdateStatus

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| POST | `/api/tenant/restaurant/kot/{id}/status` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:pos,edit |
| POST | `/api/v1/pos/restaurant/kot/{id}/status` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:pos,edit |

### Routes: App / Http / Controllers / Api / V1 / RestaurantApiController@sendToKitchen

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| POST | `/api/tenant/restaurant/orders/send-to-kitchen` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:pos,create |
| POST | `/api/v1/pos/restaurant/orders/send-to-kitchen` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:pos,create |

### Routes: App / Http / Controllers / Api / V1 / RestaurantApiController@settle

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| POST | `/api/tenant/restaurant/orders/{saleId}/settle` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:pos,create |
| POST | `/api/v1/pos/restaurant/orders/{saleId}/settle` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:pos,create |

### Routes: App / Http / Controllers / Api / V1 / RestaurantApiController@tableActionsSheet

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| GET\|HEAD | `/api/tenant/restaurant/tables/{id}/actions-sheet` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:pos,view |
| GET\|HEAD | `/api/v1/pos/restaurant/tables/{id}/actions-sheet` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:pos,view |
| GET\|HEAD | `/api/v1/pos/tables/{id}/actions-sheet` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:pos,view |
| GET\|HEAD | `/api/v1/pos/tenant/tables/{id}/actions-sheet` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:pos,view |

### Routes: App / Http / Controllers / Api / V1 / RestaurantApiController@tableShow

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| GET\|HEAD | `/api/tenant/restaurant/tables/{id}` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:pos,view |
| GET\|HEAD | `/api/v1/pos/restaurant/tables/{id}` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:pos,view |

### Routes: App / Http / Controllers / Api / V1 / RestaurantApiController@tablesDestroy

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| DELETE | `/api/tenant/restaurant/tables/{id}` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:pos,edit |
| DELETE | `/api/v1/pos/restaurant/tables/{id}` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:pos,edit |

### Routes: App / Http / Controllers / Api / V1 / RestaurantApiController@tablesIndex

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| GET\|HEAD | `/api/tenant/restaurant/tables` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:pos,view |

### Routes: App / Http / Controllers / Api / V1 / RestaurantApiController@tablesSetStatus

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| POST | `/api/tenant/restaurant/tables/{id}/status` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:pos,edit |
| POST | `/api/v1/pos/restaurant/tables/{id}/status` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:pos,edit |

### Routes: App / Http / Controllers / Api / V1 / RestaurantApiController@tablesStore

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| POST | `/api/tenant/restaurant/tables` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:pos,edit |
| POST | `/api/v1/pos/restaurant/tables` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:pos,edit |

### Routes: App / Http / Controllers / Api / V1 / RestaurantApiController@tablesUpdate

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| PUT | `/api/tenant/restaurant/tables/{id}` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:pos,edit |
| PUT | `/api/v1/pos/restaurant/tables/{id}` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:pos,edit |

### Routes: App / Http / Controllers / Api / V1 / RoleApiController@destroy

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| DELETE | `/api/tenant/roles/{id}` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:users,edit |
| DELETE | `/api/v1/pos/roles/{id}` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:users,edit |

### Routes: App / Http / Controllers / Api / V1 / RoleApiController@index

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| GET\|HEAD | `/api/tenant/roles` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:users,view |
| GET\|HEAD | `/api/v1/pos/roles` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:users,view |

### Routes: App / Http / Controllers / Api / V1 / RoleApiController@store

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| POST | `/api/tenant/roles` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:users,create |
| POST | `/api/v1/pos/roles` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:users,create |

### Routes: App / Http / Controllers / Api / V1 / RoleApiController@update

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| PUT\|PATCH | `/api/tenant/roles/{id}` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:users,edit |
| PUT\|PATCH | `/api/v1/pos/roles/{id}` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:users,edit |

### Routes: App / Http / Controllers / Api / V1 / SaleApiController@checkout

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| POST | `/api/app/pos/checkout` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:pos,create |
| POST | `/api/tenant/pos/checkout` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:pos,create |
| POST | `/api/v1/pos/checkout` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:pos,create |
| POST | `/api/v1/pos/pos/checkout` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:pos,create |

### Routes: App / Http / Controllers / Api / V1 / SaleApiController@checkoutSheet

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| GET\|HEAD | `/api/app/pos/checkout-sheet` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:pos,view |
| GET\|HEAD | `/api/tenant/pos/cart-sheet` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:pos,view |
| GET\|HEAD | `/api/tenant/pos/checkout-sheet` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:pos,view |
| GET\|HEAD | `/api/v1/pos/checkout-sheet` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:pos,view |
| GET\|HEAD | `/api/v1/pos/pos/cart-sheet` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:pos,view |
| GET\|HEAD | `/api/v1/pos/pos/checkout-sheet` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:pos,view |

### Routes: App / Http / Controllers / Api / V1 / SaleApiController@holdOrder

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| POST | `/api/app/pos/hold-order` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:pos,create |
| POST | `/api/tenant/pos/hold-order` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:pos,create |
| POST | `/api/v1/pos/pos/hold-order` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:pos,create |

### Routes: App / Http / Controllers / Api / V1 / SaleApiController@printInvoice

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| POST | `/api/app/sales/{id}/print` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications |
| POST | `/api/tenant/sales/{id}/print` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications |
| POST | `/api/v1/pos/sales/{id}/print` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications |

### Routes: App / Http / Controllers / Api / V1 / SaleApiController@sendInvoice

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| POST | `/api/app/sales/{id}/send-invoice` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications |
| POST | `/api/tenant/sales/{id}/send-invoice` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications |
| POST | `/api/v1/pos/sales/{id}/send-invoice` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications |

### Routes: App / Http / Controllers / Api / V1 / SalesTargetApiController@index

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| GET\|HEAD | `/api/v1/pos/sales-targets` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:targets,view |

### Routes: App / Http / Controllers / Api / V1 / SalesTargetApiController@store

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| POST | `/api/v1/pos/sales-targets` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:targets,edit |

### Routes: App / Http / Controllers / Api / V1 / SalonApiController@appointmentsAvailability

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| GET\|HEAD | `/api/tenant/salon/appointments/availability` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:service_orders,view |
| GET\|HEAD | `/api/v1/pos/salon/appointments/availability` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:service_orders,view |

### Routes: App / Http / Controllers / Api / V1 / SalonApiController@appointmentsIndex

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| GET\|HEAD | `/api/tenant/salon/appointments` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:service_orders,view |
| GET\|HEAD | `/api/v1/pos/salon/appointments` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:service_orders,view |

### Routes: App / Http / Controllers / Api / V1 / SalonApiController@appointmentsStore

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| POST | `/api/tenant/salon/appointments` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:service_orders,create |
| POST | `/api/v1/pos/salon/appointments` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:service_orders,create |

### Routes: App / Http / Controllers / Api / V1 / SalonApiController@appointmentsUpdateStatus

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| POST | `/api/tenant/salon/appointments/{id}/status` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:service_orders,edit |
| POST | `/api/v1/pos/salon/appointments/{id}/status` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:service_orders,edit |

### Routes: App / Http / Controllers / Api / V1 / SalonApiController@checkoutSheet

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| GET\|HEAD | `/api/tenant/salon/checkout-sheet` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:pos,view |
| GET\|HEAD | `/api/v1/pos/salon/checkout-sheet` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:pos,view |

### Routes: App / Http / Controllers / Api / V1 / SalonApiController@posCheckout

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| POST | `/api/tenant/salon/checkout` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:pos,create |
| POST | `/api/tenant/salon/pos-checkout` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:pos,create |
| POST | `/api/v1/pos/salon/checkout` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:pos,create |
| POST | `/api/v1/pos/salon/pos-checkout` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:pos,create |

### Routes: App / Http / Controllers / Api / V1 / SalonApiController@servicesDestroy

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| DELETE | `/api/tenant/salon/services/{id}` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:service_orders,edit |
| POST | `/api/tenant/salon/services/{id}/delete` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:service_orders,edit |
| DELETE | `/api/v1/pos/salon/services/{id}` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:service_orders,edit |
| POST | `/api/v1/pos/salon/services/{id}/delete` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:service_orders,edit |

### Routes: App / Http / Controllers / Api / V1 / SalonApiController@servicesEditSheet

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| GET\|HEAD | `/api/tenant/salon/services/{id}/edit-sheet` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:service_orders,view |
| GET\|HEAD | `/api/v1/pos/salon/services/{id}/edit-sheet` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:service_orders,view |

### Routes: App / Http / Controllers / Api / V1 / SalonApiController@servicesIndex

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| GET\|HEAD | `/api/tenant/salon/services` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:service_orders,view |
| GET\|HEAD | `/api/v1/pos/salon/services` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:service_orders,view |

### Routes: App / Http / Controllers / Api / V1 / SalonApiController@servicesStore

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| POST | `/api/tenant/salon/services` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:service_orders,create |
| POST | `/api/v1/pos/salon/services` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:service_orders,create |

### Routes: App / Http / Controllers / Api / V1 / SalonApiController@servicesUpdate

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| POST\|PUT | `/api/tenant/salon/services/{id}` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:service_orders,edit |
| POST\|PUT | `/api/v1/pos/salon/services/{id}` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:service_orders,edit |

### Routes: App / Http / Controllers / Api / V1 / SalonApiController@specialistSheet

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| GET\|HEAD | `/api/tenant/salon/specialist-sheet` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:service_orders,view |
| GET\|HEAD | `/api/v1/pos/salon/specialist-sheet` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:service_orders,view |

### Routes: App / Http / Controllers / Api / V1 / SalonApiController@specialistsIndex

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| GET\|HEAD | `/api/tenant/salon/specialists` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:users,view |
| GET\|HEAD | `/api/v1/pos/salon/specialists` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:users,view |

### Routes: App / Http / Controllers / Api / V1 / SalonApiController@specialistsToggle

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| POST | `/api/tenant/salon/specialists/{id}/toggle` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:users,edit |
| POST | `/api/v1/pos/salon/specialists/{id}/toggle` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:users,edit |

### Routes: App / Http / Controllers / Api / V1 / SduiViewController@show

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| GET\|HEAD | `/api/app/views/{view}` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications |
| GET\|HEAD | `/api/tenant/views/{view}` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications |
| GET\|HEAD | `/api/v1/pos/views/{view}` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications |

### Routes: App / Http / Controllers / Api / V1 / SduiViewController@submitSettings

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| POST\|PUT | `/api/app/settings/{section}` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:settings,edit |
| POST\|PUT | `/api/tenant/settings/{section}` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:settings,edit |
| POST\|PUT | `/api/v1/tenant/settings/{section}` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:settings,edit |

### Routes: App / Http / Controllers / Api / V1 / ServiceOrderApiController@destroy

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| DELETE | `/api/v1/pos/service-orders/{id}` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:service_orders,edit |

### Routes: App / Http / Controllers / Api / V1 / ServiceOrderApiController@index

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| GET\|HEAD | `/api/v1/pos/service-orders` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:service_orders,view |

### Routes: App / Http / Controllers / Api / V1 / ServiceOrderApiController@partsIndex

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| GET\|HEAD | `/api/v1/pos/service-orders/parts` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:service_orders,view |

### Routes: App / Http / Controllers / Api / V1 / ServiceOrderApiController@show

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| GET\|HEAD | `/api/v1/pos/service-orders/{id}` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:service_orders,view |

### Routes: App / Http / Controllers / Api / V1 / ServiceOrderApiController@store

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| POST | `/api/v1/pos/service-orders` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:service_orders,create |

### Routes: App / Http / Controllers / Api / V1 / ServiceOrderApiController@update

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| PUT | `/api/v1/pos/service-orders/{id}` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:service_orders,edit |

### Routes: App / Http / Controllers / Api / V1 / ServiceOrderApiController@updateStatus

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| POST | `/api/v1/pos/service-orders/{id}/status` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:service_orders,edit |

### Routes: App / Http / Controllers / Api / V1 / SettingsApiController@getDrawerNavigation

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| GET\|HEAD | `/api/app/navigation` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications |
| GET\|HEAD | `/api/app/navigation/drawer` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications |
| GET\|HEAD | `/api/tenant/navigation` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications |
| GET\|HEAD | `/api/tenant/navigation/drawer` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications |
| GET\|HEAD | `/api/tenant/ui/navigation` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications |
| GET\|HEAD | `/api/ui/navigation` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications |
| GET\|HEAD | `/api/v1/pos/navigation` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications |
| GET\|HEAD | `/api/v1/pos/navigation/drawer` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications |
| GET\|HEAD | `/api/v1/pos/ui/navigation` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications |
| GET\|HEAD | `/api/v1/tenant/navigation` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications |
| GET\|HEAD | `/api/v1/tenant/ui/navigation` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications |
| GET\|HEAD | `/api/v1/ui/navigation` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications |

### Routes: App / Http / Controllers / Api / V1 / SettingsApiController@getFormLabels

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| GET\|HEAD | `/api/app/settings/form-labels` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:settings,view |
| GET\|HEAD | `/api/tenant/settings/form-labels` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:settings,view |
| GET\|HEAD | `/api/v1/pos/settings/form-labels` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:settings,view |

### Routes: App / Http / Controllers / Api / V1 / SettingsApiController@getNavigationLabels

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| GET\|HEAD | `/api/app/settings/navigation-labels` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:settings,view |
| GET\|HEAD | `/api/tenant/settings/navigation-labels` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:settings,view |
| GET\|HEAD | `/api/v1/pos/settings/navigation-labels` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:settings,view |

### Routes: App / Http / Controllers / Api / V1 / SettingsApiController@getTheme

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| GET\|HEAD | `/api/app/theme` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications |
| GET\|HEAD | `/api/tenant/theme` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications |
| GET\|HEAD | `/api/theme` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications |
| GET\|HEAD | `/api/v1/tenant/theme` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications |
| GET\|HEAD | `/api/v1/theme` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications |

### Routes: App / Http / Controllers / Api / V1 / SettingsApiController@index

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| GET\|HEAD | `/api/v1/pos/settings` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:settings,view |

### Routes: App / Http / Controllers / Api / V1 / SettingsApiController@notificationChannelsDestroy

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| DELETE | `/api/pos/settings/custom-notifications/{id}` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications |
| DELETE | `/api/tenant/settings/custom-notifications/{id}` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications |
| DELETE | `/api/v1/pos/settings/custom-notifications/{id}` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications |

### Routes: App / Http / Controllers / Api / V1 / SettingsApiController@notificationChannelsIndex

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| GET\|HEAD | `/api/pos/settings/custom-notifications` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications |
| GET\|HEAD | `/api/tenant/settings/custom-notifications` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications |
| GET\|HEAD | `/api/v1/pos/settings/custom-notifications` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications |

### Routes: App / Http / Controllers / Api / V1 / SettingsApiController@notificationChannelsStore

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| POST | `/api/pos/settings/custom-notifications` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications |
| POST | `/api/tenant/settings/custom-notifications` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications |
| POST | `/api/v1/pos/settings/custom-notifications` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications |

### Routes: App / Http / Controllers / Api / V1 / SettingsApiController@notificationChannelsUpdate

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| PUT | `/api/pos/settings/custom-notifications/{id}` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications |
| PUT | `/api/tenant/settings/custom-notifications/{id}` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications |
| PUT | `/api/v1/pos/settings/custom-notifications/{id}` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications |

### Routes: App / Http / Controllers / Api / V1 / SettingsApiController@paymentMethodTransactions

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| GET\|HEAD | `/api/v1/pos/settings/payment-methods/{id}/transactions` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:settings,view |

### Routes: App / Http / Controllers / Api / V1 / SettingsApiController@paymentMethodsDestroy

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| DELETE | `/api/tenant/settings/payment-methods/{id}` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:settings,edit |
| POST | `/api/tenant/settings/payment-methods/{id}/delete` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:settings,edit |
| DELETE | `/api/v1/pos/settings/payment-methods/{id}` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:settings,edit |
| POST | `/api/v1/pos/settings/payment-methods/{id}/delete` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:settings,edit |

### Routes: App / Http / Controllers / Api / V1 / SettingsApiController@paymentMethodsEditSheet

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| GET\|HEAD | `/api/tenant/settings/payment-methods/{id}/edit-sheet` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:settings,view |
| GET\|HEAD | `/api/v1/pos/settings/payment-methods/{id}/edit-sheet` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:settings,view |

### Routes: App / Http / Controllers / Api / V1 / SettingsApiController@paymentMethodsIndex

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| GET\|HEAD | `/api/tenant/settings/payment-methods` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:settings,view |
| GET\|HEAD | `/api/v1/pos/settings/payment-methods` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:settings,view |

### Routes: App / Http / Controllers / Api / V1 / SettingsApiController@paymentMethodsStore

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| POST | `/api/tenant/settings/payment-methods` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:settings,edit |
| POST | `/api/v1/pos/settings/payment-methods` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:settings,edit |

### Routes: App / Http / Controllers / Api / V1 / SettingsApiController@paymentMethodsToggle

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| POST | `/api/tenant/settings/payment-methods/{id}/toggle` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:settings,edit |
| POST | `/api/v1/pos/settings/payment-methods/{id}/toggle` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:settings,edit |

### Routes: App / Http / Controllers / Api / V1 / SettingsApiController@paymentMethodsUpdate

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| PUT\|POST | `/api/tenant/settings/payment-methods/{id}` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:settings,edit |
| PUT\|POST | `/api/v1/pos/settings/payment-methods/{id}` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:settings,edit |

### Routes: App / Http / Controllers / Api / V1 / SettingsApiController@removeDrawerCover

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| DELETE | `/api/v1/pos/settings/profile/drawer-cover` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:settings,edit |

### Routes: App / Http / Controllers / Api / V1 / SettingsApiController@removeFavicon

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| DELETE | `/api/v1/pos/settings/profile/favicon` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:settings,edit |

### Routes: App / Http / Controllers / Api / V1 / SettingsApiController@removeLogo

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| DELETE | `/api/v1/pos/settings/profile/logo` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:settings,edit |

### Routes: App / Http / Controllers / Api / V1 / SettingsApiController@testNotificationChannel

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| POST | `/api/pos/settings/custom-notifications/test` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications |
| POST | `/api/tenant/settings/custom-notifications/test` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications |
| POST | `/api/v1/pos/settings/custom-notifications/test` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications |

### Routes: App / Http / Controllers / Api / V1 / SettingsApiController@updateBranding

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| PUT | `/api/v1/pos/settings/branding` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:settings,edit |

### Routes: App / Http / Controllers / Api / V1 / SettingsApiController@updateFinancial

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| PUT | `/api/v1/pos/settings/financial` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:settings,edit |

### Routes: App / Http / Controllers / Api / V1 / SettingsApiController@updateFormLabels

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| POST | `/api/app/settings/form-labels` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:settings,edit |
| POST | `/api/tenant/settings/form-labels` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:settings,edit |
| POST | `/api/v1/pos/settings/form-labels` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:settings,edit |

### Routes: App / Http / Controllers / Api / V1 / SettingsApiController@updateNavigationLabels

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| POST | `/api/app/settings/navigation-labels` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:settings,edit |
| POST | `/api/tenant/settings/navigation-labels` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:settings,edit |
| POST | `/api/v1/pos/settings/navigation-labels` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:settings,edit |

### Routes: App / Http / Controllers / Api / V1 / SettingsApiController@updateProfile

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| PUT | `/api/v1/pos/settings/profile` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:settings,edit |

### Routes: App / Http / Controllers / Api / V1 / SettingsApiController@updateReceipts

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| PUT | `/api/v1/pos/settings/receipts` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:settings,edit |

### Routes: App / Http / Controllers / Api / V1 / SettingsApiController@updateRepairChecklist

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| POST\|PUT | `/api/v1/pos/settings/repair-checklist` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:settings,edit |

### Routes: App / Http / Controllers / Api / V1 / SettingsApiController@uploadDrawerCover

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| POST | `/api/v1/pos/settings/profile/drawer-cover` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:settings,edit |

### Routes: App / Http / Controllers / Api / V1 / SettingsApiController@uploadFavicon

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| POST | `/api/v1/pos/settings/profile/favicon` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:settings,edit |

### Routes: App / Http / Controllers / Api / V1 / SettingsApiController@uploadLogo

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| POST | `/api/v1/pos/settings/profile/logo` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:settings,edit |

### Routes: App / Http / Controllers / Api / V1 / StorefrontCustomerApiController@addresses

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| GET\|HEAD | `/api/addresses` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext |
| GET\|HEAD | `/api/storefront/customer/addresses` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext |
| GET\|HEAD | `/api/v1/storefront/customer/addresses` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext |

### Routes: App / Http / Controllers / Api / V1 / StorefrontCustomerApiController@calculateCart

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| POST | `/api/storefront/cart/calculate` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext |
| POST | `/api/v1/storefront/cart/calculate` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext |

### Routes: App / Http / Controllers / Api / V1 / StorefrontCustomerApiController@deleteAddress

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| DELETE | `/api/addresses/{id}` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext |
| DELETE | `/api/storefront/customer/addresses/{id}` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext |
| DELETE | `/api/v1/storefront/customer/addresses/{id}` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext |

### Routes: App / Http / Controllers / Api / V1 / StorefrontCustomerApiController@login

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| POST | `/api/storefront/customer/login` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext |
| POST | `/api/v1/storefront/customer/login` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext |

### Routes: App / Http / Controllers / Api / V1 / StorefrontCustomerApiController@logout

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| POST | `/api/storefront/customer/logout` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext |
| POST | `/api/v1/storefront/customer/logout` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext |

### Routes: App / Http / Controllers / Api / V1 / StorefrontCustomerApiController@orders

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| GET\|HEAD | `/api/storefront/customer/orders` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext |
| GET\|HEAD | `/api/v1/storefront/customer/orders` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext |

### Routes: App / Http / Controllers / Api / V1 / StorefrontCustomerApiController@profile

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| GET\|HEAD | `/api/storefront/customer/profile` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext |
| GET\|HEAD | `/api/v1/storefront/customer/profile` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext |

### Routes: App / Http / Controllers / Api / V1 / StorefrontCustomerApiController@register

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| POST | `/api/storefront/customer/register` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext |
| POST | `/api/v1/storefront/customer/register` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext |

### Routes: App / Http / Controllers / Api / V1 / StorefrontCustomerApiController@removeWishlist

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| DELETE | `/api/storefront/customer/wishlist/{productId}` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext |
| DELETE | `/api/v1/storefront/customer/wishlist/{productId}` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext |
| DELETE | `/api/wishlist/{productId}` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext |

### Routes: App / Http / Controllers / Api / V1 / StorefrontCustomerApiController@storeAddress

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| POST | `/api/addresses` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext |
| POST | `/api/storefront/customer/addresses` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext |
| POST | `/api/v1/storefront/customer/addresses` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext |

### Routes: App / Http / Controllers / Api / V1 / StorefrontCustomerApiController@toggleWishlist

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| POST | `/api/storefront/customer/wishlist/toggle` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext |
| POST | `/api/v1/storefront/customer/wishlist/toggle` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext |
| POST | `/api/wishlist/toggle` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext |

### Routes: App / Http / Controllers / Api / V1 / StorefrontCustomerApiController@updateAddress

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| PUT | `/api/addresses/{id}` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext |
| PUT | `/api/storefront/customer/addresses/{id}` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext |
| PUT | `/api/v1/storefront/customer/addresses/{id}` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext |

### Routes: App / Http / Controllers / Api / V1 / StorefrontCustomerApiController@updateProfile

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| PUT\|POST | `/api/storefront/customer/profile` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext |
| PUT\|POST | `/api/v1/storefront/customer/profile` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext |

### Routes: App / Http / Controllers / Api / V1 / StorefrontCustomerApiController@wishlist

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| GET\|HEAD | `/api/storefront/customer/wishlist` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext |
| GET\|HEAD | `/api/v1/storefront/customer/wishlist` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext |
| GET\|HEAD | `/api/wishlist` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext |

### Routes: App / Http / Controllers / Api / V1 / TaxApiController@calculate

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| POST | `/api/v1/tax/calculate` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\PreventDemoModifications |

### Routes: App / Http / Controllers / Api / V1 / TaxApiController@getEInvoicePayload

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| GET\|HEAD | `/api/v1/tax/einvoice/{sale_id}/payload` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\PreventDemoModifications |

### Routes: App / Http / Controllers / Api / V1 / TaxApiController@getRates

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| GET\|HEAD | `/api/v1/tax/rates` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\PreventDemoModifications |

### Routes: App / Http / Controllers / Api / V1 / TaxApiController@issueInvoice

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| POST | `/api/v1/tax/invoices` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\PreventDemoModifications |

### Routes: App / Http / Controllers / Api / V1 / TenantAppPreferencesController@getNotificationAlertsScreen

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| GET\|HEAD | `/api/app/settings/app-preferences/notifications` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:settings,view |
| GET\|HEAD | `/api/app/settings/notifications-audio` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:settings,view |
| GET\|HEAD | `/api/tenant/settings/app-preferences` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:settings,view |
| GET\|HEAD | `/api/tenant/settings/app-preferences/notifications` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:settings,view |
| GET\|HEAD | `/api/tenant/settings/notifications-audio` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:settings,view |
| GET\|HEAD | `/api/v1/pos/settings/app-preferences` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:settings,view |
| GET\|HEAD | `/api/v1/pos/settings/app-preferences/notifications` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:settings,view |
| GET\|HEAD | `/api/v1/pos/settings/notifications-audio` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:settings,view |
| GET\|HEAD | `/api/v1/tenant/settings/app-preferences` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:settings,view |
| GET\|HEAD | `/api/v1/tenant/settings/app-preferences/notifications` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:settings,view |
| GET\|HEAD | `/api/v1/tenant/settings/notifications-audio` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:settings,view |

### Routes: App / Http / Controllers / Api / V1 / TenantAppPreferencesController@saveAutoReminders

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| POST | `/api/tenant/settings/auto-reminders` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:settings,edit |
| POST | `/api/v1/tenant/settings/auto-reminders` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:settings,edit |

### Routes: App / Http / Controllers / Api / V1 / TenantAppPreferencesController@saveNotificationPreferences

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| POST\|PUT | `/api/app/settings/app-preferences/notifications` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:settings,edit |
| POST\|PUT | `/api/app/settings/notifications-audio` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:settings,edit |
| POST\|PUT | `/api/tenant/settings/app-preferences` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:settings,edit |
| POST\|PUT | `/api/tenant/settings/app-preferences/notifications` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:settings,edit |
| POST\|PUT | `/api/tenant/settings/notifications-audio` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:settings,edit |
| POST\|PUT | `/api/v1/pos/settings/app-preferences` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:settings,edit |
| POST\|PUT | `/api/v1/pos/settings/app-preferences/notifications` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:settings,edit |
| POST\|PUT | `/api/v1/pos/settings/notifications-audio` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:settings,edit |
| POST\|PUT | `/api/v1/tenant/settings/app-preferences` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:settings,edit |
| POST\|PUT | `/api/v1/tenant/settings/app-preferences/notifications` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:settings,edit |
| POST\|PUT | `/api/v1/tenant/settings/notifications-audio` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:settings,edit |

### Routes: App / Http / Controllers / Api / V1 / TenantAppPreferencesController@uploadAudio

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| POST | `/api/app/settings/app-preferences/notifications/upload-audio` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:settings,edit |
| POST | `/api/tenant/settings/app-preferences/notifications/upload-audio` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:settings,edit |
| POST | `/api/v1/pos/settings/app-preferences/notifications/upload-audio` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:settings,edit |
| POST | `/api/v1/tenant/settings/app-preferences/notifications/upload-audio` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:settings,edit |

### Routes: App / Http / Controllers / Api / V1 / TenantDemoDataController@destroy

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| DELETE | `/api/app/demo-data` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:settings,edit |
| DELETE | `/api/tenant/demo-data` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:settings,edit |
| DELETE | `/api/v1/pos/demo-data` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:settings,edit |

### Routes: App / Http / Controllers / Api / V1 / UploadApiController@uploadRxAttachment

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| POST | `/api/tenant/uploads/prescription-doc` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:sales,create |

### Routes: App / Http / Controllers / Api / V1 / UserApiController@destroy

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| DELETE | `/api/v1/pos/users/{id}` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:users,edit |

### Routes: App / Http / Controllers / Api / V1 / UserApiController@index

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| GET\|HEAD | `/api/v1/pos/users` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:users,view |

### Routes: App / Http / Controllers / Api / V1 / UserApiController@invite

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| POST | `/api/v1/pos/users/invite` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:users,create |

### Routes: App / Http / Controllers / Api / V1 / UserApiController@resendInvite

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| POST | `/api/v1/pos/users/{id}/resend-invite` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:users,edit |

### Routes: App / Http / Controllers / Api / V1 / UserApiController@toggleStatus

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| POST | `/api/v1/pos/users/{id}/toggle-status` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:users,edit |

### Routes: App / Http / Controllers / Api / V1 / UserApiController@updateCommission

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| PUT | `/api/v1/pos/users/{id}/commission` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:users,edit |

### Routes: App / Http / Controllers / Api / V1 / UserApiController@updateRole

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| PUT | `/api/v1/pos/users/{id}/role` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:users,edit |

### Routes: App / Http / Controllers / Auth / SocialAuthController@callback

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| GET\|HEAD | `/api/auth/{provider}/callback` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext |
| GET\|HEAD | `/api/v1/pos/auth/{provider}/callback` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext |
| GET\|HEAD | `/auth/{provider}/callback` | social.callback | web, App\Http\Middleware\EnsureAppIsInstalled |

### Routes: App / Http / Controllers / Auth / SocialAuthController@mobileToken

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| POST | `/api/api/auth/{provider}/mobile-token` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext |
| POST | `/api/auth/{provider}/mobile-token` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext |
| POST | `/api/v1/pos/auth/{provider}/mobile-token` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext |

### Routes: App / Http / Controllers / Auth / SocialAuthController@redirect

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| GET\|HEAD | `/api/auth/{provider}/redirect` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext |
| GET\|HEAD | `/api/v1/pos/auth/{provider}/redirect` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext |
| GET\|HEAD | `/auth/{provider}/redirect` | social.redirect | web, App\Http\Middleware\EnsureAppIsInstalled |

### Routes: App / Http / Controllers / LandingPageController@index

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| GET\|HEAD | `//` | home | web, App\Http\Middleware\EnsureAppIsInstalled |
| GET\|HEAD | `/home` | landing.home | web, App\Http\Middleware\EnsureAppIsInstalled |

### Routes: App / Http / Controllers / LicenseVerificationController@verifyClientApp

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| POST | `/api/v2/verify-entitlement` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext |
| POST | `/api/verify-entitlement` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext |
| POST | `/lic/api/v2/verify-entitlement` |  | web |
| POST | `/lic/api/verify-entitlement` |  | web |

### Routes: App / Http / Controllers / LocaleController@switch

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| GET\|HEAD | `/locale/{locale}` | locale.switch | web |

### Routes: App / Http / Controllers / PublicContactController@index

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| GET\|HEAD | `/contact` | contact.index | web, App\Http\Middleware\EnsureAppIsInstalled |

### Routes: App / Http / Controllers / PublicContactController@store

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| POST | `/contact` | contact.store | web, App\Http\Middleware\EnsureAppIsInstalled, Illuminate\Routing\Middleware\ThrottleRequests:6,1 |

### Routes: App / Http / Controllers / PublicPageController@show

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| GET\|HEAD | `/page/{slug}` | page.show | web, App\Http\Middleware\EnsureAppIsInstalled |
| GET\|HEAD | `/pages/{slug}` | pages.show | web, App\Http\Middleware\EnsureAppIsInstalled |

### Routes: App / Http / Controllers / StoreProfileController@show

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| GET\|HEAD | `/api/app/store-profile` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:settings,view |
| GET\|HEAD | `/api/tenant/store-profile` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:settings,view |
| GET\|HEAD | `/api/v1/tenant/store-profile` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:settings,view |

### Routes: App / Http / Controllers / StoreProfileController@update

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| POST\|PUT | `/api/app/store-profile` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:settings,edit |
| POST\|PUT | `/api/tenant/store-profile` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:settings,edit |
| POST\|PUT | `/api/v1/tenant/store-profile` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:settings,edit |

### Routes: App / Http / Controllers / SuperAdmin / TenantController@updateModules

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| POST\|PUT | `/api/superadmin/tenants/{tenantId}/modules` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext |
| POST\|PUT | `/api/v1/superadmin/tenants/{tenantId}/modules` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext |
| POST\|PUT | `/superadmin/tenants/{tenantId}/modules` | superadmin.tenants.modules | web, App\Http\Middleware\EnsureAppIsInstalled, Illuminate\Auth\Middleware\Authenticate:platform_web, App\Http\Middleware\PeriodicLicenseCheck, App\Http\Middleware\PreventDemoModifications |

### Routes: App / Http / Controllers / SuperAdmin / ThemeCustomizerController@applyMatchingPattern

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| POST | `/superadmin/settings/theme-customizer/matching-pattern` | superadmin.theme.customizer.matching-pattern | web, App\Http\Middleware\EnsureAppIsInstalled, Illuminate\Auth\Middleware\Authenticate:platform_web, App\Http\Middleware\PeriodicLicenseCheck, App\Http\Middleware\PreventDemoModifications |

### Routes: App / Http / Controllers / SuperAdmin / ThemeCustomizerController@saveGlobalDefaults

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| POST | `/superadmin/settings/theme-customizer` | superadmin.theme.customizer.save | web, App\Http\Middleware\EnsureAppIsInstalled, Illuminate\Auth\Middleware\Authenticate:platform_web, App\Http\Middleware\PeriodicLicenseCheck, App\Http\Middleware\PreventDemoModifications |

### Routes: App / Http / Controllers / SuperAdmin / ThemeCustomizerController@saveSectionThemes

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| POST | `/superadmin/settings/theme-customizer/sections` | superadmin.theme.customizer.sections | web, App\Http\Middleware\EnsureAppIsInstalled, Illuminate\Auth\Middleware\Authenticate:platform_web, App\Http\Middleware\PeriodicLicenseCheck, App\Http\Middleware\PreventDemoModifications |

### Routes: App / Http / Controllers / Sync / CatalogViewController@placeOrder

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| POST | `/c/{id}/order` | catalog.order | web, App\Http\Middleware\EnsureAppIsInstalled |

### Routes: App / Http / Controllers / Sync / CatalogViewController@show

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| GET\|HEAD | `/c/{id}` | catalog.show | web, App\Http\Middleware\EnsureAppIsInstalled |

### Routes: App / Http / Controllers / Sync / HealthController@index

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| GET\|HEAD\|POST\|PUT\|PATCH\|DELETE\|OPTIONS | `/api/health` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode |

### Routes: App / Http / Controllers / Sync / MysqlSyncController@dispatch

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| GET\|HEAD\|POST\|PUT\|PATCH\|DELETE\|OPTIONS | `/api/mysql.php` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode |

### Routes: App / Http / Controllers / Sync / PlatformAdminSyncController@dispatch

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| GET\|HEAD\|POST\|PUT\|PATCH\|DELETE\|OPTIONS | `/api/platform_admin.php` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode |

### Routes: App / Http / Controllers / Sync / SubscriptionSyncController@dispatch

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| GET\|HEAD\|POST\|PUT\|PATCH\|DELETE\|OPTIONS | `/api/subscription.php` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode |

### Routes: App / Http / Controllers / Sync / TenantAuthSyncController@dispatch

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| GET\|HEAD\|POST\|PUT\|PATCH\|DELETE\|OPTIONS | `/api/auth.php` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode |

### Routes: App / Http / Controllers / Tenant / Auth / PasswordResetController@changePassword

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| POST | `/api/tenant/profile/change-password` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\PreventDemoModifications |
| POST | `/tenant/settings/change-password` | tenant.settings.change-password | web, App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, Illuminate\Auth\Middleware\Authenticate:web, App\Http\Middleware\ResolveTenantContext, App\Http\Middleware\EnsureTenantEmailIsVerified |

### Routes: App / Http / Controllers / Tenant / Auth / PasswordResetController@reset

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| POST | `/api/tenant/password/reset` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\PreventDemoModifications |
| POST | `/tenant/reset-password` | tenant.password.reset | web, App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, Illuminate\Auth\Middleware\RedirectIfAuthenticated:web |

### Routes: App / Http / Controllers / Tenant / Auth / PasswordResetController@sendResetLink

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| POST | `/api/tenant/password/email` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\PreventDemoModifications |
| POST | `/tenant/forgot-password` | tenant.password.email | web, App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, Illuminate\Auth\Middleware\RedirectIfAuthenticated:web |

### Routes: App / Http / Controllers / Tenant / Auth / PasswordResetController@showForgotForm

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| GET\|HEAD | `/tenant/forgot-password` | tenant.password.request | web, App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, Illuminate\Auth\Middleware\RedirectIfAuthenticated:web |

### Routes: App / Http / Controllers / Tenant / Auth / PasswordResetController@showResetForm

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| GET\|HEAD | `/tenant/reset-password/{token}` | tenant.password.reset.form | web, App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, Illuminate\Auth\Middleware\RedirectIfAuthenticated:web |

### Routes: App / Http / Controllers / Tenant / BackupDownloadController@download

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| GET\|HEAD | `/tenant/settings/backup/download` | tenant.settings.backup.download | web, App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, Illuminate\Auth\Middleware\Authenticate:web, App\Http\Middleware\ResolveTenantContext, App\Http\Middleware\EnsureTenantEmailIsVerified, App\Http\Middleware\CheckTenantPermission:settings,view |

### Routes: App / Http / Controllers / Tenant / CashRegisterSlipController@pdfMovement

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| GET\|HEAD | `/tenant/finance/cash-register/movement/{tx}/pdf` | tenant.cash_register.movement.pdf | web, App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, Illuminate\Auth\Middleware\Authenticate:web, App\Http\Middleware\ResolveTenantContext, App\Http\Middleware\EnsureTenantEmailIsVerified, App\Http\Middleware\EnsureTenantSubscriptionActive, App\Http\Middleware\CheckTenantPermission:cash_register,view |

### Routes: App / Http / Controllers / Tenant / CashRegisterSlipController@pdfZReport

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| GET\|HEAD | `/tenant/finance/cash-register/{register}/z-report/pdf` | tenant.cash_register.z_report.pdf | web, App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, Illuminate\Auth\Middleware\Authenticate:web, App\Http\Middleware\ResolveTenantContext, App\Http\Middleware\EnsureTenantEmailIsVerified, App\Http\Middleware\EnsureTenantSubscriptionActive, App\Http\Middleware\CheckTenantPermission:cash_register,view |

### Routes: App / Http / Controllers / Tenant / CashRegisterSlipController@viewMovement

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| GET\|HEAD | `/tenant/finance/cash-register/movement/{tx}` | tenant.cash_register.movement.view | web, App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, Illuminate\Auth\Middleware\Authenticate:web, App\Http\Middleware\ResolveTenantContext, App\Http\Middleware\EnsureTenantEmailIsVerified, App\Http\Middleware\EnsureTenantSubscriptionActive, App\Http\Middleware\CheckTenantPermission:cash_register,view |

### Routes: App / Http / Controllers / Tenant / CashRegisterSlipController@viewZReport

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| GET\|HEAD | `/tenant/finance/cash-register/{register}/z-report` | tenant.cash_register.z_report.view | web, App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, Illuminate\Auth\Middleware\Authenticate:web, App\Http\Middleware\ResolveTenantContext, App\Http\Middleware\EnsureTenantEmailIsVerified, App\Http\Middleware\EnsureTenantSubscriptionActive, App\Http\Middleware\CheckTenantPermission:cash_register,view |

### Routes: App / Http / Controllers / Tenant / DocumentTemplateController@apiShow

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| GET\|HEAD | `/api/tenant/templates/{type}` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications |
| GET\|HEAD | `/api/v1/tenant/templates/{type}` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications |

### Routes: App / Http / Controllers / Tenant / DocumentTemplateController@apiUpdate

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| POST\|PUT | `/api/tenant/templates/{type}` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications |
| POST\|PUT | `/api/v1/tenant/templates/{type}` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications |

### Routes: App / Http / Controllers / Tenant / DocumentTemplateController@edit

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| GET\|HEAD | `/settings/templates/{type}` | settings.templates.edit | web, Illuminate\Auth\Middleware\Authenticate:web, App\Http\Middleware\ResolveTenantContext |
| GET\|HEAD | `/tenant/settings/templates/{type}` | tenant.settings.templates.edit | web, App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, Illuminate\Auth\Middleware\Authenticate:web, App\Http\Middleware\ResolveTenantContext, App\Http\Middleware\EnsureTenantEmailIsVerified |

### Routes: App / Http / Controllers / Tenant / DocumentTemplateController@previewHtml

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| GET\|HEAD | `/settings/templates/{type}/preview` | settings.templates.preview | web, Illuminate\Auth\Middleware\Authenticate:web, App\Http\Middleware\ResolveTenantContext |
| GET\|HEAD | `/tenant/settings/templates/{type}/preview` | tenant.settings.templates.preview | web, App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, Illuminate\Auth\Middleware\Authenticate:web, App\Http\Middleware\ResolveTenantContext, App\Http\Middleware\EnsureTenantEmailIsVerified |

### Routes: App / Http / Controllers / Tenant / DocumentTemplateController@update

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| POST\|PUT | `/settings/templates/{type}` | settings.templates.update | web, Illuminate\Auth\Middleware\Authenticate:web, App\Http\Middleware\ResolveTenantContext |
| POST\|PUT | `/tenant/settings/templates/{type}` | tenant.settings.templates.update | web, App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, Illuminate\Auth\Middleware\Authenticate:web, App\Http\Middleware\ResolveTenantContext, App\Http\Middleware\EnsureTenantEmailIsVerified |

### Routes: App / Http / Controllers / Tenant / ImpersonationController@start

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| POST | `/tenant/impersonate/{user}` | tenant.impersonate.start | web, App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, Illuminate\Auth\Middleware\Authenticate:web, App\Http\Middleware\ResolveTenantContext, App\Http\Middleware\EnsureTenantEmailIsVerified, App\Http\Middleware\EnsureTenantSubscriptionActive |

### Routes: App / Http / Controllers / Tenant / ImpersonationController@stop

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| POST | `/tenant/impersonate` | tenant.impersonate.stop | web, App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, Illuminate\Auth\Middleware\Authenticate:web, App\Http\Middleware\ResolveTenantContext, App\Http\Middleware\EnsureTenantEmailIsVerified, App\Http\Middleware\EnsureTenantSubscriptionActive |

### Routes: App / Http / Controllers / Tenant / InvoiceController@pdf

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| GET\|HEAD | `/tenant/invoices/{sale}/pdf` | tenant.invoices.pdf | web, App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, Illuminate\Auth\Middleware\Authenticate:web, App\Http\Middleware\ResolveTenantContext, App\Http\Middleware\EnsureTenantEmailIsVerified, App\Http\Middleware\EnsureTenantSubscriptionActive, App\Http\Middleware\CheckTenantPermission:sales,view |
| GET\|HEAD | `/tenant/sales/{sale}/pdf` | tenant.sales.pdf | web, App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, Illuminate\Auth\Middleware\Authenticate:web, App\Http\Middleware\ResolveTenantContext, App\Http\Middleware\EnsureTenantEmailIsVerified, App\Http\Middleware\EnsureTenantSubscriptionActive, App\Http\Middleware\CheckTenantPermission:sales,view |

### Routes: App / Http / Controllers / Tenant / InvoiceController@pdfStream

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| GET\|HEAD | `/api/app/invoices/{sale}/pdf-stream` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:sales,view |
| GET\|HEAD | `/api/tenant/invoices/{sale}/pdf-stream` | invoice.pdf.stream | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:sales,view |

### Routes: App / Http / Controllers / Tenant / InvoiceController@publicShow

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| GET\|HEAD | `/i/{sale_number}` | sales.public | web, App\Http\Middleware\EnsureAppIsInstalled |

### Routes: App / Http / Controllers / Tenant / InvoiceController@send

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| POST | `/tenant/invoices/{sale}/send` | tenant.invoices.send | web, App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, Illuminate\Auth\Middleware\Authenticate:web, App\Http\Middleware\ResolveTenantContext, App\Http\Middleware\EnsureTenantEmailIsVerified, App\Http\Middleware\EnsureTenantSubscriptionActive, App\Http\Middleware\CheckTenantPermission:sales,export |
| POST | `/tenant/sales/{sale}/send` | tenant.sales.send | web, App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, Illuminate\Auth\Middleware\Authenticate:web, App\Http\Middleware\ResolveTenantContext, App\Http\Middleware\EnsureTenantEmailIsVerified, App\Http\Middleware\EnsureTenantSubscriptionActive, App\Http\Middleware\CheckTenantPermission:sales,export |

### Routes: App / Http / Controllers / Tenant / InvoiceController@sendCustom

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| POST | `/tenant/sales/{sale}/send-custom` | tenant.sales.send-custom | web, App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, Illuminate\Auth\Middleware\Authenticate:web, App\Http\Middleware\ResolveTenantContext, App\Http\Middleware\EnsureTenantEmailIsVerified, App\Http\Middleware\EnsureTenantSubscriptionActive, App\Http\Middleware\CheckTenantPermission:sales,export |

### Routes: App / Http / Controllers / Tenant / InvoiceController@signedPdf

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| GET\|HEAD | `/api/tenant/receipt/{sale}/pdf` | receipt.signed.pdf | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, Illuminate\Routing\Middleware\ValidateSignature |

### Routes: App / Http / Controllers / Tenant / LeadWebController@completeActivity

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| POST | `/tenant/leads/activities/{id}/complete` | tenant.leads.activities.complete | web, App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, Illuminate\Auth\Middleware\Authenticate:web, App\Http\Middleware\ResolveTenantContext, App\Http\Middleware\EnsureTenantEmailIsVerified, App\Http\Middleware\EnsureTenantSubscriptionActive, App\Http\Middleware\CheckTenantPermission:leads,edit, App\Http\Middleware\EnsureTenantExtension:leadmanagement |

### Routes: App / Http / Controllers / Tenant / LeadWebController@create

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| GET\|HEAD | `/tenant/leads/create` | tenant.leads.create | web, App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, Illuminate\Auth\Middleware\Authenticate:web, App\Http\Middleware\ResolveTenantContext, App\Http\Middleware\EnsureTenantEmailIsVerified, App\Http\Middleware\EnsureTenantSubscriptionActive, App\Http\Middleware\CheckTenantPermission:leads,create, App\Http\Middleware\EnsureTenantExtension:leadmanagement |

### Routes: App / Http / Controllers / Tenant / LeadWebController@destroy

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| DELETE | `/tenant/leads/{id}` | tenant.leads.destroy | web, App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, Illuminate\Auth\Middleware\Authenticate:web, App\Http\Middleware\ResolveTenantContext, App\Http\Middleware\EnsureTenantEmailIsVerified, App\Http\Middleware\EnsureTenantSubscriptionActive, App\Http\Middleware\CheckTenantPermission:leads,edit, App\Http\Middleware\EnsureTenantExtension:leadmanagement |

### Routes: App / Http / Controllers / Tenant / LeadWebController@index

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| GET\|HEAD | `/tenant/leads` | tenant.leads.index | web, App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, Illuminate\Auth\Middleware\Authenticate:web, App\Http\Middleware\ResolveTenantContext, App\Http\Middleware\EnsureTenantEmailIsVerified, App\Http\Middleware\EnsureTenantSubscriptionActive, App\Http\Middleware\CheckTenantPermission:leads,view, App\Http\Middleware\EnsureTenantExtension:leadmanagement |

### Routes: App / Http / Controllers / Tenant / LeadWebController@show

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| GET\|HEAD | `/tenant/leads/{id}` | tenant.leads.show | web, App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, Illuminate\Auth\Middleware\Authenticate:web, App\Http\Middleware\ResolveTenantContext, App\Http\Middleware\EnsureTenantEmailIsVerified, App\Http\Middleware\EnsureTenantSubscriptionActive, App\Http\Middleware\CheckTenantPermission:leads,view, App\Http\Middleware\EnsureTenantExtension:leadmanagement |

### Routes: App / Http / Controllers / Tenant / LeadWebController@store

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| POST | `/tenant/leads` | tenant.leads.store | web, App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, Illuminate\Auth\Middleware\Authenticate:web, App\Http\Middleware\ResolveTenantContext, App\Http\Middleware\EnsureTenantEmailIsVerified, App\Http\Middleware\EnsureTenantSubscriptionActive, App\Http\Middleware\CheckTenantPermission:leads,create, App\Http\Middleware\EnsureTenantExtension:leadmanagement |

### Routes: App / Http / Controllers / Tenant / LeadWebController@storeActivity

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| POST | `/tenant/leads/{id}/activities` | tenant.leads.activities.store | web, App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, Illuminate\Auth\Middleware\Authenticate:web, App\Http\Middleware\ResolveTenantContext, App\Http\Middleware\EnsureTenantEmailIsVerified, App\Http\Middleware\EnsureTenantSubscriptionActive, App\Http\Middleware\CheckTenantPermission:leads,edit, App\Http\Middleware\EnsureTenantExtension:leadmanagement |

### Routes: App / Http / Controllers / Tenant / LeadWebController@update

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| PUT\|PATCH | `/tenant/leads/{id}` | tenant.leads.update | web, App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, Illuminate\Auth\Middleware\Authenticate:web, App\Http\Middleware\ResolveTenantContext, App\Http\Middleware\EnsureTenantEmailIsVerified, App\Http\Middleware\EnsureTenantSubscriptionActive, App\Http\Middleware\CheckTenantPermission:leads,edit, App\Http\Middleware\EnsureTenantExtension:leadmanagement |

### Routes: App / Http / Controllers / Tenant / NavigationMenuController@store

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| POST | `/tenant/settings/navigation-menu` | tenant.settings.navigation-menu.store | web, App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, Illuminate\Auth\Middleware\Authenticate:web, App\Http\Middleware\ResolveTenantContext, App\Http\Middleware\EnsureTenantEmailIsVerified, App\Http\Middleware\CheckTenantPermission:settings,edit |

### Routes: App / Http / Controllers / Tenant / PwaManifestController

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| GET\|HEAD | `/tenant/app.webmanifest` | tenant.pwa.manifest | web, App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, Illuminate\Auth\Middleware\Authenticate:web, App\Http\Middleware\ResolveTenantContext, App\Http\Middleware\EnsureTenantEmailIsVerified |

### Routes: App / Http / Controllers / Tenant / QuotationController@pdf

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| GET\|HEAD | `/tenant/quotations/{quote}/pdf` | tenant.quotations.pdf | web, App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, Illuminate\Auth\Middleware\Authenticate:web, App\Http\Middleware\ResolveTenantContext, App\Http\Middleware\EnsureTenantEmailIsVerified, App\Http\Middleware\EnsureTenantSubscriptionActive, App\Http\Middleware\CheckTenantPermission:quotes,view, App\Http\Middleware\EnsureTenantPosMode:general |
| GET\|HEAD | `/tenant/quotes/{quote}/pdf` | tenant.quotes.pdf | web, App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, Illuminate\Auth\Middleware\Authenticate:web, App\Http\Middleware\ResolveTenantContext, App\Http\Middleware\EnsureTenantEmailIsVerified, App\Http\Middleware\EnsureTenantSubscriptionActive, App\Http\Middleware\CheckTenantPermission:quotes,view, App\Http\Middleware\EnsureTenantPosMode:general |

### Routes: App / Http / Controllers / Tenant / QuotationController@publicShow

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| GET\|HEAD | `/q/{quote_number}` | quotes.public | web, App\Http\Middleware\EnsureAppIsInstalled |

### Routes: App / Http / Controllers / Tenant / QuotationController@send

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| POST | `/tenant/quotations/{quote}/send` | tenant.quotations.send | web, App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, Illuminate\Auth\Middleware\Authenticate:web, App\Http\Middleware\ResolveTenantContext, App\Http\Middleware\EnsureTenantEmailIsVerified, App\Http\Middleware\EnsureTenantSubscriptionActive, App\Http\Middleware\CheckTenantPermission:quotes,export, App\Http\Middleware\EnsureTenantPosMode:general |
| POST | `/tenant/quotes/{quote}/send` | tenant.quotes.send | web, App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, Illuminate\Auth\Middleware\Authenticate:web, App\Http\Middleware\ResolveTenantContext, App\Http\Middleware\EnsureTenantEmailIsVerified, App\Http\Middleware\EnsureTenantSubscriptionActive, App\Http\Middleware\CheckTenantPermission:quotes,export, App\Http\Middleware\EnsureTenantPosMode:general |

### Routes: App / Http / Controllers / Tenant / RepairPortalController@track

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| GET\|HEAD | `/portal/repair/{ticket_number}` | repair.portal.track | web, App\Http\Middleware\EnsureAppIsInstalled |

### Routes: App / Http / Controllers / Tenant / Restaurant / KotController@print

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| GET\|HEAD | `/tenant/restaurant/kot/{kot}/print` | tenant.restaurant.kot.print | web, App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, Illuminate\Auth\Middleware\Authenticate:web, App\Http\Middleware\ResolveTenantContext, App\Http\Middleware\EnsureTenantEmailIsVerified, App\Http\Middleware\EnsureTenantSubscriptionActive, App\Http\Middleware\CheckTenantPermission:pos,view, App\Http\Middleware\EnsureTenantPosMode:restaurant |

### Routes: App / Http / Controllers / Tenant / Restaurant / TableOrderController@placeOrder

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| POST | `/order/table/{token}` | restaurant.table.order.place | web, App\Http\Middleware\EnsureAppIsInstalled |

### Routes: App / Http / Controllers / Tenant / Restaurant / TableOrderController@qrCard

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| GET\|HEAD | `/tenant/restaurant/tables/{table}/qr` | tenant.restaurant.table.qr | web, App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, Illuminate\Auth\Middleware\Authenticate:web, App\Http\Middleware\ResolveTenantContext, App\Http\Middleware\EnsureTenantEmailIsVerified, App\Http\Middleware\EnsureTenantSubscriptionActive, App\Http\Middleware\CheckTenantPermission:pos,view, App\Http\Middleware\EnsureTenantPosMode:restaurant |

### Routes: App / Http / Controllers / Tenant / Restaurant / TableOrderController@show

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| GET\|HEAD | `/order/table/{token}` | restaurant.table.order | web, App\Http\Middleware\EnsureAppIsInstalled |
| GET\|HEAD | `/t/{token}` | restaurant.table.short | web, App\Http\Middleware\EnsureAppIsInstalled |

### Routes: App / Http / Controllers / Tenant / StoreInquiryController@destroy

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| DELETE | `/api/tenant/storefront/inquiries/{id}` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications |
| POST | `/api/tenant/storefront/inquiries/{id}/delete` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications |
| DELETE | `/api/v1/pos/storefront/inquiries/{id}` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\EnsureTenantExtension:ecommerce_storefront, App\Http\Middleware\CheckTenantApiUserPermission:storefront,inquiries.action |
| POST | `/api/v1/pos/storefront/inquiries/{id}/delete` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\EnsureTenantExtension:ecommerce_storefront, App\Http\Middleware\CheckTenantApiUserPermission:storefront,inquiries.action |
| DELETE | `/api/v1/tenant/storefront/inquiries/{id}` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications |
| POST | `/api/v1/tenant/storefront/inquiries/{id}/delete` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications |
| DELETE | `/tenant/storefront/inquiries/{id}` |  | web, App\Http\Middleware\AuthenticateTenantApi |
| DELETE\|POST | `/tenant/storefront/inquiries/{id}/delete` |  | web, App\Http\Middleware\AuthenticateTenantApi |

### Routes: App / Http / Controllers / Tenant / StoreInquiryController@index

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| GET\|HEAD | `/api/tenant/storefront/inquiries` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications |
| GET\|HEAD | `/api/v1/pos/storefront/inquiries` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\EnsureTenantExtension:ecommerce_storefront, App\Http\Middleware\CheckTenantApiUserPermission:storefront,inquiries.view |
| GET\|HEAD | `/api/v1/tenant/storefront/inquiries` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications |
| GET\|HEAD | `/tenant/storefront/inquiries` |  | web, App\Http\Middleware\AuthenticateTenantApi |

### Routes: App / Http / Controllers / Tenant / StoreInquiryController@submitPublicInquiry

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| POST | `/api/api/storefront/inquiry` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext |
| POST | `/api/storefront/inquiry` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext |
| POST | `/api/v1/storefront/inquiry` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext |
| POST | `/c/{slug}/inquiry` |  | web, App\Http\Middleware\EnsureAppIsInstalled |
| POST | `/store/inquiry` | tenant.store.inquiry | web, App\Http\Middleware\EnsureAppIsInstalled |
| POST | `/storefront/inquiry` |  | web, App\Http\Middleware\EnsureAppIsInstalled |

### Routes: App / Http / Controllers / Tenant / StoreInquiryController@updateStatus

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| POST\|PUT | `/api/tenant/storefront/inquiries/{id}/status` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications |
| POST\|PUT | `/api/v1/pos/storefront/inquiries/{id}/status` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\EnsureTenantExtension:ecommerce_storefront, App\Http\Middleware\CheckTenantApiUserPermission:storefront,inquiries.action |
| POST\|PUT | `/api/v1/tenant/storefront/inquiries/{id}/status` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications |
| PUT\|POST | `/tenant/storefront/inquiries/{id}/status` |  | web, App\Http\Middleware\AuthenticateTenantApi |

### Routes: App / Http / Controllers / Tenant / StorefrontAuthController@handleProviderCallback

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| GET\|HEAD | `/store/auth/{provider}/callback` | tenant.store.auth.callback | web |

### Routes: App / Http / Controllers / Tenant / StorefrontAuthController@redirectToProvider

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| GET\|HEAD | `/store/auth/{provider}/redirect` | tenant.store.auth.redirect | web |

### Routes: App / Http / Controllers / Tenant / StorefrontController@account

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| GET\|HEAD | `/store/account` | tenant.store.account | web, App\Http\Middleware\EnsureAppIsInstalled |

### Routes: App / Http / Controllers / Tenant / StorefrontController@apiCatalog

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| GET\|HEAD | `/api/storefront/catalog` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext |
| GET\|HEAD | `/api/v1/storefront/catalog` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext |

### Routes: App / Http / Controllers / Tenant / StorefrontController@apiFaqs

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| GET\|HEAD | `/api/storefront/faqs` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext |
| GET\|HEAD | `/api/v1/storefront/faqs` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext |
| GET\|HEAD | `/store/api/faqs` | tenant.store.api.faqs | web, App\Http\Middleware\EnsureAppIsInstalled |

### Routes: App / Http / Controllers / Tenant / StorefrontController@faqsPage

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| GET\|HEAD | `/store/faqs` | tenant.store.faqs | web, App\Http\Middleware\EnsureAppIsInstalled |

### Routes: App / Http / Controllers / Tenant / StorefrontController@index

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| GET\|HEAD | `/store` | tenant.store | web, App\Http\Middleware\EnsureAppIsInstalled |

### Routes: App / Http / Controllers / Tenant / StorefrontController@initiateGatewayPayment

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| POST | `/api/storefront/payment/initiate` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext |
| POST | `/api/v1/storefront/payment/initiate` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext |
| POST | `/store/payment/initiate` | tenant.store.payment.initiate | web, App\Http\Middleware\EnsureAppIsInstalled |

### Routes: App / Http / Controllers / Tenant / StorefrontController@paymentMethods

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| GET\|HEAD | `/api/storefront/payment-methods` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext |
| GET\|HEAD | `/api/v1/storefront/payment-methods` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext |
| GET\|HEAD | `/store/payment-methods` | tenant.store.payment_methods | web, App\Http\Middleware\EnsureAppIsInstalled |

### Routes: App / Http / Controllers / Tenant / StorefrontController@placeOrder

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| POST | `/api/storefront/order` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext |
| POST | `/api/v1/storefront/order` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext |
| POST | `/store/order` | tenant.store.order | web, App\Http\Middleware\EnsureAppIsInstalled |

### Routes: App / Http / Controllers / Tenant / StorefrontController@sendVerification

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| POST | `/api/storefront/customer/send-verification` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext |
| POST | `/api/v1/storefront/customer/send-verification` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext |
| POST | `/store/auth/send-verification` | tenant.store.auth.send_verification | web, App\Http\Middleware\EnsureAppIsInstalled |

### Routes: App / Http / Controllers / Tenant / StorefrontController@showCmsPage

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| GET\|HEAD | `/store/page/{slug}` | tenant.store.page | web, App\Http\Middleware\EnsureAppIsInstalled |

### Routes: App / Http / Controllers / Tenant / StorefrontController@trackOrder

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| GET\|HEAD | `/store/track/{code}` | tenant.store.track | web, App\Http\Middleware\EnsureAppIsInstalled |

### Routes: App / Http / Controllers / Tenant / StorefrontController@validateCoupon

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| POST | `/api/storefront/coupons/validate` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext |
| POST | `/api/v1/storefront/coupons/validate` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext |
| POST | `/store/coupons/validate` | tenant.store.coupon.validate | web, App\Http\Middleware\EnsureAppIsInstalled |

### Routes: App / Http / Controllers / Tenant / StorefrontController@verifyCode

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| POST | `/api/storefront/customer/verify-code` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext |
| POST | `/api/v1/storefront/customer/verify-code` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext |
| POST | `/store/auth/verify-code` | tenant.store.auth.verify_code | web, App\Http\Middleware\EnsureAppIsInstalled |

### Routes: App / Http / Controllers / Tenant / StorefrontController@verifyGatewayPayment

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| POST | `/api/storefront/payment/verify` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext |
| POST | `/api/v1/storefront/payment/verify` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext |
| POST | `/store/payment/verify` | tenant.store.payment.verify | web, App\Http\Middleware\EnsureAppIsInstalled |

### Routes: App / Http / Controllers / Tenant / StorefrontMenuController@menusDestroy

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| DELETE | `/api/tenant/storefront/menus/{id}` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications |
| POST | `/api/tenant/storefront/menus/{id}/delete` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications |
| DELETE | `/api/v1/pos/storefront/menus/{id}` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\EnsureTenantExtension:ecommerce_storefront, App\Http\Middleware\CheckTenantApiUserPermission:storefront,menus.manage |
| POST | `/api/v1/pos/storefront/menus/{id}/delete` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\EnsureTenantExtension:ecommerce_storefront, App\Http\Middleware\CheckTenantApiUserPermission:storefront,menus.manage |
| DELETE | `/api/v1/tenant/storefront/menus/{id}` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications |
| POST | `/api/v1/tenant/storefront/menus/{id}/delete` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications |

### Routes: App / Http / Controllers / Tenant / StorefrontMenuController@menusIndex

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| GET\|HEAD | `/api/tenant/storefront/menus` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications |
| GET\|HEAD | `/api/v1/pos/storefront/menus` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\EnsureTenantExtension:ecommerce_storefront, App\Http\Middleware\CheckTenantApiUserPermission:storefront,menus.manage |
| GET\|HEAD | `/api/v1/tenant/storefront/menus` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications |

### Routes: App / Http / Controllers / Tenant / StorefrontMenuController@menusStore

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| POST | `/api/tenant/storefront/menus` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications |
| POST | `/api/v1/pos/storefront/menus` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\EnsureTenantExtension:ecommerce_storefront, App\Http\Middleware\CheckTenantApiUserPermission:storefront,menus.manage |
| POST | `/api/v1/tenant/storefront/menus` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications |

### Routes: App / Http / Controllers / Tenant / StorefrontMenuController@menusUpdate

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| PUT\|POST | `/api/tenant/storefront/menus/{id}` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications |
| PUT\|POST | `/api/v1/pos/storefront/menus/{id}` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\EnsureTenantExtension:ecommerce_storefront, App\Http\Middleware\CheckTenantApiUserPermission:storefront,menus.manage |
| PUT\|POST | `/api/v1/tenant/storefront/menus/{id}` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications |

### Routes: App / Http / Controllers / Tenant / StorefrontMenuController@pagesDestroy

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| DELETE | `/api/tenant/storefront/pages/{id}` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications |
| POST | `/api/tenant/storefront/pages/{id}/delete` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications |
| DELETE | `/api/v1/pos/storefront/pages/{id}` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\EnsureTenantExtension:ecommerce_storefront, App\Http\Middleware\CheckTenantApiUserPermission:storefront,menus.manage |
| POST | `/api/v1/pos/storefront/pages/{id}/delete` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\EnsureTenantExtension:ecommerce_storefront, App\Http\Middleware\CheckTenantApiUserPermission:storefront,menus.manage |
| DELETE | `/api/v1/tenant/storefront/pages/{id}` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications |
| POST | `/api/v1/tenant/storefront/pages/{id}/delete` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications |

### Routes: App / Http / Controllers / Tenant / StorefrontMenuController@pagesIndex

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| GET\|HEAD | `/api/tenant/storefront/pages` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications |
| GET\|HEAD | `/api/v1/pos/storefront/pages` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\EnsureTenantExtension:ecommerce_storefront, App\Http\Middleware\CheckTenantApiUserPermission:storefront,menus.manage |
| GET\|HEAD | `/api/v1/tenant/storefront/pages` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications |

### Routes: App / Http / Controllers / Tenant / StorefrontMenuController@pagesShow

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| GET\|HEAD | `/api/tenant/storefront/pages/{id}` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications |
| GET\|HEAD | `/api/v1/pos/storefront/pages/{id}` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\EnsureTenantExtension:ecommerce_storefront, App\Http\Middleware\CheckTenantApiUserPermission:storefront,menus.manage |
| GET\|HEAD | `/api/v1/tenant/storefront/pages/{id}` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications |

### Routes: App / Http / Controllers / Tenant / StorefrontMenuController@pagesStore

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| POST | `/api/tenant/storefront/pages` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications |
| POST | `/api/v1/pos/storefront/pages` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\EnsureTenantExtension:ecommerce_storefront, App\Http\Middleware\CheckTenantApiUserPermission:storefront,menus.manage |
| POST | `/api/v1/tenant/storefront/pages` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications |

### Routes: App / Http / Controllers / Tenant / StorefrontMenuController@pagesUpdate

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| PUT\|POST | `/api/tenant/storefront/pages/{id}` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications |
| PUT\|POST | `/api/v1/pos/storefront/pages/{id}` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\EnsureTenantExtension:ecommerce_storefront, App\Http\Middleware\CheckTenantApiUserPermission:storefront,menus.manage |
| PUT\|POST | `/api/v1/tenant/storefront/pages/{id}` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications |

### Routes: App / Http / Controllers / Tenant / StorefrontMenuController@publicMenus

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| GET\|HEAD | `/api/api/v1/storefront/menus` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext |
| GET\|HEAD | `/api/storefront/menus` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext |
| GET\|HEAD | `/api/v1/storefront/menus` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext |

### Routes: App / Http / Controllers / Tenant / StorefrontMenuController@reorder

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| POST | `/api/tenant/storefront/menus/reorder` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications |
| POST | `/api/v1/pos/storefront/menus/reorder` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\EnsureTenantExtension:ecommerce_storefront, App\Http\Middleware\CheckTenantApiUserPermission:storefront,menus.manage |
| POST | `/api/v1/tenant/storefront/menus/reorder` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications |

### Routes: App / Http / Controllers / Tenant / StorefrontMenuController@toggleVisibility

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| PUT\|POST | `/api/tenant/storefront/menus/{id}/toggle-visibility` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications |
| PUT\|POST | `/api/v1/pos/storefront/menus/{id}/toggle-visibility` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\EnsureTenantExtension:ecommerce_storefront, App\Http\Middleware\CheckTenantApiUserPermission:storefront,menus.manage |
| PUT\|POST | `/api/v1/tenant/storefront/menus/{id}/toggle-visibility` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications |

### Routes: App / Http / Controllers / Tenant / StorefrontReviewController@customerReviews

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| GET\|HEAD | `/api/storefront/customer/reviews` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext |
| GET\|HEAD | `/api/v1/storefront/customer/reviews` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext |
| GET\|HEAD | `/store/customer/reviews` | tenant.store.customer.reviews | web, App\Http\Middleware\EnsureAppIsInstalled |

### Routes: App / Http / Controllers / Tenant / StorefrontReviewController@index

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| GET\|HEAD | `/api/storefront/products/{id}/reviews` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext |
| GET\|HEAD | `/api/v1/storefront/products/{id}/reviews` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext |
| GET\|HEAD | `/store/products/{id}/reviews` | tenant.store.reviews.index | web, App\Http\Middleware\EnsureAppIsInstalled |

### Routes: App / Http / Controllers / Tenant / StorefrontReviewController@store

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| POST | `/api/storefront/products/{id}/reviews` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext |
| POST | `/api/v1/storefront/products/{id}/reviews` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext |
| POST | `/store/products/{id}/reviews` | tenant.store.reviews.store | web, App\Http\Middleware\EnsureAppIsInstalled |

### Routes: App / Http / Controllers / Tenant / StorefrontReviewController@tenantDestroy

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| DELETE | `/api/tenant/storefront/reviews/{id}` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications |
| POST | `/api/tenant/storefront/reviews/{id}/delete` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications |
| DELETE | `/api/v1/pos/storefront/reviews/{id}` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\EnsureTenantExtension:ecommerce_storefront, App\Http\Middleware\CheckTenantApiUserPermission:reviews,delete |
| POST | `/api/v1/pos/storefront/reviews/{id}/delete` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\EnsureTenantExtension:ecommerce_storefront, App\Http\Middleware\CheckTenantApiUserPermission:reviews,delete |
| DELETE | `/api/v1/tenant/storefront/reviews/{id}` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications |
| POST | `/api/v1/tenant/storefront/reviews/{id}/delete` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications |
| DELETE | `/tenant/storefront/reviews/{id}` |  | web, App\Http\Middleware\AuthenticateTenantApi |
| POST | `/tenant/storefront/reviews/{id}/delete` |  | web, App\Http\Middleware\AuthenticateTenantApi |

### Routes: App / Http / Controllers / Tenant / StorefrontReviewController@tenantIndex

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| GET\|HEAD | `/api/tenant/storefront/reviews` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications |
| GET\|HEAD | `/api/v1/pos/storefront/reviews` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\EnsureTenantExtension:ecommerce_storefront, App\Http\Middleware\CheckTenantApiUserPermission:reviews,view |
| GET\|HEAD | `/api/v1/tenant/storefront/reviews` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications |
| GET\|HEAD | `/tenant/storefront/reviews` |  | web, App\Http\Middleware\AuthenticateTenantApi |

### Routes: App / Http / Controllers / Tenant / StorefrontReviewController@tenantStore

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| POST | `/api/tenant/storefront/reviews` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications |
| POST | `/api/v1/pos/storefront/reviews` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\EnsureTenantExtension:ecommerce_storefront, App\Http\Middleware\CheckTenantApiUserPermission:reviews,create |
| POST | `/api/v1/tenant/storefront/reviews` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications |
| POST | `/tenant/storefront/reviews` |  | web, App\Http\Middleware\AuthenticateTenantApi |

### Routes: App / Http / Controllers / Tenant / StorefrontReviewController@toggleApproval

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| POST\|PUT | `/api/tenant/storefront/reviews/{id}/toggle-approval` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications |
| POST\|PUT | `/api/v1/pos/storefront/reviews/{id}/toggle-approval` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\EnsureTenantExtension:ecommerce_storefront, App\Http\Middleware\CheckTenantApiUserPermission:reviews,edit |
| POST\|PUT | `/api/v1/tenant/storefront/reviews/{id}/toggle-approval` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications |
| POST\|PUT | `/tenant/storefront/reviews/{id}/toggle-approval` |  | web, App\Http\Middleware\AuthenticateTenantApi |

### Routes: App / Http / Controllers / Tenant / StorefrontReviewController@updateSettings

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| POST\|PUT | `/api/tenant/storefront/reviews/settings` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications |
| POST\|PUT | `/api/v1/pos/storefront/reviews/settings` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\EnsureTenantExtension:ecommerce_storefront, App\Http\Middleware\CheckTenantApiUserPermission:settings,edit |
| POST\|PUT | `/api/v1/tenant/storefront/reviews/settings` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications |
| POST\|PUT | `/tenant/storefront/reviews/settings` |  | web, App\Http\Middleware\AuthenticateTenantApi |

### Routes: App / Http / Controllers / Tenant / SubscriptionInvoiceController@pdf

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| GET\|HEAD | `/tenant/billing/invoices/{invoice}/pdf` | tenant.billing.invoices.pdf | web, App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, Illuminate\Auth\Middleware\Authenticate:web, App\Http\Middleware\ResolveTenantContext, App\Http\Middleware\EnsureTenantEmailIsVerified |

### Routes: App / Http / Controllers / Tenant / UserPreferenceController@updateDockPosition

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| POST | `/tenant/preferences/dock-position` | tenant.preferences.dock-position | web, App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, Illuminate\Auth\Middleware\Authenticate:web, App\Http\Middleware\ResolveTenantContext, App\Http\Middleware\EnsureTenantEmailIsVerified |

### Routes: App / Http / Controllers / Webhooks / SubscriptionWebhookController@callback

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| GET\|HEAD | `/subscription/payment/callback/{gateway}` | subscription.payment.callback | web |

### Routes: App / Http / Controllers / Webhooks / SubscriptionWebhookController@handle

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| POST | `/api/v1/webhooks/mercadopago` | webhooks.mercadopago | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext |
| POST | `/api/v1/webhooks/paypal` | webhooks.paypal | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext |
| POST | `/api/v1/webhooks/razorpay` | webhooks.razorpay | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext |
| POST | `/api/v1/webhooks/stripe` | webhooks.stripe | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext |
| POST | `/api/v1/webhooks/{gateway}` | webhooks.gateway | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext |
| POST | `/api/webhooks/mercadopago` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext |
| POST | `/api/webhooks/paypal` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext |
| POST | `/api/webhooks/razorpay` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext |
| POST | `/api/webhooks/stripe` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext |
| POST | `/api/webhooks/{gateway}` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext |
| POST | `/v1/webhooks/{gateway}` | webhooks.web.v1.gateway | web |
| POST | `/webhooks/{gateway}` | webhooks.web.gateway | web |

### Routes: App / Livewire / Auth / AcceptInvite

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| GET\|HEAD | `/accept-invite` | accept-invite | web, App\Http\Middleware\EnsureAppIsInstalled, Illuminate\Auth\Middleware\RedirectIfAuthenticated:web |

### Routes: App / Livewire / Auth / PlatformLogin

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| GET\|HEAD | `/superadmin/login` | superadmin.login | web, App\Http\Middleware\EnsureAppIsInstalled, Illuminate\Auth\Middleware\RedirectIfAuthenticated:platform_web |

### Routes: App / Livewire / Auth / TenantLogin

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| GET\|HEAD | `/tenant/login` | tenant.login | web, App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, Illuminate\Auth\Middleware\RedirectIfAuthenticated:web |

### Routes: App / Livewire / Auth / TenantRegister

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| GET\|HEAD | `/tenant/register` | tenant.register | web, App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, Illuminate\Auth\Middleware\RedirectIfAuthenticated:web |

### Routes: App / Livewire / Auth / VerifyOtp

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| GET\|HEAD | `/tenant/verify-otp` | tenant.verify_otp | web, App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, Illuminate\Auth\Middleware\Authenticate:web |

### Routes: App / Livewire / Installer / AdminAccountStep

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| GET\|HEAD | `/install/admin` | install.admin | web, App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\EnsureNotInstalled |

### Routes: App / Livewire / Installer / EnvironmentStep

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| GET\|HEAD | `/install/environment` | install.environment | web, App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\EnsureNotInstalled |

### Routes: App / Livewire / Installer / FinishStep

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| GET\|HEAD | `/install/finish` | install.finish | web, App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\EnsureNotInstalled |

### Routes: App / Livewire / Installer / MigrateStep

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| GET\|HEAD | `/install/migrate` | install.migrate | web, App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\EnsureNotInstalled |

### Routes: App / Livewire / Installer / RequirementsStep

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| GET\|HEAD | `/install/requirements` | install.requirements | web, App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\EnsureNotInstalled |

### Routes: App / Livewire / SuperAdmin / ActivationCodes / Index

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| GET\|HEAD | `/superadmin/activation-codes` | superadmin.activation-codes.index | web, App\Http\Middleware\EnsureAppIsInstalled, Illuminate\Auth\Middleware\Authenticate:platform_web, App\Http\Middleware\PeriodicLicenseCheck, App\Http\Middleware\PreventDemoModifications |

### Routes: App / Livewire / SuperAdmin / AuditLogs / Index

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| GET\|HEAD | `/superadmin/audit` | superadmin.audit.index | web, App\Http\Middleware\EnsureAppIsInstalled, Illuminate\Auth\Middleware\Authenticate:platform_web, App\Http\Middleware\PeriodicLicenseCheck, App\Http\Middleware\PreventDemoModifications |

### Routes: App / Livewire / SuperAdmin / Backups / Index

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| GET\|HEAD | `/superadmin/backups` | superadmin.backups.index | web, App\Http\Middleware\EnsureAppIsInstalled, Illuminate\Auth\Middleware\Authenticate:platform_web, App\Http\Middleware\PeriodicLicenseCheck, App\Http\Middleware\PreventDemoModifications |

### Routes: App / Livewire / SuperAdmin / Dashboard

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| GET\|HEAD | `/superadmin` | superadmin.dashboard | web, App\Http\Middleware\EnsureAppIsInstalled, Illuminate\Auth\Middleware\Authenticate:platform_web, App\Http\Middleware\PeriodicLicenseCheck, App\Http\Middleware\PreventDemoModifications |

### Routes: App / Livewire / SuperAdmin / Inquiries / Index

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| GET\|HEAD | `/superadmin/inquiries` | superadmin.inquiries.index | web, App\Http\Middleware\EnsureAppIsInstalled, Illuminate\Auth\Middleware\Authenticate:platform_web, App\Http\Middleware\PeriodicLicenseCheck, App\Http\Middleware\PreventDemoModifications |

### Routes: App / Livewire / SuperAdmin / Languages / Index

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| GET\|HEAD | `/superadmin/languages` | superadmin.languages.index | web, App\Http\Middleware\EnsureAppIsInstalled, Illuminate\Auth\Middleware\Authenticate:platform_web, App\Http\Middleware\PeriodicLicenseCheck, App\Http\Middleware\PreventDemoModifications |

### Routes: App / Livewire / SuperAdmin / Modules / Index

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| GET\|HEAD | `/superadmin/modules` | superadmin.modules.index | web, App\Http\Middleware\EnsureAppIsInstalled, Illuminate\Auth\Middleware\Authenticate:platform_web, App\Http\Middleware\PeriodicLicenseCheck, App\Http\Middleware\PreventDemoModifications |

### Routes: App / Livewire / SuperAdmin / Pages / Create

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| GET\|HEAD | `/superadmin/pages/create` | superadmin.pages.create | web, App\Http\Middleware\EnsureAppIsInstalled, Illuminate\Auth\Middleware\Authenticate:platform_web, App\Http\Middleware\PeriodicLicenseCheck, App\Http\Middleware\PreventDemoModifications |

### Routes: App / Livewire / SuperAdmin / Pages / Edit

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| GET\|HEAD | `/superadmin/pages/{page}` | superadmin.pages.edit | web, App\Http\Middleware\EnsureAppIsInstalled, Illuminate\Auth\Middleware\Authenticate:platform_web, App\Http\Middleware\PeriodicLicenseCheck, App\Http\Middleware\PreventDemoModifications |

### Routes: App / Livewire / SuperAdmin / Pages / Index

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| GET\|HEAD | `/superadmin/pages` | superadmin.pages.index | web, App\Http\Middleware\EnsureAppIsInstalled, Illuminate\Auth\Middleware\Authenticate:platform_web, App\Http\Middleware\PeriodicLicenseCheck, App\Http\Middleware\PreventDemoModifications |

### Routes: App / Livewire / SuperAdmin / PaymentGateways / Index

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| GET\|HEAD | `/superadmin/payment-gateways` | superadmin.payment-gateways.index | web, App\Http\Middleware\EnsureAppIsInstalled, Illuminate\Auth\Middleware\Authenticate:platform_web, App\Http\Middleware\PeriodicLicenseCheck, App\Http\Middleware\PreventDemoModifications |

### Routes: App / Livewire / SuperAdmin / Plans / Index

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| GET\|HEAD | `/superadmin/plans` | superadmin.plans.index | web, App\Http\Middleware\EnsureAppIsInstalled, Illuminate\Auth\Middleware\Authenticate:platform_web, App\Http\Middleware\PeriodicLicenseCheck, App\Http\Middleware\PreventDemoModifications |

### Routes: App / Livewire / SuperAdmin / Settings / Index

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| GET\|HEAD | `/superadmin/settings` | superadmin.settings.index | web, App\Http\Middleware\EnsureAppIsInstalled, Illuminate\Auth\Middleware\Authenticate:platform_web, App\Http\Middleware\PeriodicLicenseCheck, App\Http\Middleware\PreventDemoModifications |
| GET\|HEAD | `/superadmin/settings/notifications` | superadmin.settings.notifications | web, App\Http\Middleware\EnsureAppIsInstalled, Illuminate\Auth\Middleware\Authenticate:platform_web, App\Http\Middleware\PeriodicLicenseCheck, App\Http\Middleware\PreventDemoModifications |
| GET\|HEAD | `/superadmin/settings/regional` | superadmin.settings.regional | web, App\Http\Middleware\EnsureAppIsInstalled, Illuminate\Auth\Middleware\Authenticate:platform_web, App\Http\Middleware\PeriodicLicenseCheck, App\Http\Middleware\PreventDemoModifications |

### Routes: App / Livewire / SuperAdmin / Smtp / Index

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| GET\|HEAD | `/superadmin/smtp` | superadmin.smtp.index | web, App\Http\Middleware\EnsureAppIsInstalled, Illuminate\Auth\Middleware\Authenticate:platform_web, App\Http\Middleware\PeriodicLicenseCheck, App\Http\Middleware\PreventDemoModifications |

### Routes: App / Livewire / SuperAdmin / System / Index

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| GET\|HEAD | `/superadmin/system` | superadmin.system.index | web, App\Http\Middleware\EnsureAppIsInstalled, Illuminate\Auth\Middleware\Authenticate:platform_web, App\Http\Middleware\PeriodicLicenseCheck, App\Http\Middleware\PreventDemoModifications |

### Routes: App / Livewire / SuperAdmin / Tax / Index

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| GET\|HEAD | `/superadmin/tax` | superadmin.tax.index | web, App\Http\Middleware\EnsureAppIsInstalled, Illuminate\Auth\Middleware\Authenticate:platform_web, App\Http\Middleware\PeriodicLicenseCheck, App\Http\Middleware\PreventDemoModifications |

### Routes: App / Livewire / SuperAdmin / Tenants / Create

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| GET\|HEAD | `/superadmin/tenants/create` | superadmin.tenants.create | web, App\Http\Middleware\EnsureAppIsInstalled, Illuminate\Auth\Middleware\Authenticate:platform_web, App\Http\Middleware\PeriodicLicenseCheck, App\Http\Middleware\PreventDemoModifications |

### Routes: App / Livewire / SuperAdmin / Tenants / Index

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| GET\|HEAD | `/superadmin/tenants` | superadmin.tenants.index | web, App\Http\Middleware\EnsureAppIsInstalled, Illuminate\Auth\Middleware\Authenticate:platform_web, App\Http\Middleware\PeriodicLicenseCheck, App\Http\Middleware\PreventDemoModifications |

### Routes: App / Livewire / SuperAdmin / Tenants / Show

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| GET\|HEAD | `/superadmin/tenants/{company}` | superadmin.tenants.show | web, App\Http\Middleware\EnsureAppIsInstalled, Illuminate\Auth\Middleware\Authenticate:platform_web, App\Http\Middleware\PeriodicLicenseCheck, App\Http\Middleware\PreventDemoModifications |

### Routes: App / Livewire / Superadmin / MenuBuilderComponent

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| GET\|HEAD | `/superadmin/menus` | superadmin.menus.index | web, App\Http\Middleware\EnsureAppIsInstalled, Illuminate\Auth\Middleware\Authenticate:platform_web, App\Http\Middleware\PeriodicLicenseCheck, App\Http\Middleware\PreventDemoModifications |

### Routes: App / Livewire / Tenant / Billing / Index

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| GET\|HEAD | `/tenant/activate` | tenant.activate | web, App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, Illuminate\Auth\Middleware\Authenticate:web, App\Http\Middleware\ResolveTenantContext, App\Http\Middleware\EnsureTenantEmailIsVerified |
| GET\|HEAD | `/tenant/billing` | tenant.billing.index | web, App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, Illuminate\Auth\Middleware\Authenticate:web, App\Http\Middleware\ResolveTenantContext, App\Http\Middleware\EnsureTenantEmailIsVerified |

### Routes: App / Livewire / Tenant / Brands / Index

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| GET\|HEAD | `/tenant/brands` | tenant.brands.index | web, App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, Illuminate\Auth\Middleware\Authenticate:web, App\Http\Middleware\ResolveTenantContext, App\Http\Middleware\EnsureTenantEmailIsVerified, App\Http\Middleware\EnsureTenantSubscriptionActive, App\Http\Middleware\CheckTenantPermission:categories,view |

### Routes: App / Livewire / Tenant / Catalog / Index

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| GET\|HEAD | `/tenant/catalog` | tenant.catalog.index | web, App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, Illuminate\Auth\Middleware\Authenticate:web, App\Http\Middleware\ResolveTenantContext, App\Http\Middleware\EnsureTenantEmailIsVerified, App\Http\Middleware\EnsureTenantSubscriptionActive, App\Http\Middleware\CheckTenantPermission:catalog,view |

### Routes: App / Livewire / Tenant / Categories / Index

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| GET\|HEAD | `/tenant/categories` | tenant.categories.index | web, App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, Illuminate\Auth\Middleware\Authenticate:web, App\Http\Middleware\ResolveTenantContext, App\Http\Middleware\EnsureTenantEmailIsVerified, App\Http\Middleware\EnsureTenantSubscriptionActive, App\Http\Middleware\CheckTenantPermission:categories,view |

### Routes: App / Livewire / Tenant / Consignments / Create

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| GET\|HEAD | `/tenant/consignments/create` | tenant.consignments.create | web, App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, Illuminate\Auth\Middleware\Authenticate:web, App\Http\Middleware\ResolveTenantContext, App\Http\Middleware\EnsureTenantEmailIsVerified, App\Http\Middleware\EnsureTenantSubscriptionActive, App\Http\Middleware\CheckTenantPermission:consignments,create, App\Http\Middleware\EnsureTenantPosMode:general |

### Routes: App / Livewire / Tenant / Consignments / Index

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| GET\|HEAD | `/tenant/consignments` | tenant.consignments.index | web, App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, Illuminate\Auth\Middleware\Authenticate:web, App\Http\Middleware\ResolveTenantContext, App\Http\Middleware\EnsureTenantEmailIsVerified, App\Http\Middleware\EnsureTenantSubscriptionActive, App\Http\Middleware\CheckTenantPermission:consignments,view, App\Http\Middleware\EnsureTenantPosMode:general |

### Routes: App / Livewire / Tenant / Consignments / Show

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| GET\|HEAD | `/tenant/consignments/{consignment}` | tenant.consignments.show | web, App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, Illuminate\Auth\Middleware\Authenticate:web, App\Http\Middleware\ResolveTenantContext, App\Http\Middleware\EnsureTenantEmailIsVerified, App\Http\Middleware\EnsureTenantSubscriptionActive, App\Http\Middleware\CheckTenantPermission:consignments,view, App\Http\Middleware\EnsureTenantPosMode:general |

### Routes: App / Livewire / Tenant / Coupons / Index

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| GET\|HEAD | `/tenant/coupons` | tenant.coupons.index | web, App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, Illuminate\Auth\Middleware\Authenticate:web, App\Http\Middleware\ResolveTenantContext, App\Http\Middleware\EnsureTenantEmailIsVerified, App\Http\Middleware\EnsureTenantSubscriptionActive, App\Http\Middleware\CheckTenantPermission:sales,view |
| GET\|HEAD | `/tenant/settings/coupons` | tenant.settings.coupons | web, App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, Illuminate\Auth\Middleware\Authenticate:web, App\Http\Middleware\ResolveTenantContext, App\Http\Middleware\EnsureTenantEmailIsVerified, App\Http\Middleware\CheckTenantPermission:settings,view |

### Routes: App / Livewire / Tenant / Customers / Index

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| GET\|HEAD | `/tenant/customers` | tenant.customers.index | web, App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, Illuminate\Auth\Middleware\Authenticate:web, App\Http\Middleware\ResolveTenantContext, App\Http\Middleware\EnsureTenantEmailIsVerified, App\Http\Middleware\EnsureTenantSubscriptionActive, App\Http\Middleware\CheckTenantPermission:customers,view |

### Routes: App / Livewire / Tenant / Dashboard

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| GET\|HEAD | `/tenant` | tenant.dashboard | web, App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, Illuminate\Auth\Middleware\Authenticate:web, App\Http\Middleware\ResolveTenantContext, App\Http\Middleware\EnsureTenantEmailIsVerified, App\Http\Middleware\EnsureTenantSubscriptionActive |

### Routes: App / Livewire / Tenant / Devices / Index

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| GET\|HEAD | `/tenant/devices` | tenant.devices.index | web, App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, Illuminate\Auth\Middleware\Authenticate:web, App\Http\Middleware\ResolveTenantContext, App\Http\Middleware\EnsureTenantEmailIsVerified, App\Http\Middleware\EnsureTenantSubscriptionActive, App\Http\Middleware\CheckTenantPermission:settings,view |

### Routes: App / Livewire / Tenant / Faqs / Index

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| GET\|HEAD | `/tenant/faqs` | tenant.faqs.index | web, App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, Illuminate\Auth\Middleware\Authenticate:web, App\Http\Middleware\ResolveTenantContext, App\Http\Middleware\EnsureTenantEmailIsVerified, App\Http\Middleware\CheckTenantPermission:settings,view |
| GET\|HEAD | `/tenant/settings/faqs` | tenant.settings.faqs | web, App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, Illuminate\Auth\Middleware\Authenticate:web, App\Http\Middleware\ResolveTenantContext, App\Http\Middleware\EnsureTenantEmailIsVerified, App\Http\Middleware\CheckTenantPermission:settings,view |

### Routes: App / Livewire / Tenant / Financials / CashRegister

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| GET\|HEAD | `/tenant/finance/cash-register` | tenant.financials.cash_register | web, App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, Illuminate\Auth\Middleware\Authenticate:web, App\Http\Middleware\ResolveTenantContext, App\Http\Middleware\EnsureTenantEmailIsVerified, App\Http\Middleware\EnsureTenantSubscriptionActive, App\Http\Middleware\CheckTenantPermission:cash_register,view |

### Routes: App / Livewire / Tenant / Financials / Payables

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| GET\|HEAD | `/tenant/finance/payables` | tenant.financials.payables | web, App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, Illuminate\Auth\Middleware\Authenticate:web, App\Http\Middleware\ResolveTenantContext, App\Http\Middleware\EnsureTenantEmailIsVerified, App\Http\Middleware\EnsureTenantSubscriptionActive, App\Http\Middleware\CheckTenantPermission:finance,view |

### Routes: App / Livewire / Tenant / Financials / PaymentMethodLedger

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| GET\|HEAD | `/tenant/finance/payment-methods/{paymentMethod}/ledger` | tenant.financials.payment_method_ledger | web, App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, Illuminate\Auth\Middleware\Authenticate:web, App\Http\Middleware\ResolveTenantContext, App\Http\Middleware\EnsureTenantEmailIsVerified, App\Http\Middleware\EnsureTenantSubscriptionActive, App\Http\Middleware\CheckTenantPermission:settings,view |

### Routes: App / Livewire / Tenant / Financials / Receivables

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| GET\|HEAD | `/tenant/finance/receivables` | tenant.financials.receivables | web, App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, Illuminate\Auth\Middleware\Authenticate:web, App\Http\Middleware\ResolveTenantContext, App\Http\Middleware\EnsureTenantEmailIsVerified, App\Http\Middleware\EnsureTenantSubscriptionActive, App\Http\Middleware\CheckTenantPermission:finance,view |

### Routes: App / Livewire / Tenant / Languages / Index

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| GET\|HEAD | `/tenant/languages` | tenant.languages.index | web, App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, Illuminate\Auth\Middleware\Authenticate:web, App\Http\Middleware\ResolveTenantContext, App\Http\Middleware\EnsureTenantEmailIsVerified, App\Http\Middleware\CheckTenantPermission:settings,view |

### Routes: App / Livewire / Tenant / Pharmacy / Batches

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| GET\|HEAD | `/tenant/pharmacy/batches` | tenant.pharmacy.batches | web, App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, Illuminate\Auth\Middleware\Authenticate:web, App\Http\Middleware\ResolveTenantContext, App\Http\Middleware\EnsureTenantEmailIsVerified, App\Http\Middleware\EnsureTenantSubscriptionActive, App\Http\Middleware\EnsureTenantVertical:pharmacy, App\Http\Middleware\CheckTenantPermission:products,view |

### Routes: App / Livewire / Tenant / Pharmacy / Dashboard

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| GET\|HEAD | `/tenant/pharmacy` | tenant.pharmacy.dashboard | web, App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, Illuminate\Auth\Middleware\Authenticate:web, App\Http\Middleware\ResolveTenantContext, App\Http\Middleware\EnsureTenantEmailIsVerified, App\Http\Middleware\EnsureTenantSubscriptionActive, App\Http\Middleware\EnsureTenantVertical:pharmacy, App\Http\Middleware\CheckTenantPermission:products,view |

### Routes: App / Livewire / Tenant / Pharmacy / Prescriptions

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| GET\|HEAD | `/tenant/pharmacy/prescriptions` | tenant.pharmacy.prescriptions | web, App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, Illuminate\Auth\Middleware\Authenticate:web, App\Http\Middleware\ResolveTenantContext, App\Http\Middleware\EnsureTenantEmailIsVerified, App\Http\Middleware\EnsureTenantSubscriptionActive, App\Http\Middleware\EnsureTenantVertical:pharmacy, App\Http\Middleware\CheckTenantPermission:sales,view |

### Routes: App / Livewire / Tenant / Products / Index

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| GET\|HEAD | `/tenant/products` | tenant.products.index | web, App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, Illuminate\Auth\Middleware\Authenticate:web, App\Http\Middleware\ResolveTenantContext, App\Http\Middleware\EnsureTenantEmailIsVerified, App\Http\Middleware\EnsureTenantSubscriptionActive, App\Http\Middleware\CheckTenantPermission:products,view |

### Routes: App / Livewire / Tenant / Quotes / Create

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| GET\|HEAD | `/tenant/quotations/create` | tenant.quotations.create | web, App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, Illuminate\Auth\Middleware\Authenticate:web, App\Http\Middleware\ResolveTenantContext, App\Http\Middleware\EnsureTenantEmailIsVerified, App\Http\Middleware\EnsureTenantSubscriptionActive, App\Http\Middleware\CheckTenantPermission:quotes,create, App\Http\Middleware\EnsureTenantPosMode:general |
| GET\|HEAD | `/tenant/quotes/create` | tenant.quotes.create | web, App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, Illuminate\Auth\Middleware\Authenticate:web, App\Http\Middleware\ResolveTenantContext, App\Http\Middleware\EnsureTenantEmailIsVerified, App\Http\Middleware\EnsureTenantSubscriptionActive, App\Http\Middleware\CheckTenantPermission:quotes,create, App\Http\Middleware\EnsureTenantPosMode:general |

### Routes: App / Livewire / Tenant / Quotes / Edit

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| GET\|HEAD | `/tenant/quotations/{quote}/edit` | tenant.quotations.edit | web, App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, Illuminate\Auth\Middleware\Authenticate:web, App\Http\Middleware\ResolveTenantContext, App\Http\Middleware\EnsureTenantEmailIsVerified, App\Http\Middleware\EnsureTenantSubscriptionActive, App\Http\Middleware\CheckTenantPermission:quotes,create, App\Http\Middleware\EnsureTenantPosMode:general |
| GET\|HEAD | `/tenant/quotes/{quote}/edit` | tenant.quotes.edit | web, App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, Illuminate\Auth\Middleware\Authenticate:web, App\Http\Middleware\ResolveTenantContext, App\Http\Middleware\EnsureTenantEmailIsVerified, App\Http\Middleware\EnsureTenantSubscriptionActive, App\Http\Middleware\CheckTenantPermission:quotes,create, App\Http\Middleware\EnsureTenantPosMode:general |

### Routes: App / Livewire / Tenant / Quotes / Index

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| GET\|HEAD | `/tenant/quotations` | tenant.quotations.index | web, App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, Illuminate\Auth\Middleware\Authenticate:web, App\Http\Middleware\ResolveTenantContext, App\Http\Middleware\EnsureTenantEmailIsVerified, App\Http\Middleware\EnsureTenantSubscriptionActive, App\Http\Middleware\CheckTenantPermission:quotes,view, App\Http\Middleware\EnsureTenantPosMode:general |
| GET\|HEAD | `/tenant/quotes` | tenant.quotes.index | web, App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, Illuminate\Auth\Middleware\Authenticate:web, App\Http\Middleware\ResolveTenantContext, App\Http\Middleware\EnsureTenantEmailIsVerified, App\Http\Middleware\EnsureTenantSubscriptionActive, App\Http\Middleware\CheckTenantPermission:quotes,view, App\Http\Middleware\EnsureTenantPosMode:general |

### Routes: App / Livewire / Tenant / Quotes / Show

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| GET\|HEAD | `/tenant/quotations/{quote}` | tenant.quotations.show | web, App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, Illuminate\Auth\Middleware\Authenticate:web, App\Http\Middleware\ResolveTenantContext, App\Http\Middleware\EnsureTenantEmailIsVerified, App\Http\Middleware\EnsureTenantSubscriptionActive, App\Http\Middleware\CheckTenantPermission:quotes,view, App\Http\Middleware\EnsureTenantPosMode:general |
| GET\|HEAD | `/tenant/quotes/{quote}` | tenant.quotes.show | web, App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, Illuminate\Auth\Middleware\Authenticate:web, App\Http\Middleware\ResolveTenantContext, App\Http\Middleware\EnsureTenantEmailIsVerified, App\Http\Middleware\EnsureTenantSubscriptionActive, App\Http\Middleware\CheckTenantPermission:quotes,view, App\Http\Middleware\EnsureTenantPosMode:general |

### Routes: App / Livewire / Tenant / Repair / Categories

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| GET\|HEAD | `/tenant/repair/categories` | tenant.repair.categories | web, App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, Illuminate\Auth\Middleware\Authenticate:web, App\Http\Middleware\ResolveTenantContext, App\Http\Middleware\EnsureTenantEmailIsVerified, App\Http\Middleware\EnsureTenantSubscriptionActive, App\Http\Middleware\EnsureTenantVertical:repair_technician, App\Http\Middleware\CheckTenantPermission:repair,diagnose |

### Routes: App / Livewire / Tenant / Repair / Dashboard

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| GET\|HEAD | `/tenant/repair` | tenant.repair.dashboard | web, App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, Illuminate\Auth\Middleware\Authenticate:web, App\Http\Middleware\ResolveTenantContext, App\Http\Middleware\EnsureTenantEmailIsVerified, App\Http\Middleware\EnsureTenantSubscriptionActive, App\Http\Middleware\EnsureTenantVertical:repair_technician, App\Http\Middleware\CheckTenantPermission:repair,view |
| GET\|HEAD | `/tenant/repairs` | tenant. | web, App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, Illuminate\Auth\Middleware\Authenticate:web, App\Http\Middleware\ResolveTenantContext, App\Http\Middleware\EnsureTenantEmailIsVerified, App\Http\Middleware\EnsureTenantSubscriptionActive, App\Http\Middleware\EnsureTenantVertical:repair_technician, App\Http\Middleware\CheckTenantPermission:repair,view |

### Routes: App / Livewire / Tenant / Repair / TicketDetail

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| GET\|HEAD | `/tenant/repair/tickets/{ticket}` | tenant.repair.ticket | web, App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, Illuminate\Auth\Middleware\Authenticate:web, App\Http\Middleware\ResolveTenantContext, App\Http\Middleware\EnsureTenantEmailIsVerified, App\Http\Middleware\EnsureTenantSubscriptionActive, App\Http\Middleware\EnsureTenantVertical:repair_technician, App\Http\Middleware\CheckTenantPermission:repair,view |

### Routes: App / Livewire / Tenant / Repair / Tickets

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| GET\|HEAD | `/tenant/repair/tickets` | tenant.repair.tickets | web, App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, Illuminate\Auth\Middleware\Authenticate:web, App\Http\Middleware\ResolveTenantContext, App\Http\Middleware\EnsureTenantEmailIsVerified, App\Http\Middleware\EnsureTenantSubscriptionActive, App\Http\Middleware\EnsureTenantVertical:repair_technician, App\Http\Middleware\CheckTenantPermission:repair,view |
| GET\|HEAD | `/tenant/repairs/tickets` | tenant. | web, App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, Illuminate\Auth\Middleware\Authenticate:web, App\Http\Middleware\ResolveTenantContext, App\Http\Middleware\EnsureTenantEmailIsVerified, App\Http\Middleware\EnsureTenantSubscriptionActive, App\Http\Middleware\EnsureTenantVertical:repair_technician, App\Http\Middleware\CheckTenantPermission:repair,view |

### Routes: App / Livewire / Tenant / Reports / Index

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| GET\|HEAD | `/tenant/reports` | tenant.reports.index | web, App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, Illuminate\Auth\Middleware\Authenticate:web, App\Http\Middleware\ResolveTenantContext, App\Http\Middleware\EnsureTenantEmailIsVerified, App\Http\Middleware\EnsureTenantSubscriptionActive, App\Http\Middleware\CheckTenantPermission:reports,view |
| GET\|HEAD | `/tenant/reports/profit-loss` | tenant.reports.profit-loss | web, App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, Illuminate\Auth\Middleware\Authenticate:web, App\Http\Middleware\ResolveTenantContext, App\Http\Middleware\EnsureTenantEmailIsVerified, App\Http\Middleware\EnsureTenantSubscriptionActive, App\Http\Middleware\CheckTenantPermission:reports,view |
| GET\|HEAD | `/tenant/reports/sales` | tenant.reports.sales | web, App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, Illuminate\Auth\Middleware\Authenticate:web, App\Http\Middleware\ResolveTenantContext, App\Http\Middleware\EnsureTenantEmailIsVerified, App\Http\Middleware\EnsureTenantSubscriptionActive, App\Http\Middleware\CheckTenantPermission:reports,view |

### Routes: App / Livewire / Tenant / Restaurant / Kds

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| GET\|HEAD | `/tenant/restaurant/kds` | tenant.restaurant.kds | web, App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, Illuminate\Auth\Middleware\Authenticate:web, App\Http\Middleware\ResolveTenantContext, App\Http\Middleware\EnsureTenantEmailIsVerified, App\Http\Middleware\EnsureTenantSubscriptionActive, App\Http\Middleware\CheckTenantPermission:pos,view, App\Http\Middleware\EnsureTenantPosMode:restaurant |

### Routes: App / Livewire / Tenant / Restaurant / Pos

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| GET\|HEAD | `/tenant/restaurant/pos` | tenant.restaurant.pos | web, App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, Illuminate\Auth\Middleware\Authenticate:web, App\Http\Middleware\ResolveTenantContext, App\Http\Middleware\EnsureTenantEmailIsVerified, App\Http\Middleware\EnsureTenantSubscriptionActive, App\Http\Middleware\CheckTenantPermission:pos,create, App\Http\Middleware\EnsureTenantPosMode:restaurant |

### Routes: App / Livewire / Tenant / Restaurant / Tables

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| GET\|HEAD | `/tenant/restaurant/tables` | tenant.restaurant.tables | web, App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, Illuminate\Auth\Middleware\Authenticate:web, App\Http\Middleware\ResolveTenantContext, App\Http\Middleware\EnsureTenantEmailIsVerified, App\Http\Middleware\EnsureTenantSubscriptionActive, App\Http\Middleware\CheckTenantPermission:pos,view, App\Http\Middleware\EnsureTenantPosMode:restaurant |

### Routes: App / Livewire / Tenant / Reviews / Index

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| GET\|HEAD | `/tenant/reviews` | tenant.reviews.index | web, App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, Illuminate\Auth\Middleware\Authenticate:web, App\Http\Middleware\ResolveTenantContext, App\Http\Middleware\EnsureTenantEmailIsVerified, App\Http\Middleware\CheckTenantPermission:settings,view |
| GET\|HEAD | `/tenant/settings/reviews` | tenant.settings.reviews | web, App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, Illuminate\Auth\Middleware\Authenticate:web, App\Http\Middleware\ResolveTenantContext, App\Http\Middleware\EnsureTenantEmailIsVerified, App\Http\Middleware\CheckTenantPermission:settings,view |

### Routes: App / Livewire / Tenant / SalesTargets / Index

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| GET\|HEAD | `/tenant/sales-targets` | tenant.sales-targets.index | web, App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, Illuminate\Auth\Middleware\Authenticate:web, App\Http\Middleware\ResolveTenantContext, App\Http\Middleware\EnsureTenantEmailIsVerified, App\Http\Middleware\EnsureTenantSubscriptionActive, App\Http\Middleware\CheckTenantPermission:targets,view |
| GET\|HEAD | `/tenant/targets` | tenant.targets.index | web, App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, Illuminate\Auth\Middleware\Authenticate:web, App\Http\Middleware\ResolveTenantContext, App\Http\Middleware\EnsureTenantEmailIsVerified, App\Http\Middleware\EnsureTenantSubscriptionActive, App\Http\Middleware\CheckTenantPermission:targets,view |

### Routes: App / Livewire / Tenant / Sales / Create

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| GET\|HEAD | `/tenant/sales/create` | tenant.sales.create | web, App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, Illuminate\Auth\Middleware\Authenticate:web, App\Http\Middleware\ResolveTenantContext, App\Http\Middleware\EnsureTenantEmailIsVerified, App\Http\Middleware\EnsureTenantSubscriptionActive, App\Http\Middleware\CheckTenantPermission:pos,create, App\Http\Middleware\EnsureTenantPosMode:general |

### Routes: App / Livewire / Tenant / Sales / Index

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| GET\|HEAD | `/tenant/invoices` | tenant.invoices.index | web, App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, Illuminate\Auth\Middleware\Authenticate:web, App\Http\Middleware\ResolveTenantContext, App\Http\Middleware\EnsureTenantEmailIsVerified, App\Http\Middleware\EnsureTenantSubscriptionActive, App\Http\Middleware\CheckTenantPermission:sales,view |
| GET\|HEAD | `/tenant/sales` | tenant.sales.index | web, App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, Illuminate\Auth\Middleware\Authenticate:web, App\Http\Middleware\ResolveTenantContext, App\Http\Middleware\EnsureTenantEmailIsVerified, App\Http\Middleware\EnsureTenantSubscriptionActive, App\Http\Middleware\CheckTenantPermission:sales,view |

### Routes: App / Livewire / Tenant / Sales / Show

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| GET\|HEAD | `/tenant/invoices/{sale}` | tenant.invoices.show | web, App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, Illuminate\Auth\Middleware\Authenticate:web, App\Http\Middleware\ResolveTenantContext, App\Http\Middleware\EnsureTenantEmailIsVerified, App\Http\Middleware\EnsureTenantSubscriptionActive, App\Http\Middleware\CheckTenantPermission:sales,view |
| GET\|HEAD | `/tenant/sales/{sale}` | tenant.sales.show | web, App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, Illuminate\Auth\Middleware\Authenticate:web, App\Http\Middleware\ResolveTenantContext, App\Http\Middleware\EnsureTenantEmailIsVerified, App\Http\Middleware\EnsureTenantSubscriptionActive, App\Http\Middleware\CheckTenantPermission:sales,view |

### Routes: App / Livewire / Tenant / Salon / Calendar

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| GET\|HEAD | `/tenant/salon` | tenant.salon.calendar | web, App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, Illuminate\Auth\Middleware\Authenticate:web, App\Http\Middleware\ResolveTenantContext, App\Http\Middleware\EnsureTenantEmailIsVerified, App\Http\Middleware\EnsureTenantSubscriptionActive, App\Http\Middleware\EnsureTenantVertical:service_booking, App\Http\Middleware\CheckTenantPermission:service_orders,view |

### Routes: App / Livewire / Tenant / Salon / ServiceCatalog

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| GET\|HEAD | `/tenant/salon/services` | tenant.salon.services | web, App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, Illuminate\Auth\Middleware\Authenticate:web, App\Http\Middleware\ResolveTenantContext, App\Http\Middleware\EnsureTenantEmailIsVerified, App\Http\Middleware\EnsureTenantSubscriptionActive, App\Http\Middleware\EnsureTenantVertical:service_booking, App\Http\Middleware\CheckTenantPermission:products,view |

### Routes: App / Livewire / Tenant / Salon / Stylists

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| GET\|HEAD | `/tenant/salon/stylists` | tenant.salon.stylists | web, App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, Illuminate\Auth\Middleware\Authenticate:web, App\Http\Middleware\ResolveTenantContext, App\Http\Middleware\EnsureTenantEmailIsVerified, App\Http\Middleware\EnsureTenantSubscriptionActive, App\Http\Middleware\EnsureTenantVertical:service_booking, App\Http\Middleware\CheckTenantPermission:service_orders,view |

### Routes: App / Livewire / Tenant / ServiceOrders / Index

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| GET\|HEAD | `/tenant/service-orders` | tenant.service-orders.index | web, App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, Illuminate\Auth\Middleware\Authenticate:web, App\Http\Middleware\ResolveTenantContext, App\Http\Middleware\EnsureTenantEmailIsVerified, App\Http\Middleware\EnsureTenantSubscriptionActive, App\Http\Middleware\CheckTenantPermission:service_orders,view, App\Http\Middleware\EnsureTenantPosMode:general |

### Routes: App / Livewire / Tenant / Settings / Index

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| GET\|HEAD | `/tenant/settings` | tenant.settings.index | web, App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, Illuminate\Auth\Middleware\Authenticate:web, App\Http\Middleware\ResolveTenantContext, App\Http\Middleware\EnsureTenantEmailIsVerified, App\Http\Middleware\CheckTenantPermission:settings,view |
| GET\|HEAD | `/tenant/settings/api` | tenant.settings.api | web, App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, Illuminate\Auth\Middleware\Authenticate:web, App\Http\Middleware\ResolveTenantContext, App\Http\Middleware\EnsureTenantEmailIsVerified, App\Http\Middleware\CheckTenantPermission:settings,view |
| GET\|HEAD | `/tenant/settings/financial` | tenant.settings.financial | web, App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, Illuminate\Auth\Middleware\Authenticate:web, App\Http\Middleware\ResolveTenantContext, App\Http\Middleware\EnsureTenantEmailIsVerified, App\Http\Middleware\CheckTenantPermission:settings,view |
| GET\|HEAD | `/tenant/settings/integrations` | tenant.settings.integrations | web, App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, Illuminate\Auth\Middleware\Authenticate:web, App\Http\Middleware\ResolveTenantContext, App\Http\Middleware\EnsureTenantEmailIsVerified, App\Http\Middleware\CheckTenantPermission:settings,view |
| GET\|HEAD | `/tenant/settings/mode` | tenant.settings.mode | web, App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, Illuminate\Auth\Middleware\Authenticate:web, App\Http\Middleware\ResolveTenantContext, App\Http\Middleware\EnsureTenantEmailIsVerified, App\Http\Middleware\CheckTenantPermission:settings,view |
| GET\|HEAD | `/tenant/settings/navigation` | tenant.settings.navigation | web, App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, Illuminate\Auth\Middleware\Authenticate:web, App\Http\Middleware\ResolveTenantContext, App\Http\Middleware\EnsureTenantEmailIsVerified, App\Http\Middleware\CheckTenantPermission:settings,view |
| GET\|HEAD | `/tenant/settings/payments` | tenant.settings.payments | web, App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, Illuminate\Auth\Middleware\Authenticate:web, App\Http\Middleware\ResolveTenantContext, App\Http\Middleware\EnsureTenantEmailIsVerified, App\Http\Middleware\CheckTenantPermission:settings,view |
| GET\|HEAD | `/tenant/settings/profile` | tenant.settings.profile | web, App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, Illuminate\Auth\Middleware\Authenticate:web, App\Http\Middleware\ResolveTenantContext, App\Http\Middleware\EnsureTenantEmailIsVerified, App\Http\Middleware\CheckTenantPermission:settings,view |
| GET\|HEAD | `/tenant/settings/receipts` | tenant.settings.receipts | web, App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, Illuminate\Auth\Middleware\Authenticate:web, App\Http\Middleware\ResolveTenantContext, App\Http\Middleware\EnsureTenantEmailIsVerified, App\Http\Middleware\CheckTenantPermission:settings,view |
| GET\|HEAD | `/tenant/settings/storefront` | tenant.settings.storefront | web, App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, Illuminate\Auth\Middleware\Authenticate:web, App\Http\Middleware\ResolveTenantContext, App\Http\Middleware\EnsureTenantEmailIsVerified, App\Http\Middleware\CheckTenantPermission:settings,view |
| GET\|HEAD | `/tenant/settings/taxes` | tenant.settings.taxes | web, App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, Illuminate\Auth\Middleware\Authenticate:web, App\Http\Middleware\ResolveTenantContext, App\Http\Middleware\EnsureTenantEmailIsVerified, App\Http\Middleware\CheckTenantPermission:settings,view |

### Routes: App / Livewire / Tenant / Storefront / MenuBuilderComponent

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| GET\|HEAD | `/tenant/settings/storefront/menus` | tenant.settings.storefront.menus | web, App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, Illuminate\Auth\Middleware\Authenticate:web, App\Http\Middleware\ResolveTenantContext, App\Http\Middleware\EnsureTenantEmailIsVerified, App\Http\Middleware\EnsureTenantExtension:ecommerce_storefront, App\Http\Middleware\CheckTenantPermission:storefront,menus.manage |
| GET\|HEAD | `/tenant/storefront/menus` | tenant.storefront.menus | web, App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, Illuminate\Auth\Middleware\Authenticate:web, App\Http\Middleware\ResolveTenantContext, App\Http\Middleware\EnsureTenantEmailIsVerified, App\Http\Middleware\EnsureTenantExtension:ecommerce_storefront, App\Http\Middleware\CheckTenantPermission:storefront,menus.manage |

### Routes: App / Livewire / Tenant / Suppliers / Index

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| GET\|HEAD | `/tenant/suppliers` | tenant.suppliers.index | web, App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, Illuminate\Auth\Middleware\Authenticate:web, App\Http\Middleware\ResolveTenantContext, App\Http\Middleware\EnsureTenantEmailIsVerified, App\Http\Middleware\EnsureTenantSubscriptionActive, App\Http\Middleware\CheckTenantPermission:suppliers,view |

### Routes: App / Livewire / Tenant / Units / Index

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| GET\|HEAD | `/tenant/units` | tenant.units.index | web, App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, Illuminate\Auth\Middleware\Authenticate:web, App\Http\Middleware\ResolveTenantContext, App\Http\Middleware\EnsureTenantEmailIsVerified, App\Http\Middleware\EnsureTenantSubscriptionActive, App\Http\Middleware\CheckTenantPermission:units,view |

### Routes: App / Livewire / Tenant / Users / Index

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| GET\|HEAD | `/tenant/users` | tenant.users.index | web, App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, Illuminate\Auth\Middleware\Authenticate:web, App\Http\Middleware\ResolveTenantContext, App\Http\Middleware\EnsureTenantEmailIsVerified, App\Http\Middleware\EnsureTenantSubscriptionActive, App\Http\Middleware\CheckTenantPermission:users,view |

### Routes: App / Livewire / Tenant / Users / Permissions

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| GET\|HEAD | `/tenant/users/permissions` | tenant.users.permissions | web, App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, Illuminate\Auth\Middleware\Authenticate:web, App\Http\Middleware\ResolveTenantContext, App\Http\Middleware\EnsureTenantEmailIsVerified, App\Http\Middleware\EnsureTenantSubscriptionActive, App\Http\Middleware\CheckTenantPermission:users,view |
| GET\|HEAD | `/tenant/users/{user}/permissions` | tenant.users.user-permissions | web, App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, Illuminate\Auth\Middleware\Authenticate:web, App\Http\Middleware\ResolveTenantContext, App\Http\Middleware\EnsureTenantEmailIsVerified, App\Http\Middleware\EnsureTenantSubscriptionActive, App\Http\Middleware\CheckTenantPermission:users,view |

### Routes: Closure

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| GET\|HEAD | `/api/app/views/{subpath}` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications |
| GET\|HEAD | `/api/tenant/views/{subpath}` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications |
| GET\|HEAD | `/demo-branding/{asset}` |  | web, App\Http\Middleware\EnsureAppIsInstalled |
| GET\|HEAD | `/desktop/session/{token}` | desktop.session | web, App\Http\Middleware\EnsureAppIsInstalled |
| GET\|HEAD | `/livewire-aaf55318/css/{component}.css` |  |  |
| GET\|HEAD | `/livewire-aaf55318/css/{component}.global.css` |  |  |
| GET\|HEAD | `/livewire-aaf55318/js/{component}.js` |  |  |
| GET\|HEAD | `/login` | login | web, App\Http\Middleware\EnsureAppIsInstalled |
| POST | `/logout` | logout | web, App\Http\Middleware\EnsureAppIsInstalled |
| GET\|HEAD | `/pos-standalone` | pos.standalone | web |
| GET\|HEAD | `/register` | register | web, App\Http\Middleware\EnsureAppIsInstalled |
| GET\|HEAD | `/sales/invoices` |  | web |
| OPTIONS | `/storage/{path}` |  | web |
| GET\|HEAD | `/storage/{path}` | storage.local |  |
| PUT | `/storage/{path}` | storage.local.upload |  |
| GET\|HEAD | `/superadmin/branding` | superadmin.branding.index | web, App\Http\Middleware\EnsureAppIsInstalled, Illuminate\Auth\Middleware\Authenticate:platform_web, App\Http\Middleware\PeriodicLicenseCheck, App\Http\Middleware\PreventDemoModifications |
| POST | `/superadmin/logout` | superadmin.logout | web, App\Http\Middleware\EnsureAppIsInstalled, Illuminate\Auth\Middleware\Authenticate:platform_web |
| GET\|HEAD | `/tenant/demo-branding/{asset}` |  | web, App\Http\Middleware\EnsureAppIsInstalled |
| GET\|HEAD | `/tenant/invoices/create` |  | web |
| POST | `/tenant/logout` | tenant.logout | web, App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, Illuminate\Auth\Middleware\Authenticate:web |
| GET\|HEAD | `/tenant/quotations/{id}` |  | web |
| POST | `/tenant/quotations/{id}/dispatch` |  | web |
| GET\|HEAD | `/tenant/quotations/{id}/pdf` |  | web |
| GET\|HEAD | `/tenant/quotations/{id}/preview` |  | web |
| GET\|HEAD | `/tenant/quotations/{id}/preview-sheet` |  | web |
| GET\|HEAD | `/tenant/quotations/{id}/send` |  | web |
| GET\|HEAD | `/tenant/quotations/{id}/send-sheet` |  | web |
| GET\|HEAD | `/tenant/views/invoices/create` |  | web |
| GET\|HEAD | `/tenant/views/quotations/create` |  | web |
| GET\|HEAD | `/tenant/views/quotations/{id}` |  | web |
| GET\|HEAD | `/tenant/views/quotations/{id}/pdf` |  | web |
| GET\|HEAD | `/tenant/views/quotations/{id}/preview` |  | web |
| GET\|HEAD | `/tenant/views/quotations/{id}/preview-sheet` |  | web |
| GET\|HEAD | `/tenant/views/quotations/{id}/send` |  | web |
| GET\|HEAD | `/tenant/views/quotations/{id}/send-sheet` |  | web |
| GET\|HEAD | `/up` |  |  |
| GET\|HEAD | `/v1/tenant/invoices` |  | web |
| GET\|HEAD | `/v1/tenant/sales` |  | web |

### Routes: Illuminate / Routing / RedirectController

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| GET\|HEAD\|POST\|PUT\|PATCH\|DELETE\|OPTIONS | `/admin/settings/general` |  | web |
| GET\|HEAD\|POST\|PUT\|PATCH\|DELETE\|OPTIONS | `/admin/settings/regional` |  | web |
| GET\|HEAD\|POST\|PUT\|PATCH\|DELETE\|OPTIONS | `/install` | install. | web, App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\EnsureNotInstalled |
| GET\|HEAD\|POST\|PUT\|PATCH\|DELETE\|OPTIONS | `/tenant/settings-redirect` | tenant.settings | web, App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, Illuminate\Auth\Middleware\Authenticate:web, App\Http\Middleware\ResolveTenantContext, App\Http\Middleware\EnsureTenantEmailIsVerified |

### Routes: Livewire / Features / SupportFileUploads / FilePreviewController@handle

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| GET\|HEAD | `/livewire-aaf55318/preview-file/{filename}` | livewire.preview-file | web |

### Routes: Livewire / Features / SupportFileUploads / FileUploadController@handle

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| POST | `/livewire-aaf55318/upload-file` | livewire.upload-file | web, Illuminate\Routing\Middleware\ThrottleRequests:60,1 |

### Routes: Livewire / Mechanisms / FrontendAssets / FrontendAssets@cspMaps

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| GET\|HEAD | `/livewire-aaf55318/livewire.csp.min.js.map` |  |  |

### Routes: Livewire / Mechanisms / FrontendAssets / FrontendAssets@maps

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| GET\|HEAD | `/livewire-aaf55318/livewire.min.js.map` |  |  |

### Routes: Livewire / Mechanisms / FrontendAssets / FrontendAssets@returnJavaScriptAsFile

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| GET\|HEAD | `/livewire-aaf55318/livewire.js` |  |  |

### Routes: Livewire / Mechanisms / HandleRequests / HandleRequests@handleUpdate

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| POST | `/livewire-aaf55318/update` | default-livewire.update | web, Livewire\Mechanisms\HandleRequests\RequireLivewireHeaders |

### Routes: Modules / leadmanagement / Http / Controllers / LeadModuleController@activitiesStore

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| POST | `/api/tenant/lead-module/activities` |  | App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\EnsureTenantExtension:leadmanagement |

### Routes: Modules / leadmanagement / Http / Controllers / LeadModuleController@activitiesView

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| GET\|HEAD | `/api/tenant/lead-module/views/activities` |  | App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\EnsureTenantExtension:leadmanagement |

### Routes: Modules / leadmanagement / Http / Controllers / LeadModuleController@activityComplete

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| POST | `/api/tenant/lead-module/activities/{id}/complete` |  | App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\EnsureTenantExtension:leadmanagement |

### Routes: Modules / leadmanagement / Http / Controllers / LeadModuleController@createLeadView

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| GET\|HEAD | `/api/tenant/lead-module/views/create-lead` |  | App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\EnsureTenantExtension:leadmanagement |

### Routes: Modules / leadmanagement / Http / Controllers / LeadModuleController@createSchema

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| GET\|HEAD | `/api/tenant/leads/schema` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:leads,view, App\Http\Middleware\EnsureTenantExtension:leadmanagement |
| GET\|HEAD | `/api/v1/tenant/leads/schema` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:leads,view, App\Http\Middleware\EnsureTenantExtension:leadmanagement |

### Routes: Modules / leadmanagement / Http / Controllers / LeadModuleController@customerSearch

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| GET\|HEAD | `/api/tenant/lead-module/customers/search` |  | App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\EnsureTenantExtension:leadmanagement |

### Routes: Modules / leadmanagement / Http / Controllers / LeadModuleController@dashboard

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| GET\|HEAD | `/api/tenant/lead-module/views/dashboard` |  | App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\EnsureTenantExtension:leadmanagement |

### Routes: Modules / leadmanagement / Http / Controllers / LeadModuleController@followupsEndpoint

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| GET\|HEAD | `/api/tenant/lead-module/leads/followups` |  | App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\EnsureTenantExtension:leadmanagement |

### Routes: Modules / leadmanagement / Http / Controllers / LeadModuleController@leadAddReminder

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| POST | `/api/tenant/lead-module/leads/{id}/reminders` |  | App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\EnsureTenantExtension:leadmanagement |
| POST | `/api/tenant/leads/{id}/reminders` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:leads,edit, App\Http\Middleware\EnsureTenantExtension:leadmanagement |
| POST | `/api/v1/tenant/leads/{id}/reminders` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:leads,edit, App\Http\Middleware\EnsureTenantExtension:leadmanagement |

### Routes: Modules / leadmanagement / Http / Controllers / LeadModuleController@leadConvert

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| POST | `/api/tenant/lead-module/leads/{id}/convert` |  | App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\EnsureTenantExtension:leadmanagement |
| POST | `/api/tenant/leads/{id}/convert` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:leads,convert, App\Http\Middleware\EnsureTenantExtension:leadmanagement |
| POST | `/api/v1/tenant/leads/{id}/convert` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:leads,convert, App\Http\Middleware\EnsureTenantExtension:leadmanagement |

### Routes: Modules / leadmanagement / Http / Controllers / LeadModuleController@leadConvertToInvoice

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| POST | `/api/tenant/lead-module/leads/{id}/convert-to-invoice` |  | App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\EnsureTenantExtension:leadmanagement |
| POST | `/api/tenant/leads/{id}/convert-to-invoice` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:leads,convert, App\Http\Middleware\EnsureTenantExtension:leadmanagement |
| POST | `/api/v1/tenant/leads/{id}/convert-to-invoice` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:leads,convert, App\Http\Middleware\EnsureTenantExtension:leadmanagement |

### Routes: Modules / leadmanagement / Http / Controllers / LeadModuleController@leadDetail

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| GET\|HEAD | `/api/tenant/lead-module/views/lead-detail` |  | App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\EnsureTenantExtension:leadmanagement |
| GET\|HEAD | `/api/tenant/lead-module/views/leads/{id}` |  | App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\EnsureTenantExtension:leadmanagement |

### Routes: Modules / leadmanagement / Http / Controllers / LeadModuleController@leadStatus

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| POST | `/api/tenant/lead-module/leads/{id}/status` |  | App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\EnsureTenantExtension:leadmanagement |

### Routes: Modules / leadmanagement / Http / Controllers / LeadModuleController@leadsIndex

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| GET\|HEAD | `/api/tenant/lead-module/leads` |  | App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\EnsureTenantExtension:leadmanagement |
| GET\|HEAD | `/api/tenant/leads` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:leads,view, App\Http\Middleware\EnsureTenantExtension:leadmanagement |
| GET\|HEAD | `/api/v1/tenant/leads` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:leads,view, App\Http\Middleware\EnsureTenantExtension:leadmanagement |

### Routes: Modules / leadmanagement / Http / Controllers / LeadModuleController@leadsListEndpoint

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| GET\|HEAD | `/api/tenant/lead-module/leads/list` |  | App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\EnsureTenantExtension:leadmanagement |

### Routes: Modules / leadmanagement / Http / Controllers / LeadModuleController@leadsShow

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| GET\|HEAD | `/api/tenant/lead-module/leads/{id}` |  | App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\EnsureTenantExtension:leadmanagement |
| GET\|HEAD | `/api/tenant/leads/{id}` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:leads,view, App\Http\Middleware\EnsureTenantExtension:leadmanagement |
| GET\|HEAD | `/api/v1/tenant/leads/{id}` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:leads,view, App\Http\Middleware\EnsureTenantExtension:leadmanagement |

### Routes: Modules / leadmanagement / Http / Controllers / LeadModuleController@leadsStore

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| POST | `/api/tenant/lead-module/leads` |  | App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\EnsureTenantExtension:leadmanagement |
| POST | `/api/tenant/leads` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:leads,create, App\Http\Middleware\EnsureTenantExtension:leadmanagement |
| POST | `/api/v1/tenant/leads` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:leads,create, App\Http\Middleware\EnsureTenantExtension:leadmanagement |

### Routes: Modules / leadmanagement / Http / Controllers / LeadModuleController@leadsUpdate

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| PUT\|PATCH | `/api/tenant/lead-module/leads/{id}` |  | App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\EnsureTenantExtension:leadmanagement |
| PUT\|PATCH | `/api/tenant/leads/{id}` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:leads,edit, App\Http\Middleware\EnsureTenantExtension:leadmanagement |
| PUT\|PATCH | `/api/v1/tenant/leads/{id}` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:leads,edit, App\Http\Middleware\EnsureTenantExtension:leadmanagement |

### Routes: Modules / leadmanagement / Http / Controllers / LeadModuleController@leadsView

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| GET\|HEAD | `/api/tenant/lead-module/views/leads` |  | App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\EnsureTenantExtension:leadmanagement |

### Routes: Modules / leadmanagement / Http / Controllers / LeadModuleController@salesReps

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| GET\|HEAD | `/api/tenant/lead-module/staff/sales-reps` |  | App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\EnsureTenantExtension:leadmanagement |
| GET\|HEAD | `/api/tenant/staff/sales-reps` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:users,view, App\Http\Middleware\EnsureTenantExtension:leadmanagement |
| GET\|HEAD | `/api/v1/tenant/staff/sales-reps` |  | App\Http\Middleware\EnsureAppIsInstalled, App\Http\Middleware\CheckMaintenanceMode, App\Http\Middleware\SetLocale, App\Http\Middleware\ClearStoreContext, App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\ResolveStoreContext, App\Http\Middleware\PreventDemoModifications, App\Http\Middleware\CheckTenantApiUserPermission:users,view, App\Http\Middleware\EnsureTenantExtension:leadmanagement |

### Routes: Modules / leadmanagement / Http / Controllers / LeadModuleController@sourcesStore

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| POST | `/api/tenant/lead-module/sources` |  | App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\EnsureTenantExtension:leadmanagement |

### Routes: Modules / leadmanagement / Http / Controllers / LeadModuleController@sourcesView

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| GET\|HEAD | `/api/tenant/lead-module/views/sources` |  | App\Http\Middleware\AuthenticateTenantApi, App\Http\Middleware\EnsureTenantExtension:leadmanagement |

### Routes: Modules / pharmacy / Http / Controllers / PharmacyModuleController@batchesStore

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| POST | `/api/tenant/pharmacy-module/batches` |  | App\Http\Middleware\AuthenticateTenantApi |

### Routes: Modules / pharmacy / Http / Controllers / PharmacyModuleController@batchesView

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| GET\|HEAD | `/api/tenant/pharmacy-module/views/batches` |  | App\Http\Middleware\AuthenticateTenantApi |

### Routes: Modules / pharmacy / Http / Controllers / PharmacyModuleController@dashboard

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| GET\|HEAD | `/api/tenant/pharmacy-module/views/dashboard` |  | App\Http\Middleware\AuthenticateTenantApi |

### Routes: Modules / pharmacy / Http / Controllers / PharmacyModuleController@prescriptionDetail

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| GET\|HEAD | `/api/tenant/pharmacy-module/views/prescription-detail` |  | App\Http\Middleware\AuthenticateTenantApi |

### Routes: Modules / pharmacy / Http / Controllers / PharmacyModuleController@prescriptionDispense

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| POST | `/api/tenant/pharmacy-module/prescriptions/{id}/dispense` |  | App\Http\Middleware\AuthenticateTenantApi |

### Routes: Modules / pharmacy / Http / Controllers / PharmacyModuleController@prescriptionsStore

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| POST | `/api/tenant/pharmacy-module/prescriptions` |  | App\Http\Middleware\AuthenticateTenantApi |

### Routes: Modules / pharmacy / Http / Controllers / PharmacyModuleController@prescriptionsView

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| GET\|HEAD | `/api/tenant/pharmacy-module/views/prescriptions` |  | App\Http\Middleware\AuthenticateTenantApi |

### Routes: Modules / repairtechnician / Http / Controllers / RepairModuleController@categoriesStore

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| POST | `/api/tenant/repair-module/categories` |  | App\Http\Middleware\AuthenticateTenantApi |

### Routes: Modules / repairtechnician / Http / Controllers / RepairModuleController@categoriesView

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| GET\|HEAD | `/api/tenant/repair-module/views/categories` |  | App\Http\Middleware\AuthenticateTenantApi |

### Routes: Modules / repairtechnician / Http / Controllers / RepairModuleController@dashboard

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| GET\|HEAD | `/api/tenant/repair-module/views/dashboard` |  | App\Http\Middleware\AuthenticateTenantApi |

### Routes: Modules / repairtechnician / Http / Controllers / RepairModuleController@ticketDetail

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| GET\|HEAD | `/api/tenant/repair-module/views/ticket-detail` |  | App\Http\Middleware\AuthenticateTenantApi |

### Routes: Modules / repairtechnician / Http / Controllers / RepairModuleController@ticketStatus

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| POST | `/api/tenant/repair-module/tickets/{id}/status` |  | App\Http\Middleware\AuthenticateTenantApi |

### Routes: Modules / repairtechnician / Http / Controllers / RepairModuleController@ticketsStore

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| POST | `/api/tenant/repair-module/tickets` |  | App\Http\Middleware\AuthenticateTenantApi |

### Routes: Modules / repairtechnician / Http / Controllers / RepairModuleController@ticketsView

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| GET\|HEAD | `/api/tenant/repair-module/views/tickets` |  | App\Http\Middleware\AuthenticateTenantApi |

### Routes: Modules / salon / Http / Controllers / SalonModuleController@appointmentDetail

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| GET\|HEAD | `/api/tenant/salon-module/views/appointment-detail` |  | App\Http\Middleware\AuthenticateTenantApi |

### Routes: Modules / salon / Http / Controllers / SalonModuleController@appointmentStatus

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| POST | `/api/tenant/salon-module/appointments/{id}/status` |  | App\Http\Middleware\AuthenticateTenantApi |

### Routes: Modules / salon / Http / Controllers / SalonModuleController@appointmentsStore

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| POST | `/api/tenant/salon-module/appointments` |  | App\Http\Middleware\AuthenticateTenantApi |

### Routes: Modules / salon / Http / Controllers / SalonModuleController@appointmentsView

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| GET\|HEAD | `/api/tenant/salon-module/views/appointments` |  | App\Http\Middleware\AuthenticateTenantApi |

### Routes: Modules / salon / Http / Controllers / SalonModuleController@dashboard

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| GET\|HEAD | `/api/tenant/salon-module/views/dashboard` |  | App\Http\Middleware\AuthenticateTenantApi |

### Routes: Modules / salon / Http / Controllers / SalonModuleController@servicesStore

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| POST | `/api/tenant/salon-module/services` |  | App\Http\Middleware\AuthenticateTenantApi |

### Routes: Modules / salon / Http / Controllers / SalonModuleController@servicesView

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| GET\|HEAD | `/api/tenant/salon-module/views/services` |  | App\Http\Middleware\AuthenticateTenantApi |

### Routes: Modules / salon / Http / Controllers / SalonModuleController@stylistsStore

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| POST | `/api/tenant/salon-module/stylists` |  | App\Http\Middleware\AuthenticateTenantApi |

### Routes: Modules / salon / Http / Controllers / SalonModuleController@stylistsView

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| GET\|HEAD | `/api/tenant/salon-module/views/stylists` |  | App\Http\Middleware\AuthenticateTenantApi |

### Routes: Native / Desktop / Http / Controllers / CreateSecurityCookieController

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| GET\|HEAD | `/_native/api/cookie` |  | Native\Desktop\Http\Middleware\OptionalNightwatchNever |

### Routes: Native / Desktop / Http / Controllers / DispatchEventFromAppController

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| POST | `/_native/api/events` |  | Native\Desktop\Http\Middleware\OptionalNightwatchNever, Native\Desktop\Http\Middleware\PreventRegularBrowserAccess |

### Routes: Native / Desktop / Http / Controllers / NativeAppBootedController

| Method | URI | Name | Middleware |
| --- | --- | --- | --- |
| POST | `/_native/api/booted` |  | Native\Desktop\Http\Middleware\OptionalNightwatchNever, Native\Desktop\Http\Middleware\PreventRegularBrowserAccess |

## Public source actions and controls

### Source actions: app/Casts/SafeEncryptedArray.php

Public actions/helpers: `get`, `set`.

### Source actions: app/Casts/SafeEncryptedString.php

Public actions/helpers: `get`, `set`.

### Source actions: app/Console/Commands/ApplyLandingReferenceDesignCommand.php

Public actions/helpers: `handle`.

### Source actions: app/Console/Commands/CheckLicenseStatusCommand.php

Public actions/helpers: `handle`.

### Source actions: app/Console/Commands/DispatchAutomatedReminders.php

Public actions/helpers: `handle`.

### Source actions: app/Console/Commands/DispatchScheduledNotifications.php

Public actions/helpers: `handle`.

### Source actions: app/Console/Commands/DispatchVerticalReminders.php

Public actions/helpers: `handle`.

### Source actions: app/Console/Commands/LedgerBackfillCommand.php

Public actions/helpers: `handle`.

### Source actions: app/Console/Commands/SeedLandingContentCommand.php

Public actions/helpers: `handle`.

### Source actions: app/Console/Commands/SendRepairRemindersCommand.php

Public actions/helpers: `handle`.

### Source actions: app/Console/Commands/SetupLandingMatchingColorsCommand.php

Public actions/helpers: `handle`.

### Source actions: app/Events/TenantRegistered.php

Public actions/helpers: `__construct`.

### Source actions: app/Http/Controllers/Api/AppPreferenceController.php

Public actions/helpers: `updatePreferences`, `getPreferences`.

### Source actions: app/Http/Controllers/Api/CustomerController.php

Public actions/helpers: `search`.

### Source actions: app/Http/Controllers/Api/DashboardController.php

Public actions/helpers: `__construct`, `show`, `summary`, `salesChart`.

### Source actions: app/Http/Controllers/Api/DispatchController.php

Public actions/helpers: `dispatchSms`, `dispatchEmail`, `dispatchDocument`.

### Source actions: app/Http/Controllers/Api/DocumentActionController.php

Public actions/helpers: `actionsSheet`.

### Source actions: app/Http/Controllers/Api/DocumentDispatchController.php

Public actions/helpers: `getDispatchOptions`, `getEnabledChannels`, `dispatchDocument`, `dispatchKot`.

### Source actions: app/Http/Controllers/Api/DocumentPreviewController.php

Public actions/helpers: `previewModal`, `renderHtml`.

### Source actions: app/Http/Controllers/Api/InvoiceController.php

Public actions/helpers: `index`, `createSchema`, `store`, `actionsSheet`.

### Source actions: app/Http/Controllers/Api/InvoicePreviewController.php

Public actions/helpers: `previewSheet`.

### Source actions: app/Http/Controllers/Api/LeadController.php

Public actions/helpers: `middleware`, `index`, `showSchema`, `show`, `createSchema`, `getCaptureLeadSchema`, `captureSchema`, `list`, `leadsList`, `leadsListEndpoint`, `leadsIndex`, `followups`, `buildLeadListComponents`.

### Source actions: app/Http/Controllers/Api/LeadReminderController.php

Public actions/helpers: `middleware`, `store`, `index`.

### Source actions: app/Http/Controllers/Api/LicenseActivationController.php

Public actions/helpers: `activate`.

### Source actions: app/Http/Controllers/Api/NavigationController.php

Public actions/helpers: `getDrawerMenu`, `getDrawerMenuComponents`, `formatDrawerItem`, `getDrawerNavigation`.

### Source actions: app/Http/Controllers/Api/NavigationMenuController.php

Public actions/helpers: `saveMenuSettings`, `save`, `store`, `getDrawerMenu`, `formatCustomMenuComponents`, `buildComponentsFromFlatItems`, `formatDrawerItem`, `getDefaultMenuComponents`.

### Source actions: app/Http/Controllers/Api/NotificationController.php

Public actions/helpers: `__construct`, `feed`, `dismiss`, `clearAll`, `unreadCount`.

### Source actions: app/Http/Controllers/Api/QuotationController.php

Public actions/helpers: `createModal`, `createSchema`, `store`, `showSchema`, `previewView`, `sendView`, `previewSheet`, `sendSheet`, `actionsSheet`, `dispatchQuotation`.

### Source actions: app/Http/Controllers/Api/ReceivablesController.php

Public actions/helpers: `reminderSheet`.

### Source actions: app/Http/Controllers/Api/RolePermissionController.php

Public actions/helpers: `resolveTenantEnabledModuleSlugs`, `getTenantEnabledModuleSlugs`, `getFilteredModulesForTenant`, `getPermissionsSchema`.

### Source actions: app/Http/Controllers/Api/Tenant/CouponApiController.php

Public actions/helpers: `index`, `store`, `show`, `update`, `destroy`.

### Source actions: app/Http/Controllers/Api/Tenant/FaqApiController.php

Public actions/helpers: `index`, `store`, `show`, `update`, `destroy`.

### Source actions: app/Http/Controllers/Api/Tenant/SalesController.php

Public actions/helpers: `index`.

### Source actions: app/Http/Controllers/Api/Tenant/StoreController.php

Public actions/helpers: `index`, `store`, `update`, `switch`, `management`, `webStore`, `webUpdate`, `webSwitch`, `staff`, `assignStaff`.

### Source actions: app/Http/Controllers/Api/Tenant/StorefrontSettingsController.php

Public actions/helpers: `getBannerAuth`, `updateBannerAuth`, `getPaymentGateways`, `updatePaymentGateways`, `getDomainConfig`, `updateDomainConfig`.

### Source actions: app/Http/Controllers/Api/TenantSettingsController.php

Public actions/helpers: `saveSmsCredentials`, `sendTestSms`, `updatePreferences`.

### Source actions: app/Http/Controllers/Api/UnifiedDispatchController.php

Public actions/helpers: `dispatch`, `batchDispatch`.

### Source actions: app/Http/Controllers/Api/V1/AiImageApiController.php

Public actions/helpers: `availability`, `generate`.

### Source actions: app/Http/Controllers/Api/V1/ApiIntegrationsController.php

Public actions/helpers: `__construct`, `index`, `saveChannel`, `testChannel`, `dispatchDocument`, `regenerateApiKey`.

### Source actions: app/Http/Controllers/Api/V1/AppBootstrapController.php

Public actions/helpers: `bootstrap`, `updateNav`, `switchMode`.

### Source actions: app/Http/Controllers/Api/V1/AuthApiController.php

Public actions/helpers: `checkSubdomain`, `register`, `verifyEmailOtp`, `resendOtp`.

### Source actions: app/Http/Controllers/Api/V1/CashRegisterApiController.php

Public actions/helpers: `current`, `status`, `open`, `openRegister`, `recordTransaction`, `close`, `closeRegister`, `history`, `show`.

### Source actions: app/Http/Controllers/Api/V1/CatalogAdminApiController.php

Public actions/helpers: `categoriesIndex`, `categoriesStore`, `categoriesUpdate`, `categoriesDestroy`, `brandsIndex`, `brandsStore`, `brandsUpdate`, `brandsDestroy`, `unitsIndex`, `unitsStore`, `unitsUpdate`, `unitsDestroy`, `suppliersIndex`, `suppliersStore`, `suppliersUpdate`, `suppliersDestroy`.

### Source actions: app/Http/Controllers/Api/V1/CatalogApiController.php

Public actions/helpers: `index`, `store`, `destroy`.

### Source actions: app/Http/Controllers/Api/V1/ConsignmentApiController.php

Public actions/helpers: `index`, `show`, `store`, `dispatch`, `reconcile`, `finalize`, `destroy`.

### Source actions: app/Http/Controllers/Api/V1/DeviceApiController.php

Public actions/helpers: `index`, `revoke`.

### Source actions: app/Http/Controllers/Api/V1/EcommerceWebhookController.php

Public actions/helpers: `handleOrders`.

### Source actions: app/Http/Controllers/Api/V1/LandingApiController.php

Public actions/helpers: `show`, `submitContact`, `plans`, `planDetail`.

### Source actions: app/Http/Controllers/Api/V1/LanguageApiController.php

Public actions/helpers: `index`, `translations`, `appTranslations`, `setDefault`.

### Source actions: app/Http/Controllers/Api/V1/PayablesApiController.php

Public actions/helpers: `index`, `store`, `update`, `destroy`, `pay`.

### Source actions: app/Http/Controllers/Api/V1/PermissionApiController.php

Public actions/helpers: `show`, `update`.

### Source actions: app/Http/Controllers/Api/V1/PharmacyApiController.php

Public actions/helpers: `search`, `checkout`, `batchBarcode`, `batchesIndex`, `batchesStore`, `batchesAdjust`, `batchesReturn`, `prescriptionsIndex`, `prescriptionsStore`, `prescriptionsDispense`, `prescriptionCheckoutSheet`, `prescriptionCheckout`, `batchSheet`, `checkoutSheet`.

### Source actions: app/Http/Controllers/Api/V1/PosDesktopSyncController.php

Public actions/helpers: `__construct`, `pull`, `push`.

### Source actions: app/Http/Controllers/Api/V1/PosSyncApiController.php

Public actions/helpers: `login`, `registrationConfig`, `registrationMeta`, `branding`, `publicSettings`, `authConfig`, `register`, `session`, `desktopWebSession`, `status`, `syncPull`, `syncPush`, `syncBatch`, `inventoryIndex`, `inventoryStoreProduct`, `inventoryDestroyProduct`, `inventoryUploadProductImage`, `inventoryBulkImport`, `inventoryAdjustStock`, `customersIndex`, `customersSearch`, `customersStore`, `customerLedger`, `customerRecordPayment`, `analytics`, `subscription`, `subscriptionFeatures`, `subscriptionEntitlements`, `subscriptionRedeem`, `subscriptionActivateFree`, `subscriptionRazorpayOrder`, `subscriptionRazorpayVerify`, `subscriptionMercadoPagoPreference`, `subscriptionMercadoPagoVerify`, `sendDelivery`, `saleDetails`, `salePdf`, `taxRulesIndex`, `taxRulesStore`, `taxRulesUpdate`, `taxRulesSeedCountry`, `taxRulesToggle`, `taxRulesEditSheet`, `taxRulesSetDefault`, `taxRulesDestroy`, `dueReceivables`, `remindReceivable`, `scheduleReceivableReminder`.

### Source actions: app/Http/Controllers/Api/V1/PushDeviceApiController.php

Public actions/helpers: `config`, `test`, `store`, `destroy`.

### Source actions: app/Http/Controllers/Api/V1/QuotationApiController.php

Public actions/helpers: `index`, `show`, `defaults`, `store`, `update`, `destroy`, `convert`, `pdf`.

### Source actions: app/Http/Controllers/Api/V1/RepairApiController.php

Public actions/helpers: `__construct`, `stats`, `categoriesIndex`, `categoriesStore`, `categoriesUpdate`, `categoriesDestroy`, `ticketsIndex`, `ticketsStore`, `ticketsShow`, `ticketsShareSheet`, `ticketsShareDispatchSheet`, `ticketsDispatch`, `ticketsUpdateStatus`, `ticketsAssign`, `ticketsAddPart`, `ticketsRemovePart`, `ticketsSetLabor`, `ticketsUpdateChecklist`, `ticketsDestroy`, `ticketsSettle`, `ticketCheckoutSheet`, `checkoutSheet`, `posCheckout`, `ticketIntakeSheet`.

### Source actions: app/Http/Controllers/Api/V1/ReportsApiController.php

Public actions/helpers: `summary`, `profitLoss`, `paymentMethods`, `tillClosings`, `commissions`, `aging`, `export`.

### Source actions: app/Http/Controllers/Api/V1/RestaurantApiController.php

Public actions/helpers: `floorsIndex`, `floorsStore`, `floorsUpdate`, `floorsDestroy`, `tablesStore`, `tablesUpdate`, `tablesSetStatus`, `tablesDestroy`, `tableShow`, `tableActionsSheet`, `sendToKitchen`, `settle`, `kotIndex`, `kotUpdateStatus`, `kotDismissAlarm`, `kotPrint`.

### Source actions: app/Http/Controllers/Api/V1/RoleApiController.php

Public actions/helpers: `index`, `store`, `update`, `destroy`, `getPermissionsSchema`.

### Source actions: app/Http/Controllers/Api/V1/SaleApiController.php

Public actions/helpers: `sendInvoice`, `printInvoice`, `checkoutSheet`, `checkout`, `holdOrder`, `resolveDiscountAmount`.

### Source actions: app/Http/Controllers/Api/V1/SalesTargetApiController.php

Public actions/helpers: `index`, `store`.

### Source actions: app/Http/Controllers/Api/V1/SalonApiController.php

Public actions/helpers: `checkoutSheet`, `specialistSheet`, `appointmentsIndex`, `getAppointments`, `appointmentsStore`, `appointmentsAvailability`, `appointmentsUpdateStatus`, `posCheckout`, `specialistsIndex`, `specialistsToggle`, `servicesIndex`, `servicesStore`, `servicesEditSheet`, `servicesUpdate`, `servicesDestroy`.

### Source actions: app/Http/Controllers/Api/V1/SduiViewController.php

Public actions/helpers: `show`, `submitSettings`.

### Source actions: app/Http/Controllers/Api/V1/ServiceOrderApiController.php

Public actions/helpers: `index`, `show`, `store`, `partsIndex`, `update`, `updateStatus`, `destroy`.

### Source actions: app/Http/Controllers/Api/V1/SettingsApiController.php

Public actions/helpers: `index`, `updateProfile`, `updateBranding`, `getTheme`, `uploadLogo`, `removeLogo`, `uploadFavicon`, `removeFavicon`, `uploadDrawerCover`, `removeDrawerCover`, `updateReceipts`, `updateRepairChecklist`, `updateFinancial`, `updateNotifications`, `updateNotificationSounds`, `testEmail`, `paymentMethodsIndex`, `paymentMethodsStore`, `paymentMethodsUpdate`, `paymentMethodsToggle`, `paymentMethodsDestroy`, `paymentMethodsEditSheet`, `presentNotificationSounds`, `paymentMethodTransactions`, `paymentMethodTransactionsExport`, `notificationChannelsIndex`, `notificationChannelsStore`, `notificationChannelsUpdate`, `notificationChannelsDestroy`, `testNotificationChannel`, `getNavigationLabels`, `updateNavigationLabels`, `getDrawerNavigation`, `getDrawerMenu`, `getFormLabels`, `updateFormLabels`.

### Source actions: app/Http/Controllers/Api/V1/StorefrontCustomerApiController.php

Public actions/helpers: `resolveCompany`, `register`, `login`, `logout`, `profile`, `updateProfile`, `addresses`, `storeAddress`, `updateAddress`, `deleteAddress`, `wishlist`, `toggleWishlist`, `removeWishlist`, `calculateCart`, `orders`.

### Source actions: app/Http/Controllers/Api/V1/TaxApiController.php

Public actions/helpers: `__construct`, `calculate`, `issueInvoice`, `getRates`, `getEInvoicePayload`.

### Source actions: app/Http/Controllers/Api/V1/TenantAppPreferencesController.php

Public actions/helpers: `defaultPresets`, `durationOptions`, `recurringIntervalOptions`, `vibrationPatternOptions`, `defaultChannelDefinitions`, `getEffectivePreferences`, `getNotificationAlertsScreen`, `getNotificationPreferences`, `index`, `saveNotificationPreferences`, `saveAutoReminders`, `update`, `uploadAudio`, `buildSduiSchema`.

### Source actions: app/Http/Controllers/Api/V1/TenantDemoDataController.php

Public actions/helpers: `destroy`.

### Source actions: app/Http/Controllers/Api/V1/TenantPasswordResetController.php

Public actions/helpers: `sendResetLink`, `reset`, `changePassword`.

### Source actions: app/Http/Controllers/Api/V1/UploadApiController.php

Public actions/helpers: `uploadRxAttachment`.

### Source actions: app/Http/Controllers/Api/V1/UserApiController.php

Public actions/helpers: `index`, `invite`, `resendInvite`, `updateRole`, `toggleStatus`, `updateCommission`, `destroy`.

### Source actions: app/Http/Controllers/Auth/SocialAuthController.php

Public actions/helpers: `redirect`, `callback`, `mobileToken`, `providerConfigured`, `enabledProviders`.

### Source actions: app/Http/Controllers/DemoLoginController.php

Public actions/helpers: `login`.

### Source actions: app/Http/Controllers/LandingPageController.php

Public actions/helpers: `index`.

### Source actions: app/Http/Controllers/LicenseVerificationController.php

Public actions/helpers: `verifyClientApp`.

### Source actions: app/Http/Controllers/LocaleController.php

Public actions/helpers: `switch`.

### Source actions: app/Http/Controllers/PublicContactController.php

Public actions/helpers: `index`, `store`.

### Source actions: app/Http/Controllers/PublicPageController.php

Public actions/helpers: `show`.

### Source actions: app/Http/Controllers/StoreProfileController.php

Public actions/helpers: `show`, `update`.

### Source actions: app/Http/Controllers/SuperAdmin/TenantController.php

Public actions/helpers: `updateModules`.

### Source actions: app/Http/Controllers/SuperAdmin/ThemeCustomizerController.php

Public actions/helpers: `saveGlobalDefaults`, `saveSectionThemes`, `applyMatchingPattern`.

### Source actions: app/Http/Controllers/Sync/CatalogViewController.php

Public actions/helpers: `show`, `placeOrder`.

### Source actions: app/Http/Controllers/Sync/HealthController.php

Public actions/helpers: `index`.

### Source actions: app/Http/Controllers/Sync/MysqlSyncController.php

Public actions/helpers: `dispatch`.

### Source actions: app/Http/Controllers/Sync/PlatformAdminSyncController.php

Public actions/helpers: `dispatch`.

### Source actions: app/Http/Controllers/Sync/SubscriptionSyncController.php

Public actions/helpers: `dispatch`.

### Source actions: app/Http/Controllers/Sync/TenantAuthSyncController.php

Public actions/helpers: `dispatch`.

### Source actions: app/Http/Controllers/Tenant/Auth/PasswordResetController.php

Public actions/helpers: `showForgotForm`, `showResetForm`, `sendResetLink`, `reset`, `changePassword`.

### Source actions: app/Http/Controllers/Tenant/BackupDownloadController.php

Public actions/helpers: `download`.

### Source actions: app/Http/Controllers/Tenant/CashRegisterSlipController.php

Public actions/helpers: `pdfZReport`, `viewZReport`, `pdfMovement`, `viewMovement`.

### Source actions: app/Http/Controllers/Tenant/DocumentTemplateController.php

Public actions/helpers: `edit`, `update`, `previewHtml`, `apiShow`, `apiUpdate`.

### Source actions: app/Http/Controllers/Tenant/ImpersonationController.php

Public actions/helpers: `start`, `stop`.

### Source actions: app/Http/Controllers/Tenant/InvoiceController.php

Public actions/helpers: `pdfStream`, `signedPdf`, `pdf`, `sendCustom`, `send`, `publicShow`.

### Source actions: app/Http/Controllers/Tenant/LeadWebController.php

Public actions/helpers: `middleware`, `__construct`, `index`, `create`, `store`, `show`, `update`, `destroy`, `storeActivity`, `completeActivity`.

### Source actions: app/Http/Controllers/Tenant/NavigationMenuController.php

Public actions/helpers: `store`, `populateDefaultNavigation`, `drawerHeader`.

### Source actions: app/Http/Controllers/Tenant/PwaManifestController.php

Public actions/helpers: `__invoke`.

### Source actions: app/Http/Controllers/Tenant/QuotationController.php

Public actions/helpers: `pdf`, `send`, `publicShow`.

### Source actions: app/Http/Controllers/Tenant/RepairPortalController.php

Public actions/helpers: `track`.

### Source actions: app/Http/Controllers/Tenant/Restaurant/KotController.php

Public actions/helpers: `print`.

### Source actions: app/Http/Controllers/Tenant/Restaurant/TableOrderController.php

Public actions/helpers: `show`, `placeOrder`, `qrCard`.

### Source actions: app/Http/Controllers/Tenant/StoreInquiryController.php

Public actions/helpers: `submitPublicInquiry`, `index`, `updateStatus`, `destroy`.

### Source actions: app/Http/Controllers/Tenant/StorefrontAuthController.php

Public actions/helpers: `redirectToProvider`, `handleProviderCallback`.

### Source actions: app/Http/Controllers/Tenant/StorefrontController.php

Public actions/helpers: `resolveCompany`, `index`, `getStoreMenus`, `showCmsPage`, `placeOrder`, `sendVerification`, `verifyCode`, `validateCoupon`, `paymentMethods`, `account`, `trackOrder`, `apiCatalog`, `apiFaqs`, `faqsPage`, `initiateGatewayPayment`, `verifyGatewayPayment`.

### Source actions: app/Http/Controllers/Tenant/StorefrontMenuController.php

Public actions/helpers: `pagesIndex`, `pagesStore`, `pagesShow`, `pagesUpdate`, `pagesDestroy`, `menusIndex`, `menusStore`, `menusUpdate`, `toggleVisibility`, `menusDestroy`, `reorder`, `publicMenus`.

### Source actions: app/Http/Controllers/Tenant/StorefrontReviewController.php

Public actions/helpers: `index`, `store`, `customerReviews`, `tenantIndex`, `toggleApproval`, `tenantDestroy`, `updateSettings`, `tenantStore`.

### Source actions: app/Http/Controllers/Tenant/SubscriptionInvoiceController.php

Public actions/helpers: `pdf`.

### Source actions: app/Http/Controllers/Tenant/UserPreferenceController.php

Public actions/helpers: `updateDockPosition`.

### Source actions: app/Http/Controllers/Webhooks/SubscriptionWebhookController.php

Public actions/helpers: `handle`, `callback`.

### Source actions: app/Http/Middleware/AuthenticateTenantApi.php

Public actions/helpers: `handle`.

### Source actions: app/Http/Middleware/CheckMaintenanceMode.php

Public actions/helpers: `handle`.

### Source actions: app/Http/Middleware/CheckTenantApiUserPermission.php

Public actions/helpers: `__construct`, `handle`.

### Source actions: app/Http/Middleware/CheckTenantPermission.php

Public actions/helpers: `__construct`, `handle`.

### Source actions: app/Http/Middleware/ClearStoreContext.php

Public actions/helpers: `handle`.

### Source actions: app/Http/Middleware/EnsureAppIsInstalled.php

Public actions/helpers: `handle`.

### Source actions: app/Http/Middleware/EnsureNotInstalled.php

Public actions/helpers: `handle`.

### Source actions: app/Http/Middleware/EnsureTenantEmailIsVerified.php

Public actions/helpers: `handle`.

### Source actions: app/Http/Middleware/EnsureTenantExtension.php

Public actions/helpers: `handle`.

### Source actions: app/Http/Middleware/EnsureTenantPosMode.php

Public actions/helpers: `handle`.

### Source actions: app/Http/Middleware/EnsureTenantSubscriptionActive.php

Public actions/helpers: `handle`.

### Source actions: app/Http/Middleware/EnsureTenantVertical.php

Public actions/helpers: `handle`.

### Source actions: app/Http/Middleware/PeriodicLicenseCheck.php

Public actions/helpers: `handle`, `terminate`.

### Source actions: app/Http/Middleware/PreventDemoModifications.php

Public actions/helpers: `handle`.

### Source actions: app/Http/Middleware/ResolveStoreContext.php

Public actions/helpers: `handle`.

### Source actions: app/Http/Middleware/ResolveTenantContext.php

Public actions/helpers: `handle`.

### Source actions: app/Http/Middleware/SecurityHeaders.php

Public actions/helpers: `handle`.

### Source actions: app/Http/Middleware/SetLocale.php

Public actions/helpers: `__construct`, `handle`.

### Source actions: app/Http/Requests/Traits/NormalizesPhoneNumber.php

Public actions/helpers: `normalizePhoneNumber`, `normalizePhoneFields`.

### Source actions: app/Http/Resources/InvoiceResource.php

Public actions/helpers: `toArray`.

### Source actions: app/Http/Resources/ReceiptModalResource.php

Public actions/helpers: `toArray`.

### Source actions: app/Http/Resources/SaleResource.php

Public actions/helpers: `toArray`.

### Source actions: app/Jobs/DispatchAutomatedCustomerReminder.php

Public actions/helpers: `__construct`, `backoff`, `handle`, `failed`.

### Source actions: app/Jobs/GenerateProductImage.php

Public actions/helpers: `__construct`, `handle`.

### Source actions: app/Jobs/RunDesktopSyncCycle.php

Public actions/helpers: `handle`.

### Source actions: app/Jobs/SeedTenantSampleDataJob.php

Public actions/helpers: `__construct`, `handle`.

### Source actions: app/Listeners/TenantRegisteredListener.php

Public actions/helpers: `handle`.

### Source actions: app/Livewire/Auth/AcceptInvite.php

Public actions/helpers: `mount`, `accept`, `render`.

UI settings/state identifiers (names only): `code`, `password`, `password_confirmation`, `error`.

### Source actions: app/Livewire/Auth/PlatformLogin.php

Public actions/helpers: `mount`, `fillDemo`, `login`, `render`.

UI settings/state identifiers (names only): `email`, `password`, `error`.

### Source actions: app/Livewire/Auth/TenantLogin.php

Public actions/helpers: `mount`, `fillDemo`, `login`, `render`.

UI settings/state identifiers (names only): `identifier`, `password`, `error`.

### Source actions: app/Livewire/Auth/TenantRegister.php

Public actions/helpers: `mount`, `getActiveRegistrationModulesProperty`, `getAllowedRegistrationModesProperty`, `updatedStoreName`, `toggleDomainSettings`, `selectPlan`, `toggleActivationCode`, `backToForm`, `register`, `verifyOtp`, `resendOtp`, `render`.

UI settings/state identifiers (names only): `step`, `otp`, `otpStatusMessage`, `storeName`, `slug`, `customDomain`, `showDomainSettings`, `posMode`, `ownerName`, `email`, `phone`, `taxId`, `password`, `password_confirmation`, `planName`, `activationCode`, `hasActivationCode`, `errorMessage`.

### Source actions: app/Livewire/Auth/VerifyOtp.php

Public actions/helpers: `mount`, `verify`, `resend`, `logout`, `render`.

UI settings/state identifiers (names only): `otp`, `errorMessage`, `statusMessage`, `userEmail`.

### Source actions: app/Livewire/Installer/AdminAccountStep.php

Public actions/helpers: `mount`, `getStoreLinkProperty`, `save`, `render`.

UI settings/state identifiers (names only): `name`, `email`, `password`, `password_confirmation`, `licenseKey`, `buyerUsername`, `licenseStatus`, `alreadyBootstrapped`.

### Source actions: app/Livewire/Installer/EnvironmentStep.php

Public actions/helpers: `mount`, `testConnection`, `save`, `render`.

UI settings/state identifiers (names only): `appUrl`, `dbConnection`, `dbHost`, `dbPort`, `dbDatabase`, `dbUsername`, `dbPassword`, `connectionOk`, `connectionMessage`.

### Source actions: app/Livewire/Installer/FinishStep.php

Public actions/helpers: `mount`, `finish`, `render`.

UI settings/state identifiers (names only): `done`, `licenseData`.

### Source actions: app/Livewire/Installer/MigrateStep.php

Public actions/helpers: `runMigrations`, `render`.

UI settings/state identifiers (names only): `ran`, `failed`, `output`.

### Source actions: app/Livewire/Installer/RequirementsStep.php

Public actions/helpers: `mount`, `render`.

UI settings/state identifiers (names only): `checks`, `allPassed`.

### Source actions: app/Livewire/Public/ContactForm.php

Public actions/helpers: `submit`, `render`.

UI settings/state identifiers (names only): `name`, `email`, `storeType`, `phone`, `subject`, `message`, `customData`, `submitted`.

### Source actions: app/Livewire/SuperAdmin/ActivationCodes/Index.php

Public actions/helpers: `generate`, `revoke`, `render`.

UI settings/state identifiers (names only): `showForm`, `planName`, `maxUses`, `validityDays`, `notes`, `justGeneratedCode`.

### Source actions: app/Livewire/SuperAdmin/AuditLogs/Index.php

Public actions/helpers: `updating`, `render`.

UI settings/state identifiers (names only): `action`, `companyId`.

### Source actions: app/Livewire/SuperAdmin/Backups/Index.php

Public actions/helpers: `mount`, `savePolicy`, `createSnapshot`, `deleteSnapshot`, `download`, `render`.

UI settings/state identifiers (names only): `frequency`, `retentionDays`.

### Source actions: app/Livewire/SuperAdmin/Branding/Index.php

Public actions/helpers: `mount`, `updatedLandingPageId`, `setHomepageMode`, `setMenuLocation`, `addAnchorMenuLink`, `addPageMenuLink`, `addCustomMenuLink`, `moveMenuItemUp`, `moveMenuItemDown`, `toggleMenuItemActive`, `deleteMenuItem`, `editMenuItem`, `saveMenuItem`, `cancelEditMenuItem`, `moveSectionUp`, `moveSectionDown`, `resetSectionOrder`, `addHeroHighlight`, `removeHeroHighlight`, `addHighlight`, `removeHighlight`, `addHeroProduct`, `removeHeroProduct`, `addProduct`, `removeProduct`, `addHardwareItem`, `removeHardwareItem`, `addHardware`, `removeHardware`, `addFeature`, `removeFeature`, `addSolution`, `removeSolution`, `addStat`, `removeStat`, `addTestimonial`, `removeTestimonial`, `addFaq`, `removeFaq`, `addLandingItem`, `removeLandingItem`, `removeLogo`, `removeAuthBanner`, `removeHeroBanner`, `save`, `render`.

UI settings/state identifiers (names only): `platformName`, `logoUrl`, `logoImage`, `faviconUrl`, `showAuthBanner`, `authBannerImageUrl`, `authBannerImage`, `enableRegistrationDomainSetup`, `primaryColor`, `superadminSidebarColor`, `landingPrimaryColor`, `landingAccentColor`, `supportEmail`, `supportPhone`, `headOfficeAddress`, `workingHours`, `otpRegistrationEnabled`, `landingPageEnabled`, `landingPageId`, `landingTheme`, `activeStudioTab`, `activeTab`, `homepageMode`, `menuLocation`, `newMenuTitle`, `newMenuUrl`, `newMenuPageId`, `newMenuTargetBlank`, `editingMenuItemId`, `editingMenuItemTitle`, `editingMenuItemUrl`, `editingMenuItemTarget`, `editingMenuItemActive`, `sectionHero`, `sectionTrustBar`, `sectionFeatures`, `sectionSolutions`, `sectionDownloads`, `sectionStats`, `sectionAbout`, `sectionTestimonials`, `sectionPricing`, `sectionFaq`, `sectionContact`, `sectionCta`, `landingSectionOrder`, `landingPlaystoreUrl`, `landingPlaystoreEnabled`, `landingWindowsUrl`, `landingWindowsEnabled`, `landingHeroBadge`, `landingHeroTitle`, `landingHeroSubtitle`, `landingHeroCtaPrimaryText`, `landingHeroCtaPrimaryUrl`, `landingHeroCtaSecondaryText`, `landingHeroCtaSecondaryUrl`, `landingHeroBannerImageUrl`, `landingHeroBannerImage`, `landingHeroDashboardTitle`, `landingHeroDashboardStatus`, `landingHeroTotalLabel`, `landingHeroPaymentLabel`, `landingHeroTotalAmount`, `landingHeroHighlights`, `landingHeroProducts`, `sectionMeta`, `landingHardwareItems`, `landingFeatures`, `landingSolutions`, `landingStats`, `landingTestimonials`, `landingFaqs`, `pricingDiscountBadge`, `pricingNote`, `ctaPrimaryText`, `ctaPrimaryUrl`, `ctaSecondaryText`, `ctaSecondaryUrl`, `landingCustomHtml`, `landingContentJson`.

### Source actions: app/Livewire/SuperAdmin/Dashboard.php

Public actions/helpers: `activateCore`, `render`.

UI settings/state identifiers (names only): `coreLicenseKey`.

### Source actions: app/Livewire/SuperAdmin/Inquiries/Index.php

Public actions/helpers: `mount`, `loadFieldsAndSettings`, `setTab`, `updatingSearch`, `updatingStatusFilter`, `updatedSelectAll`, `viewInquiry`, `closeDetailModal`, `updateStatus`, `deleteInquiry`, `bulkMarkAsRead`, `bulkMarkAsReplied`, `bulkDelete`, `openAddFieldModal`, `editField`, `closeFieldModal`, `updatedFieldLabel`, `saveField`, `deleteField`, `moveUp`, `moveDown`, `resetDefaultFields`, `saveFormSettings`, `render`.

UI settings/state identifiers (names only): `tab`, `search`, `statusFilter`, `selectedInquiries`, `selectAll`, `showDetailModal`, `viewingInquiryId`, `viewingInquiry`, `fields`, `showFieldModal`, `isEditingField`, `editingFieldId`, `fieldLabel`, `fieldName`, `fieldType`, `fieldPlaceholder`, `fieldOptions`, `fieldWidth`, `fieldRequired`, `pageTitle`, `pageSubtitle`, `submitButtonText`, `successMessage`, `recipientEmail`, `formEnabled`.

### Source actions: app/Livewire/SuperAdmin/Languages/Index.php

Public actions/helpers: `mount`, `selectLocale`, `loadTranslations`, `updatedTranslations`, `updateKey`, `saveTranslations`, `openAddKeyModal`, `addKey`, `deleteKey`, `syncMissingFromEnglish`, `toggleLanguageStatus`, `createLanguage`, `render`.

UI settings/state identifiers (names only): `activeTab`, `selectedLocale`, `searchQuery`, `showCreateModal`, `newCode`, `newName`, `newNativeName`, `newFlag`, `newDirection`, `newIsActive`, `showAddKeyModal`, `newKey`, `newValue`, `translations`, `modifiedKeys`, `successMessage`, `errorMessage`.

### Source actions: app/Livewire/SuperAdmin/Modules/Index.php

Public actions/helpers: `install`, `getModule`, `activate`, `revalidateLicense`, `deactivate`, `uninstall`, `pruneOrphan`, `render`.

UI settings/state identifiers (names only): `zipFile`, `licenseKeys`, `catalogKeys`.

### Source actions: app/Livewire/SuperAdmin/Pages/Create.php

Public actions/helpers: `save`, `render`.

UI settings/state identifiers (names only): `title`, `slug`, `content`, `metaDescription`, `isActive`, `showInFooter`.

### Source actions: app/Livewire/SuperAdmin/Pages/Edit.php

Public actions/helpers: `mount`, `save`, `render`.

UI settings/state identifiers (names only): `page`, `title`, `slug`, `content`, `metaDescription`, `isActive`, `showInFooter`.

### Source actions: app/Livewire/SuperAdmin/Pages/Index.php

Public actions/helpers: `updatingSearch`, `delete`, `render`.

UI settings/state identifiers (names only): `search`.

### Source actions: app/Livewire/SuperAdmin/PaymentGateways/Index.php

Public actions/helpers: `mount`, `hasStoredSecret`, `hasStoredWebhookSecret`, `save`, `render`.

UI settings/state identifiers (names only): `gateways`.

### Source actions: app/Livewire/SuperAdmin/Plans/Index.php

Public actions/helpers: `addCustomExtension`, `newPlan`, `edit`, `save`, `delete`, `getAvailableExtensionsProperty`, `render`.

UI settings/state identifiers (names only): `showForm`, `editingName`, `name`, `displayName`, `billingCycle`, `durationDays`, `price`, `currency`, `active`, `limitUsers`, `limitDevices`, `limitStorageMb`, `limitBranches`, `invoiceLimit`, `productsLimit`, `deviceLimit`, `staffLimit`, `extensions`, `customExtensionInput`, `customFeaturesText`, `featureMultiLocation`, `featureAutomaticBackup`, `featureOnlineStore`, `featureQuotations`, `featureConsignments`, `featureCashRegister`, `featureCustomerCrm`, `featureAnalyticsReports`, `featureRestaurantMode`, `featureServiceBooking`, `featureRepairWorkbench`, `featurePharmacyBatches`, `featureThermalPrinting`, `featureApiAccess`.

### Source actions: app/Livewire/SuperAdmin/Settings/Index.php

Public actions/helpers: `mount`, `setTab`, `updatedPlatformDefaultCurrency`, `updatedPlatformDefaultCountryIso`, `updatedAppCurrency`, `updatedPlatformDefaultTimezone`, `updatedAppTimezone`, `savePushNotifications`, `testPushNotifications`, `setLandingTheme`, `updatedLandingTheme`, `saveAppearance`, `applyMatchingPalettePattern`, `saveSocialLogin`, `saveGeneral`, `clearSystemCache`, `saveSmtp`, `sendSmtpTest`, `addFaq`, `removeFaq`, `addFeature`, `removeFeature`, `addTestimonial`, `removeTestimonial`, `addHardware`, `removeHardware`, `addHardwareItem`, `removeHardwareItem`, `addSolution`, `removeSolution`, `addStat`, `removeStat`, `addHeroHighlight`, `removeHeroHighlight`, `addHighlight`, `removeHighlight`, `addHeroProduct`, `removeHeroProduct`, `addProduct`, `removeProduct`, `addLandingItem`, `removeLandingItem`, `removeAuthBanner`, `updatedLandingPageId`, `setHomepageMode`, `setMenuLocation`, `addAnchorMenuLink`, `addPageMenuLink`, `addCustomMenuLink`, `moveMenuItemUp`, `moveMenuItemDown`, `toggleMenuItemActive`, `deleteMenuItem`, `editMenuItem`, `saveMenuItem`, `cancelEditMenuItem`, `saveBranding`, `updatingPageSearch`, `deletePage`, `render`, `moduleGovernance`.

UI settings/state identifiers (names only): `activeTab`, `landingTheme`, `social`, `appName`, `appCurrency`, `appTimezone`, `platformDefaultCurrency`, `platformDefaultLanguage`, `platformDefaultTimezone`, `platformDefaultCountryIso`, `platformDefaultDialCode`, `maintenanceMode`, `maintenanceMessage`, `minClientBuildVersion`, `appVersion`, `showPoweredBy`, `allowedRegistrationModes`, `enabledRegistrationModules`, `autoSeedDemoDataOnRegistration`, `aiImageEnabled`, `aiImageProvider`, `aiImageOpenaiApiKey`, `aiImageGeminiApiKey`, `aiImageClaudeApiKey`, `hasAiImageOpenaiApiKey`, `hasAiImageGeminiApiKey`, `hasAiImageClaudeApiKey`, `smtpHost`, `smtpPort`, `smtpUsername`, `smtpPassword`, `smtpEncryption`, `smtpFromAddress`, `smtpFromName`, `hasStoredPassword`, `testEmailTo`, `pushEnabled`, `fcmProjectId`, `fcmServiceAccountJson`, `fcmServerKey`, `hasFcmServiceAccount`, `hasFcmServerKey`, `androidApiKey`, `androidAppId`, `messagingSenderId`, `hasAndroidApiKey`, `orderChannelId`, `orderChannelName`, `orderSound`, `invoiceChannelId`, `invoiceChannelName`, `invoiceSound`, `alarmRepeatSeconds`, `homepageMode`, `menuLocation`, `newMenuTitle`, `newMenuUrl`, `newMenuPageId`, `newMenuTargetBlank`, `editingMenuItemId`, `editingMenuItemTitle`, `editingMenuItemUrl`, `editingMenuItemTarget`, `editingMenuItemActive`, `platformName`, `logoUrl`, `logoImage`, `faviconUrl`, `showAuthBanner`, `authBannerImageUrl`, `authBannerImage`, `enableRegistrationDomainSetup`, `primaryColor`, `superadminSidebarColor`, `landingPrimaryColor`, `landingAccentColor`, `supportEmail`, `supportPhone`, `headOfficeAddress`, `workingHours`, `otpRegistrationEnabled`, `landingPageEnabled`, `landingPageId`, `landingHeroBadge`, `landingHeroTitle`, `landingHeroSubtitle`, `landingHeroCtaPrimaryText`, `landingHeroCtaPrimaryUrl`, `landingHeroCtaSecondaryText`, `landingHeroCtaSecondaryUrl`, `landingHeroBannerImageUrl`, `sectionTrustBar`, `sectionFeatures`, `sectionSolutions`, `sectionStats`, `sectionAbout`, `sectionTestimonials`, `sectionPricing`, `sectionContact`, `sectionCta`, `sectionHero`, `sectionDownloads`, `sectionFaq`, `landingPlaystoreUrl`, `landingPlaystoreEnabled`, `landingWindowsUrl`, `landingWindowsEnabled`, `sectionMeta`, `landingSectionOrder`, `landingFaqs`, `landingFeatures`, `landingTestimonials`, `landingFeaturesJson`, `landingTestimonialsJson`, `landingContentJson`, `landingCustomHtml`, `landingHeroHighlights`, `landingHeroProducts`, `landingHardwareItems`, `landingStats`, `landingSolutions`, `pageSearch`.

### Source actions: app/Livewire/SuperAdmin/Smtp/Index.php

Public actions/helpers: `mount`, `save`, `sendTest`, `render`.

UI settings/state identifiers (names only): `smtpHost`, `smtpPort`, `smtpUsername`, `smtpPassword`, `smtpEncryption`, `smtpFromAddress`, `smtpFromName`, `hasStoredPassword`, `testEmailTo`.

### Source actions: app/Livewire/SuperAdmin/System/Index.php

Public actions/helpers: `mount`, `save`, `render`.

UI settings/state identifiers (names only): `maintenanceMode`, `maintenanceMessage`, `minClientBuildVersion`, `appVersion`.

### Source actions: app/Livewire/SuperAdmin/Tax/Index.php

Public actions/helpers: `selectCountry`, `render`.

UI settings/state identifiers (names only): `selectedCountry`.

### Source actions: app/Livewire/SuperAdmin/Tenants/Create.php

Public actions/helpers: `save`, `render`.

UI settings/state identifiers (names only): `name`, `email`, `phone`, `country`, `planName`, `adminName`, `adminLogin`, `adminEmail`, `adminPassword`.

### Source actions: app/Livewire/SuperAdmin/Tenants/Index.php

Public actions/helpers: `updatingSearch`, `render`.

UI settings/state identifiers (names only): `search`.

### Source actions: app/Livewire/SuperAdmin/Tenants/Show.php

Public actions/helpers: `mount`, `selectAllModules`, `deselectAllModules`, `save`, `suspend`, `activate`, `extendExpiry`, `render`.

UI settings/state identifiers (names only): `company`, `name`, `email`, `phone`, `planName`, `status`, `expiresAt`, `maxUsers`, `maxDevices`, `posMode`, `licensedModules`.

### Source actions: app/Livewire/Superadmin/MenuBuilderComponent.php

Public actions/helpers: `updatedActiveLocation`, `addSelectedPages`, `addAnchorLink`, `addCustomLink`, `updateMenuOrder`, `toggleStatus`, `startEdit`, `saveEdit`, `cancelEdit`, `deleteItem`, `render`.

UI settings/state identifiers (names only): `activeLocation`, `selectedPages`, `customTitle`, `customUrl`, `customTargetBlank`, `editingId`, `editingTitle`, `editingUrl`, `editingTarget`.

### Source actions: app/Livewire/Tenant/Billing/Index.php

Public actions/helpers: `mount`, `redeemCode`, `selectPlanToUpgrade`, `closePaymentModal`, `initiateRazorpayPayment`, `verifyAndActivateRazorpayPayment`, `initiateMercadoPagoPayment`, `processSubscriptionPayment`, `openSendEmailModal`, `sendInvoiceEmail`, `getWhatsAppUrl`, `render`.

UI settings/state identifiers (names only): `activationCode`, `emailRecipient`, `selectedInvoiceIdForEmail`, `showEmailModal`, `showPaymentModal`, `selectedPlanId`, `paymentGateway`, `cardHolder`, `cardNumber`, `cardExpiry`, `cardCvv`, `paymentActivationCode`, `paymentError`, `successMessage`, `errorMessage`.

### Source actions: app/Livewire/Tenant/Brands/Index.php

Public actions/helpers: `newBrand`, `edit`, `save`, `delete`, `render`.

UI settings/state identifiers (names only): `showForm`, `editingId`, `name`.

### Source actions: app/Livewire/Tenant/Catalog/Index.php

Public actions/helpers: `mount`, `selectAllProducts`, `clearSelectedProducts`, `openNewProductModal`, `closeNewProductModal`, `saveNewProduct`, `publish`, `revoke`, `render`.

UI settings/state identifiers (names only): `title`, `description`, `whatsappNumber`, `selectedProductIds`, `ttlDays`, `enableQuotations`, `enableInvoices`, `productSearch`, `filterCategory`, `showProductModal`, `newProductName`, `newProductDescription`, `newProductPrice`, `newProductCost`, `newProductStock`, `newProductCategory`, `newProductCode`, `newProductBarcode`.

### Source actions: app/Livewire/Tenant/Categories/Index.php

Public actions/helpers: `newCategory`, `edit`, `save`, `delete`, `render`.

UI settings/state identifiers (names only): `showForm`, `editingId`, `name`, `color`, `description`.

### Source actions: app/Livewire/Tenant/Consignments/Create.php

Public actions/helpers: `mount`, `addItem`, `removeItem`, `updatedItems`, `getTotalDispatchedProperty`, `save`, `render`.

UI settings/state identifiers (names only): `customerId`, `customerName`, `dueDate`, `notes`, `items`.

### Source actions: app/Livewire/Tenant/Consignments/Index.php

Public actions/helpers: `mount`, `updatedSearch`, `updatedStatusFilter`, `deleteConsignment`, `render`.

UI settings/state identifiers (names only): `search`, `statusFilter`.

### Source actions: app/Livewire/Tenant/Consignments/Show.php

Public actions/helpers: `mount`, `loadItems`, `updatedItems`, `updatedReconciliationInputs`, `soldRevenue`, `returnedAmount`, `totalDispatchedAmount`, `dispatchGoods`, `saveReconciliation`, `openFinalizeModal`, `confirmAndGenerateInvoice`, `finalizeToSale`, `render`.

UI settings/state identifiers (names only): `consignment`, `paymentMethod`, `showFinalizeModal`, `items`, `reconciliationInputs`.

### Source actions: app/Livewire/Tenant/Coupons/Index.php

Public actions/helpers: `openCreateModal`, `openEditModal`, `saveCoupon`, `toggleStatus`, `deleteCoupon`, `render`.

UI settings/state identifiers (names only): `search`, `statusFilter`, `showModal`, `editingCouponId`, `code`, `discount_type`, `discount_value`, `min_order_amount`, `max_discount_amount`, `usage_limit_total`, `usage_limit_per_customer`, `starts_at`, `expires_at`, `is_active`, `description`.

### Source actions: app/Livewire/Tenant/Customers/Index.php

Public actions/helpers: `updatingSearch`, `newCustomer`, `edit`, `save`, `delete`, `startPointsAdjust`, `applyPointsAdjust`, `openCreditLedger`, `closeCreditLedger`, `loadCreditSales`, `openSettleModal`, `closeSettleModal`, `recordSettlement`, `render`.

UI settings/state identifiers (names only): `search`, `showForm`, `editingId`, `name`, `document`, `email`, `phone`, `address`, `city`, `state`, `pointsAdjustId`, `pointsDelta`, `showCreditModal`, `creditCustomer`, `creditSales`, `showSettleModal`, `selectedSaleId`, `selectedSale`, `paymentAmount`, `paymentMethod`, `paymentNotes`.

### Source actions: app/Livewire/Tenant/Dashboard.php

Public actions/helpers: `openPosLayoutModal`, `selectLayout`, `launchPosWithLayout`, `render`.

UI settings/state identifiers (names only): `showPosLayoutModal`, `selectedPosLayout`.

### Source actions: app/Livewire/Tenant/DesktopPrinterSettings.php

Public actions/helpers: `mount`, `save`, `render`.

UI settings/state identifiers (names only): `isDesktop`, `printers`, `printer58mm`, `printer80mm`, `printerA4`.

### Source actions: app/Livewire/Tenant/DesktopSyncStatus.php

Public actions/helpers: `mount`, `refreshStatus`, `render`.

UI settings/state identifiers (names only): `isDesktop`, `status`, `lastSyncedAt`.

### Source actions: app/Livewire/Tenant/Devices/Index.php

Public actions/helpers: `revoke`, `render`.

### Source actions: app/Livewire/Tenant/Faqs/Index.php

Public actions/helpers: `openCreateModal`, `edit`, `save`, `toggleActive`, `delete`, `seedDefaults`, `render`.

UI settings/state identifiers (names only): `search`, `categoryFilter`, `showModal`, `editingFaqId`, `question`, `answer`, `category`, `sort_order`, `is_active`.

### Source actions: app/Livewire/Tenant/Financials/CashRegister.php

Public actions/helpers: `mount`, `updatedDenominations`, `updatedClosingDenominations`, `openRegisterModal`, `openRegister`, `openMovementModal`, `recordMovement`, `openCloseModal`, `closeRegister`, `buildReportSummary`, `getCloseVarianceProperty`, `viewRegister`, `closeViewRegister`, `resetFilters`, `render`.

UI settings/state identifiers (names only): `dateFrom`, `dateTo`, `cashierId`, `statusFilter`, `showOpenModal`, `openingBalance`, `terminalId`, `openingNotes`, `showDenominationCounter`, `denominations`, `showMovementModal`, `movementType`, `movementCategory`, `movementAmount`, `movementReason`, `lastCreatedTransactionId`, `showCloseModal`, `countedClosingBalance`, `closeExpectedCash`, `closeNotes`, `closingDenominations`, `showStaleShiftModal`, `viewingRegisterId`.

### Source actions: app/Livewire/Tenant/Financials/Payables.php

Public actions/helpers: `mount`, `updatingSearch`, `updatingStatusFilter`, `updatingCategoryFilter`, `updatingSupplierFilter`, `openCreateBillModal`, `openEditBillModal`, `closeBillModal`, `saveBill`, `openPaymentModal`, `closePaymentModal`, `recordSettlement`, `deleteBill`, `render`.

UI settings/state identifiers (names only): `search`, `statusFilter`, `categoryFilter`, `supplierFilter`, `showBillModal`, `editingBillId`, `supplierId`, `vendorName`, `billNumber`, `category`, `title`, `amount`, `taxAmount`, `billDate`, `dueDate`, `notes`, `attachment`, `showPaymentModal`, `selectedBillId`, `selectedBill`, `settlementAmount`, `settlementMethod`, `settlementDate`, `referenceNumber`, `settlementNotes`, `settlementProof`.

### Source actions: app/Livewire/Tenant/Financials/PaymentMethodLedger.php

Public actions/helpers: `mount`, `exportCsv`, `render`.

UI settings/state identifiers (names only): `paymentMethod`, `dateFrom`, `dateTo`.

### Source actions: app/Livewire/Tenant/Financials/Receivables.php

Public actions/helpers: `mount`, `updatingSearch`, `updatingStatusFilter`, `updatingCustomerFilter`, `setTab`, `openPaymentModal`, `closePaymentModal`, `recordPayment`, `openReminderModal`, `scheduleReminder`, `sendReminder`, `render`.

UI settings/state identifiers (names only): `search`, `statusFilter`, `customerFilter`, `activeTab`, `showPaymentModal`, `selectedSaleId`, `selectedSale`, `paymentAmount`, `paymentMethod`, `paymentDate`, `referenceNumber`, `paymentNotes`, `showReminderModal`, `reminderSaleId`, `reminderDueDate`, `reminderAt`.

### Source actions: app/Livewire/Tenant/Languages/Index.php

Public actions/helpers: `mount`, `selectLocale`, `loadTranslations`, `updateOverride`, `clearOverride`, `saveAllOverrides`, `saveStoreDefaultLanguage`, `openAddModal`, `addCustomPhrase`, `render`.

UI settings/state identifiers (names only): `storeDefaultLanguage`, `selectedLocale`, `searchQuery`, `baseTranslations`, `customOverrides`, `editingOverrides`, `showAddModal`, `customKey`, `customValue`, `successMessage`, `errorMessage`.

### Source actions: app/Livewire/Tenant/Pharmacy/Batches.php

Public actions/helpers: `newBatch`, `save`, `startAdjust`, `startReturn`, `confirmAction`, `cancelAction`, `render`.

UI settings/state identifiers (names only): `filter`, `search`, `showForm`, `productId`, `batchNumber`, `rackLocation`, `manufacturingDate`, `expiryDate`, `costPrice`, `sellingPrice`, `stockQty`, `alertDaysBeforeExpiry`, `actingBatchId`, `actingMode`, `adjustNewStock`, `returnQty`, `reason`.

### Source actions: app/Livewire/Tenant/Pharmacy/Dashboard.php

Public actions/helpers: `render`.

### Source actions: app/Livewire/Tenant/Pharmacy/Prescriptions.php

Public actions/helpers: `newIntake`, `save`, `dispense`, `cancel`, `toggle`, `render`.

UI settings/state identifiers (names only): `status`, `search`, `searchMode`, `expiryThresholdDays`, `showForm`, `patientName`, `patientPhone`, `doctorName`, `doctorRegistrationNo`, `prescriptionDate`, `diagnosis`, `medicines`, `dosageDurationDays`, `expandedId`.

### Source actions: app/Livewire/Tenant/Products/Index.php

Public actions/helpers: `updatingSearch`, `productsImported`, `newProduct`, `addVariant`, `removeVariant`, `addModifier`, `removeModifier`, `addSpiceLevel`, `removeSpiceLevel`, `generateItemCode`, `generateIdentifiers`, `barcodeScanned`, `getAiImageAvailableProperty`, `generateAiPhoto`, `edit`, `save`, `delete`, `startAdjust`, `applyAdjustment`, `render`.

UI settings/state identifiers (names only): `search`, `showForm`, `editingId`, `name`, `description`, `code`, `barcode`, `imageFile`, `imageUrl`, `categoryId`, `brandId`, `unit`, `costPrice`, `salePrice`, `currentStock`, `minimumStock`, `active`, `taxable`, `adjustingId`, `adjustmentQty`, `adjustmentReason`, `variants`, `modifiers`, `spiceLevels`, `newVariantName`, `newVariantPrice`, `newModifierName`, `newModifierPrice`, `newSpiceLevelName`, `newSpiceLevelPrice`.

### Source actions: app/Livewire/Tenant/Products/ProductBulkImportComponent.php

Public actions/helpers: `downloadDemoData`, `processImport`, `render`.

UI settings/state identifiers (names only): `importFile`, `autoGenerateAiImages`, `showImportModal`.

### Source actions: app/Livewire/Tenant/Quotes/Create.php

Public actions/helpers: `mount`, `addItem`, `removeItem`, `updatedItems`, `updatedSelectedTaxRuleId`, `getSubtotalProperty`, `getCalculatedDiscountProperty`, `getTaxProperty`, `getTaxSummaryTableProperty`, `getTotalProperty`, `createQuickCustomer`, `updatedAgreedPaymentMethod`, `updatedPaymentMethod`, `updatedQuoteNotes`, `updatedNotes`, `save`, `render`.

UI settings/state identifiers (names only): `customerId`, `leadId`, `userId`, `discountType`, `discountValue`, `taxPercent`, `selectedTaxRuleId`, `taxName`, `availableTaxRules`, `isTaxExempt`, `quoteNumber`, `status`, `paymentMethod`, `agreedPaymentMethod`, `availablePaymentMethods`, `notes`, `quoteNotes`, `dueDate`, `paymentTerms`, `showQuickCustomerModal`, `newCustomerName`, `newCustomerPhone`, `newCustomerEmail`, `newCustomerCity`, `newCustomerDocument`, `items`.

### Source actions: app/Livewire/Tenant/Quotes/Edit.php

Public actions/helpers: `mount`, `addItem`, `removeItem`, `updatedItems`, `getSubtotalProperty`, `getCalculatedDiscountProperty`, `getTaxProperty`, `getTaxSummaryTableProperty`, `getTotalProperty`, `createQuickCustomer`, `updatedAgreedPaymentMethod`, `updatedPaymentMethod`, `updatedQuoteNotes`, `updatedNotes`, `save`, `render`.

UI settings/state identifiers (names only): `quote`, `customerId`, `userId`, `discountType`, `discountValue`, `taxPercent`, `isTaxExempt`, `quoteNumber`, `status`, `paymentMethod`, `agreedPaymentMethod`, `availablePaymentMethods`, `notes`, `quoteNotes`, `dueDate`, `paymentTerms`, `showQuickCustomerModal`, `newCustomerName`, `newCustomerPhone`, `newCustomerEmail`, `newCustomerCity`, `newCustomerDocument`, `items`.

### Source actions: app/Livewire/Tenant/Quotes/Index.php

Public actions/helpers: `updatedSelectAll`, `deleteQuote`, `bulkDelete`, `convertToSale`, `render`.

UI settings/state identifiers (names only): `search`, `statusFilter`, `selectedQuotes`, `selectAll`.

### Source actions: app/Livewire/Tenant/Quotes/Show.php

Public actions/helpers: `mount`, `getWhatsAppUrlProperty`, `openSendModal`, `resetMessage`, `appendPlaceholder`, `getWhatsAppApiConfiguredProperty`, `getEmailApiConfiguredProperty`, `getSmsApiConfiguredProperty`, `getDeviceMessageProperty`, `getEmailUrlProperty`, `getSmsUrlProperty`, `sendSms`, `getDesktopPrintReadyProperty`, `printNow`, `sendWhatsApp`, `trackWhatsAppSent`, `setStatus`, `sendEmail`, `convertToSale`, `openInPos`, `render`.

UI settings/state identifiers (names only): `quote`, `recipientEmail`, `recipientPhone`, `attachPdf`, `customMessage`, `activeChannel`, `showSendModal`.

### Source actions: app/Livewire/Tenant/Repair/Categories.php

Public actions/helpers: `seedDefaults`, `newCategory`, `edit`, `save`, `delete`, `render`.

UI settings/state identifiers (names only): `showForm`, `editingId`, `name`, `identifierType`, `brands`, `checklistItems`.

### Source actions: app/Livewire/Tenant/Repair/Dashboard.php

Public actions/helpers: `render`.

### Source actions: app/Livewire/Tenant/Repair/TicketDetail.php

Public actions/helpers: `mount`, `updateStatus`, `assign`, `addPart`, `removePart`, `setLabor`, `toggleChecklist`, `render`.

UI settings/state identifiers (names only): `ticket`, `newStatus`, `technicianDiagnosis`, `assignTechnicianId`, `partProductId`, `partName`, `partQty`, `partUnitPrice`, `laborFee`, `laborDescription`.

### Source actions: app/Livewire/Tenant/Repair/Tickets.php

Public actions/helpers: `newTicket`, `create`, `updateTicketStatus`, `render`.

UI settings/state identifiers (names only): `status`, `viewMode`, `search`, `showForm`, `customerName`, `customerPhone`, `categoryId`, `brand`, `model`, `serial`, `problemReported`, `priority`, `estimatedCost`, `diagnosticFee`.

### Source actions: app/Livewire/Tenant/Reports/Index.php

Public actions/helpers: `mount`, `setTab`, `setDatePreset`, `updatedStartDate`, `updatedEndDate`, `updatedSalespersonFilter`, `getKpiMetricsProperty`, `getSalesSummaryProperty`, `getTopProductsProperty`, `getChartSalesTrendProperty`, `getChartTopProductsProperty`, `getPaymentMethodsSummaryProperty`, `getChartPaymentMethodsProperty`, `getTillClosingsProperty`, `getChartTillClosingsProperty`, `viewRegister`, `closeViewRegister`, `getCommissionReportProperty`, `getChartCommissionsProperty`, `getAgingReportProperty`, `getChartAgingProperty`, `exportCsv`, `getDreStatementProperty`, `render`.

UI settings/state identifiers (names only): `activeTab`, `datePreset`, `startDate`, `endDate`, `salespersonFilter`, `viewingRegisterId`.

### Source actions: app/Livewire/Tenant/Restaurant/Kds.php

Public actions/helpers: `startPreparing`, `markReady`, `markServed`, `cancelKot`, `dismissAlarm`, `render`.

UI settings/state identifiers (names only): `filterServiceType`, `activeStatusTab`.

### Source actions: app/Livewire/Tenant/Restaurant/Pos.php

Public actions/helpers: `mount`, `updatedGatingDenominations`, `checkRegisterSession`, `openRegisterFromPos`, `settleStaleRegisterFromPos`, `setServiceType`, `selectTable`, `addSeat`, `setActiveSeat`, `openModifierModal`, `selectVariant`, `selectSpiceLevel`, `toggleModifier`, `addCustomizedItemToCart`, `addItemDirect`, `getCanOverridePriceProperty`, `applyPriceOverride`, `incrementItem`, `decrementItem`, `removeItem`, `clearOrder`, `getSubtotalProperty`, `getTotalProperty`, `openCheckoutModal`, `toggleSplitPayment`, `addSplitRow`, `removeSplitRow`, `getSplitTotalPaidProperty`, `getRemainingBalanceProperty`, `getChangeDueProperty`, `sendToKitchen`, `closeKotModalAndResetOrder`, `closeKotModalKeepOrder`, `transferTable`, `getCheckoutCustomerProperty`, `getCheckoutCustomerResultsProperty`, `openCustomerPicker`, `closeCustomerPicker`, `selectCheckoutCustomer`, `clearCheckoutCustomer`, `openNewCheckoutCustomerForm`, `createCheckoutCustomer`, `closeSettledDispatchModal`, `dispatchSettledInvoiceEmail`, `dispatchSettledInvoiceCustomChannel`, `getSettledDispatchChannelsProperty`, `settleBill`, `render`.

UI settings/state identifiers (names only): `serviceType`, `selectedTableId`, `activeTable`, `guestCount`, `activeSeat`, `seats`, `customerName`, `customerPhone`, `pickupTime`, `deliveryAddress`, `driverName`, `driverPhone`, `prepMinutes`, `intimationMinutes`, `selectedCategoryId`, `search`, `showModifierModal`, `selectedProduct`, `selectedVariantName`, `selectedVariantPrice`, `selectedModifiers`, `selectedSpiceLevelName`, `selectedSpiceLevelPrice`, `itemNote`, `items`, `showTableSelectorModal`, `showTransferModal`, `transferTargetTableId`, `showSplitBillModal`, `splitCount`, `showCheckoutModal`, `paymentMethod`, `discount`, `isSplitPayment`, `splitPayments`, `cashTendered`, `notes`, `dueDate`, `checkoutCustomerId`, `showCustomerPickerModal`, `showNewCustomerForm`, `customerSearchTerm`, `newCustomerQuickName`, `newCustomerQuickPhone`, `newCustomerQuickEmail`, `showSettledDispatchModal`, `lastSettledSaleId`, `lastSettledSaleNumber`, `lastSettledCustomerName`, `lastSettledCustomerPhone`, `lastSettledCustomerEmail`, `showKotSuccessModal`, `lastDispatchedKotNumber`, `lastDispatchedTableName`, `lastDispatchedItemCount`, `lastDispatchedKotPrintUrl`, `lastDispatchedKotId`, `showRegisterGatingModal`, `isStaleMidnightRegister`, `gatingOpeningBalance`, `gatingTerminalId`, `gatingOpeningNotes`, `showGatingDenominations`, `gatingDenominations`, `staleExpectedCash`, `staleCountedCash`, `staleClosingNotes`.

### Source actions: app/Livewire/Tenant/Restaurant/Tables.php

Public actions/helpers: `mount`, `openAddFloor`, `openEditFloor`, `saveFloor`, `deleteFloor`, `openAddTable`, `openEditTable`, `saveTable`, `setTableStatus`, `deleteTable`, `render`.

UI settings/state identifiers (names only): `selectedFloorId`, `showFloorModal`, `editingFloorId`, `floorName`, `floorOrderIndex`, `showTableModal`, `editingTableId`, `tableNumber`, `tableFloorId`, `tableCapacity`, `tableStatus`.

### Source actions: app/Livewire/Tenant/Reviews/Index.php

Public actions/helpers: `mount`, `toggleReviewsEnabled`, `toggleReviewApproval`, `approve`, `reject`, `delete`, `render`.

UI settings/state identifiers (names only): `search`, `statusFilter`, `ratingFilter`, `enable_product_reviews`, `require_review_approval`, `successMessage`.

### Source actions: app/Livewire/Tenant/Sales/Create.php

Public actions/helpers: `mount`, `updatedGatingDenominations`, `checkRegisterSession`, `openRegisterFromPos`, `settleStaleRegisterFromPos`, `switchLayout`, `setActiveStandTab`, `applyQuickDiscount`, `appendKeypad`, `clearKeypad`, `addCustomKeypadItem`, `addItem`, `removeItem`, `addProductToCart`, `increaseQuantity`, `decreaseQuantity`, `clearCart`, `selectCategory`, `getSelectedCustomerProperty`, `openCustomerSelectModal`, `selectCustomer`, `clearSelectedCustomer`, `openQuickCustomerModal`, `createQuickCustomer`, `toggleInModalCustomerSearch`, `openInModalNewCustomer`, `closeInModalCustomer`, `selectInModalCustomer`, `createInModalCustomer`, `getInModalCustomerResultsProperty`, `getSelectedCustomerDebtProperty`, `parseScaleBarcode`, `applyScaleWeight`, `updatedSearch`, `barcodeScanned`, `updatedItems`, `getCanOverridePriceProperty`, `applyPriceOverride`, `getFiscalCalculationsProperty`, `getSubtotalProperty`, `getTaxProperty`, `getTaxAmountProperty`, `getTotalProperty`, `getTaxSummaryTableProperty`, `getFlattenedTaxComponentsProperty`, `getCartItemCountProperty`, `getTotalPayableProperty`, `getTaxesProperty`, `getCartItemsProperty`, `incrementQty`, `decrementQty`, `removeFromCart`, `openNewCustomerModal`, `openCheckoutModal`, `closeCheckoutModal`, `openInvoicePreview`, `closeInvoicePreview`, `getAssignedSalespersonProperty`, `toggleSplitPayment`, `addSplitRow`, `removeSplitRow`, `getSplitTotalPaidProperty`, `getRemainingBalanceProperty`, `getChangeDueProperty`, `getInstallmentOptionsProperty`, `getCardFeePercentageProperty`, `getMerchantFeeAmountProperty`, `getNetReceivableAmountProperty`, `getCanConsignProperty`, `getPixPayloadProperty`, `getPixQrSvgProperty`, `holdOrder`, `save`, `getCompletedSaleProperty`, `getCompletedSaleWhatsAppUrlProperty`, `getCompletedSaleWhatsAppApiConfiguredProperty`, `getCompletedSaleDesktopPrintReadyProperty`, `printCompletedSaleNow`, `sendSaleEmail`, `sendSaleWhatsApp`, `startNextSale`, `render`.

UI settings/state identifiers (names only): `customerId`, `paymentMethod`, `discount`, `notes`, `search`, `selectedCategoryId`, `orderNumber`, `taxPercent`, `layout`, `activeStandTab`, `touchPage`, `keypadAmount`, `showCustomerSelectModal`, `customerSearch`, `completedSaleId`, `showSaleSuccessModal`, `shareEmail`, `sharePhone`, `emailStatus`, `emailError`, `showQuickCustomerModal`, `newCustomerName`, `newCustomerPhone`, `newCustomerEmail`, `newCustomerCity`, `items`, `showCheckoutModal`, `showInvoicePreview`, `isSplitPayment`, `inModalCustomerSearchOpen`, `inModalNewCustomerOpen`, `inModalCustomerSearch`, `inModalNewCustomerName`, `inModalNewCustomerPhone`, `inModalNewCustomerEmail`, `splitPayments`, `cashTendered`, `dueDate`, `dueReminderAt`, `salespersonId`, `cardType`, `installments`, `convertedFromQuoteId`, `showRegisterGatingModal`, `isStaleMidnightRegister`, `gatingOpeningBalance`, `gatingTerminalId`, `gatingOpeningNotes`, `showGatingDenominations`, `gatingDenominations`, `staleExpectedCash`, `staleCountedCash`, `staleClosingNotes`.

### Source actions: app/Livewire/Tenant/Sales/Index.php

Public actions/helpers: `updatingSearch`, `render`.

UI settings/state identifiers (names only): `search`.

### Source actions: app/Livewire/Tenant/Sales/Show.php

Public actions/helpers: `mount`, `getWhatsAppUrlProperty`, `getWhatsAppApiConfiguredProperty`, `getEmailApiConfiguredProperty`, `getSmsApiConfiguredProperty`, `getDeviceMessageProperty`, `getEmailUrlProperty`, `getSmsUrlProperty`, `sendSms`, `getDesktopPrintReadyProperty`, `printNow`, `openSendModal`, `resetMessage`, `appendPlaceholder`, `sendEmail`, `sendWhatsApp`, `markCompleted`, `cancel`, `render`.

UI settings/state identifiers (names only): `sale`, `recipientEmail`, `recipientPhone`, `attachPdf`, `customMessage`, `activeChannel`, `showSendModal`.

### Source actions: app/Livewire/Tenant/SalesTargets/Index.php

Public actions/helpers: `mount`, `updatedYear`, `updatedMonth`, `loadTargets`, `splitEvenly`, `save`, `render`.

UI settings/state identifiers (names only): `year`, `month`, `companyTargetAmount`, `companyNotes`, `userTargets`, `showEditModal`.

### Source actions: app/Livewire/Tenant/Salon/Calendar.php

Public actions/helpers: `selectCategory`, `toggleCartService`, `clearCart`, `mount`, `newBooking`, `book`, `setStatus`, `shiftDay`, `render`.

UI settings/state identifiers (names only): `date`, `showForm`, `serviceId`, `specialistId`, `customerName`, `customerPhone`, `appointmentDate`, `appointmentTime`, `notes`, `advancePaid`, `activeCategory`, `stylistFilter`, `cartServices`.

### Source actions: app/Livewire/Tenant/Salon/ServiceCatalog.php

Public actions/helpers: `newService`, `edit`, `save`, `delete`, `render`.

UI settings/state identifiers (names only): `showForm`, `editingId`, `name`, `price`, `durationMinutes`, `description`.

### Source actions: app/Livewire/Tenant/Salon/Stylists.php

Public actions/helpers: `toggle`, `render`.

### Source actions: app/Livewire/Tenant/ServiceOrders/Index.php

Public actions/helpers: `updatingSearch`, `updatingStatusFilter`, `openCreateModal`, `selectCustomer`, `addPart`, `removePart`, `updatedPartsUsed`, `updatedLaborCost`, `updatedDiscount`, `recalculateTotals`, `saveServiceOrder`, `getSelectedCustomerProperty`, `getGrandTotalProperty`, `save`, `openEditModal`, `openViewModal`, `updateOrderStatus`, `deleteOrder`, `render`.

UI settings/state identifiers (names only): `search`, `statusFilter`, `priorityFilter`, `technicianFilter`, `showModal`, `isEditing`, `editingOrderId`, `customerId`, `customerName`, `customerPhone`, `customerEmail`, `equipmentName`, `brandModel`, `serialNumber`, `reportedDefect`, `technicalDiagnosis`, `partsUsed`, `partsTotal`, `laborCost`, `discount`, `totalAmount`, `status`, `priority`, `warrantyPeriod`, `warrantyTerms`, `technicianId`, `notes`, `partSearch`, `showViewModal`, `viewingOrder`.

### Source actions: app/Livewire/Tenant/Settings/Index.php

Public actions/helpers: `mount`, `setQuotationColor`, `setThemeColor`, `setPosLayout`, `setReceiptFormat`, `getRestaurantModeLockedProperty`, `getLogoPreviewUrlProperty`, `getFaviconPreviewUrlProperty`, `updatedLogoFile`, `updatedFaviconFile`, `removeLogo`, `removeFavicon`, `removeStoreBannerImage`, `resetStoreBannerToDefault`, `addOtherCurrency`, `removeOtherCurrency`, `save`, `getModelPresetsProperty`, `saveAiConfiguration`, `saveWhatsAppGateway`, `saveSmsGateway`, `saveSmtpGateway`, `saveWebhookGateway`, `saveNotificationPreferences`, `toggleGateway`, `testWhatsApp`, `testSms`, `testSmtp`, `testWebhook`, `openAddChannelModal`, `openEditChannelModal`, `saveChannel`, `deleteChannel`, `openAddPaymentMethodModal`, `openEditPaymentMethodModal`, `savePaymentMethod`, `togglePaymentMethodStatus`, `deletePaymentMethod`, `sendTestEmail`, `newTaxRule`, `editTaxRule`, `addTaxSubComponent`, `removeTaxSubComponent`, `saveTaxRule`, `setDefaultTaxRule`, `deleteTaxRule`, `seedJurisdictionTaxRules`, `preSeedTaxRules`, `createApiKey`, `revokeApiKey`, `toggleApiKey`, `render`, `saveNavConfig`.

UI settings/state identifiers (names only): `company`, `name`, `slug`, `customDomain`, `tradeName`, `taxId`, `email`, `phone`, `website`, `address`, `city`, `state`, `postalCode`, `country`, `currency`, `currencySymbol`, `currencyDecimals`, `currencySymbolPosition`, `otherCurrencies`, `invoicePrefix`, `quotationPrefix`, `invoiceTerms`, `quoteTerms`, `bankDetails`, `logoFile`, `faviconFile`, `newLogo`, `newFavicon`, `logo`, `favicon`, `primaryColor`, `accentColor`, `drawerBg`, `themeColor`, `posLayout`, `receiptFormat`, `defaultCommissionRate`, `defaultCommissionType`, `enableConsignments`, `showCashRegisterAlerts`, `posMode`, `smtpHost`, `smtpPort`, `smtpUsername`, `smtpPassword`, `smtpEncryption`, `smtpFromAddress`, `smtpFromName`, `hasStoredSmtpPassword`, `testEmailTo`, `whatsappPhonePrefix`, `whatsappCustomNote`, `restaurantAlertIntervalMinutes`, `restaurantAlertSoundPreset`, `restaurantAlertSoundUrl`, `whatsappPhoneNumberId`, `whatsappApiToken`, `hasWhatsappApiToken`, `showChannelModal`, `editingChannelId`, `channelName`, `channelIcon`, `channelUrl`, `channelMethod`, `channelHeaders`, `channelAuthType`, `channelAuthValue`, `channelPayloadTemplate`, `channelEventTypes`, `channelIsActive`, `integrationsSubTab`, `whatsappEnabled`, `whatsappProvider`, `metaPhoneNumberId`, `metaWabaId`, `metaAccessToken`, `metaTemplateNamespace`, `twilioWhatsappSid`, `twilioWhatsappToken`, `twilioWhatsappFrom`, `unofficialWhatsappUrl`, `unofficialWhatsappToken`, `whatsappTestPhone`, `whatsappTestResult`, `whatsappTestStatus`, `smsEnabled`, `smsProvider`, `smsTwilioSid`, `smsTwilioToken`, `smsTwilioFrom`, `msg91AuthKey`, `msg91SenderId`, `msg91DltTemplateId`, `genericSmsUrl`, `genericSmsMethod`, `genericSmsApiKey`, `smsTestPhone`, `smsTestResult`, `smsTestStatus`, `smtpEnabled`, `smtpTestEmail`, `smtpTestResult`, `smtpTestStatus`, `webhookEnabled`, `webhookUrl`, `webhookMethod`, `webhookSecret`, `webhookEvents`, `webhookTestResult`, `webhookTestStatus`, `requireCustomerVerification`, `verificationChannels`, `enableOrderNotifications`, `orderNotificationChannels`, `orderNotificationEvents`, `showPaymentMethodModal`, `editingPaymentMethodId`, `pmName`, `pmCode`, `pmDescription`, `pmIsActive`, `pmOrderIndex`, `pmBankName`, `pmAccountNo`, `pmIfscCode`, `pmUpiId`, `pmHolderName`, `pixKeyType`, `pixKey`, `pixMerchantName`, `pixMerchantCity`, `pixQrImage`, `newPixQrImage`, `cardFeeDebit`, `cardFeeCredit1x`, `cardFeeCreditInstallments`, `barcodeScalePrefix`, `barcodeScaleType`, `showTaxRuleModal`, `editingTaxRuleId`, `taxRuleName`, `taxRuleCode`, `taxRuleRate`, `taxRuleType`, `taxRuleIsInclusive`, `taxRuleIsCompound`, `taxRuleIsDefault`, `taxRuleDescription`, `taxRuleSubComponents`, `showApiKeyModal`, `newApiKeyName`, `newApiKeyPermissions`, `recentlyGeneratedToken`, `defaultAiProvider`, `openaiModel`, `geminiModel`, `claudeModel`, `openaiApiKey`, `geminiApiKey`, `claudeApiKey`, `hasOpenaiApiKey`, `hasGeminiApiKey`, `hasClaudeApiKey`, `storeBannerIsActive`, `storeBannerTag`, `storeBannerTitle`, `storeBannerSubtitle`, `storeBannerCtaText`, `storeBannerCtaLink`, `storeBannerImageUrl`, `storeBannerImageFile`, `enableGoogleLogin`, `googleClientId`, `googleClientSecret`, `enableProductReviews`, `requireReviewApproval`, `hasStoredGoogleClientSecret`, `storefrontGateways`, `activeSection`.

### Source actions: app/Livewire/Tenant/Storefront/MenuBuilderComponent.php

Public actions/helpers: `mount`, `updatedActiveLocation`, `addSelectedPages`, `addSelectedCategories`, `addAnchorLink`, `addCustomLink`, `updateMenuOrder`, `toggleStatus`, `startEdit`, `saveEdit`, `cancelEdit`, `deleteItem`, `openCreatePageModal`, `openEditPageModal`, `closePageModal`, `savePage`, `deletePage`, `render`.

UI settings/state identifiers (names only): `activeLocation`, `selectedPages`, `selectedCategories`, `customTitle`, `customUrl`, `customTargetBlank`, `editingId`, `editingTitle`, `editingUrl`, `editingTarget`, `showPageModal`, `editingPageId`, `pageTitle`, `pageSlug`, `pageContent`, `pageMetaTitle`, `pageMetaDescription`, `pageIsPublished`.

### Source actions: app/Livewire/Tenant/Suppliers/Index.php

Public actions/helpers: `updatingSearch`, `newSupplier`, `edit`, `save`, `delete`, `render`.

UI settings/state identifiers (names only): `search`, `showForm`, `editingId`, `name`, `legalName`, `taxId`, `email`, `phone`, `city`, `state`, `active`.

### Source actions: app/Livewire/Tenant/SystemAlarmBanner.php

Public actions/helpers: `mount`, `refreshAlarms`, `dismissOrder`, `dismissInvoice`, `render`.

UI settings/state identifiers (names only): `orderAlarms`, `invoiceAlarms`, `lastOrderChimeAt`, `seenInvoiceIds`.

### Source actions: app/Livewire/Tenant/Units/Index.php

Public actions/helpers: `newUnit`, `edit`, `save`, `delete`, `render`.

UI settings/state identifiers (names only): `showForm`, `editingId`, `name`, `abbreviation`.

### Source actions: app/Livewire/Tenant/Users/Index.php

Public actions/helpers: `mount`, `newInvite`, `openCommissionModal`, `updateCommission`, `invite`, `sendEmailInvite`, `resendInvite`, `updateUserRole`, `toggleUserStatus`, `switchToUser`, `delete`, `render`.

UI settings/state identifiers (names only): `showInviteForm`, `inviteName`, `inviteEmail`, `invitePhone`, `inviteRole`, `inviteCommissionRate`, `inviteCommissionType`, `sendViaEmail`, `showCommissionModal`, `editingUserId`, `editingUserName`, `editingCommissionRate`, `editingCommissionType`, `justInvitedUser`, `justInvitedCode`, `justInvitedLink`, `justInvitedWhatsAppUrl`, `search`.

### Source actions: app/Livewire/Tenant/Users/Permissions.php

Public actions/helpers: `mount`, `updatedSelectedUserId`, `loadUserPermissions`, `applyPreset`, `toggleRow`, `toggleColumn`, `save`, `render`.

UI settings/state identifiers (names only): `selectedUserId`, `targetUser`, `grid`.

### Source actions: app/Mail/ContactInquiryMailable.php

Public actions/helpers: `__construct`, `envelope`, `content`.

### Source actions: app/Mail/DueReminderMailable.php

Public actions/helpers: `__construct`, `envelope`, `content`.

### Source actions: app/Mail/InvoiceMailable.php

Public actions/helpers: `__construct`, `envelope`, `content`, `attachments`.

### Source actions: app/Mail/OtpVerificationMail.php

Public actions/helpers: `__construct`, `envelope`, `content`.

### Source actions: app/Mail/QuotationMailable.php

Public actions/helpers: `__construct`, `envelope`, `content`, `attachments`.

### Source actions: app/Mail/TenantPasswordResetMail.php

Public actions/helpers: `__construct`, `envelope`, `content`.

### Source actions: app/Mail/TenantSubscriptionInvoiceMailable.php

Public actions/helpers: `__construct`, `envelope`, `content`.

### Source actions: app/Models/ActivationCode.php

Public actions/helpers: `idPrefix`, `plan`.

### Source actions: app/Models/AdminSession.php

Public actions/helpers: `admin`, `scopeActive`.

### Source actions: app/Models/AiQuery.php

Public actions/helpers: `idPrefix`.

### Source actions: app/Models/AuditHistory.php

Public actions/helpers: `log`.

### Source actions: app/Models/AuditLog.php

Public actions/helpers: `record`.

### Source actions: app/Models/AutomatedReminderDispatch.php

Public actions/helpers: `company`, `sale`.

### Source actions: app/Models/CashRegister.php

Public actions/helpers: `company`, `opener`, `closer`, `transactions`, `openFor`, `isOpen`, `isStaleMidnight`, `computeMetrics`.

### Source actions: app/Models/CashRegisterTransaction.php

Public actions/helpers: `company`, `cashRegister`, `creator`, `getCategoryLabel`.

### Source actions: app/Models/Category.php

Public actions/helpers: `getSlugAttribute`, `getBrandsAttribute`, `getChecklistItemsAttribute`, `scopeActive`, `scopeOfType`, `scopeForDevices`, `getBrandsListAttribute`, `getChecklistPointsAttribute`, `getIdentifierTypeAttribute`, `products`, `getIconAttribute`.

### Source actions: app/Models/Company.php

Public actions/helpers: `getFormFieldLabels`, `resolveFormFieldLabel`, `formatMoney`, `getLogoUrl`, `getFaviconUrl`, `getDrawerCoverUrl`, `getReceiptFormat`, `idPrefix`, `stores`, `plan`, `users`, `subscriptions`, `subscriptionInvoices`, `paymentMethods`, `inquiries`, `getUnreadInquiriesCountAttribute`, `isSuspended`, `isSubscriptionActive`, `isSubscriptionExpired`, `isExpired`, `getDaysRemainingAttribute`, `isRestaurantMode`, `getNavigationMenuCustomizationAttribute`, `normalizedNavConfig`, `resolveTimezone`, `defaultTimezoneForCountry`, `isGeneralMode`, `licensedModuleKeys`, `hasModule`, `repairChecklistSchema`, `getThemeColor`, `getPrimaryColor`, `getAccentColor`, `getDrawerBg`, `getThemeTokens`, `getPosLayout`, `getThemeColorClasses`, `translations`, `getDefaultLocale`, `getDisplayNameAttribute`, `getBusinessNameAttribute`, `getTradingNameAttribute`, `getSubdomainAttribute`, `getStorefrontUrlAttribute`, `getStorefrontUrl`, `getStoreTypeAttribute`, `getGstinAttribute`, `isDemoPlaceholderName`, `getEffectiveTradeName`, `getDrawerHeaderPayload`, `flushTenantCaches`, `coupons`, `faqs`, `reviews`, `reviewsEnabled`, `getStoreBanner`, `getStorefrontPaymentMethods`.

### Source actions: app/Models/Concerns/BelongsToCompany.php

Public actions/helpers: `scopeForCompany`.

### Source actions: app/Models/Concerns/BelongsToStore.php

Public actions/helpers: `store`.

### Source actions: app/Models/Concerns/HasLegacyStringId.php

Public actions/helpers: `initializeHasLegacyStringId`, `idPrefix`.

### Source actions: app/Models/Concerns/TracksSyncState.php

Public actions/helpers: `scopeUnsynced`, `markSynced`.

### Source actions: app/Models/Configuration.php

Public actions/helpers: `setForCompany`, `getForCompany`.

### Source actions: app/Models/Consignment.php

Public actions/helpers: `customer`, `user`, `company`, `items`, `sale`, `recalculateTotals`.

### Source actions: app/Models/ConsignmentItem.php

Public actions/helpers: `consignment`, `product`.

### Source actions: app/Models/ContactInquiry.php

Public actions/helpers: `isNew`, `isRead`, `isReplied`, `markAsRead`, `markAsReplied`, `scopeSearch`, `scopeStatus`.

### Source actions: app/Models/Coupon.php

Public actions/helpers: `company`, `usages`, `validateForOrder`, `calculateDiscount`.

### Source actions: app/Models/CouponUsage.php

Public actions/helpers: `coupon`, `company`, `customer`, `sale`.

### Source actions: app/Models/CustomNotificationChannel.php

Public actions/helpers: `company`, `handlesEvent`, `iconDisplay`, `isIconUrl`.

### Source actions: app/Models/Customer.php

Public actions/helpers: `isVerified`, `markVerified`, `addresses`, `wishlists`, `wishlistProducts`, `sales`, `leads`, `ledgerEntries`, `getTotalDueAttribute`, `getCompanyNameAttribute`.

### Source actions: app/Models/CustomerAddress.php

Public actions/helpers: `customer`.

### Source actions: app/Models/CustomerLedger.php

Public actions/helpers: `customer`, `sale`, `payment`.

### Source actions: app/Models/CustomerWishlist.php

Public actions/helpers: `customer`, `product`.

### Source actions: app/Models/DiningFloor.php

Public actions/helpers: `idPrefix`, `company`, `tables`.

### Source actions: app/Models/DiningTable.php

Public actions/helpers: `idPrefix`, `company`, `floor`, `currentSale`, `getQrOrderUrl`.

### Source actions: app/Models/DynamicSetting.php

Public actions/helpers: `get`, `put`, `set`, `has`, `forget`, `getTenantSettings`, `putTenantSettings`.

### Source actions: app/Models/Faq.php

Public actions/helpers: `company`, `scopeActive`, `scopeOrdered`, `getDefaultFaqs`, `seedDefaultsForCompany`.

### Source actions: app/Models/GlobalSetting.php

Public actions/helpers: `get`, `set`.

### Source actions: app/Models/Invoice.php

Public actions/helpers: `lead`, `saleItems`, `items`.

### Source actions: app/Models/KitchenTicket.php

Public actions/helpers: `isOverdue`, `isAlarmActive`, `idPrefix`, `company`, `sale`, `table`, `getElapsedMinutes`.

### Source actions: app/Models/Language.php

Public actions/helpers: `isRtl`.

### Source actions: app/Models/MenuItem.php

Public actions/helpers: `clearMenuCache`, `getMenu`, `page`.

### Source actions: app/Models/MessageQueue.php

Public actions/helpers: `sale`, `isRetryable`.

### Source actions: app/Models/NotificationReminder.php

Public actions/helpers: `customer`.

### Source actions: app/Models/OrderPayment.php

Public actions/helpers: `sale`, `company`, `cashRegister`.

### Source actions: app/Models/Page.php

Public actions/helpers: `uniqueSlugFrom`, `creator`.

### Source actions: app/Models/PaymentMethod.php

Public actions/helpers: `idPrefix`, `company`, `getForCompany`.

### Source actions: app/Models/PaymentTransaction.php

Public actions/helpers: `idPrefix`.

### Source actions: app/Models/PendingRegistration.php

Public actions/helpers: `idPrefix`, `isExpired`.

### Source actions: app/Models/Permission.php

Public actions/helpers: `idPrefix`, `user`.

### Source actions: app/Models/PharmacyBatch.php

Public actions/helpers: `company`, `product`, `getStockQuantityAttribute`, `setStockQuantityAttribute`, `getUnitCostAttribute`, `setUnitCostAttribute`, `getDaysUntilExpiryAttribute`, `getExpiryStatusAttribute`, `getExpiryColorAttribute`.

### Source actions: app/Models/PharmacyPrescription.php

Public actions/helpers: `company`, `customer`, `dispensedByUser`, `sale`.

### Source actions: app/Models/Plan.php

Public actions/helpers: `getProductLimitAttribute`, `getInvoiceLimitAttribute`, `getDeviceLimitAttribute`, `getStaffLimitAttribute`, `getEnabledExtensionsAttribute`, `getFeatureListAttribute`, `getExtensionLabel`.

### Source actions: app/Models/PlatformAdmin.php

Public actions/helpers: `idPrefix`, `isSuperAdmin`, `hasRole`, `normalizeRole`.

### Source actions: app/Models/PlatformBranding.php

Public actions/helpers: `getLogoPublicUrl`, `getFaviconPublicUrl`, `publicSettings`, `getHeadOfficeAddress`, `getWorkingHours`, `landingPage`, `getHeroBadge`, `getHeroTitle`, `getHeroSubtitle`, `getHeroCtaPrimaryText`, `getHeroCtaPrimaryUrl`, `getHeroCtaSecondaryText`, `getHeroCtaSecondaryUrl`, `isSectionEnabled`, `landingSectionOrder`, `sectionMeta`, `landingText`, `landingList`, `getSectionTitle`, `getSectionSubtitle`, `getSectionBadge`, `getSectionBody`, `landingHardware`, `landingStatsList`, `landingSolutionsList`, `playStoreLink`, `windowsAppLink`, `hasAnyDownloadLink`, `landingFaqs`, `landingFeatures`, `landingTestimonials`, `testimonialInitials`, `isSmtpConfigured`, `isOtpVerificationRequired`, `current`.

### Source actions: app/Models/PlatformNotification.php

Public actions/helpers: `idPrefix`.

### Source actions: app/Models/PlatformSystem.php

Public actions/helpers: `get`, `set`.

### Source actions: app/Models/Product.php

Public actions/helpers: `getCurrentStockAttribute`, `setCurrentStockAttribute`, `setStoreStock`, `increment`, `decrement`, `scopeLowStock`, `category`, `brand`, `reviews`, `approvedReviews`, `getAverageRatingAttribute`, `getReviewsCountAttribute`, `pharmacyBatches`, `activePharmacyBatches`, `repairParts`, `isService`, `scopeSpareParts`, `scopeServices`, `getPriceAttribute`, `setPriceAttribute`, `getImageUrlOrDefault`, `decrementStock`, `incrementStock`, `getSkuAttribute`, `setSkuAttribute`.

### Source actions: app/Models/ProductReview.php

Public actions/helpers: `company`, `product`, `customer`, `scopeApproved`, `scopeRecent`, `scopeForProduct`, `seedSampleReviewsForCompany`.

### Source actions: app/Models/PublishedCatalog.php

Public actions/helpers: `isExpired`, `company`.

### Source actions: app/Models/PushDevice.php

Public actions/helpers: `idPrefix`, `company`, `user`.

### Source actions: app/Models/PushNotificationSetting.php

Public actions/helpers: `current`, `publicConfig`, `discoverFirebaseConfig`.

### Source actions: app/Models/Quotation.php

Public actions/helpers: `lead`, `saleItems`, `items`, `getQuotationNumberAttribute`.

### Source actions: app/Models/Reminder.php

Public actions/helpers: `customer`, `remindable`, `setNotesAttribute`, `setDescriptionAttribute`, `setCallScriptAttribute`, `setTitleAttribute`, `setSubjectAttribute`, `setDueDateAttribute`, `setDueAtAttribute`, `setTenantIdAttribute`, `setCompanyIdAttribute`, `getNotesAttribute`, `getDescriptionAttribute`, `getCallScriptAttribute`, `getTitleAttribute`, `getSubjectAttribute`, `newEloquentBuilder`, `where`.

### Source actions: app/Models/RepairDeviceCategory.php

Public actions/helpers: `defaultPresets`.

### Source actions: app/Models/RepairTicket.php

Public actions/helpers: `customer`, `company`, `tenant`, `category`, `technician`, `advanceSale`, `finalSale`, `items`, `parts`, `laborItems`, `getPartsCostAttribute`, `getLaborFeeAttribute`, `getTotalAmountAttribute`, `getBalanceDueAttribute`, `syncStoredTotal`, `getStatusColorAttribute`, `getPriorityColorAttribute`, `getDeviceTypeAttribute`, `setDeviceTypeAttribute`, `setLaborFeeAttribute`, `setPartsCostAttribute`, `setTotalAmountAttribute`, `getSerialOrImeiAttribute`, `setSerialOrImeiAttribute`, `getPasscodeOrPatternAttribute`, `setPasscodeOrPatternAttribute`, `getIssueDescriptionAttribute`, `setIssueDescriptionAttribute`, `getAdvancePaidAttribute`, `setAdvancePaidAttribute`, `getDeviceCategoryIdAttribute`, `setDeviceCategoryIdAttribute`, `getTechnicianIdAttribute`, `setTechnicianIdAttribute`, `setStatusAttribute`.

### Source actions: app/Models/RepairTicketItem.php

Public actions/helpers: `ticket`, `product`, `taxRule`, `getPartNameAttribute`.

### Source actions: app/Models/RepairTicketPart.php

Public actions/helpers: `getPartNameAttribute`, `setPartNameAttribute`, `getRepairTicketIdAttribute`, `setRepairTicketIdAttribute`.

### Source actions: app/Models/Role.php

Public actions/helpers: `company`, `scopeForTenant`, `permissionMapFor`, `makeSlug`.

### Source actions: app/Models/SaaSPlan.php

Public actions/helpers: `scopeIsActive`.

### Source actions: app/Models/Sale.php

Public actions/helpers: `customer`, `lead`, `user`, `company`, `table`, `kitchenTickets`, `payments`, `tenant`, `saleItems`, `items`, `getTenantIdAttribute`, `cashRegister`, `getTermsAttribute`, `setTermsAttribute`, `getSubtotalAttribute`, `getDiscountAmountAttribute`, `getTotalAmountAttribute`, `getFlattenedTaxComponentsAttribute`, `newEloquentBuilder`, `getInvoiceNumberAttribute`, `getBalanceDueAttribute`, `getCustomerPhoneAttribute`, `getQuotationNumberAttribute`, `couponUsage`, `getTrackingStatus`.

### Source actions: app/Models/SaleBuilder.php

Public actions/helpers: `where`.

### Source actions: app/Models/SaleItem.php

Public actions/helpers: `sale`, `product`, `batch`, `staff`.

### Source actions: app/Models/SalesTarget.php

Public actions/helpers: `user`, `company`, `getProgress`.

### Source actions: app/Models/SalonAppointment.php

Public actions/helpers: `getAdvanceDepositAttribute`, `company`, `customer`, `service`, `specialist`, `sale`.

### Source actions: app/Models/SduiModule.php

Public actions/helpers: `screens`, `isExtension`, `isLicensed`, `licenseIsExpired`, `scopeLicenseManaged`.

### Source actions: app/Models/SduiScreen.php

Public actions/helpers: `module`.

### Source actions: app/Models/ServiceOrder.php

Public actions/helpers: `sale`, `customer`, `getCustomerRecordAttribute`, `technician`, `company`, `getTicketNumberAttribute`, `getEquipmentBrandAttribute`, `getPartsSubtotalAttribute`, `getGrandTotalAttribute`, `getStatusInfo`, `recalculateTotals`, `generateOrderNumber`.

### Source actions: app/Models/Store.php

Public actions/helpers: `company`, `tenant`, `users`, `getEffectiveTaxIdAttribute`, `getEffectiveAddressAttribute`, `getEffectivePhoneAttribute`.

### Source actions: app/Models/Subscription.php

Public actions/helpers: `idPrefix`, `company`, `plan`.

### Source actions: app/Models/SubscriptionInvoice.php

Public actions/helpers: `idPrefix`, `company`, `subscription`, `plan`, `isPaid`, `getFormattedTotal`, `getFormattedSubtotal`, `getFormattedTax`.

### Source actions: app/Models/TaxRule.php

Public actions/helpers: `idPrefix`, `getComponents`.

### Source actions: app/Models/Tenant.php

Public actions/helpers: `getDisplayNameAttribute`.

### Source actions: app/Models/TenantApiKey.php

Public actions/helpers: `idPrefix`, `user`, `generateToken`, `hasPermission`.

### Source actions: app/Models/TenantCustomPage.php

Public actions/helpers: `company`, `menuItems`, `scopePublished`, `scopeRecent`, `seedDefaultsForCompany`.

### Source actions: app/Models/TenantDocumentTemplate.php

Public actions/helpers: `defaultTemplate`, `getForCompany`, `renderMessage`.

### Source actions: app/Models/TenantDynamicSetting.php

Public actions/helpers: `company`.

### Source actions: app/Models/TenantFeature.php

Public actions/helpers: `company`.

### Source actions: app/Models/TenantInquiry.php

Public actions/helpers: `getTenantIdAttribute`, `setTenantIdAttribute`, `scopeUnread`, `scopeContacted`, `scopeClosed`, `scopeFilter`.

### Source actions: app/Models/TenantNavigationSetting.php

Public actions/helpers: `company`.

### Source actions: app/Models/TenantNotification.php

Public actions/helpers: `idPrefix`.

### Source actions: app/Models/TenantNotificationGateway.php

Public actions/helpers: `company`, `scopeEnabled`, `scopeForChannel`, `getCredential`, `setCredential`, `isConfigured`.

### Source actions: app/Models/TenantSession.php

Public actions/helpers: `user`, `company`, `scopeActive`, `isImpersonation`.

### Source actions: app/Models/TenantSetting.php

Public actions/helpers: `get`, `set`.

### Source actions: app/Models/TenantStoreMenu.php

Public actions/helpers: `company`, `page`, `category`, `scopeVisible`, `scopeLocation`, `scopeOrdered`, `getResolvedUrlAttribute`, `seedDefaultsForCompany`.

### Source actions: app/Models/TenantTranslation.php

Public actions/helpers: `company`, `tenant`, `getCompanyIdAttribute`, `setCompanyIdAttribute`.

### Source actions: app/Models/User.php

Public actions/helpers: `idPrefix`, `company`, `stores`, `currentStore`, `tenant`, `getTenantIdAttribute`, `permissions`, `isPrivilegedRole`, `hasRole`, `hasPermission`.

### Source actions: app/Models/VendorBill.php

Public actions/helpers: `supplier`, `company`, `creator`, `payments`, `getEffectiveVendorNameAttribute`, `isOverdue`.

### Source actions: app/Models/VendorBillPayment.php

Public actions/helpers: `vendorBill`, `company`, `creator`.

### Source actions: app/Observers/SaleObserver.php

Public actions/helpers: `created`, `updated`.

### Source actions: app/Policies/InvoicePolicy.php

Public actions/helpers: `viewAny`, `view`.

### Source actions: app/Policies/SalePolicy.php

Public actions/helpers: `viewAny`, `view`.

### Source actions: app/Providers/AppServiceProvider.php

Public actions/helpers: `register`, `boot`.

### Source actions: app/Providers/AuthGuardServiceProvider.php

Public actions/helpers: `boot`.

### Source actions: app/Providers/LocalizationServiceProvider.php

Public actions/helpers: `register`, `boot`.

### Source actions: app/Providers/ModuleServiceProvider.php

Public actions/helpers: `register`, `boot`, `bootModule`.

### Source actions: app/Providers/NativeAppServiceProvider.php

Public actions/helpers: `boot`, `phpIni`.

### Source actions: app/Scopes/StoreScope.php

Public actions/helpers: `apply`.

### Source actions: app/Services/AiImageGeneratorService.php

Public actions/helpers: `generateProductImage`, `isAvailable`.

### Source actions: app/Services/Auth/CustomerVerificationService.php

Public actions/helpers: `__construct`, `shouldRequireVerification`, `getActiveGatewayChannels`, `generateVerificationCode`, `sendVerificationCode`, `verifyCode`.

### Source actions: app/Services/Auth/DesktopAuthBootstrapService.php

Public actions/helpers: `__construct`, `attemptOnlineBootstrap`, `registerOnline`.

### Source actions: app/Services/Auth/DesktopSessionGuard.php

Public actions/helpers: `clearStaleSessionsOnBoot`.

### Source actions: app/Services/Auth/OtpVerificationService.php

Public actions/helpers: `isOtpRequired`, `configurePlatformMail`, `generateOtp`, `sendOtpForRegistration`, `sendOtpForUser`, `verifyRegistrationOtp`, `verifyUserOtp`.

### Source actions: app/Services/Auth/PermissionChecker.php

Public actions/helpers: `canonicalModuleSlug`, `getActionsForModule`, `getActionDescription`, `can`, `allows`, `roleDefaultAllows`, `flushRoleCache`, `getRoleDefaults`.

### Source actions: app/Services/Auth/PermissionRegistry.php

Public actions/helpers: `userHasPermission`.

### Source actions: app/Services/Auth/TenantAuthService.php

Public actions/helpers: `login`, `logout`, `listSessions`, `revokeSession`, `impersonate`, `endImpersonation`, `mintToken`.

### Source actions: app/Services/Backup/BackupService.php

Public actions/helpers: `create`, `list`, `delete`, `applyRetention`.

### Source actions: app/Services/CardFeeCalculator.php

Public actions/helpers: `calculateInstallments`.

### Source actions: app/Services/CashRegister/CashRegisterReportService.php

Public actions/helpers: `generateVoucherNumber`, `generateZReportPdf`, `generateMovementPdf`, `generateMovementWhatsAppUrl`, `generateZReportWhatsAppUrl`.

### Source actions: app/Services/CommissionService.php

Public actions/helpers: `calculate`, `calculateCommission`, `calculateProfitCommission`, `formatRate`, `normalizeType`.

### Source actions: app/Services/ContactFormService.php

Public actions/helpers: `getDefaultFields`, `getFields`, `saveFields`, `getSettings`, `saveSettings`, `buildValidationRules`, `processSubmission`.

### Source actions: app/Services/Delivery/MessageQueueService.php

Public actions/helpers: `__construct`, `sendOrQueueEmail`, `sendOrQueueWhatsApp`, `processDue`.

### Source actions: app/Services/Delivery/WebhookDispatchService.php

Public actions/helpers: `dispatchEvent`, `dispatch`.

### Source actions: app/Services/Dispatch/DocumentDispatchService.php

Public actions/helpers: `__construct`, `dispatchWhatsApp`, `dispatchEmail`, `dispatchSms`, `dispatchWebhook`, `dispatchCustom`.

### Source actions: app/Services/DispatchChannelService.php

Public actions/helpers: `isSmsConfigured`, `isWhatsAppConfigured`, `isEmailConfigured`, `getAvailableChannels`, `splitChannels`, `groupedComponents`, `documentUtilities`, `visibleChannels`, `apiSettings`, `buildBottomSheetSchema`.

### Source actions: app/Services/Documents/DocumentNumberService.php

Public actions/helpers: `next`.

### Source actions: app/Services/Documents/TenantDocumentResolver.php

Public actions/helpers: `resolveSaleOrInvoice`.

### Source actions: app/Services/Financial/CustomerLedgerService.php

Public actions/helpers: `recordInvoice`, `recordPayment`.

### Source actions: app/Services/FinancialAnalyticsService.php

Public actions/helpers: `getExecutiveDashboardKpis`, `clearCache`.

### Source actions: app/Services/FiscalEInvoicing/EInvoicingDriverInterface.php

Public actions/helpers: `generatePayload`, `validateCompliance`, `generateQrCodeData`, `submitInvoice`.

### Source actions: app/Services/FiscalEInvoicing/FiscalEInvoicingManager.php

Public actions/helpers: `driverForCompany`, `driverForSale`.

### Source actions: app/Services/FiscalEInvoicing/IndiaGstEInvoiceDriver.php

Public actions/helpers: `generatePayload`, `validateCompliance`, `generateQrCodeData`, `submitInvoice`.

### Source actions: app/Services/FiscalEInvoicing/PeppolEInvoiceDriver.php

Public actions/helpers: `generatePayload`, `validateCompliance`, `generateQrCodeData`, `submitInvoice`.

### Source actions: app/Services/FiscalEInvoicing/ZatcaEInvoiceDriver.php

Public actions/helpers: `generatePayload`, `validateCompliance`, `generateQrCodeData`, `submitInvoice`.

### Source actions: app/Services/Installer/EnvironmentWriterService.php

Public actions/helpers: `write`.

### Source actions: app/Services/Integrations/EcommercePayloadNormalizer.php

Public actions/helpers: `__construct`, `process`, `detectPlatform`, `extractExternalId`.

### Source actions: app/Services/Integrations/OutboundWebhookService.php

Public actions/helpers: `dispatch`.

### Source actions: app/Services/Invoice/InvoiceDeliveryService.php

Public actions/helpers: `getSmtpConfig`, `resolveLogoBase64`, `generateQuotationPdf`, `generateInvoicePdf`, `generateReceiptQrCode`, `formatCustomMessage`, `getDefaultQuotationMessage`, `getDefaultInvoiceMessage`, `sendQuotationEmail`, `sendInvoiceEmail`, `buildQuotationWhatsAppMessage`, `generateQuotationWhatsAppUrl`, `buildInvoiceWhatsAppMessage`, `generateInvoiceWhatsAppUrl`, `generateWhatsAppUrl`, `buildDueReminderMessage`, `generateDueReminderWhatsAppUrl`, `sendDueReminderEmail`, `sendTestEmail`, `sendInvitationEmail`, `generateInvitationWhatsAppUrl`.

### Source actions: app/Services/LeadService.php

Public actions/helpers: `getCreateLeadSchema`, `getTabbedLeadManagementSchema`.

### Source actions: app/Services/License/CustomLicenseServerClient.php

Public actions/helpers: `__construct`, `isConfigured`, `storeUrl`, `downloadModule`, `catalog`, `verify`, `issue`.

### Source actions: app/Services/License/EnvatoLicenseVerificationService.php

Public actions/helpers: `verify`.

### Source actions: app/Services/License/LicenseService.php

Public actions/helpers: `__construct`, `getActiveDriver`, `isOfflineFallback`, `verify`, `issue`, `catalog`, `storeUrl`, `downloadModule`, `currentDomain`.

### Source actions: app/Services/Localization/LocalizationService.php

Public actions/helpers: `ensureDefaultLanguages`, `getActiveLanguages`, `getAllLanguages`, `getActiveLocale`, `resolveActiveCompany`, `isValidLocale`, `getActiveLanguage`, `isRtl`, `setLocale`, `flushTranslator`, `getLanguageFilePath`, `getLanguageFileContent`, `saveLanguageFileContent`, `addTranslationKey`, `updateTranslationKey`, `deleteTranslationKey`, `syncMissingKeys`, `createLanguage`, `getTenantTranslations`, `getCompanyTranslations`, `saveTenantTranslation`, `saveCompanyTranslation`, `deleteTenantTranslation`, `getMergedTranslations`, `translationVersion`, `registerModuleTranslations`.

### Source actions: app/Services/Localization/PlatformRegionalService.php

Public actions/helpers: `currencyOptions`, `getCurrencyDetails`, `currencySymbol`, `currencyDecimals`, `currencyPosition`, `languageOptions`, `countryOptions`, `timezoneOptions`, `dialCodeForCountry`, `countryForCurrency`, `dialCodeOptions`, `defaultCurrency`, `defaultLanguage`, `defaultTimezone`, `defaultCountryIso`, `defaultDialCode`, `getPlatformDefaults`, `setPlatformDefaults`.

### Source actions: app/Services/Localization/TenantAwareTranslator.php

Public actions/helpers: `ensureTenantContext`, `flushLoaded`, `get`, `choice`, `hasForLocale`, `has`.

### Source actions: app/Services/Localization/TenantTranslationLoader.php

Public actions/helpers: `__construct`, `load`, `addNamespace`, `addJsonPath`, `namespaces`, `addPath`, `paths`, `jsonPaths`, `getFileLoader`, `resolveActiveTenantId`, `getTenantOverrides`, `flushCache`, `__call`.

### Source actions: app/Services/Modular/ModuleCatalog.php

Public actions/helpers: `available`, `for`, `storeLink`.

### Source actions: app/Services/Modular/ModulePackageService.php

Public actions/helpers: `install`, `installFromLicenseServer`, `activate`, `authorizeExtensionAssignment`, `deactivate`, `verifyAndRecordLicense`, `clearLicense`, `uninstall`, `orphanedModuleDirs`, `pruneOrphanDir`.

### Source actions: app/Services/Modular/ModuleRegistry.php

Public actions/helpers: `extendedSchemas`, `canonicalKey`, `allModules`, `find`, `operatingModules`, `isModuleInstalledAndActive`, `getAvailableModes`, `getAvailableExtensions`, `extensionKeys`, `isExtension`, `modulesFor`, `isActive`, `isInstalled`, `resolveActiveMode`, `enabledRegistrationModes`, `registrationModules`, `availableModes`, `activeFeaturesFor`, `getModule`, `paymentMethodsSchema`, `paymentMethodPresentation`, `statusLabelsSchema`, `taxConfigurationSchema`, `actionPillsSchema`, `getModulesForStoreType`.

### Source actions: app/Services/Navigation/MenuService.php

Public actions/helpers: `populateDefaultNavigation`, `getNavigationForTenant`.

### Source actions: app/Services/Navigation/NavigationAppearanceCustomizer.php

Public actions/helpers: `getAvailableAdminDockItems`.

### Source actions: app/Services/Navigation/NavigationMenuService.php

Public actions/helpers: `isSpecializedTenant`, `enforceRootCommerceItem`, `attachConsignmentsToInventory`, `deduplicateVerticalSections`, `sanitizeSections`, `sanitizeComponents`, `cleanTenantNavigation`.

### Source actions: app/Services/Navigation/NavigationSanitizerService.php

Public actions/helpers: `sanitizeSections`, `sanitizeItems`, `sanitizeItem`, `sanitizeChildren`, `normalizeNavPayload`, `deduplicateStorefrontFromStoreSettings`.

### Source actions: app/Services/Navigation/TenantNavRegistry.php

Public actions/helpers: `resolveTenantModes`, `filterDomainMismatches`, `getEffectiveNavForTenant`, `getBaseNavSectionsForTenant`, `buildCustomNavTree`, `getRetailSalesSection`, `getStorefrontSection`, `getInventorySection`, `getFinancialSection`, `getRestaurantMenuItems`, `getPharmacyMenuItems`, `getRepairMenuItems`, `getSalonMenuItems`, `buildGenericModuleSection`, `menuStructureForModule`, `getAdministrationSection`, `sectionsFor`, `menuStructureForMode`, `withActionableSectionParents`, `formatDrawerItem`, `applyNavigationLabels`, `normalizeSection`, `collapsedFlags`, `normalizeItem`, `settingsTabItems`.

### Source actions: app/Services/Navigation/TenantNavigationConfigService.php

Public actions/helpers: `validationRules`, `normalize`.

### Source actions: app/Services/NotificationAlertService.php

Public actions/helpers: `unreadCount`, `alerts`.

### Source actions: app/Services/Notifications/AutomatedReminderSettingsService.php

Public actions/helpers: `defaults`, `get`, `save`.

### Source actions: app/Services/Notifications/CustomChannelDispatcherService.php

Public actions/helpers: `dispatchEvent`, `dispatch`, `renderTemplate`.

### Source actions: app/Services/Notifications/DeviceMessageService.php

Public actions/helpers: `url`, `appUrl`, `prepare`, `plainText`.

### Source actions: app/Services/Notifications/StorefrontOrderNotificationService.php

Public actions/helpers: `__construct`, `shouldSendNotifications`, `getActiveChannels`, `notifyOrderPlaced`, `notifyOrderStatusChanged`.

### Source actions: app/Services/Notifications/TenantNotificationDispatcherService.php

Public actions/helpers: `__construct`, `getEnabledChannels`, `isChannelActive`, `getGateway`, `dispatchWhatsApp`, `dispatchSms`, `dispatchEmail`, `dispatchWebhook`, `dispatchReceipt`, `dispatchInvoice`, `dispatchQuotation`, `dispatchDueReminder`, `testGateway`.

### Source actions: app/Services/Notifications/VerticalReminderService.php

Public actions/helpers: `__construct`, `schedulePharmacyRefill`, `scheduleSalonFollowUp`, `dispatchDue`.

### Source actions: app/Services/OmnichannelRegistryService.php

Public actions/helpers: `resolveChannels`.

### Source actions: app/Services/Payment/PixService.php

Public actions/helpers: `generatePayload`.

### Source actions: app/Services/Payment/StorefrontPaymentService.php

Public actions/helpers: `resolveCredentials`, `initiatePayment`, `verifyAndCapture`.

### Source actions: app/Services/Payment/SubscriptionPaymentGatewayService.php

Public actions/helpers: `getGatewaySetting`, `getEnabledGateways`, `createRazorpayOrder`, `verifyRazorpayPayment`, `createMercadoPagoPreference`, `verifyMercadoPagoPayment`, `processCardPayment`.

### Source actions: app/Services/Pos/Adapters/PharmacyCartAdapter.php

Public actions/helpers: `__construct`, `getLineItems`, `getCustomer`, `getPrepaidDeposit`, `getModuleContext`.

### Source actions: app/Services/Pos/Adapters/RepairCartAdapter.php

Public actions/helpers: `__construct`, `getLineItems`, `getCustomer`, `getPrepaidDeposit`, `getModuleContext`.

### Source actions: app/Services/Pos/Adapters/SalonCartAdapter.php

Public actions/helpers: `__construct`, `getLineItems`, `getCustomer`, `getPrepaidDeposit`, `getModuleContext`.

### Source actions: app/Services/Pos/SduiPosAdapterInterface.php

Public actions/helpers: `getLineItems`, `getCustomer`, `getPrepaidDeposit`, `getModuleContext`.

### Source actions: app/Services/Printing/DesktopPrintService.php

Public actions/helpers: `__construct`, `isDesktop`, `availablePrinters`, `printReceipt`, `printA4Document`, `defaultPrinterFor`, `setDefaultPrinter`, `printReceiptAuto`, `printA4DocumentAuto`.

### Source actions: app/Services/Push/FirebasePushService.php

Public actions/helpers: `sendToCompany`, `sendToUser`, `mapToSystemSound`.

### Source actions: app/Services/Repair/RepairNotificationService.php

Public actions/helpers: `__construct`, `whatsAppUrl`, `buildCustomerMessage`, `notifyStatusTransition`, `notifyTicketCreated`, `notifyStatusReadyForPickup`, `notifyTechnicianAssigned`, `sendPendingPickupReminders`.

### Source actions: app/Services/Restaurant/KotDeliveryService.php

Public actions/helpers: `find`, `message`, `variables`.

### Source actions: app/Services/Sdui/DashboardSduiService.php

Public actions/helpers: `buildAmountReceivableCard`.

### Source actions: app/Services/Sdui/SchemaResponse.php

Public actions/helpers: `isDarkMode`, `themeToken`, `themeColors`, `container`, `card`, `scrollView`, `gridView`, `accordionGroup`, `column`, `row`, `wrap`, `tabs`, `text`, `callout`, `imageNetwork`, `badge`, `icon`, `divider`, `codeSnippet`, `textInput`, `searchBar`, `cashTenderedField`, `dropdownSelect`, `creatableSelect`, `checkbox`, `toggleSwitch`, `dateTimePicker`, `colorPicker`, `fileUpload`, `filePicker`, `customerSelector`, `lineItemTile`, `tableGrid`, `stepCounter`, `entityRecordCard`, `pipelineStageTracker`, `progressBarStat`, `segmentedFilterChips`, `fabAction`, `buttonPrimary`, `buttonOutlined`, `buttonDanger`, `fab`, `actionSheetTrigger`, `chip`, `postSaleActionData`, `postSaleActionResponse`, `postSaleActionSheet`, `navigateAction`, `formSubmitAction`, `apiPostAction`, `openModalAction`, `addToCartAction`, `openRemoteSheetAction`, `openBottomSheetAction`, `openQuotationModalAction`, `popAction`, `loadRxToPosAction`, `loadRepairToPosAction`, `repairPosPayload`, `openUrlAction`, `filterViewAction`, `screen`, `sheet`, `stepper`, `jsonResponse`, `contract`, `modeView`, `dashboardView`, `profileView`, `brandingView`, `advancedView`, `storefrontBannerAuthView`, `storefrontPaymentGatewaysView`, `storefrontDomainView`, `storefrontInquiriesView`, `storefrontMenusView`, `couponsView`, `faqsView`, `reviewsView`, `restaurantTablesView`, `tableActionsSheet`, `formatKotThermalText`, `kotPrintModalSheet`, `restaurantKdsView`, `pharmacyPosView`, `pharmacyBatchesView`, `pharmacyPrescriptionsView`, `pharmacyRxCreateView`, `repairDashboardView`, `repairCreateTicketView`, `repairTicketsView`, `repairMyJobsView`, `repairDetailView`, `repairCategoriesView`, `repairChecklistSettingsView`, `categoriesView`, `serviceCalendarView`, `salonBookingCreateView`, `serviceStylistsView`, `restaurantPosView`, `diningHistoryView`, `serviceCatalogRatesView`, `serviceOrdersView`, `serviceCreateView`, `formLabelsView`, `salonPosView`, `retailPosView`, `repairPosView`, `posView`, `salesView`, `quotationsView`, `customersView`, `cashRegisterView`, `devicesView`, `changePasswordView`, `rolesView`, `receiptsView`, `hardwareSetupView`, `financialView`, `paymentMethodsView`, `paymentMethodCreateView`, `appearanceView`, `localizationView`, `taxesView`, `taxRuleCreateView`, `apiView`, `apiIntegrationsTabbedView`, `navigationView`, `drawerMenuView`, `moduleView`, `notificationsView`, `screenDirectory`, `verifyOtpView`, `loginView`, `registerView`, `normalizeViewKey`, `storedScreen`, `requiredPermission`, `renderView`.

### Source actions: app/Services/Sdui/SchemaValidator.php

Public actions/helpers: `validate`.

### Source actions: app/Services/Sdui/UniversalPosBuilder.php

Public actions/helpers: `buildCartSheet`, `retailPosScreen`, `pharmacyPosScreen`, `repairPosScreen`, `salonPosScreen`, `restaurantPosScreen`, `genericPosScreen`, `posScreenForModule`, `specialistRosterScreen`, `pharmacyBatchSheet`, `checkoutSheet`, `appendQuery`, `quickCashSuggestions`, `collectCategory`, `envelope`, `sheet`.

### Source actions: app/Services/SmsGatewayService.php

Public actions/helpers: `send`.

### Source actions: app/Services/Stores/StoreContext.php

Public actions/helpers: `bind`, `ensurePrimary`.

### Source actions: app/Services/Subscription/SubscriptionEntitlementService.php

Public actions/helpers: `tenantHasFeature`, `tenantFeatureLimit`, `tenantCanUseExtension`, `tenantLimitRemaining`, `canCreateInvoice`, `canCreateDevice`, `canCreateStaff`, `getEntitlements`.

### Source actions: app/Services/Sync/DesktopSyncClient.php

Public actions/helpers: `__construct`, `isConfigured`, `configure`, `status`, `lastSyncedAt`, `checkConnectivity`, `runCycle`, `hasPendingWork`.

### Source actions: app/Services/Sync/DesktopSyncEngine.php

Public actions/helpers: `registry`, `pull`, `syncableModelClasses`, `rowToPushPayload`, `upsertFromPull`, `push`.

### Source actions: app/Services/Sync/FieldAliasMap.php

Public actions/helpers: `expandForRead`, `normalizeForWrite`.

### Source actions: app/Services/Sync/SyncCompatService.php

Public actions/helpers: `__construct`, `resolveTenant`, `saveTable`, `loadAll`.

### Source actions: app/Services/TaxCalculationService.php

Public actions/helpers: `calculateLineItemTax`, `calculateCartTotals`, `seedTenantDefaultTaxRules`, `getJurisdictionPresets`.

### Source actions: app/Services/TaxEngineService.php

Public actions/helpers: `isIndia`, `buildTaxSummaryFromRates`, `getTaxIdentifierLabel`, `normalizeTaxBreakdown`.

### Source actions: app/Services/TaxService.php

Public actions/helpers: `calculate`, `calculateLine`.

### Source actions: app/Services/Tenancy/TenantProvisioningService.php

Public actions/helpers: `registerTenant`, `seedTenantDemoData`, `create`, `redeemActivationCode`, `createSubscriptionInvoice`, `generateNextInvoiceNumber`, `activatePlan`, `calculateExpiry`.

### Source actions: app/Services/Tenancy/TenantSampleDataService.php

Public actions/helpers: `seed`, `seedBusinessProfile`, `seedShared`, `seedRetail`, `seedRestaurant`, `seedPharmacy`, `seedRepairTechnician`, `seedServiceBooking`, `seedTaxRuleForCountry`, `purgeDemoData`.

### Source actions: app/Services/WhatsApp/WhatsAppCloudApiClient.php

Public actions/helpers: `isConfigured`, `sendText`, `sendDocument`.

### Source actions: app/Support/Desktop.php

Public actions/helpers: `isRunning`, `resolveOrCreatePersistentAppKey`, `rememberActiveCompany`, `activeCompanyId`.

### Source actions: app/Support/HtmlSanitizer.php

Public actions/helpers: `clean`.

### Source actions: app/Support/IdGenerator.php

Public actions/helpers: `make`.

### Source actions: app/Support/Installation.php

Public actions/helpers: `isInstalled`, `markAsInstalled`.

### Source actions: app/View/Composers/TenantNavigationComposer.php

Public actions/helpers: `__construct`, `compose`.

### Source actions: module-packages/leadmanagement/Database/Migrations/2026_09_12_000001_create_lead_module_tables.php

Public actions/helpers: `up`, `down`.

### Source actions: module-packages/leadmanagement/Database/Migrations/2026_09_12_000002_modify_leads_table_integrate_core_crm.php

Public actions/helpers: `up`, `down`.

### Source actions: module-packages/leadmanagement/Database/Migrations/2026_09_13_110000_enhance_reminders_table_fields.php

Public actions/helpers: `up`, `down`.

### Source actions: module-packages/leadmanagement/Http/Controllers/LeadModuleController.php

Public actions/helpers: `middleware`, `__construct`, `dashboard`, `leadsView`, `leadsListEndpoint`, `followupsEndpoint`, `createLeadView`, `createSchema`, `leadsStore`, `resolveLead`, `show`, `leadDetail`, `buildLeadSchemaResponse`, `leadsIndex`, `leadsShow`, `leadsUpdate`, `leadStatus`, `leadConvert`, `leadConvertToInvoice`, `leadAddReminder`, `salesReps`, `customerSearch`, `activitiesView`, `activitiesStore`, `activityComplete`, `sourcesView`, `sourcesStore`.

### Source actions: module-packages/leadmanagement/Models/Lead.php

Public actions/helpers: `source`, `activities`, `customer`, `assignedUser`, `quotations`, `invoices`, `sales`, `reminders`, `setExpectedValueAttribute`, `setEstimatedValueAttribute`.

### Source actions: module-packages/leadmanagement/Models/LeadActivity.php

Public actions/helpers: `lead`.

### Source actions: module-packages/leadmanagement/Models/LeadSource.php

Public actions/helpers: `leads`.

### Source actions: module-packages/leadmanagement/Services/LeadService.php

Public actions/helpers: `__construct`, `searchCustomers`, `autoProvisionCustomer`, `createLead`, `updateLead`, `convertToCustomer`, `convertToInvoice`, `scheduleReminder`, `dispatchNotifications`, `getSalesReps`, `getCreateLeadSchema`, `getTabbedLeadManagementSchema`.

### Source actions: module-packages/pharmacy/Database/Migrations/2026_09_08_000001_create_pharmacy_module_tables.php

Public actions/helpers: `up`, `down`.

### Source actions: module-packages/pharmacy/Http/Controllers/PharmacyModuleController.php

Public actions/helpers: `dashboard`, `batchesView`, `batchesStore`, `prescriptionsView`, `prescriptionsStore`, `prescriptionDetail`, `prescriptionDispense`.

### Source actions: module-packages/pharmacy/Models/Prescription.php

Public actions/helpers: `items`.

### Source actions: module-packages/pharmacy/Models/PrescriptionItem.php

Public actions/helpers: `prescription`.

### Source actions: module-packages/repairtechnician/Database/Migrations/2026_09_08_000001_create_repair_module_tables.php

Public actions/helpers: `up`, `down`.

### Source actions: module-packages/repairtechnician/Http/Controllers/RepairModuleController.php

Public actions/helpers: `dashboard`, `ticketsView`, `ticketsStore`, `ticketDetail`, `ticketStatus`, `categoriesView`, `categoriesStore`.

### Source actions: module-packages/repairtechnician/Models/RepairTicket.php

Public actions/helpers: `items`.

### Source actions: module-packages/repairtechnician/Models/RepairTicketItem.php

Public actions/helpers: `ticket`.

### Source actions: module-packages/salon/Database/Migrations/2026_09_08_000001_create_salon_module_tables.php

Public actions/helpers: `up`, `down`.

### Source actions: module-packages/salon/Http/Controllers/SalonModuleController.php

Public actions/helpers: `dashboard`, `servicesView`, `servicesStore`, `stylistsView`, `stylistsStore`, `appointmentsView`, `appointmentsStore`, `appointmentDetail`, `appointmentStatus`.

### Source actions: module-packages/salon/Models/Appointment.php

Public actions/helpers: `stylist`, `service`.

## Maintenance commands, events, policies and infrastructure source

### Service inventory: app/Console/Commands

- `app/Console/Commands/ApplyLandingReferenceDesignCommand.php`
- `app/Console/Commands/CheckLicenseStatusCommand.php`
- `app/Console/Commands/DispatchAutomatedReminders.php`
- `app/Console/Commands/DispatchScheduledNotifications.php`
- `app/Console/Commands/DispatchVerticalReminders.php`
- `app/Console/Commands/LedgerBackfillCommand.php`
- `app/Console/Commands/SeedLandingContentCommand.php`
- `app/Console/Commands/SendRepairRemindersCommand.php`
- `app/Console/Commands/SetupLandingMatchingColorsCommand.php`

### Service inventory: app/Jobs

- `app/Jobs/DispatchAutomatedCustomerReminder.php`
- `app/Jobs/GenerateProductImage.php`
- `app/Jobs/RunDesktopSyncCycle.php`
- `app/Jobs/SeedTenantSampleDataJob.php`

### Service inventory: app/Observers

- `app/Observers/SaleObserver.php`

### Service inventory: app/Policies

- `app/Policies/InvoicePolicy.php`
- `app/Policies/SalePolicy.php`

### Service inventory: app/Http/Middleware

- `app/Http/Middleware/AuthenticateTenantApi.php`
- `app/Http/Middleware/CheckMaintenanceMode.php`
- `app/Http/Middleware/CheckTenantApiUserPermission.php`
- `app/Http/Middleware/CheckTenantPermission.php`
- `app/Http/Middleware/ClearStoreContext.php`
- `app/Http/Middleware/EnsureAppIsInstalled.php`
- `app/Http/Middleware/EnsureNotInstalled.php`
- `app/Http/Middleware/EnsureTenantEmailIsVerified.php`
- `app/Http/Middleware/EnsureTenantExtension.php`
- `app/Http/Middleware/EnsureTenantPosMode.php`
- `app/Http/Middleware/EnsureTenantSubscriptionActive.php`
- `app/Http/Middleware/EnsureTenantVertical.php`
- `app/Http/Middleware/PeriodicLicenseCheck.php`
- `app/Http/Middleware/PreventDemoModifications.php`
- `app/Http/Middleware/ResolveStoreContext.php`
- `app/Http/Middleware/ResolveTenantContext.php`
- `app/Http/Middleware/SecurityHeaders.php`
- `app/Http/Middleware/SetLocale.php`

### Service inventory: app/Providers

- `app/Providers/AppServiceProvider.php`
- `app/Providers/AuthGuardServiceProvider.php`
- `app/Providers/LocalizationServiceProvider.php`
- `app/Providers/ModuleServiceProvider.php`
- `app/Providers/NativeAppServiceProvider.php`

### Service inventory: app/Events

- `app/Events/TenantRegistered.php`

### Service inventory: app/Listeners

- `app/Listeners/TenantRegisteredListener.php`

Inventory records 2,705 public method declarations across 435 PHP source files with public methods or UI fields. Source coverage includes unmounted and compatibility code; end-to-end verification remains release-specific.
