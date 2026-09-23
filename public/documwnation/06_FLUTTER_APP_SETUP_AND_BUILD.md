# Flutter App — Features, Instructions and Release Builds

The client source is present in `mobile/` as a separate ignored source tree. Its package is `zoom_pos_mobile`, version `1.0.5+7`, with Dart constraint `>=3.4.0 <4.0.0`. This chapter is based on the actual client files.

## Initial setup and sign-in

1. Launch the client and enter the backend HTTPS address on Server Address.
2. Save and inspect the license-verification result; resolve network/domain/entitlement errors before proceeding.
3. Sign in, register a store or use password recovery as appropriate; complete OTP where required.
4. Select an assigned active store. Review bootstrap branding, available menus and subscription access.
5. Configure the printer and app appearance; verify a small sale and synchronization.

The server-address license flow is implemented in `core/security/license_security_engine.dart`. It normalizes the URL, signs `server_url|timestamp|platform` using HMAC-SHA256 and sends the signature in the JSON body to the reconstructed endpoint. Chapter 08 distinguishes this client handshake from backend purchase verification.

## App feature families and instructions

The native app screens below complement the Laravel workflows in chapter 00. Server-driven pages provide additional module and settings surfaces.

| Feature family | Included controls and operations | Instructions and benefit |
| --- | --- | --- |
| Landing and contact | Landing content, interactive inquiry fields, plan listing/details | Open the app landing page; review plans or submit the contact form; carries platform marketing into app onboarding |
| Authentication | Auth gate, login, registration, OTP, password recovery, social-auth integration | Configure server, sign in and complete verification; maintains the tenant session |
| Dashboard | Metric grid, sparkline/chart widgets, receivable panel, date selection and layout picker | Choose a range and tap a metric; reaches the underlying record list |
| Stores | Store provider, management, create/edit and switch | Select the branch before inventory or sales operations; preserves store context |
| Inventory | Inventory/management screens, product form, stock adjustment and bulk import | Create/import products and adjust stock from Inventory; reduces repeated data entry |
| Catalog administration | Categories, brands, units, suppliers and their forms | Maintain reusable catalog records from the menu |
| Retail POS | Product/cart screens, customer picker, checkout, invoice preview/actions and universal POS | Select items/customer, review totals and checkout; serves cashier sales |
| Sales | Sales list, detail, due filters and document actions | Filter and open the sale; track payment and customer delivery |
| Quotations | List, detail, product picker and quotation form | Prepare and share an offer; convert when accepted |
| Customers | Customer list/form, credit ledger and payment sheet | Maintain profiles and record account collections |
| Receivables | Due-receivables list and reminders | Prioritize outstanding accounts and initiate follow-up |
| Payables | Bill list/form and bill-payment sheet | Record supplier obligations and settlements |
| Cash register | Current register, opening/closing/movement sheets and history | Open the shift, record movements and reconcile closing cash |
| Restaurant | Tables, order screen, POS, KDS, KOT slip, elapsed-time chips and QR stand | Select a table, send to kitchen, update preparation and settle |
| Consignments | List, form and detail/reconciliation | Record dispatched goods and finalize sold/returned quantities |
| Service orders | List, form and detail | Follow service work and associated parts/status |
| Reports and analytics | Report tabs/ranges/export and analytics screen | Select dates, inspect metrics and export results |
| Sales targets | Targets screen and allocation | Enter period targets and save the allocation |
| Catalog publishing | Product selection and publish sheet | Publish a shareable catalog and manage its link |
| Storefront | Storefront controls, product reviews and store inquiries | Manage the online presence and review incoming feedback |
| Tax | Rules screen/form and shared tax services | Configure tax rules and validate a representative checkout |
| Staff and permissions | Staff list, invite sheet and permission matrix | Invite staff, assign access and inspect permitted menus |
| Devices | Device list/revoke | Retire an old device session |
| Subscription | Plan/activation/payment screen | Renew or activate access using the offered flow |
| Language | Language picker and dynamic/cached translations | Select language and refresh server phrases |
| Document templates | Invoice/quotation settings tab | Adjust document appearance, preview and save |
| Printers | Global setup, selection dialog and thermal/PDF services | Select the supported printer and print a test receipt |
| Notifications | Push service, local sound alerts and device registration | Enable permissions, configure sound/repeats and test delivery |
| SDUI industry pages | Dynamic module/schema screens and action dispatcher | Refresh bootstrap after module changes; open the available industry menu |
| Offline operation | Cached records, mutation/sale outboxes, sync state, retries and conflict history | Inspect pending/error state and sync when connected; see chapter 07 for limits |

## Personalization and desktop behavior

Open App Preferences to select brand color, drawer/canvas surfaces, dashboard layout, system/light/dark theme, page-transition style and dock position (left, top, right or bottom). Reset individual overrides or all colors to follow defaults. Navigation settings also expose ordering/hierarchy and menu configuration. Some preferences are local; tenant branding changes can be pushed to the backend.

Desktop widgets provide a content area, title/chrome, status bar and window-close guard. Verify closing/logout behavior on the intended desktop build. Adaptive sheets, loading/error views, image fallbacks, barcode scanning, PDF preview and unified document dispatch are shared controls used throughout the screens.

## Build and distribute the client

Run inside `mobile/`, not the Laravel root:

```bash
cd mobile
flutter doctor -v
flutter pub get
flutter build apk --release --split-per-abi
flutter build appbundle --release
```

Android configuration currently uses application ID/namespace `com.zoomnearby.zoompos`, Java 17 and minimum SDK 23. Compile/target SDK versions follow the Flutter build configuration. Configure the release keystore using the supported `android/key.properties` or release environment variables. Keep signing credentials private. Rebranding must update the identifiers, displayed names, icons and provider registrations together.

For Windows, use Windows with Visual Studio's Desktop development with C++ tools and SDK, then run `flutter build windows --release`. Package the complete release directory and required runtimes. See [Windows setup](https://docs.flutter.dev/platform-integration/windows/setup) and [Windows distribution](https://docs.flutter.dev/platform-integration/windows/building).

For web, run `flutter build web --release` and deploy its generated web bundle with appropriate routing and backend connectivity; see [web deployment](https://docs.flutter.dev/deployment/web). Review target/plugin compatibility before promising iOS, Linux or macOS support; the presence of Flutter source is not evidence of a tested signed release on every platform.

### Release verification

Verify server setup, login/OTP, tenant/store switching, permissions, checkout, invoice dispatch, one offline sale and its sync, notification delivery and the target printer. Check a restricted staff account as well as an owner. This documentation update does not compile or distribute new app binaries.

## Complete Flutter source-surface inventory

Every Dart file under `mobile/lib` is included below, grouped by feature or core area. This includes shared widgets and alternate/legacy surfaces so that smaller capabilities remain discoverable. A file entry is source coverage, not proof it is reachable in every build. User instructions are in the feature families above and chapters 00, 05 and 07.

### App source: core/api

- **Api Client** — `mobile/lib/core/api/api_client.dart`.
- **Api Exception** — `mobile/lib/core/api/api_exception.dart`.

### App source: core/config

- **App Config** — `mobile/lib/core/config/app_config.dart`.
- **Bootstrap Cache** — `mobile/lib/core/config/bootstrap_cache.dart`.
- **Countries** — `mobile/lib/core/config/countries.dart`.
- **Dashboard Layout** — `mobile/lib/core/config/dashboard_layout.dart`.
- **Locale Provider** — `mobile/lib/core/config/locale_provider.dart`.
- **Nav Dock Provider** — `mobile/lib/core/config/nav_dock_provider.dart`.
- **Page Transitions** — `mobile/lib/core/config/page_transitions.dart`.
- **Platform Branding Provider** — `mobile/lib/core/config/platform_branding_provider.dart`.
- **Tax Jurisdictions** — `mobile/lib/core/config/tax_jurisdictions.dart`.
- **Theme** — `mobile/lib/core/config/theme.dart`.
- **Theme Provider** — `mobile/lib/core/config/theme_provider.dart`.

### App source: core/models

- **Analytics Model** — `mobile/lib/core/models/analytics_model.dart`.
- **Brand Model** — `mobile/lib/core/models/brand_model.dart`.
- **Cash Register Model** — `mobile/lib/core/models/cash_register_model.dart`.
- **Category Model** — `mobile/lib/core/models/category_model.dart`.
- **Company Model** — `mobile/lib/core/models/company_model.dart`.
- **Consignment Model** — `mobile/lib/core/models/consignment_model.dart`.
- **Customer Model** — `mobile/lib/core/models/customer_model.dart`.
- **Dashboard Summary Model** — `mobile/lib/core/models/dashboard_summary_model.dart`.
- **Device Session Model** — `mobile/lib/core/models/device_session_model.dart`.
- **Language Model** — `mobile/lib/core/models/language_model.dart`.
- **Ledger Entry Model** — `mobile/lib/core/models/ledger_entry_model.dart`.
- **Permission Grid Model** — `mobile/lib/core/models/permission_grid_model.dart`.
- **Product Model** — `mobile/lib/core/models/product_model.dart`.
- **Published Catalog Model** — `mobile/lib/core/models/published_catalog_model.dart`.
- **Quotation Model** — `mobile/lib/core/models/quotation_model.dart`.
- **Receivable Model** — `mobile/lib/core/models/receivable_model.dart`.
- **Report Models** — `mobile/lib/core/models/report_models.dart`.
- **Restaurant Models** — `mobile/lib/core/models/restaurant_models.dart`.
- **Sale Model** — `mobile/lib/core/models/sale_model.dart`.
- **Sales Target Model** — `mobile/lib/core/models/sales_target_model.dart`.
- **Service Order Model** — `mobile/lib/core/models/service_order_model.dart`.
- **Settings Models** — `mobile/lib/core/models/settings_models.dart`.
- **Staff User Model** — `mobile/lib/core/models/staff_user_model.dart`.
- **Subscription Model** — `mobile/lib/core/models/subscription_model.dart`.
- **Supplier Model** — `mobile/lib/core/models/supplier_model.dart`.
- **Tax Rule Model** — `mobile/lib/core/models/tax_rule_model.dart`.
- **Unit Model** — `mobile/lib/core/models/unit_model.dart`.
- **User Model** — `mobile/lib/core/models/user_model.dart`.
- **Vendor Bill Model** — `mobile/lib/core/models/vendor_bill_model.dart`.

### App source: core/navigation

- **Navigation Provider** — `mobile/lib/core/navigation/navigation_provider.dart`.

### App source: core/providers

- **Dashboard Provider** — `mobile/lib/core/providers/dashboard_provider.dart`.
- **Sales Provider** — `mobile/lib/core/providers/sales_provider.dart`.

### App source: core/sdui

- **App Router** — `mobile/lib/core/sdui/app_router.dart`.
- **Navigation Tree Builder** — `mobile/lib/core/sdui/components/navigation_tree_builder.dart`.
- **Dynamic Schema Context** — `mobile/lib/core/sdui/dynamic_schema_context.dart`.
- **Dynamic Schema Parser** — `mobile/lib/core/sdui/dynamic_schema_parser.dart`.
- **Sdui Models** — `mobile/lib/core/sdui/models/sdui_models.dart`.
- **Schema Cache** — `mobile/lib/core/sdui/schema_cache.dart`.
- **Dynamic Module Screen** — `mobile/lib/core/sdui/screens/dynamic_module_screen.dart`.
- **Dynamic Schema Page** — `mobile/lib/core/sdui/screens/dynamic_schema_page.dart`.
- **Sdui Action Dispatcher** — `mobile/lib/core/sdui/sdui_action_dispatcher.dart`.
- **Sdui Component Registry** — `mobile/lib/core/sdui/sdui_component_registry.dart`.
- **Sdui Icon Registry** — `mobile/lib/core/sdui/sdui_icon_registry.dart`.
- **Sdui Tab Advancer** — `mobile/lib/core/sdui/sdui_tab_advancer.dart`.

### App source: core/security

- **License Security Engine** — `mobile/lib/core/security/license_security_engine.dart`.

### App source: core/services

- **Desktop Window** — `mobile/lib/core/services/desktop/desktop_window.dart`.
- **Window Close Guard** — `mobile/lib/core/services/desktop/window_close_guard.dart`.
- **Dynamic String Service** — `mobile/lib/core/services/dynamic_string_service.dart`.
- **Push Notification Service** — `mobile/lib/core/services/push_notification_service.dart`.
- **Sound Alert Service** — `mobile/lib/core/services/sound_alert_service.dart`.
- **Offline Writeable** — `mobile/lib/core/services/sync/offline_writeable.dart`.
- **Sync Engine** — `mobile/lib/core/services/sync/sync_engine.dart`.
- **Sync Status Badge** — `mobile/lib/core/services/sync/sync_status_badge.dart`.
- **Tenant Time Service** — `mobile/lib/core/services/tenant_time_service.dart`.
- **Thermal Printer Service** — `mobile/lib/core/services/thermal/thermal_printer_service.dart`.
- **Translations Cache** — `mobile/lib/core/services/translations_cache.dart`.

### App source: core/storage

- **App Database** — `mobile/lib/core/storage/app_database.dart`.
- **App Preferences** — `mobile/lib/core/storage/app_preferences.dart`.
- **Secure Storage Service** — `mobile/lib/core/storage/secure_storage_service.dart`.
- **Session Cache** — `mobile/lib/core/storage/session_cache.dart`.

### App source: core/stores

- **Store Provider** — `mobile/lib/core/stores/store_provider.dart`.

### App source: core/utils

- **Color Utils** — `mobile/lib/core/utils/color_utils.dart`.
- **Currency Formatter** — `mobile/lib/core/utils/currency_formatter.dart`.
- **File Download** — `mobile/lib/core/utils/file_download/file_download.dart`.
- **File Download Io** — `mobile/lib/core/utils/file_download/file_download_io.dart`.
- **File Download Stub** — `mobile/lib/core/utils/file_download/file_download_stub.dart`.
- **File Download Web** — `mobile/lib/core/utils/file_download/file_download_web.dart`.
- **Image Url** — `mobile/lib/core/utils/image_url.dart`.
- **Responsive** — `mobile/lib/core/utils/responsive.dart`.

### App source: core/widgets

- **Adaptive Sheet** — `mobile/lib/core/widgets/adaptive_sheet.dart`.
- **App Design System** — `mobile/lib/core/widgets/app_design_system.dart`.
- **App Network Image** — `mobile/lib/core/widgets/app_network_image.dart`.
- **Barcode Scanner Screen** — `mobile/lib/core/widgets/barcode_scanner_screen.dart`.
- **Coming Soon Screen** — `mobile/lib/core/widgets/coming_soon_screen.dart`.
- **Dashboard Kit** — `mobile/lib/core/widgets/dashboard_kit.dart`.
- **Dashboard Shell** — `mobile/lib/core/widgets/dashboard_shell.dart`.
- **Desktop Chrome** — `mobile/lib/core/widgets/desktop_chrome.dart`.
- **Desktop Status Bar** — `mobile/lib/core/widgets/desktop_status_bar.dart`.
- **Error View** — `mobile/lib/core/widgets/error_view.dart`.
- **Loading Indicator** — `mobile/lib/core/widgets/loading_indicator.dart`.
- **Desktop Content Area** — `mobile/lib/core/widgets/responsive/desktop_content_area.dart`.
- **Sdui Cart Components** — `mobile/lib/core/widgets/sdui/sdui_cart_components.dart`.
- **Sdui Catalog Layouts** — `mobile/lib/core/widgets/sdui/sdui_catalog_layouts.dart`.
- **Sdui Containers** — `mobile/lib/core/widgets/sdui/sdui_containers.dart`.
- **Sdui Controls** — `mobile/lib/core/widgets/sdui/sdui_controls.dart`.
- **Sdui Modals** — `mobile/lib/core/widgets/sdui/sdui_modals.dart`.
- **Split Navigation Tile** — `mobile/lib/core/widgets/split_navigation_tile.dart`.
- **Tappable Scale** — `mobile/lib/core/widgets/tappable_scale.dart`.
- **Unified Document Dispatch Sheet** — `mobile/lib/core/widgets/unified_document_dispatch_sheet.dart`.
- **Webview Screen** — `mobile/lib/core/widgets/webview_screen.dart`.

### App source: features/analytics

- **Analytics Repository** — `mobile/lib/features/analytics/analytics_repository.dart`.
- **Analytics Screen** — `mobile/lib/features/analytics/screens/analytics_screen.dart`.
- **Analytics Widgets** — `mobile/lib/features/analytics/widgets/analytics_widgets.dart`.

### App source: features/auth

- **Auth Provider** — `mobile/lib/features/auth/auth_provider.dart`.
- **Auth Repository** — `mobile/lib/features/auth/auth_repository.dart`.
- **Auth Gate** — `mobile/lib/features/auth/screens/auth_gate.dart`.
- **Forgot Password Screen** — `mobile/lib/features/auth/screens/forgot_password_screen.dart`.
- **Login Screen** — `mobile/lib/features/auth/screens/login_screen.dart`.
- **Register Screen** — `mobile/lib/features/auth/screens/register_screen.dart`.
- **Verify Otp Screen** — `mobile/lib/features/auth/screens/verify_otp_screen.dart`.
- **Auth Illustration** — `mobile/lib/features/auth/widgets/auth_illustration.dart`.
- **Auth Scaffold** — `mobile/lib/features/auth/widgets/auth_scaffold.dart`.
- **Auth Widgets** — `mobile/lib/features/auth/widgets/auth_widgets.dart`.

### App source: features/cash_register

- **Cash Register Provider** — `mobile/lib/features/cash_register/cash_register_provider.dart`.
- **Cash Register Repository** — `mobile/lib/features/cash_register/cash_register_repository.dart`.
- **Cash Movement Sheet** — `mobile/lib/features/cash_register/screens/cash_movement_sheet.dart`.
- **Cash Register Screen** — `mobile/lib/features/cash_register/screens/cash_register_screen.dart`.
- **Close Register Sheet** — `mobile/lib/features/cash_register/screens/close_register_sheet.dart`.
- **Open Register Sheet** — `mobile/lib/features/cash_register/screens/open_register_sheet.dart`.
- **Register History Screen** — `mobile/lib/features/cash_register/screens/register_history_screen.dart`.
- **Cash Register Status Card** — `mobile/lib/features/cash_register/widgets/cash_register_status_card.dart`.

### App source: features/catalog

- **Catalog Repository** — `mobile/lib/features/catalog/catalog_repository.dart`.
- **Catalog Screen** — `mobile/lib/features/catalog/screens/catalog_screen.dart`.
- **Publish Catalog Sheet** — `mobile/lib/features/catalog/screens/publish_catalog_sheet.dart`.

### App source: features/catalog_admin

- **Brands Repository** — `mobile/lib/features/catalog_admin/brands_repository.dart`.
- **Categories Repository** — `mobile/lib/features/catalog_admin/categories_repository.dart`.
- **Brand Form Dialog** — `mobile/lib/features/catalog_admin/screens/brand_form_dialog.dart`.
- **Catalog Admin Screen** — `mobile/lib/features/catalog_admin/screens/catalog_admin_screen.dart`.
- **Category Form Sheet** — `mobile/lib/features/catalog_admin/screens/category_form_sheet.dart`.
- **Supplier Form Sheet** — `mobile/lib/features/catalog_admin/screens/supplier_form_sheet.dart`.
- **Unit Form Dialog** — `mobile/lib/features/catalog_admin/screens/unit_form_dialog.dart`.
- **Suppliers Repository** — `mobile/lib/features/catalog_admin/suppliers_repository.dart`.
- **Units Repository** — `mobile/lib/features/catalog_admin/units_repository.dart`.

### App source: features/consignments

- **Consignments Repository** — `mobile/lib/features/consignments/consignments_repository.dart`.
- **Consignment Detail Screen** — `mobile/lib/features/consignments/screens/consignment_detail_screen.dart`.
- **Consignment Form Sheet** — `mobile/lib/features/consignments/screens/consignment_form_sheet.dart`.
- **Consignments Screen** — `mobile/lib/features/consignments/screens/consignments_screen.dart`.

### App source: features/customers

- **Customers Provider** — `mobile/lib/features/customers/customers_provider.dart`.
- **Customers Repository** — `mobile/lib/features/customers/customers_repository.dart`.
- **Customer Form Sheet** — `mobile/lib/features/customers/screens/customer_form_sheet.dart`.
- **Customer Ledger Screen** — `mobile/lib/features/customers/screens/customer_ledger_screen.dart`.
- **Customers Screen** — `mobile/lib/features/customers/screens/customers_screen.dart`.
- **Record Payment Sheet** — `mobile/lib/features/customers/screens/record_payment_sheet.dart`.

### App source: features/dashboard

- **Dashboard Provider** — `mobile/lib/features/dashboard/dashboard_provider.dart`.
- **Dashboard Screen** — `mobile/lib/features/dashboard/dashboard_screen.dart`.
- **Dashboard Layout Screen** — `mobile/lib/features/dashboard/screens/dashboard_layout_screen.dart`.
- **Dock Rail Slot** — `mobile/lib/features/dashboard/widgets/dock_rail_slot.dart`.
- **Oroit Dashboard** — `mobile/lib/features/dashboard/widgets/oroit_dashboard.dart`.
- **Posh Dashboard** — `mobile/lib/features/dashboard/widgets/posh_dashboard.dart`.
- **Redesigned Metric Dashboard** — `mobile/lib/features/dashboard/widgets/redesigned_metric_dashboard.dart`.

### App source: features/devices

- **Devices Repository** — `mobile/lib/features/devices/devices_repository.dart`.
- **Devices Screen** — `mobile/lib/features/devices/screens/devices_screen.dart`.

### App source: features/inventory

- **Inventory Provider** — `mobile/lib/features/inventory/inventory_provider.dart`.
- **Inventory Repository** — `mobile/lib/features/inventory/inventory_repository.dart`.
- **Adjust Stock Sheet** — `mobile/lib/features/inventory/screens/adjust_stock_sheet.dart`.
- **Bulk Import Screen** — `mobile/lib/features/inventory/screens/bulk_import_screen.dart`.
- **Inventory Management Screen** — `mobile/lib/features/inventory/screens/inventory_management_screen.dart`.
- **Inventory Screen** — `mobile/lib/features/inventory/screens/inventory_screen.dart`.
- **Product Form Sheet** — `mobile/lib/features/inventory/screens/product_form_sheet.dart`.

### App source: features/landing

- **Landing Data** — `mobile/lib/features/landing/models/landing_data.dart`.
- **Landing Screen** — `mobile/lib/features/landing/screens/landing_screen.dart`.
- **Contact Form Service** — `mobile/lib/features/landing/services/contact_form_service.dart`.
- **Landing Provider** — `mobile/lib/features/landing/services/landing_provider.dart`.
- **Interactive Contact Form** — `mobile/lib/features/landing/widgets/interactive_contact_form.dart`.

### App source: features/languages

- **Languages Repository** — `mobile/lib/features/languages/languages_repository.dart`.
- **Languages Screen** — `mobile/lib/features/languages/screens/languages_screen.dart`.

### App source: features/navigation

- **App Drawer** — `mobile/lib/features/navigation/presentation/widgets/app_drawer.dart`.

### App source: features/payables

- **Payables Provider** — `mobile/lib/features/payables/payables_provider.dart`.
- **Payables Repository** — `mobile/lib/features/payables/payables_repository.dart`.
- **Bill Form Sheet** — `mobile/lib/features/payables/screens/bill_form_sheet.dart`.
- **Payables Screen** — `mobile/lib/features/payables/screens/payables_screen.dart`.
- **Record Bill Payment Sheet** — `mobile/lib/features/payables/screens/record_bill_payment_sheet.dart`.

### App source: features/pos

- **Cart Item** — `mobile/lib/features/pos/cart_item.dart`.
- **Held Carts Store** — `mobile/lib/features/pos/held_carts_store.dart`.
- **Payment Entry** — `mobile/lib/features/pos/payment_entry.dart`.
- **Pos Provider** — `mobile/lib/features/pos/pos_provider.dart`.
- **Rx Cart Handoff** — `mobile/lib/features/pos/rx_cart_handoff.dart`.
- **Sales Repository** — `mobile/lib/features/pos/sales_repository.dart`.
- **Cart Sheet** — `mobile/lib/features/pos/screens/cart_sheet.dart`.
- **Customer Picker Sheet** — `mobile/lib/features/pos/screens/customer_picker_sheet.dart`.
- **Invoice Actions Sheet** — `mobile/lib/features/pos/screens/invoice_actions_sheet.dart`.
- **Invoice Preview Screen** — `mobile/lib/features/pos/screens/invoice_preview_screen.dart`.
- **Pos Screen** — `mobile/lib/features/pos/screens/pos_screen.dart`.

### App source: features/pos_universal

- **Local Cart** — `mobile/lib/features/pos_universal/local_cart.dart`.
- **Pos Screen Model** — `mobile/lib/features/pos_universal/pos_screen_model.dart`.
- **Universal Pos Screen** — `mobile/lib/features/pos_universal/screens/universal_pos_screen.dart`.
- **Universal Catalog Card** — `mobile/lib/features/pos_universal/widgets/universal_catalog_card.dart`.

### App source: features/quotations

- **Quotations Provider** — `mobile/lib/features/quotations/quotations_provider.dart`.
- **Quotations Repository** — `mobile/lib/features/quotations/quotations_repository.dart`.
- **Product Picker Sheet** — `mobile/lib/features/quotations/screens/product_picker_sheet.dart`.
- **Quotation Detail Screen** — `mobile/lib/features/quotations/screens/quotation_detail_screen.dart`.
- **Quotation Form Sheet** — `mobile/lib/features/quotations/screens/quotation_form_sheet.dart`.
- **Quotations Screen** — `mobile/lib/features/quotations/screens/quotations_screen.dart`.

### App source: features/receivables

- **Receivables Repository** — `mobile/lib/features/receivables/receivables_repository.dart`.
- **Due Receivables Screen** — `mobile/lib/features/receivables/screens/due_receivables_screen.dart`.

### App source: features/repair

- **Ticket Share Sheet** — `mobile/lib/features/repair/ticket_share_sheet.dart`.

### App source: features/reports

- **Reports Provider** — `mobile/lib/features/reports/reports_provider.dart`.
- **Reports Repository** — `mobile/lib/features/reports/reports_repository.dart`.
- **Reports Screen** — `mobile/lib/features/reports/screens/reports_screen.dart`.

### App source: features/restaurant

- **Restaurant Repository** — `mobile/lib/features/restaurant/restaurant_repository.dart`.
- **Restaurant Kds Screen** — `mobile/lib/features/restaurant/screens/restaurant_kds_screen.dart`.
- **Restaurant Order Screen** — `mobile/lib/features/restaurant/screens/restaurant_order_screen.dart`.
- **Restaurant Pos Screen** — `mobile/lib/features/restaurant/screens/restaurant_pos_screen.dart`.
- **Restaurant Tables Screen** — `mobile/lib/features/restaurant/screens/restaurant_tables_screen.dart`.
- **Kot Slip** — `mobile/lib/features/restaurant/widgets/kot_slip.dart`.
- **Minutes Chip Row** — `mobile/lib/features/restaurant/widgets/minutes_chip_row.dart`.
- **Qr Stand Dialog** — `mobile/lib/features/restaurant/widgets/qr_stand_dialog.dart`.

### App source: features/sales

- **Sale Detail Screen** — `mobile/lib/features/sales/screens/sale_detail_screen.dart`.
- **Sales Screen** — `mobile/lib/features/sales/screens/sales_screen.dart`.

### App source: features/sales_targets

- **Sales Targets Repository** — `mobile/lib/features/sales_targets/sales_targets_repository.dart`.
- **Sales Targets Screen** — `mobile/lib/features/sales_targets/screens/sales_targets_screen.dart`.

### App source: features/service_orders

- **Service Order Details Screen** — `mobile/lib/features/service_orders/screens/service_order_details_screen.dart`.
- **Service Order Form Sheet** — `mobile/lib/features/service_orders/screens/service_order_form_sheet.dart`.
- **Service Orders Screen** — `mobile/lib/features/service_orders/screens/service_orders_screen.dart`.
- **Service Orders Repository** — `mobile/lib/features/service_orders/service_orders_repository.dart`.

### App source: features/settings

- **Navigation Menu Screen** — `mobile/lib/features/settings/presentation/screens/navigation_menu_screen.dart`.
- **Navigation Reorder Screen** — `mobile/lib/features/settings/presentation/screens/navigation_reorder_screen.dart`.
- **App Preferences Screen** — `mobile/lib/features/settings/screens/app_preferences_screen.dart`.
- **Change Password Screen** — `mobile/lib/features/settings/screens/change_password_screen.dart`.
- **Document Templates Tab** — `mobile/lib/features/settings/screens/document_templates_tab.dart`.
- **Global Printer Setup Screen** — `mobile/lib/features/settings/screens/global_printer_setup_screen.dart`.
- **Nav Menu Settings Tab** — `mobile/lib/features/settings/screens/nav_menu_settings_tab.dart`.
- **Payment Methods Screen** — `mobile/lib/features/settings/screens/payment_methods_screen.dart`.
- **Printer Selection Dialog** — `mobile/lib/features/settings/screens/printer_selection_dialog.dart`.
- **Store Domain Screen** — `mobile/lib/features/settings/screens/store_domain_screen.dart`.
- **Tenant Settings Screen** — `mobile/lib/features/settings/screens/tenant_settings_screen.dart`.
- **Server Settings Screen** — `mobile/lib/features/settings/server_settings_screen.dart`.
- **Settings Repository** — `mobile/lib/features/settings/settings_repository.dart`.

### App source: features/staff

- **Invite User Sheet** — `mobile/lib/features/staff/screens/invite_user_sheet.dart`.
- **Permissions Screen** — `mobile/lib/features/staff/screens/permissions_screen.dart`.
- **Staff Screen** — `mobile/lib/features/staff/screens/staff_screen.dart`.
- **Staff Repository** — `mobile/lib/features/staff/staff_repository.dart`.

### App source: features/storefront

- **Product Reviews Screen** — `mobile/lib/features/storefront/screens/product_reviews_screen.dart`.
- **Store Inquiries Screen** — `mobile/lib/features/storefront/screens/store_inquiries_screen.dart`.
- **Storefront Screen** — `mobile/lib/features/storefront/screens/storefront_screen.dart`.

### App source: features/stores

- **Store Editor Dialog** — `mobile/lib/features/stores/store_editor_dialog.dart`.
- **Store Management Screen** — `mobile/lib/features/stores/store_management_screen.dart`.
- **Store Switcher Sheet** — `mobile/lib/features/stores/store_switcher_sheet.dart`.

### App source: features/subscription

- **Subscription Screen** — `mobile/lib/features/subscription/screens/subscription_screen.dart`.
- **Subscription Repository** — `mobile/lib/features/subscription/subscription_repository.dart`.

### App source: features/taxes

- **Tax Form Sheet** — `mobile/lib/features/taxes/screens/tax_form_sheet.dart`.
- **Taxes Screen** — `mobile/lib/features/taxes/screens/taxes_screen.dart`.
- **Taxes Repository** — `mobile/lib/features/taxes/taxes_repository.dart`.

### App source: l10n

- **App Localizations** — `mobile/lib/l10n/app_localizations.dart`.
- **App Strings Ar** — `mobile/lib/l10n/app_strings_ar.dart`.
- **App Strings De** — `mobile/lib/l10n/app_strings_de.dart`.
- **App Strings En** — `mobile/lib/l10n/app_strings_en.dart`.
- **App Strings Es** — `mobile/lib/l10n/app_strings_es.dart`.
- **App Strings Fr** — `mobile/lib/l10n/app_strings_fr.dart`.
- **App Strings Hi** — `mobile/lib/l10n/app_strings_hi.dart`.
- **App Strings Id** — `mobile/lib/l10n/app_strings_id.dart`.
- **App Strings It** — `mobile/lib/l10n/app_strings_it.dart`.
- **App Strings Ja** — `mobile/lib/l10n/app_strings_ja.dart`.
- **App Strings Pt** — `mobile/lib/l10n/app_strings_pt.dart`.
- **App Strings Ru** — `mobile/lib/l10n/app_strings_ru.dart`.
- **App Strings Tr** — `mobile/lib/l10n/app_strings_tr.dart`.
- **App Strings Zh** — `mobile/lib/l10n/app_strings_zh.dart`.
- **Translations Cache** — `mobile/lib/l10n/translations_cache.dart`.

### App source: main.dart

- **Main** — `mobile/lib/main.dart`.

### App source: screens/auth

- **Server Address Screen** — `mobile/lib/screens/auth/server_address_screen.dart`.

### App source: screens/dashboard

- **Amount Receivable Card** — `mobile/lib/screens/dashboard/widgets/amount_receivable_card.dart`.
- **Metric Cards Grid** — `mobile/lib/screens/dashboard/widgets/metric_cards_grid.dart`.
- **Sales Overview Chart** — `mobile/lib/screens/dashboard/widgets/sales_overview_chart.dart`.

### App source: screens/inventory

- **Product Form Screen** — `mobile/lib/screens/inventory/product_form_screen.dart`.

### App source: screens/pos

- **Invoice View Screen** — `mobile/lib/screens/pos/invoice_view_screen.dart`.
- **Receipt Preview Screen** — `mobile/lib/screens/pos/receipt_preview_screen.dart`.

### App source: widgets

- **App Drawer** — `mobile/lib/widgets/app_drawer.dart`.
- **Tenant Logo Avatar** — `mobile/lib/widgets/tenant_logo_avatar.dart`.

### App source: widgets/inputs

- **Phone Number Field** — `mobile/lib/widgets/inputs/phone_number_field.dart`.

### App source: widgets/modals

- **Create Store Modal** — `mobile/lib/widgets/modals/create_store_modal.dart`.
