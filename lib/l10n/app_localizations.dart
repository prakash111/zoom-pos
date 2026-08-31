import 'package:flutter/material.dart';

import 'app_strings_ar.dart';
import 'app_strings_de.dart';
import 'app_strings_en.dart';
import 'app_strings_es.dart';
import 'app_strings_fr.dart';
import 'app_strings_hi.dart';
import 'app_strings_id.dart';
import 'app_strings_it.dart';
import 'app_strings_ja.dart';
import 'app_strings_pt.dart';
import 'app_strings_ru.dart';
import 'app_strings_tr.dart';
import 'app_strings_zh.dart';

/// Hand-written localizations (not Flutter's `.arb`/codegen pipeline — see
/// the Phase 5 plan for why) for the highest-traffic screens: login/splash,
/// dashboard chrome, POS, and Settings' Profile tab. Covers every locale the
/// Laravel backend's language catalog supports (see LocalizationService and
/// LanguagesScreen). A missing key in the active locale falls back to
/// English rather than crashing or showing a blank string.
class AppLocalizations {
  AppLocalizations(this.localeName) : _strings = _stringsFor(localeName);

  final String localeName;
  final Map<String, String> _strings;

  static const _byLocale = <String, Map<String, String>>{
    'en': kEnStrings,
    'es': kEsStrings,
    'fr': kFrStrings,
    'de': kDeStrings,
    'ar': kArStrings,
    'hi': kHiStrings,
    'pt': kPtStrings,
    'it': kItStrings,
    'zh': kZhStrings,
    'ja': kJaStrings,
    'ru': kRuStrings,
    'id': kIdStrings,
    'tr': kTrStrings,
  };

  static Map<String, String> _stringsFor(String localeName) => _byLocale[localeName] ?? kEnStrings;

  static AppLocalizations of(BuildContext context) {
    return Localizations.of<AppLocalizations>(context, AppLocalizations)!;
  }

  static const LocalizationsDelegate<AppLocalizations> delegate = _AppLocalizationsDelegate();

  String _s(String key) => _strings[key] ?? kEnStrings[key] ?? key;

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
  String lowStockWarning(int count) => _format('lowStockWarning', {'n': '$count'});

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

  String itemsCountBadge(int count) => _format('itemsCountBadge', {'n': '$count'});
  String heldOrdersTitle(int count) => _format('heldOrdersTitle', {'n': '$count'});
  String heldChip(int count) => _format('heldChip', {'n': '$count'});
  String heldCartSubtitle(int count, String total) => _format('heldCartSubtitle', {'n': '$count', 'total': total});
  String completeSaleButton(String amount) => _format('completeSaleButton', {'amount': amount});
}

class _AppLocalizationsDelegate extends LocalizationsDelegate<AppLocalizations> {
  const _AppLocalizationsDelegate();

  @override
  bool isSupported(Locale locale) => AppLocalizations._byLocale.containsKey(locale.languageCode);

  @override
  Future<AppLocalizations> load(Locale locale) async => AppLocalizations(locale.languageCode);

  @override
  bool shouldReload(_AppLocalizationsDelegate old) => false;
}
