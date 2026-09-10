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

  testWidgets('show_tagline:true renders the description; header_inline:false stacks',
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
