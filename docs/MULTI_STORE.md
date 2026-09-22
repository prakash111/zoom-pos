# Tenant stores and branches

## Deployment

Run `php artisan migrate --force` before releasing the Flutter client, then
`php artisan view:cache`. In particular, migration
`2026_09_21_000002_create_stores_and_store_stock` must be applied: without it,
store discovery returns HTTP 500 because the `stores` table is missing.
The migration backfills each tenant's main branch, staff assignments and stock.

## API

All routes require tenant authentication and enforce staff branch assignments.

- `GET /api/v1/tenant/stores`: `data` contains authorized active branches;
  `meta` includes the tenant's total store count, plan limit, current store ID,
  creation/management grants, and default phone dial code. Managers may request
  `include_inactive=1`. Legacy `stores`, `store_count`, and `store_limit` aliases
  remain available to installed apps.
- `POST /api/v1/tenant/stores`: requires `stores.create`. Accepts name, optional
  code, phone, email, address and tax ID. Generates a unique code when omitted,
  normalizes phone numbers, attaches the creator, seeds zero stock and receipt
  prefixes, and selects the new branch. Quota exhaustion returns HTTP 403 with
  `error: quota_exceeded` and `upgrade_required: true`.
- `PUT /api/v1/tenant/stores/{id}`: requires `stores.manage` (or legacy
  `stores.edit`). Supports details and `is_active`. The main branch and branches
  with an open cash session cannot be deactivated.
- `POST /api/v1/tenant/stores/{id}/switch`: requires `stores.view` and assignment
  to the active target branch. The older `/stores/switch` URL accepting
  `store_id` remains supported.

The application uses TenantApiKey/TenantSession authentication. Store selection
is persisted on the user and resolved on every request; token replacement is
unnecessary. Flutter sends `X-Store-Id` for explicit request context. An inactive
header can recover during discovery/switching, but is rejected for normal POS
and stock operations. Foreign-tenant and unassigned headers are always rejected.

Cash register rows represent sessions, not physical drawers. Branch creation
seeds the default drawer in `stores.settings.cash_register`; opening a session
uses that branch's default terminal ID without creating an artificial session.

## Management and limits

Laravel: **Store Settings → Stores & Branches** (`/tenant/settings/stores`).
Flutter: the same drawer child (`/settings/store/branches`) and the switcher
footer. Creation appears only when permission and plan capacity allow it.
Super Admin → Plans controls the store limit; `-1` means unlimited. Inactive
branches count toward the quota.

Flutter refreshes bootstrap and dashboard analytics on branch changes, partitions
store data caches, and holds background sync while changing request context.
Pending offline changes must finish syncing before switching.

## Regression checks

- `php artisan test tests/Feature/Tenant/MultiStoreTest.php`
- From `mobile`: `flutter test test/store_provider_test.dart`
