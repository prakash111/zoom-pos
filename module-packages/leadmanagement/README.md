# Lead Management System — Packaged Vertical Module

A deeply integrated, standalone Lead Management module for the ZoomNearby CRM ecosystem, compatible with the Perfex-style plugin engine (`App\Services\Modular\ModulePackageService`).

---

## Architecture & Integration Highlights

1. **Core Data Model & Database Schema Relations:**
   - Table: `lead_mod_leads` (and database view `leads` for core CRM parity).
   - Foreign relations:
     - `customer_id` (foreign key references `customers(id)` on delete set null).
     - `assigned_to` (foreign key references `users(id)` on delete set null).
     - `sales.lead_id` (indexed foreign reference linking quotations and invoices to leads).
     - `reminders` polymorphic relation (`remindable_type = App\Models\Lead` or `Modules\leadmanagement\Models\Lead`).
   - Pipeline stages: `new` → `contacted` → `qualified` → `proposal_sent` → `won` / `lost`.
   - Soft deletes (`deleted_at`) for audit compliance and data retention.

2. **Customer Auto-Link & Instant CRM Provisioning:**
   - Real-time customer search: `GET /api/v1/tenant/customers/search?q={query}`.
   - When capturing a lead:
     - If `customer_id` is supplied: immediately associates the master customer record.
     - If `customer_id` is empty: searches the tenant's `customers` table by phone or email.
     - If not found: automatically creates and provisions a genuine `Customer` record in the master CRM table (`customers`).

3. **Downstream Quotation & Invoice Integrations:**
   - **Quotations:**
     - `Livewire\Tenant\Quotes\Create` & `QuotationApiController@store` accept `lead_id`.
     - Automatically pre-fills customer, scope, notes, and estimated pricing from the lead.
     - Upon quotation dispatch: automatically advances lead stage to `proposal_sent`.
     - When quotation is accepted or won: automatically transitions associated lead to `won`.
   - **Invoices (1-Tap Conversion):**
     - Endpoint: `POST /api/v1/tenant/leads/{id}/convert-to-invoice`.
     - Generates a draft tax invoice in `sales` table linked via `lead_id`.
     - Immediately marks lead as `won` with full audit activity logging.

4. **Follow-Up Reminders & Push Notifications:**
   - Polymorphic relationship with the master `Reminder` table.
   - Triggers high-priority FCM push notifications to assigned sales representatives via `FirebasePushService`.

5. **Multi-Channel Notification Dispatch:**
   - Integrated with `TenantNotificationDispatcherService`:
     - **WhatsApp:** Auto-sends welcome/inquiry receipt to prospect.
     - **SMS (Twilio / MSG91):** Automated SMS dispatch to prospect.
     - **Webhooks:** Emits `lead_created`, `lead_stage_updated`, and `lead_converted` with HMAC signature.
     - **Firebase Push:** Instant push notifications to assigned sales reps.

6. **Role-Based Access Control (RBAC):**
   - Registered in `PermissionChecker`:
     - `leads.view_any`: Access all organization leads (Administrators & Managers).
     - `leads.view`: Access assigned leads (Sales Representatives).
     - `leads.create`: Capture new leads.
     - `leads.edit`: Update details and move pipeline stages.
     - `leads.delete`: Remove leads.
     - `leads.assign`: Assign leads to team members.
     - `leads.convert`: Convert leads to customers and invoices.

7. **Headless Server-Driven UI (SDUI) — Zero Flutter Touch:**
   - Serves declarative JSON schemas consumable by Flutter mobile & web clients without app store recompilation:
     - `GET /api/v1/tenant/leads/schema`
     - `GET /api/tenant/lead-module/views/create-lead`
     - `GET /api/v1/tenant/staff/sales-reps`

---

## Module Layout

```
module.json                                      Manifest (metadata, navigation, features, permissions)
routes.php                                       Tenant API route declarations
Database/Migrations/
├── 2026_09_12_000001_create_lead_module_tables.php
└── 2026_09_12_000002_modify_leads_table_integrate_core_crm.php
Models/
├── Lead.php                                     Lead model with SoftDeletes & core relations
├── LeadActivity.php                             Activity logs and follow-up tasks
└── LeadSource.php                               Attribution sources
Services/
└── LeadService.php                              Core CRM orchestration, auto-provisioning & SDUI generator
Http/Controllers/
└── LeadModuleController.php                     SDUI views + REST endpoints
README.md                                        Documentation
```

---

## Installation & Deployment

1. **Super Admin → Modules → Upload** `leadmanagement.zip`.
2. Click **Activate**. Migrations run automatically and register all tables and schema constraints.
3. Access tenant workspace: **Lead Management** navigation drawer appears with full permissions and SDUI screens ready.
