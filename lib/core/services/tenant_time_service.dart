import 'package:timezone/data/latest.dart' as tzdata;
import 'package:timezone/timezone.dart' as tz;

/// Converts UTC timestamps from the API (order creation times, KOT logs,
/// prep-timer targets) into the tenant's configured store timezone — set
/// under Settings > Profile > Timezone & Regional Settings, either a
/// manual override or a default derived from the store's country (see
/// Company::resolveTimezone() server-side) — instead of the device's own
/// system timezone, which may not match where the store actually operates
/// (a manager checking orders from a different city/country, a
/// misconfigured device clock, etc).
///
/// A bare singleton (same pattern as AppDatabase/TranslationsCache): every
/// caller just needs "the current tenant's timezone", set once by
/// AuthProvider whenever the signed-in company changes.
class TenantTimeService {
  TenantTimeService._();

  static final TenantTimeService instance = TenantTimeService._();

  bool _dataInitialized = false;
  tz.Location _location = tz.UTC;

  String get timezoneName => _location.name;

  void _ensureDataInitialized() {
    if (_dataInitialized) return;
    tzdata.initializeTimeZones();
    _dataInitialized = true;
  }

  /// Call whenever the signed-in company (or its timezone) changes —
  /// AuthProvider does this right after login/session-restore/profile save.
  void setTimezone(String? ianaName) {
    _ensureDataInitialized();
    if (ianaName == null || ianaName.isEmpty) {
      _location = tz.UTC;
      return;
    }
    try {
      _location = tz.getLocation(ianaName);
    } catch (_) {
      // Not a recognized IANA identifier (shouldn't happen — the backend
      // validates it against the same tzdata) — fall back rather than crash.
      _location = tz.UTC;
    }
  }

  /// Reinterprets [dateTime] (typically a UTC instant parsed from the API)
  /// as wall-clock time in the tenant's timezone, DST included.
  tz.TZDateTime toTenantTime(DateTime dateTime) {
    _ensureDataInitialized();
    return tz.TZDateTime.from(dateTime, _location);
  }

  /// The current instant, expressed as wall-clock time in the tenant's
  /// timezone — for anything that needs "now" in store-local terms rather
  /// than the device's own zone.
  tz.TZDateTime now() {
    _ensureDataInitialized();
    return tz.TZDateTime.now(_location);
  }
}
