import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:provider/provider.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'package:zoom_pos_mobile/core/api/api_client.dart';
import 'package:zoom_pos_mobile/core/config/platform_branding_provider.dart';
import 'package:zoom_pos_mobile/features/auth/widgets/auth_scaffold.dart';

class _FakeBrandingApi extends Fake implements ApiClient {
  _FakeBrandingApi(this.payload);
  final Map<String, dynamic> payload;

  @override
  Future<Map<String, dynamic>> get(String path,
          {Map<String, dynamic>? query}) async =>
      payload;
}

Widget _host(PlatformBrandingProvider branding) => MaterialApp(
      home: ChangeNotifierProvider<PlatformBrandingProvider>.value(
        value: branding,
        child: const AuthScaffold(heading: 'Login', form: SizedBox.shrink()),
      ),
    );

void main() {
  setUp(() => SharedPreferences.setMockInitialValues({}));

  test('branding refresh reads header_inline / show_tagline flags', () async {
    final b = PlatformBrandingProvider();
    await b.refresh(_FakeBrandingApi({
      'success': true,
      'platform_name': 'Acme POS',
      'brand_logo_url': 'https://example.test/logo.png',
      'platform_tagline': null,
      'header_inline': true,
      'show_tagline': false,
    }));

    expect(b.platformName, 'Acme POS');
    expect(b.headerInline, isTrue);
    expect(b.showTagline, isFalse);
  });

  test('branding refresh accepts stringified booleans', () async {
    final b = PlatformBrandingProvider();
    await b.refresh(_FakeBrandingApi({
      'header_inline': 'false',
      'show_tagline': 'true',
    }));
    expect(b.headerInline, isFalse);
    expect(b.showTagline, isTrue);
  });

  test('branding refresh binds the Superadmin platform + theme contract',
      () async {
    final b = PlatformBrandingProvider();
    await b.refresh(_FakeBrandingApi({
      'success': true,
      'platform': {
        'name': 'POS Systems',
        'headline': 'Run your business smarter.',
        'description': 'Sales, inventory & orders — all in one place.',
        'logo_url': 'https://saas.zoomnearby.com/uploads/superadmin-logo.png',
        'favicon_url': 'https://saas.zoomnearby.com/uploads/favicon.png',
      },
      'theme': {
        'primary_color': '#F95700',
        'secondary_color': '#0F172A',
        'accent_color': '#FF7A00',
        'splash_bg_color': '#0F172A',
        'auth_bg_color': '#F8FAFC',
      },
    }));

    expect(b.platformName, 'POS Systems');
    expect(b.headline, 'Run your business smarter.');
    expect(b.description, 'Sales, inventory & orders — all in one place.');
    expect(b.faviconUrl, 'https://saas.zoomnearby.com/uploads/favicon.png');
    expect(b.primaryColor, const Color(0xFFF95700));
    expect(b.secondaryColor, const Color(0xFF0F172A));
    expect(b.accentColor, const Color(0xFFFF7A00));
    expect(b.splashBgColor, const Color(0xFF0F172A));
    expect(b.authBgColor, const Color(0xFFF8FAFC));
  });

  testWidgets('AuthScaffold paints the Superadmin auth_bg_color + primary',
      (tester) async {
    final b = PlatformBrandingProvider()
      ..authBgColor = const Color(0xFFEAF2FF)
      ..primaryColor = const Color(0xFF00A3FF);

    await tester.pumpWidget(_host(b));
    await tester.pump();

    final scaffold = tester.widget<Scaffold>(find.byType(Scaffold));
    expect(scaffold.backgroundColor, const Color(0xFFEAF2FF));

    final ctx = tester.element(find.byType(Scaffold));
    expect(Theme.of(ctx).colorScheme.primary, const Color(0xFF00A3FF));
  });

  testWidgets('default header is logo + title inline (12px gap), no tagline',
      (tester) async {
    await tester.binding.setSurfaceSize(const Size(700, 900));
    addTearDown(() => tester.binding.setSurfaceSize(null));

    final b = PlatformBrandingProvider()
      ..platformName = 'TestCo'
      ..brandLogoUrl = 'https://example.test/logo.png'
      ..tagline = 'Retail and restaurant checkout, real-time multi-branch...'
      ..headerInline = true
      ..showTagline = false;

    await tester.pumpWidget(_host(b));
    await tester.pump();

    expect(find.text('TestCo'), findsOneWidget);
    // Description block is not rendered.
    expect(
      find.textContaining('real-time multi-branch'),
      findsNothing,
    );
    // Logo and title share a Row with a 12px gap between them.
    final rowFinder = find.ancestor(
      of: find.text('TestCo'),
      matching: find.byType(Row),
    );
    expect(rowFinder, findsWidgets);
    expect(
      find.descendant(
        of: rowFinder.first,
        matching: find.byWidgetPredicate(
            (w) => w is SizedBox && w.width == 12 && w.height == null),
      ),
      findsOneWidget,
    );
  });

  testWidgets(
      'show_tagline:true renders the description; header_inline:false stacks',
      (tester) async {
    await tester.binding.setSurfaceSize(const Size(700, 900));
    addTearDown(() => tester.binding.setSurfaceSize(null));

    final b = PlatformBrandingProvider()
      ..platformName = 'TestCo'
      ..tagline = 'A short strap line'
      ..headerInline = false
      ..showTagline = true;

    await tester.pumpWidget(_host(b));
    await tester.pump();

    expect(find.text('TestCo'), findsOneWidget);
    expect(find.text('A short strap line'), findsOneWidget);
  });
}
