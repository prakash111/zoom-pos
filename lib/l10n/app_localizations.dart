import 'package:flutter/material.dart';

import '../core/services/dynamic_string_service.dart';

/// Compatibility facade for legacy widgets while they are phased out.
/// Every value is resolved by [DynamicStringService]; this file contains no
/// locale dictionary and is not registered by the production app.
class AppLocalizations {
  AppLocalizations(this.localeName);

  final String localeName;

  static AppLocalizations of(BuildContext context) {
    return Localizations.of<AppLocalizations>(context, AppLocalizations) ??
        AppLocalizations(DynamicStringService.instance.locale);
  }

  static const LocalizationsDelegate<AppLocalizations> delegate =
      _AppLocalizationsDelegate();

  String _s(String key) => DynamicStringService.instance.translate(key);

  /// Resolves any dynamic key delivered from the server payload,
  /// checking only the runtime server dictionary. The optional fallback is
  /// retained for source compatibility with deprecated screens.
  String text(String key, {String? fallback}) {
    final value = _s(key);
    return value == key && fallback != null ? fallback : value;
  }

  String s(String key, {String? fallback}) => text(key, fallback: fallback);

  /// Substitutes `{name}` placeholders in a template string, e.g.
  /// `_format('{n} items', {'n': '3'})` → `'3 items'`.
  String _format(String key, Map<String, String> args) {
    var value = _s(key);
    for (final entry in args.entries) {
      value = value.replaceAll('{${entry.key}}', entry.value);
    }
    return value;
  }

  // Common
  String get required => _s('required');
  String get cancel => _s('cancel');
  String get serverAddress => _s('serverAddress');
  String get signOut => _s('signOut');
  String get signOutConfirmTitle => _s('signOutConfirmTitle');
  String get signOutConfirmBody => _s('signOutConfirmBody');
  String get changePassword => _s('changePassword');

  // Login
  String get signIn => _s('signIn');
  String get emailOrLogin => _s('emailOrLogin');
  String get password => _s('password');
  String get storeAccountIdOptional => _s('storeAccountIdOptional');
  String get iHaveAccountId => _s('iHaveAccountId');
  String get noStoreYetPrompt => _s('noStoreYetPrompt');
  String get createOne => _s('createOne');

  // Dashboard
  String get refresh => _s('refresh');
  String get todaysSales => _s('todaysSales');
  String get ordersToday => _s('ordersToday');
  String get avgOrder => _s('avgOrder');
  String get revenueTrend => _s('revenueTrend');
  String get topSelling => _s('topSelling');
  String lowStockWarning(int count) =>
      _format('lowStockWarning', {'n': '$count'});

  // Settings
  String get settingsTitle => _s('settingsTitle');
  String get tabProfile => _s('tabProfile');
  String get tabReceipts => _s('tabReceipts');
  String get tabFinancial => _s('tabFinancial');
  String get tabNotifications => _s('tabNotifications');
  String get storeName => _s('storeName');
  String get tradeName => _s('tradeName');
  String get gstin => _s('gstin');
  String get taxId => _s('taxId');
  String get manageTaxRules => _s('manageTaxRules');
  String get email => _s('email');
  String get phone => _s('phone');
  String get website => _s('website');
  String get address => _s('address');
  String get city => _s('city');
  String get state => _s('state');
  String get postalCode => _s('postalCode');
  String get country => _s('country');
  String get countryOther => _s('countryOther');
  String get countryCodeIso2 => _s('countryCodeIso2');
  String get defaultCommissionRate => _s('defaultCommissionRate');
  String get type => _s('type');
  String get commissionPercentage => _s('commissionPercentage');
  String get commissionFixed => _s('commissionFixed');
  String get brandColor => _s('brandColor');
  String get brandColorDescription => _s('brandColorDescription');
  String get customHex => _s('customHex');
  String get branding => _s('branding');
  String get brandingDescription => _s('brandingDescription');
  String get logo => _s('logo');
  String get favicon => _s('favicon');
  String get drawerCoverImage => _s('drawerCoverImage');
  String get saveProfile => _s('saveProfile');
  String get profileSaved => _s('profileSaved');
  String get storeNameRequired => _s('storeNameRequired');
  String get camera => _s('camera');
  String get gallery => _s('gallery');
  String get remove => _s('remove');

  // POS
  String get searchProductsHint => _s('searchProductsHint');
  String get scanBarcode => _s('scanBarcode');
  String get registerClosedBanner => _s('registerClosedBanner');
  String get open => _s('open');
  String get saleCompleted => _s('saleCompleted');
  String get saleQueuedOffline => _s('saleQueuedOffline');

  // Offline sync
  String get syncNow => _s('syncNow');
  String get syncing => _s('syncing');
  String get syncStatusTitle => _s('syncStatusTitle');
  String get syncNeverRun => _s('syncNeverRun');
  String unsyncedSalesCount(int count) =>
      _format('unsyncedSalesCount', {'n': '$count'});
  String lastSyncedAt(String when) => _format('lastSyncedAt', {'when': when});
  String get syncCompleted => _s('syncCompleted');
  String get menu => _s('menu');

  // Appearance / Workspace
  String get tabAppearance => _s('tabAppearance');
  String get timezoneSectionTitle => _s('timezoneSectionTitle');
  String get timezoneSectionDescription => _s('timezoneSectionDescription');
  String get timezoneManualOverride => _s('timezoneManualOverride');
  String timezoneUseCountryDefault(String zone) =>
      _format('timezoneUseCountryDefault', {'zone': zone});
  String get tabNavigationMenu => _s('tabNavigationMenu');
  String get navMenuDescription => _s('navMenuDescription');
  String get navMenuSectionOrderHint => _s('navMenuSectionOrderHint');
  String get navMenuSaved => _s('navMenuSaved');
  String get navDockTitle => _s('navDockTitle');
  String get navDockDescription => _s('navDockDescription');
  String get navDockLeft => _s('navDockLeft');
  String get navDockTop => _s('navDockTop');
  String get navDockRight => _s('navDockRight');
  String get navDockBottom => _s('navDockBottom');

  // Navigation dock destinations — shared by the drawer, rail, top bar, and
  // bottom bar (see DashboardScreen), so a locale change never leaves the
  // menu in a different language than the rest of the app.
  String get navHome => _s('navHome');
  String get featurePos => _s('featurePos');
  String get featureKitchenDisplay => _s('featureKitchenDisplay');
  String get featureSales => _s('featureSales');
  String get featureQuotations => _s('featureQuotations');
  String get featureInventory => _s('featureInventory');
  String get featureCustomers => _s('featureCustomers');
  String get featureCashRegister => _s('featureCashRegister');
  String get featurePayables => _s('featurePayables');
  String get featureDueReceivables => _s('featureDueReceivables');
  String get featureConsignments => _s('featureConsignments');
  String get featureServiceOrders => _s('featureServiceOrders');
  String get featureSalesTargets => _s('featureSalesTargets');
  String get featureReports => _s('featureReports');
  String get featureTaxes => _s('featureTaxes');
  String get featureAnalytics => _s('featureAnalytics');
  String get featureSubscription => _s('featureSubscription');
  String get featureStaff => _s('featureStaff');
  String get featureOnlineCatalog => _s('featureOnlineCatalog');
  String get featureLanguages => _s('featureLanguages');
  String get featureDevices => _s('featureDevices');
  String get featureSettings => _s('featureSettings');

  // Restaurant-mode-only nav labels — see DashboardScreen's restaurant nav
  // section list.
  String get featureRestaurantPos => _s('featureRestaurantPos');
  String get featureFloorPlan => _s('featureFloorPlan');
  String get featureDiningHistory => _s('featureDiningHistory');
  String get featureAccountsReceivable => _s('featureAccountsReceivable');
  String get featureAccountsPayable => _s('featureAccountsPayable');
  String get featureReportsAnalytics => _s('featureReportsAnalytics');
  String get featureMenuDishes => _s('featureMenuDishes');
  String get featureCategories => _s('featureCategories');
  String get featureBrands => _s('featureBrands');
  String get featureUnits => _s('featureUnits');
  String get featureSuppliers => _s('featureSuppliers');
  String get featureFoodSuppliers => _s('featureFoodSuppliers');
  String get featureGuestDirectory => _s('featureGuestDirectory');

  String get navHeaderRestaurantOperations =>
      _s('navHeaderRestaurantOperations');
  String get navHeaderOrdersCash => _s('navHeaderOrdersCash');
  String get navHeaderFinancialManagement => _s('navHeaderFinancialManagement');
  String get navHeaderKitchenMenuCatalog => _s('navHeaderKitchenMenuCatalog');
  String get navHeaderAdministration => _s('navHeaderAdministration');
  String get navHeaderCashierSales => _s('navHeaderCashierSales');
  String get navHeaderProductsInventory => _s('navHeaderProductsInventory');

  String get restaurantPosStartOrder => _s('restaurantPosStartOrder');
  String get restaurantPosSubtitle => _s('restaurantPosSubtitle');
  String get restaurantPosDineIn => _s('restaurantPosDineIn');
  String get restaurantPosDineInSubtitle => _s('restaurantPosDineInSubtitle');
  String get restaurantPosTakeaway => _s('restaurantPosTakeaway');
  String get restaurantPosTakeawaySubtitle =>
      _s('restaurantPosTakeawaySubtitle');
  String get restaurantPosDelivery => _s('restaurantPosDelivery');
  String get restaurantPosDeliverySubtitle =>
      _s('restaurantPosDeliverySubtitle');

  String get orderCart => _s('orderCart');
  String get clearCart => _s('clearCart');
  String get cartEmptyTitle => _s('cartEmptyTitle');
  String get cartEmptySubtitle => _s('cartEmptySubtitle');
  String get holdCurrentCart => _s('holdCurrentCart');
  String get cartPutOnHold => _s('cartPutOnHold');
  String get noHeldOrders => _s('noHeldOrders');
  String get resume => _s('resume');
  String get addCustomer => _s('addCustomer');
  String get hold => _s('hold');
  String get note => _s('note');
  String get noteChecked => _s('noteChecked');
  String get discount => _s('discount');
  String get discountChecked => _s('discountChecked');
  String get paymentMethod => _s('paymentMethod');
  String get subtotal => _s('subtotal');
  String get grandTotal => _s('grandTotal');
  String get cgst => _s('cgst');
  String get sgst => _s('sgst');

  String itemsCountBadge(int count) =>
      _format('itemsCountBadge', {'n': '$count'});
  String heldOrdersTitle(int count) =>
      _format('heldOrdersTitle', {'n': '$count'});
  String heldChip(int count) => _format('heldChip', {'n': '$count'});
  String heldCartSubtitle(int count, String total) =>
      _format('heldCartSubtitle', {'n': '$count', 'total': total});
  String completeSaleButton(String amount) =>
      _format('completeSaleButton', {'amount': amount});
}

class _AppLocalizationsDelegate
    extends LocalizationsDelegate<AppLocalizations> {
  const _AppLocalizationsDelegate();

  @override
  bool isSupported(Locale locale) => locale.languageCode.isNotEmpty;

  @override
  Future<AppLocalizations> load(Locale locale) async =>
      AppLocalizations(locale.languageCode);

  @override
  bool shouldReload(_AppLocalizationsDelegate old) => false;
}
