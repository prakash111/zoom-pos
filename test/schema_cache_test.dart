import 'package:flutter_test/flutter_test.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'package:zoom_pos_mobile/core/sdui/schema_cache.dart';

void main() {
  TestWidgetsFlutterBinding.ensureInitialized();

  setUp(() async {
    SharedPreferences.setMockInitialValues({});
    await SchemaCache.instance.clear();
  });

  test('normalizeKey strips scheme, host and query string', () {
    expect(
      SchemaCache.normalizeKey('https://acme.test/api/tenant/views/x?tab=a&q=z'),
      '/api/tenant/views/x',
    );
    expect(
      SchemaCache.normalizeKey('/api/tenant/views/x?search=foo'),
      '/api/tenant/views/x',
    );
  });

  test('put then get round-trips a schema and records cachedAt', () async {
    final schema = {
      'schema_version': 1,
      'title': 'Tax Rules',
      'components': [
        {'type': 'text', 'text': 'hello'},
      ],
    };

    await SchemaCache.instance.put('/api/tenant/settings/tax-rules', schema);
    final hit = await SchemaCache.instance.get('/api/tenant/settings/tax-rules');

    expect(hit, isNotNull);
    expect(hit!.schema['title'], 'Tax Rules');
    expect((hit.schema['components'] as List), hasLength(1));
    expect(
      DateTime.now().difference(hit.cachedAt).inMinutes,
      lessThan(1),
    );
  });

  test('a filtered URL resolves to the same cached base schema', () async {
    await SchemaCache.instance.put(
      'https://acme.test/api/tenant/views/customers',
      {'components': const []},
    );

    final viaQuery = await SchemaCache.instance
        .get('https://acme.test/api/tenant/views/customers?search=jo&page=2');

    expect(viaQuery, isNotNull);
  });

  test('get returns null for an endpoint never cached', () async {
    expect(await SchemaCache.instance.get('/api/tenant/views/never'), isNull);
  });

  test('the store is capped and evicts the oldest entries first', () async {
    // 80-entry cap. Write 85 and confirm the newest survive, oldest gone.
    for (var i = 0; i < 85; i++) {
      await SchemaCache.instance.put('/api/screen/$i', {'n': i});
      // keep timestamps strictly ordered
      await Future<void>.delayed(const Duration(milliseconds: 1));
    }

    expect(await SchemaCache.instance.get('/api/screen/0'), isNull);
    expect(await SchemaCache.instance.get('/api/screen/4'), isNull);
    expect(await SchemaCache.instance.get('/api/screen/84'), isNotNull);
    expect(await SchemaCache.instance.get('/api/screen/50'), isNotNull);
  });
}
