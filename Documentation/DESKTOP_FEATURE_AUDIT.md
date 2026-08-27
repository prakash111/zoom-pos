# Desktop production feature audit

Audit date: 2026-08-27. Windows and Linux use the same Electron source and bundled renderer; their feature status is identical. “Integrated” means the canonical Laravel/Livewire feature renders inside a sandboxed Zoom POS Electron window—not an external browser—and retains normal role, subscription and POS-mode middleware.

| Web application feature | Windows | Linux | Desktop access and offline behavior |
| --- | --- | --- | --- |
| Login / registration / logout | Fully implemented | Fully implemented | Bundled screen, encrypted credential storage, cached-session offline start, online validation |
| Dashboard | Fully implemented | Fully implemented | Native cached KPIs offline; canonical dashboard integrated online |
| Retail POS | Fully implemented | Fully implemented | Native offline POS; canonical web POS integrated online |
| Checkout and receipt | Fully implemented | Fully implemented | Native review/payment/order/receipt offline; canonical split-payment checkout online |
| Products | Fully implemented | Fully implemented | Native lookup/create/edit/stock offline; canonical management integrated online |
| Categories | Fully implemented | Fully implemented | Cached offline; complete management integrated online |
| Brands and units | Fully implemented | Fully implemented | Integrated online; cached product attributes remain usable offline |
| Inventory and adjustments | Fully implemented | Fully implemented | Native view/adjustment queue offline; canonical inventory integrated online |
| Customers | Fully implemented | Fully implemented | Native directory/create/edit/lookup offline; canonical management integrated online |
| Customer ledger / Khata | Fully implemented | Fully implemented | Native credit and payment queue offline; receivables integrated online |
| Suppliers | Fully implemented | Fully implemented | Canonical supplier UI integrated online |
| Sales, orders and invoices | Fully implemented | Fully implemented | Native locally cached orders offline; list/detail/PDF/send integrated online |
| Purchases | Not applicable | Not applicable | No standalone purchase-order module exists in the audited tenant web routes |
| Expenses / vendor bills | Fully implemented | Fully implemented | Provided by Payables/vendor bills and integrated online |
| Cash register / Z reports | Fully implemented | Fully implemented | Register, movements and Z reports integrated online |
| Receivables | Fully implemented | Fully implemented | Native balances/payments offline; canonical feature integrated online |
| Payables | Fully implemented | Fully implemented | Canonical feature integrated online |
| Reports / profit and loss | Fully implemented | Fully implemented | Native cached KPIs offline; canonical reports integrated online |
| Payments / split payments | Fully implemented | Fully implemented | Native cash/card/UPI/credit; canonical split payment and merchant fees online |
| Quotes / quotations | Fully implemented | Fully implemented | Create/edit/show/PDF/send/convert integrated online |
| Consignments | Fully implemented | Fully implemented | Canonical feature integrated online |
| Service orders / repairs | Fully implemented | Fully implemented | Canonical feature integrated online |
| Sales targets | Fully implemented | Fully implemented | Canonical feature integrated online |
| Users | Fully implemented | Fully implemented | Users and invitations integrated online |
| Roles and permissions | Fully implemented | Fully implemented | Native tabs use server permissions; integrated routes enforce server middleware |
| Settings / taxes / API / languages / backup | Fully implemented | Fully implemented | Terminal settings native; canonical settings integrated online |
| Subscription / billing / plans | Fully implemented | Fully implemented | Native cached status/redemption; canonical billing and plans integrated online |
| Activation codes | Fully implemented | Fully implemented | Tenant redemption native and integrated; platform administration is not tenant-desktop scope |
| Restaurant POS | Fully implemented | Fully implemented | Canonical restaurant-mode POS integrated and POS-mode guarded |
| Tables / QR cards | Fully implemented | Fully implemented | Canonical table and QR management integrated online |
| KOT / kitchen display | Fully implemented | Fully implemented | Canonical KDS and KOT printing integrated online |
| Public QR ordering | Not applicable | Not applicable | Customer-facing public URL; table/QR administration is in desktop |
| Public catalog | Fully implemented | Fully implemented | Administration integrated; share URL remains customer-facing |
| Devices | Fully implemented | Fully implemented | Canonical device management integrated online |

## Offline and conflict rules

- Sales, adjustments, payments, products and customers use stable client UUIDs.
- Sales use `(company_id, external_id)` uniqueness. Adjustments and payments use transactional `desktop_sync_receipts`, so retry after a lost acknowledgement cannot reapply an operation.
- Checkout writes the sale, stock deductions and credit balance atomically in IndexedDB. A separate checkout draft restores a cart after restart.
- Records are marked synchronized only from server acknowledgement arrays. Timeouts and errors leave them pending.
- One push and one pull may run concurrently. Requests time out, retry backoff is honored, hidden windows do not poll and reconnect triggers an immediate retry.
- Delta pulls skip pending local products/customers: local wins until acknowledged, then the server becomes authoritative.

## Security and performance boundaries

- Renderers have Node disabled, context isolation and sandboxing enabled. The preload API only opens modules and accesses encrypted credentials.
- Desktop keys are user-bound. Native APIs and integrated routes use the same permission matrix as the web UI.
- The internal workspace uses a two-minute one-use bridge, same-origin navigation and cookie clearing on logout.
- Only camera, serial and USB hardware permissions are granted.
- Runtime assets are packaged locally. Polling is 60 seconds, pauses while hidden and cannot create duplicate workers.

## Visual reference

`/directory` is absent. Both supplied images in `read/` and the current web UI were reviewed. The compact POS proportions, fixed cart, card spacing, accent treatment and dedicated login presentation were retained without an unrelated redesign.
