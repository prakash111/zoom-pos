import 'package:flutter/widgets.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:zoom_pos_mobile/core/config/countries.dart';
import 'package:zoom_pos_mobile/core/config/locale_provider.dart';
import 'package:zoom_pos_mobile/core/config/tax_jurisdictions.dart';

void main() {
  group('LocaleProvider.parseLocale', () {
    test('accepts a bare 2-letter code', () {
      expect(LocaleProvider.parseLocale('pt'), const Locale('pt'));
      expect(LocaleProvider.parseLocale('EN'), const Locale('en'));
    });

    test('accepts region-qualified codes that used to be dropped', () {
      expect(LocaleProvider.parseLocale('pt-BR'), const Locale('pt', 'BR'));
      expect(LocaleProvider.parseLocale('pt_br'), const Locale('pt', 'BR'));
      expect(LocaleProvider.parseLocale('zh-CN'), const Locale('zh', 'CN'));
    });

    test('accepts a script subtag', () {
      expect(
        LocaleProvider.parseLocale('zh-Hans'),
        Locale.fromSubtags(languageCode: 'zh', scriptCode: 'Hans'),
      );
    });

    test('rejects junk', () {
      expect(LocaleProvider.parseLocale(''), isNull);
      expect(LocaleProvider.parseLocale('english'), isNull);
      expect(LocaleProvider.parseLocale('123'), isNull);
    });
  });

  group('countries', () {
    test('includes Brazil and keeps every tax-jurisdiction entry', () {
      expect(kCountries['BR'], 'Brazil');
      for (final code in kTaxJurisdictions.keys) {
        final present =
            countryPickerOptions().any((entry) => entry.key == code);
        expect(present, isTrue, reason: '$code missing from the picker');
      }
    });

    test('picker is a broad list, sorted by display name', () {
      final options = countryPickerOptions();
      expect(options.length, greaterThan(180));
      final names = options.map((e) => e.value.toLowerCase()).toList();
      final sorted = [...names]..sort();
      expect(names, sorted);
    });

    test('resolveCountryCode maps codes and names', () {
      expect(resolveCountryCode('BR'), 'BR');
      expect(resolveCountryCode('br'), 'BR');
      expect(resolveCountryCode('Brazil'), 'BR');
      expect(resolveCountryCode('  united states '), 'US');
      expect(resolveCountryCode('Atlantis'), isNull);
      expect(resolveCountryCode(''), isNull);
    });
  });
}
