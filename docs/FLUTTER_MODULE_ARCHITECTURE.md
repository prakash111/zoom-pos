# Flutter Module Architecture — assessment & one-time change checklist

**Scope.** This document explains how the Flutter client (`mobile/`, package
`zoom_pos_mobile`) consumes server-driven modules today, states which future
changes need a new Flutter release and which do not, and lists a small set of
one-time hardening changes to apply to `mobile/` so that **any** module added
later on the server surfaces on existing installs with no further app release.

`mobile/` is a separate git repository and is **not** part of the distributed
server ZIP. Nothing in it was modified while preparing the commercial build —
this is an assessment only. The changes in the checklist are for the mobile
team to apply and ship once.

---

## 1. How it works today

### 1.1 One cold-start call

`GET /api/app/bootstrap?locale=<xx>` (`AppBootstrapController::bootstrap`,
`routes/api.php`) returns the whole client runtime in one payload:

| Key | Produced by | Client consumer |
|---|---|---|
| `modules` | `ModuleRegistry::allModules()` | `BootstrapCache.modules` (`Map<String, ModuleSchema>`) |
| `active_module` | `ModuleRegistry::getModule($activeMode)` | `BootstrapCache.activeModule` |
| `available_modes` / `store_types` | `ModuleRegistry::availableModes($company)` | `BootstrapCache.availableModes` |
| `active_features` | `ModuleRegistry::activeFeaturesFor($company)` | feature gates across screens |
| `menu_structure` | tenant nav registry + overrides | `BootstrapCache.menuStructure` → drawer |
| `screens` | `SchemaResponse::screenDirectory($company)` | screen directory |
| `schema_contract` | `SchemaResponse::contract()` | version negotiation |
| `ui_schema` | payment methods, status labels, tax config, action pills | `BootstrapCache.uiSchema` |
| `translations` | `LocalizationService` | `DynamicStringService` |
| `theme`, `nav`, `config`, `navigation_labels`, `form_field_customizations` | tenant settings | branding, labels |

`BootstrapCache` (`lib/core/config/bootstrap_cache.dart`) persists every block
to `SharedPreferences`, so the app opens offline from the last payload and
refreshes in the background. A returning user keeps seeing the cached drawer
while the new payload loads; a new module simply appears on the next refresh.

### 1.2 The drawer is fully server-driven

`_DashboardScreenState._buildDrawer` (`lib/features/dashboard/dashboard_screen.dart`)
iterates `BootstrapCache.instance.effectiveSections` (the server
`menu_structure`, falling back to a cached copy, then to three baseline
sections). For each nav item it calls:

```dart
SduiComponentRegistry.instance.resolve(
  item.component ?? item.key,
  targetEndpoint: item.targetEndpoint,
)
```

Sections, titles, icons, ordering, nesting and visibility all come from the
server. No vertical is named in the drawer code.

### 1.3 `SduiComponentRegistry` — the resolver

`lib/core/sdui/sdui_component_registry.dart` maps a component key → screen
builder with a **four-tier priority and a universal fallback that never
throws**:

1. A pre-registered **native** screen (`pos`, `restaurant_pos`, `floor_plan`,
   `kitchen_display`, `inventory`, `sales`, `cash_register`, `settings`, …).
2. An explicit `target_endpoint` → `DynamicSchemaPage(endpoint: …)`.
3. A key that matches a `BootstrapCache.modules` entry → `DynamicModuleScreen`.
4. **Anything else** → `DynamicSchemaPage(endpoint:
   '/api/tenant/views/<key-with-dashes>')`.

So an unknown module, screen key or endpoint resolves to a server-rendered
page instead of a crash or a "not found". `register(key, builder)` also allows
adding a builder at runtime.

### 1.4 `DynamicSchemaPage` — the generic screen

`lib/core/sdui/screens/dynamic_schema_page.dart` fetches a JSON layout from its
endpoint and renders it with `DynamicSchemaParser.buildComponent`
(`lib/core/sdui/dynamic_schema_parser.dart`), which supports ~60 component
`type`s (containers, inputs, tables, line-item tiles, buttons, tabs, steppers,
action-sheet triggers, the navigation tree builder, …). Unknown `type` →
`SizedBox.shrink()` (silently skipped, never fatal). Actions are dispatched
through `SduiActionDispatcher`.

### 1.5 Module schema on the client

`ModuleSchema.fromJson` (`lib/core/sdui/models/sdui_models.dart`) keeps `id`,
`title`, `description`, `layout_type`, `icon`, `features{}` and
`cart_configuration{}`. `layout_type` is a hint string only —
`'standard_grid'` when absent; the client renders whatever components the
server returns, so a **new `layout_type` (e.g. `repair_kanban`,
`service_booking_list`) needs no client change** as long as the screens it
produces use known component types.

### 1.6 Mode switching

`POST /api/app/mode` (`switchMode`) returns a fresh `menu_structure`;
`BootstrapCache.switchOperatingMode` swaps the drawer and caches it. Native
Retail and Restaurant paths (`PosScreen`, `RestaurantPosScreen`,
`RestaurantTablesScreen`, `RestaurantKdsScreen`) are untouched by any of this.

---

## 2. What does NOT require a Flutter release

A new server-side module (native or licensed package) is fully usable on
existing installs, no app update, when it ships:

- an `sdui_modules` row (`ModuleRegistry` merges it into `allModules()`);
- `menu_structure` entries with `target_endpoint`s (drawer renders them);
- SDUI screen JSON at those endpoints built from existing component `type`s
  (`DynamicSchemaPage` renders it);
- `active_features` flags, `ui_schema` entries (payment methods, status
  labels), and `translations` keys;
- an `icon` string already in `SduiIconRegistry` (unknown → a sane fallback
  icon, not an error).

This covers essentially every "new vertical" case: catalogue + list/detail +
create/edit + status workflow + payment + reporting screens.

---

## 3. When a Flutter change IS unavoidable

Only when the module needs a capability the SDUI layer genuinely cannot
express:

| Need | Why SDUI can't do it | Mitigation (one-time) |
|---|---|---|
| New **hardware** integration (weighing scale, EMV pinpad, RFID, label printer protocol) | needs a platform channel / native plugin | add a native screen or widget, register it under a stable `component` key; the server then references that key freely |
| **Camera / scanner / AR** flow beyond the existing barcode scanner | needs `camera`/ML plugins | same — new native screen keyed by a `component` string |
| A **new interactive widget type** (signature pad, custom chart, map, drag-drop board) not in `DynamicSchemaParser` | parser has no `case` for it | add one `case` to `DynamicSchemaParser.buildComponent`, keyed by a schema `type` string; every module can use it afterwards |
| Offline-first **local persistence** for a module's own data | bootstrap cache only stores the schema | add a Drift/SQLite table + sync in `mobile/` |

The pattern in every row: **add the primitive to the client once, keyed by a
string the server sends.** After that the server composes it without further
app releases. There is no way around shipping the native primitive itself —
that is the one unavoidable limitation, and it is inherent to any
native-mobile + SDUI design.

---

## 4. One-time change checklist for `mobile/`

Apply these once and ship. Each removes a place where the client still assumes
the original five verticals, so that a differently-named future module behaves
identically to Retail/Restaurant.

### 4.1 Stop inferring screen choice from the mode name

- **File:** `lib/core/sdui/sdui_component_registry.dart` →
  `_resolveServiceOrdersScreen()`.
- **Current:** picks `ServiceOrdersScreen` vs a service-catalog
  `DynamicSchemaPage` by testing whether `BootstrapCache.activeMode` string
  `contains('repair' | 'auto' | 'tech' | 'service_order')`.
- **Change:** drive the choice from the server — honour the nav item's
  `target_endpoint` if present, else a `module.features` flag
  (e.g. `has_repair_workflow`). Remove the substring sniffing.
- **Why:** a future services-type module with a different slug (`workshop`,
  `garage`, `field_service`) would silently get the wrong screen.

### 4.2 Make `DynamicModuleScreen` entry points server-driven

- **Files:** `lib/core/sdui/models/sdui_models.dart` (`ModuleSchema`),
  `lib/core/sdui/screens/dynamic_module_screen.dart`.
- **Current:** `ModuleSchema.fromJson` drops any `navigation` / `actions` /
  `routes` the server sends; `DynamicModuleScreen` then shows three hardcoded
  tiles ("Launch Point of Sale", "Catalog & Inventory", "Transaction History")
  wired to fixed action keys `pos` / `inventory` / `sales`.
- **Change:** keep a `List<ModuleNavEntry> navigation` (or a generic
  `Map<String,dynamic> extra`) on `ModuleSchema`; in `DynamicModuleScreen`
  render one tile per server entry (label + icon + `target_endpoint`/action),
  falling back to the current three only when the server sends none.
- **Why:** this is the only screen that still hardcodes a vertical's shape. It
  is reached rarely (tier 3 of the resolver), but a module whose capabilities
  aren't "POS + inventory + sales" gets misleading tiles today.

### 4.3 Surface unknown SDUI component types in debug builds

- **File:** `lib/core/sdui/dynamic_schema_parser.dart` → `buildComponent`
  `default:` branch.
- **Current:** returns `SizedBox.shrink()` — a server component the client
  doesn't understand vanishes with no trace.
- **Change:** in `kDebugMode`, return a small visible "unsupported component:
  <type>" placeholder (keep `SizedBox.shrink()` in release).
- **Why:** makes it obvious during rollout when a new module's schema depends
  on a component type the shipped client predates (i.e. when §3 row 3
  actually applies).

### 4.4 Confirm the drawer renders modules with no local feature folder

- **File:** `lib/features/dashboard/dashboard_screen.dart` →
  `_buildDrawer` / the section-compile helper (~line 100–160).
- **Check:** a `menu_structure` section whose items are all unknown
  `component` keys with `target_endpoint`s still renders (it should — each item
  goes through `SduiComponentRegistry.resolve`, which falls through to
  `DynamicSchemaPage`). Add a widget test that feeds a synthetic "future
  module" section and asserts the tiles appear and open a `DynamicSchemaPage`.
- **Why:** locks the guarantee in §2 with a regression test.

### 4.5 (Optional) Prefer `target_endpoint` over key aliases in module manifests

- **Files:** server `module-packages/<key>/module.json` nav entries (already
  the case for `salon` and `repairtechnician`).
- **Note:** the registry's pharmacy/repair/salon `component`-key aliases
  (`pharmacy_prescriptions`, `repair_pos`, `salon_pos`, …) are additive
  compatibility shims. New modules should always ship `target_endpoint` on
  their nav items so they never depend on a client-side alias.

### 4.6 Nothing to do: `layout_type`

No code path throws on an unknown `layout_type`; it is a server-side builder
hint and the client renders the resulting component tree generically. New
layout types (`repair_kanban`, `service_booking_list`, future ones) need no
client change.

---

## 5. Summary

- The client is **already a generic, module-capable SDUI renderer**: the
  drawer, screens, icons, features, translations, payment methods and status
  labels are all server-driven, and every unknown key falls back to a
  server-rendered page rather than failing.
- A new server-side module that stays within the existing SDUI component
  vocabulary reaches every installed app on its next bootstrap refresh, with
  **no Flutter release**.
- The checklist in §4 removes the last few vertical-specific assumptions
  (`_resolveServiceOrdersScreen` mode sniffing, `DynamicModuleScreen`'s
  hardcoded tiles) and adds regression coverage.
- The one irreducible limitation: a module that needs a **new native
  primitive** (hardware, camera/AR, a brand-new interactive widget) requires
  that primitive to be added to `mobile/` once, keyed by a string the server
  sends. After that, the server composes it freely.
