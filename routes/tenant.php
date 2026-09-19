<?php

use App\Http\Controllers\Sync\CatalogViewController;
use App\Http\Controllers\Api\DispatchController as ApiDispatchController;
use App\Http\Controllers\Api\UnifiedDispatchController;
use App\Http\Controllers\Api\DocumentDispatchController;
use App\Http\Controllers\Api\DocumentActionController as ApiDocumentActionController;
use App\Http\Controllers\Api\DocumentPreviewController as ApiDocumentPreviewController;
use App\Http\Controllers\Api\NotificationController as ApiNotificationController;
use App\Http\Controllers\Tenant\Auth\PasswordResetController;
use App\Http\Controllers\Tenant\BackupDownloadController;
use App\Http\Controllers\Tenant\CashRegisterSlipController;
use App\Http\Controllers\Tenant\ImpersonationController;
use App\Http\Controllers\Tenant\InvoiceController;
use App\Http\Controllers\Tenant\NavigationMenuController;
use App\Http\Controllers\Tenant\PwaManifestController;
use App\Http\Controllers\Tenant\QuotationController;
use App\Http\Controllers\Tenant\RepairPortalController;
use App\Http\Controllers\Api\V1\RepairApiController;
use App\Http\Controllers\Tenant\Restaurant\KotController;
use App\Http\Controllers\Tenant\Restaurant\TableOrderController;
use App\Http\Controllers\Tenant\SubscriptionInvoiceController;
use App\Http\Controllers\Tenant\UserPreferenceController;
use App\Http\Middleware\CheckMaintenanceMode;
use App\Http\Middleware\ResolveTenantContext;
use App\Livewire\Auth\AcceptInvite;
use App\Livewire\Auth\TenantLogin;
use App\Livewire\Auth\TenantRegister;
use App\Livewire\Auth\VerifyOtp;
use App\Livewire\Tenant\Billing;
use App\Livewire\Tenant\Brands;
use App\Livewire\Tenant\Catalog;
use App\Livewire\Tenant\Categories;
use App\Livewire\Tenant\Consignments\Create;
use App\Livewire\Tenant\Consignments\Index;
use App\Livewire\Tenant\Consignments\Show;
use App\Livewire\Tenant\Customers;
use App\Livewire\Tenant\Dashboard;
use App\Livewire\Tenant\Devices;
use App\Livewire\Tenant\Financials;
use App\Livewire\Tenant\Languages;
use App\Livewire\Tenant\Pharmacy\Batches;
use App\Livewire\Tenant\Pharmacy\Prescriptions;
use App\Livewire\Tenant\Products;
use App\Livewire\Tenant\Quotes;
use App\Livewire\Tenant\Repair\TicketDetail;
use App\Livewire\Tenant\Repair\Tickets;
use App\Livewire\Tenant\Reports;
use App\Livewire\Tenant\Restaurant;
use App\Livewire\Tenant\Sales;
use App\Livewire\Tenant\Salon\Calendar;
use App\Livewire\Tenant\Salon\ServiceCatalog;
use App\Livewire\Tenant\Salon\Stylists;
use App\Livewire\Tenant\Settings;
use App\Livewire\Tenant\Suppliers;
use App\Livewire\Tenant\Units;
use App\Livewire\Tenant\Users;
use App\Models\CashRegister;
use App\Models\CashRegisterTransaction;
use App\Models\DiningTable;
use App\Models\KitchenTicket;
use App\Models\Sale;
use App\Models\SubscriptionInvoice;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::model('quote', Sale::class);
Route::model('kot', KitchenTicket::class);
Route::model('table', DiningTable::class);
Route::model('subscription_invoice', SubscriptionInvoice::class);
Route::model('register', CashRegister::class);
Route::model('tx', CashRegisterTransaction::class);

Route::prefix('tenant')->name('tenant.')->middleware(CheckMaintenanceMode::class)->group(function () {
    Route::get('/login', TenantLogin::class)
        ->middleware('guest:web')
        ->name('login');

    Route::get('/register', TenantRegister::class)
        ->middleware('guest:web')
        ->name('register');

    Route::middleware('guest:web')->group(function () {
        Route::get('/forgot-password', [PasswordResetController::class, 'showForgotForm'])->name('password.request');
        Route::post('/forgot-password', [PasswordResetController::class, 'sendResetLink'])->name('password.email');
        Route::get('/reset-password/{token}', [PasswordResetController::class, 'showResetForm'])->name('password.reset.form');
        Route::post('/reset-password', [PasswordResetController::class, 'reset'])->name('password.reset');
    });

    Route::get('/verify-otp', VerifyOtp::class)
        ->middleware('auth:web')
        ->name('verify_otp');

    Route::post('/logout', function () {
        Auth::guard('web')->logout();

        return redirect()->route('tenant.login');
    })->middleware('auth:web')->name('logout');

    Route::middleware(['auth:web', ResolveTenantContext::class, 'tenant.verified'])->group(function () {
        Route::get('/app.webmanifest', PwaManifestController::class)->name('pwa.manifest');

        // Billing, Invoicing & Activation (Always reachable by tenant admin even if expired)
        Route::get('/billing', Billing\Index::class)->name('billing.index');
        Route::get('/activate', Billing\Index::class)->name('activate');
        Route::get('/billing/invoices/{invoice}/pdf', [SubscriptionInvoiceController::class, 'pdf'])->name('billing.invoices.pdf');

        // Store Settings & Languages (Reachable by tenant admin to manage store configs)
        Route::get('/settings', Settings\Index::class)->middleware('tenant.permission:settings,view')->name('settings.index');
        Route::get('/settings/mode', Settings\Index::class)->middleware('tenant.permission:settings,view')->name('settings.mode');
        Route::get('/settings/profile', Settings\Index::class)->middleware('tenant.permission:settings,view')->name('settings.profile');
        Route::get('/settings/receipts', Settings\Index::class)->middleware('tenant.permission:settings,view')->name('settings.receipts');
        Route::get('/settings/financial', Settings\Index::class)->middleware('tenant.permission:settings,view')->name('settings.financial');
        Route::get('/settings/taxes', Settings\Index::class)->middleware('tenant.permission:settings,view')->name('settings.taxes');
        Route::get('/settings/api', Settings\Index::class)->middleware('tenant.permission:settings,view')->name('settings.api');
        Route::get('/settings/integrations', Settings\Index::class)->middleware('tenant.permission:settings,view')->name('settings.integrations');
        Route::get('/settings/navigation', Settings\Index::class)->middleware('tenant.permission:settings,view')->name('settings.navigation');
        Route::post('/settings/navigation-menu', [NavigationMenuController::class, 'store'])
            ->middleware('tenant.permission:settings,edit')
            ->name('settings.navigation-menu.store');
        Route::post('/settings/change-password', [PasswordResetController::class, 'changePassword'])->name('settings.change-password');

        // Per-user workspace preference — the dockable nav position. Any
        // signed-in user may move their own dock; no settings permission.
        Route::post('/preferences/dock-position', [UserPreferenceController::class, 'updateDockPosition'])
            ->name('preferences.dock-position');
        Route::redirect('/settings-redirect', '/tenant/settings')->name('settings');
        Route::get('/settings/backup/download', [BackupDownloadController::class, 'download'])->middleware('tenant.permission:settings,view')->name('settings.backup.download');
        Route::get('/languages', Languages\Index::class)->middleware('tenant.permission:settings,view')->name('languages.index');

        // Core Dashboard & POS Operations (Protected by Subscription Status Middleware)
        Route::middleware('tenant.subscription')->group(function () {
            Route::get('/', Dashboard::class)->name('dashboard');

            // Session-authenticated SDUI endpoints used by the web dashboard.
            // They share the same controllers and schemas as the Sanctum API
            // endpoints so browser and Flutter surfaces stay in sync.
            Route::get('/notifications/feed', [ApiNotificationController::class, 'feed'])
                ->name('notifications.feed');
            Route::post('/notifications/clear-all', [ApiNotificationController::class, 'clearAll'])
                ->name('notifications.clear-all');
            Route::post('/notifications/{type}/{id}/dismiss', [ApiNotificationController::class, 'dismiss'])
                ->name('notifications.dismiss');
            Route::post('/notifications/{id}/dismiss', [ApiNotificationController::class, 'dismiss'])
                ->name('notifications.dismiss.simple');
            Route::get('/documents/{type}/{id}/preview-modal', [ApiDocumentPreviewController::class, 'previewModal'])
                ->name('documents.preview-modal');
            Route::get('/documents/{type}/{id}/render-html', [ApiDocumentPreviewController::class, 'renderHtml'])
                ->name('documents.render-html');
            Route::get('/documents/{type}/{id}/actions-sheet', [ApiDocumentActionController::class, 'actionsSheet'])
                ->middleware('tenant.permission:sales,view')
                ->name('documents.actions-sheet');
            Route::post('/dispatch/sms', [ApiDispatchController::class, 'dispatchSms'])
                ->name('dispatch.sms');
            Route::post('/dispatch/email', [ApiDispatchController::class, 'dispatchEmail'])
                ->name('dispatch.email');
            // Browser-session equivalents of the SDUI dispatch endpoints.
            // The mobile client uses /api/v1/... with a bearer token; the web
            // dashboard must stay on the auth:web session and CSRF cookie.
            Route::post('/dispatch/send', [UnifiedDispatchController::class, 'dispatch'])
                ->middleware('tenant.permission:pos,create')
                ->name('dispatch.send');
            Route::post('/dispatch/batch', [UnifiedDispatchController::class, 'batchDispatch'])
                ->middleware('tenant.permission:pos,create')
                ->name('dispatch.batch');
            Route::post('/dispatch/batch-send', [UnifiedDispatchController::class, 'batchDispatch'])
                ->middleware('tenant.permission:pos,create')
                ->name('dispatch.batch-send');
            Route::post('/dispatch/{type}/{id}', [ApiDispatchController::class, 'dispatchDocument'])
                ->name('dispatch.document');
            Route::post('/documents/dispatch', [DocumentDispatchController::class, 'dispatchDocument'])
                ->middleware('tenant.permission:pos,create')
                ->name('documents.dispatch');
            Route::get('/documents/{type}/{id}/dispatch-options', [DocumentDispatchController::class, 'getDispatchOptions'])
                ->middleware('tenant.permission:sales,view')
                ->name('documents.dispatch-options');

            Route::get('/products', Products\Index::class)->middleware('tenant.permission:products,view')->name('products.index');
            Route::get('/categories', Categories\Index::class)->middleware('tenant.permission:categories,view')->name('categories.index');
            Route::get('/brands', Brands\Index::class)->middleware('tenant.permission:categories,view')->name('brands.index');
            Route::get('/units', Units\Index::class)->middleware('tenant.permission:units,view')->name('units.index');
            Route::get('/suppliers', Suppliers\Index::class)->middleware('tenant.permission:suppliers,view')->name('suppliers.index');

            Route::get('/customers', Customers\Index::class)->middleware('tenant.permission:customers,view')->name('customers.index');

            Route::get('/sales', Sales\Index::class)->middleware('tenant.permission:sales,view')->name('sales.index');
            Route::get('/sales/create', Sales\Create::class)->middleware(['tenant.permission:pos,create', 'tenant.pos_mode:general'])->name('sales.create');
            Route::get('/sales/{sale}', Sales\Show::class)->middleware('tenant.permission:sales,view')->name('sales.show');
            Route::get('/sales/{sale}/pdf', [InvoiceController::class, 'pdf'])->middleware('tenant.permission:sales,view')->name('sales.pdf');
            Route::post('/sales/{sale}/send', [InvoiceController::class, 'send'])->middleware('tenant.permission:sales,export')->name('sales.send');
            Route::post('/sales/{sale}/send-custom', [InvoiceController::class, 'sendCustom'])->middleware('tenant.permission:sales,export')->name('sales.send-custom');

            // Invoice named route aliases for compatibility
            Route::get('/invoices', Sales\Index::class)->middleware('tenant.permission:sales,view')->name('invoices.index');
            Route::get('/invoices/{sale}', Sales\Show::class)->middleware('tenant.permission:sales,view')->name('invoices.show');
            Route::get('/invoices/{sale}/pdf', [InvoiceController::class, 'pdf'])->middleware('tenant.permission:sales,view')->name('invoices.pdf');
            Route::post('/invoices/{sale}/send', [InvoiceController::class, 'send'])->middleware('tenant.permission:sales,export')->name('invoices.send');

            Route::get('/quotes', Quotes\Index::class)->middleware(['tenant.permission:quotes,view', 'tenant.pos_mode:general'])->name('quotes.index');
            Route::get('/quotes/create', Quotes\Create::class)->middleware(['tenant.permission:quotes,create', 'tenant.pos_mode:general'])->name('quotes.create');
            Route::get('/quotes/{quote}', Quotes\Show::class)->middleware(['tenant.permission:quotes,view', 'tenant.pos_mode:general'])->name('quotes.show');
            Route::get('/quotes/{quote}/edit', Quotes\Edit::class)->middleware(['tenant.permission:quotes,create', 'tenant.pos_mode:general'])->name('quotes.edit');
            Route::get('/quotes/{quote}/pdf', [QuotationController::class, 'pdf'])->middleware(['tenant.permission:quotes,view', 'tenant.pos_mode:general'])->name('quotes.pdf');
            Route::post('/quotes/{quote}/send', [QuotationController::class, 'send'])->middleware(['tenant.permission:quotes,export', 'tenant.pos_mode:general'])->name('quotes.send');

            // Quotation named route aliases for compatibility
            Route::get('/quotations', Quotes\Index::class)->middleware(['tenant.permission:quotes,view', 'tenant.pos_mode:general'])->name('quotations.index');
            Route::get('/quotations/create', Quotes\Create::class)->middleware(['tenant.permission:quotes,create', 'tenant.pos_mode:general'])->name('quotations.create');
            Route::get('/quotations/{quote}', Quotes\Show::class)->middleware(['tenant.permission:quotes,view', 'tenant.pos_mode:general'])->name('quotations.show');
            Route::get('/quotations/{quote}/edit', Quotes\Edit::class)->middleware(['tenant.permission:quotes,create', 'tenant.pos_mode:general'])->name('quotations.edit');
            Route::get('/quotations/{quote}/pdf', [QuotationController::class, 'pdf'])->middleware(['tenant.permission:quotes,view', 'tenant.pos_mode:general'])->name('quotations.pdf');
            Route::post('/quotations/{quote}/send', [QuotationController::class, 'send'])->middleware(['tenant.permission:quotes,export', 'tenant.pos_mode:general'])->name('quotations.send');

            // Lead Management System
            Route::get('/leads', [\App\Http\Controllers\Tenant\LeadWebController::class, 'index'])->middleware('tenant.permission:leads,view')->name('leads.index');
            Route::get('/leads/create', [\App\Http\Controllers\Tenant\LeadWebController::class, 'create'])->middleware('tenant.permission:leads,create')->name('leads.create');
            Route::get('/leads/{id}', [\App\Http\Controllers\Tenant\LeadWebController::class, 'show'])->middleware('tenant.permission:leads,view')->name('leads.show');
            Route::post('/leads', [\App\Http\Controllers\Tenant\LeadWebController::class, 'store'])->middleware('tenant.permission:leads,create')->name('leads.store');
            Route::match(['put', 'patch'], '/leads/{id}', [\App\Http\Controllers\Tenant\LeadWebController::class, 'update'])->middleware('tenant.permission:leads,edit')->name('leads.update');
            Route::delete('/leads/{id}', [\App\Http\Controllers\Tenant\LeadWebController::class, 'destroy'])->middleware('tenant.permission:leads,edit')->name('leads.destroy');
            Route::post('/leads/{id}/activities', [\App\Http\Controllers\Tenant\LeadWebController::class, 'storeActivity'])->middleware('tenant.permission:leads,edit')->name('leads.activities.store');
            Route::post('/leads/activities/{id}/complete', [\App\Http\Controllers\Tenant\LeadWebController::class, 'completeActivity'])->middleware('tenant.permission:leads,edit')->name('leads.activities.complete');

            // Consignments
            Route::get('/consignments', Index::class)->middleware(['tenant.permission:consignments,view', 'tenant.pos_mode:general'])->name('consignments.index');
            Route::get('/consignments/create', Create::class)->middleware(['tenant.permission:consignments,create', 'tenant.pos_mode:general'])->name('consignments.create');
            Route::get('/consignments/{consignment}', Show::class)->middleware(['tenant.permission:consignments,view', 'tenant.pos_mode:general'])->name('consignments.show');

            // Service Orders & Warranty Repair Tracking
            Route::get('/service-orders', App\Livewire\Tenant\ServiceOrders\Index::class)->middleware(['tenant.permission:service_orders,view', 'tenant.pos_mode:general'])->name('service-orders.index');

            // Sales Targets & Goals
            Route::get('/sales-targets', App\Livewire\Tenant\SalesTargets\Index::class)->middleware('tenant.permission:targets,view')->name('sales-targets.index');
            Route::get('/targets', App\Livewire\Tenant\SalesTargets\Index::class)->middleware('tenant.permission:targets,view')->name('targets.index');

            // Financial Management: Cash Register, Accounts Receivable & Payable
            Route::get('/finance/cash-register', Financials\CashRegister::class)->middleware('tenant.permission:cash_register,view')->name('financials.cash_register');
            Route::get('/finance/cash-register/{register}/z-report', [CashRegisterSlipController::class, 'viewZReport'])->middleware('tenant.permission:cash_register,view')->name('cash_register.z_report.view');
            Route::get('/finance/cash-register/{register}/z-report/pdf', [CashRegisterSlipController::class, 'pdfZReport'])->middleware('tenant.permission:cash_register,view')->name('cash_register.z_report.pdf');
            Route::get('/finance/cash-register/movement/{tx}', [CashRegisterSlipController::class, 'viewMovement'])->middleware('tenant.permission:cash_register,view')->name('cash_register.movement.view');
            Route::get('/finance/cash-register/movement/{tx}/pdf', [CashRegisterSlipController::class, 'pdfMovement'])->middleware('tenant.permission:cash_register,view')->name('cash_register.movement.pdf');
            Route::get('/finance/receivables', Financials\Receivables::class)->middleware('tenant.permission:finance,view')->name('financials.receivables');
            Route::get('/finance/payables', Financials\Payables::class)->middleware('tenant.permission:finance,view')->name('financials.payables');
            Route::get('/finance/payment-methods/{paymentMethod}/ledger', Financials\PaymentMethodLedger::class)->middleware('tenant.permission:settings,view')->name('financials.payment_method_ledger');

            // Reports & Financial Analytics
            Route::get('/reports', Reports\Index::class)->middleware('tenant.permission:reports,view')->name('reports.index');
            Route::get('/reports/sales', Reports\Index::class)->middleware('tenant.permission:reports,view')->name('reports.sales');
            Route::get('/reports/profit-loss', Reports\Index::class)->middleware('tenant.permission:reports,view')->name('reports.profit-loss');

            // Food & Restaurant POS Mode Subsystem (Strictly Isolated)
            Route::get('/restaurant/pos', Restaurant\Pos::class)->middleware(['tenant.permission:pos,create', 'tenant.pos_mode:restaurant'])->name('restaurant.pos');
            Route::get('/restaurant/tables', Restaurant\Tables::class)->middleware(['tenant.permission:pos,view', 'tenant.pos_mode:restaurant'])->name('restaurant.tables');
            Route::get('/restaurant/kds', Restaurant\Kds::class)->middleware(['tenant.permission:pos,view', 'tenant.pos_mode:restaurant'])->name('restaurant.kds');
            Route::get('/restaurant/kot/{kot}/print', [KotController::class, 'print'])->middleware(['tenant.permission:pos,view', 'tenant.pos_mode:restaurant'])->name('restaurant.kot.print');
            Route::get('/restaurant/tables/{table}/qr', [TableOrderController::class, 'qrCard'])->middleware(['tenant.permission:pos,view', 'tenant.pos_mode:restaurant'])->name('restaurant.table.qr');

            // Pharmacy vertical — gated to stores licensed for the pharmacy module.
            Route::middleware('tenant.vertical:pharmacy')->prefix('pharmacy')->name('pharmacy.')->group(function () {
                Route::get('/', App\Livewire\Tenant\Pharmacy\Dashboard::class)->middleware('tenant.permission:products,view')->name('dashboard');
                Route::get('/batches', Batches::class)->middleware('tenant.permission:products,view')->name('batches');
                Route::get('/prescriptions', Prescriptions::class)->middleware('tenant.permission:sales,view')->name('prescriptions');
            });

            // Salon & Service Booking vertical.
            Route::middleware('tenant.vertical:service_booking')->prefix('salon')->name('salon.')->group(function () {
                Route::get('/', Calendar::class)->middleware('tenant.permission:service_orders,view')->name('calendar');
                Route::get('/stylists', Stylists::class)->middleware('tenant.permission:service_orders,view')->name('stylists');
                Route::get('/services', ServiceCatalog::class)->middleware('tenant.permission:products,view')->name('services');
            });

            // Repair & Technician workbench vertical.
            Route::middleware('tenant.vertical:repair_technician')->prefix('repair')->name('repair.')->group(function () {
                Route::get('/', App\Livewire\Tenant\Repair\Dashboard::class)->middleware('tenant.permission:repair,view')->name('dashboard');
                Route::get('/tickets', Tickets::class)->middleware('tenant.permission:repair,view')->name('tickets');
                Route::get('/tickets/{ticket}', TicketDetail::class)->middleware('tenant.permission:repair,view')->name('ticket');
                Route::get('/tickets/{ticket}/share-sheet', [RepairApiController::class, 'ticketsShareDispatchSheet'])->middleware('tenant.permission:repair,view')->name('ticket.share-sheet');
                Route::post('/tickets/{ticket}/dispatch', [RepairApiController::class, 'ticketsDispatch'])->middleware('tenant.permission:repair,view')->name('ticket.dispatch');
                Route::get('/categories', App\Livewire\Tenant\Repair\Categories::class)->middleware('tenant.permission:repair,diagnose')->name('categories');
            });
            Route::middleware('tenant.vertical:repair_technician')->prefix('repairs')->group(function () {
                Route::get('/', App\Livewire\Tenant\Repair\Dashboard::class)->middleware('tenant.permission:repair,view');
                Route::get('/tickets', Tickets::class)->middleware('tenant.permission:repair,view');
            });

            Route::get('/catalog', Catalog\Index::class)->middleware('tenant.permission:catalog,view')->name('catalog.index');

            Route::get('/users', Users\Index::class)->middleware('tenant.permission:users,view')->name('users.index');
            Route::get('/users/permissions', Users\Permissions::class)->middleware('tenant.permission:users,view')->name('users.permissions');
            Route::get('/users/{user}/permissions', Users\Permissions::class)->middleware('tenant.permission:users,view')->name('users.user-permissions');

            Route::get('/devices', Devices\Index::class)->middleware('tenant.permission:settings,view')->name('devices.index');

            Route::post('/impersonate/{user}', [ImpersonationController::class, 'start'])->name('impersonate.start');
            Route::post('/impersonate', [ImpersonationController::class, 'stop'])->name('impersonate.stop');
        });
    });
});

// Invite acceptance is deliberately outside the auth:web/company-scoped
// group above — the invitee has no session and no known company yet.
Route::get('/accept-invite', AcceptInvite::class)
    ->middleware('guest:web')
    ->name('accept-invite');

// Public QR Code Table Digital Ordering
Route::get('/order/table/{token}', [TableOrderController::class, 'show'])->name('restaurant.table.order');
Route::post('/order/table/{token}', [TableOrderController::class, 'placeOrder'])->name('restaurant.table.order.place');
Route::get('/t/{token}', [TableOrderController::class, 'show'])->name('restaurant.table.short');

// Public online-catalog share links.
Route::get('/c/{id}', [CatalogViewController::class, 'show'])
    ->where('id', '[a-f0-9]{32}')
    ->name('catalog.show');
Route::post('/c/{id}/order', [CatalogViewController::class, 'placeOrder'])
    ->where('id', '[a-f0-9]{32}')
    ->name('catalog.order');

// Public shareable document links (for customers clicking from WhatsApp or Email).
Route::get('/i/{sale_number}', [InvoiceController::class, 'publicShow'])->name('sales.public');
Route::get('/q/{quote_number}', [QuotationController::class, 'publicShow'])->name('quotes.public');

// Public, login-free repair-ticket tracking page — linked from the intake
// "Track progress: …" SMS / WhatsApp message.
Route::get('/portal/repair/{ticket_number}', [RepairPortalController::class, 'track'])->name('repair.portal.track');
