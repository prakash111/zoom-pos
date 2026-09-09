import 'package:cached_network_image/cached_network_image.dart';
import 'package:flutter/material.dart';
import 'package:image_picker/image_picker.dart';
import 'package:provider/provider.dart';

import '../../../core/api/api_client.dart';
import '../../../core/api/api_exception.dart';
import '../../../core/config/nav_dock_provider.dart';
import '../../../core/config/tax_jurisdictions.dart';
import '../../../core/config/theme.dart';
import '../../../core/config/theme_provider.dart';
import '../../../core/models/settings_models.dart';
import '../../../core/utils/color_utils.dart';
import '../../../core/widgets/error_view.dart';
import '../../../core/widgets/loading_indicator.dart';
import '../../../l10n/app_localizations.dart';
import '../../taxes/screens/taxes_screen.dart';
import '../settings_repository.dart';
import '../../../core/sdui/components/navigation_tree_builder.dart';
import '../../../core/sdui/screens/dynamic_schema_page.dart';
import 'payment_methods_screen.dart';
import 'global_printer_setup_screen.dart';

/// Preset brand-color swatches offered in the Profile tab — a fixed palette
/// avoids pulling in a color-picker package for what's a fairly small need.
const List<Color> _brandColorSwatches = [
  Color(0xFF2563EB), // blue (default)
  Color(0xFF2D7A58), // green
  Color(0xFFDC2626), // red
  Color(0xFFEA580C), // orange
  Color(0xFFD97706), // amber
  Color(0xFF65A30D), // lime
  Color(0xFF0D9488), // teal
  Color(0xFF7C3AED), // violet
  Color(0xFFDB2777), // pink
  Color(0xFF334155), // slate
];

const _tabs = [
  'Profile',
  'Receipts',
  'Financial',
  'Navigation Menu',
  'Appearance'
];

/// Tenant Settings: Profile / Receipts / Financial, mirroring
/// those tabs on the web Settings page (Mode and API/AI-config tabs are out
/// of scope for mobile), plus a mobile-only Appearance tab for local
/// workspace preferences (the nav dock layout). Each tab saves its own
/// section independently.
class TenantSettingsScreen extends StatefulWidget {
  const TenantSettingsScreen({super.key});

  @override
  State<TenantSettingsScreen> createState() => _TenantSettingsScreenState();
}

class _TenantSettingsScreenState extends State<TenantSettingsScreen>
    with SingleTickerProviderStateMixin {
  late final TabController _tabController;
  late final SettingsRepository _repository;
  late Future<TenantSettingsBundle> _future;

  @override
  void initState() {
    super.initState();
    _tabController = TabController(length: _tabs.length, vsync: this);
    _repository = SettingsRepository(context.read<ApiClient>());
    _future = _repository.fetchAll();
  }

  @override
  void dispose() {
    _tabController.dispose();
    super.dispose();
  }

  void _reload() => setState(() => _future = _repository.fetchAll());

  /// Wraps one server-backed tab in its own [FutureBuilder] over the shared
  /// [_future] — each tab gets its own loading/error state instead of one
  /// failed fetch blanking every tab, which matters now that Appearance
  /// (below) is a purely local, always-available tab in the same bar.
  Widget _serverTab(Widget Function(TenantSettingsBundle bundle) builder) {
    return FutureBuilder<TenantSettingsBundle>(
      future: _future,
      builder: (context, snapshot) {
        if (snapshot.connectionState != ConnectionState.done)
          return const LoadingIndicator();
        if (snapshot.hasError) {
          final message = snapshot.error is ApiException
              ? (snapshot.error as ApiException).message
              : 'Could not load settings.';
          return ErrorView(message: message, onRetry: _reload);
        }
        return builder(snapshot.data!);
      },
    );
  }

  @override
  Widget build(BuildContext context) {
    final l10n = AppLocalizations.of(context);
    final tabLabels = [
      l10n.tabProfile,
      l10n.tabReceipts,
      l10n.tabFinancial,
      l10n.tabNavigationMenu,
      l10n.tabAppearance,
    ];

    return Scaffold(
      appBar: AppBar(
        title: Text(l10n.settingsTitle),
        actions: [
          IconButton(
            icon: const Icon(Icons.tune),
            tooltip: 'Custom Form Labels',
            onPressed: () {
              Navigator.of(context).push(
                MaterialPageRoute(
                  builder: (_) => const DynamicSchemaPage(
                    endpoint: '/api/tenant/views/settings-form-labels',
                    initialTitle: 'Custom Form Labels',
                  ),
                ),
              );
            },
          ),
        ],
        bottom: TabBar(
            controller: _tabController,
            isScrollable: true,
            tabs: [for (final t in tabLabels) Tab(text: t)]),
      ),
      body: TabBarView(
        controller: _tabController,
        children: [
          for (final tab in <Widget>[
            _serverTab((bundle) => _ProfileTab(
                repository: _repository,
                initial: bundle.profile,
                timezones: bundle.timezones)),
            _serverTab((bundle) => _ReceiptsTab(
                repository: _repository, initial: bundle.receipts)),
            _serverTab((bundle) => _FinancialTab(
                repository: _repository, initial: bundle.financial)),
            _serverTab((bundle) => NavMenuSettingsTab(
                repository: _repository, initial: bundle.nav)),
            // A per-device workspace preference, not a tenant setting — never
            // gated behind the server fetch above, so it's reachable offline.
            const _AppearanceTab(),
          ])
            // Keep forms readable on wide desktop windows instead of letting
            // every field stretch edge to edge.
            Align(
              alignment: Alignment.topCenter,
              child: ConstrainedBox(
                constraints: const BoxConstraints(maxWidth: 820),
                child: tab,
              ),
            ),
        ],
      ),
    );
  }
}

/// Settings > Appearance — the navigation-dock layout preference from the
/// desktop-parity spec. Purely local (SharedPreferences via
/// [NavDockProvider]), nothing here is synced to the server.
class _AppearanceTab extends StatelessWidget {
  const _AppearanceTab();

  @override
  Widget build(BuildContext context) {
    final l10n = AppLocalizations.of(context);
    final navDock = context.watch<NavDockProvider>();
    final themeProvider = context.watch<ThemeProvider>();

    final options = <(NavDockPosition, IconData, String)>[
      (NavDockPosition.left, Icons.arrow_back, l10n.navDockLeft),
      (NavDockPosition.top, Icons.arrow_upward, l10n.navDockTop),
      (NavDockPosition.right, Icons.arrow_forward, l10n.navDockRight),
      (NavDockPosition.bottom, Icons.arrow_downward, l10n.navDockBottom),
    ];

    const themeOptions = <(ThemeMode, IconData, String)>[
      (ThemeMode.system, Icons.brightness_auto_outlined, 'Match device'),
      (ThemeMode.light, Icons.light_mode_outlined, 'Light'),
      (ThemeMode.dark, Icons.dark_mode_outlined, 'Dark'),
    ];

    return ListView(
      padding: const EdgeInsets.all(16),
      children: [
        Text('Theme', style: Theme.of(context).textTheme.titleMedium),
        const SizedBox(height: 4),
        Text('Choose how the app looks on this device.',
            style: TextStyle(color: Colors.grey.shade600)),
        const SizedBox(height: 12),
        RadioGroup<ThemeMode>(
          groupValue: themeProvider.themeMode,
          onChanged: (value) {
            if (value != null) themeProvider.setThemeMode(value);
          },
          child: Card(
            margin: EdgeInsets.zero,
            child: Column(
              children: [
                for (final option in themeOptions)
                  RadioListTile<ThemeMode>(
                    value: option.$1,
                    secondary: Icon(option.$2),
                    title: Text(option.$3),
                  ),
              ],
            ),
          ),
        ),
        const SizedBox(height: 24),
        Text(l10n.navDockTitle, style: Theme.of(context).textTheme.titleMedium),
        const SizedBox(height: 4),
        Text(l10n.navDockDescription,
            style: TextStyle(color: Colors.grey.shade600)),
        const SizedBox(height: 12),
        RadioGroup<NavDockPosition>(
          groupValue: navDock.position,
          onChanged: (value) {
            if (value != null) navDock.setPosition(value);
          },
          child: Card(
            margin: EdgeInsets.zero,
            child: Column(
              children: [
                for (final option in options)
                  RadioListTile<NavDockPosition>(
                    value: option.$1,
                    secondary: Icon(option.$2),
                    title: Text(option.$3),
                  ),
              ],
            ),
          ),
        ),
      ],
    );
  }
}

class _ProfileTab extends StatefulWidget {
  const _ProfileTab(
      {required this.repository,
      required this.initial,
      required this.timezones});

  final SettingsRepository repository;
  final ProfileSettings initial;

  /// Full IANA identifier list for the manual-override picker below.
  final List<String> timezones;

  @override
  State<_ProfileTab> createState() => _ProfileTabState();
}

class _ProfileTabState extends State<_ProfileTab> {
  late final TextEditingController _name;
  late final TextEditingController _tradeName;
  late final TextEditingController _taxId;
  late final TextEditingController _email;
  late final TextEditingController _phone;
  late final TextEditingController _website;
  late final TextEditingController _address;
  late final TextEditingController _city;
  late final TextEditingController _state;
  late final TextEditingController _postalCode;
  late final TextEditingController _country;
  late final TextEditingController _commissionRate;
  late final TextEditingController _colorHex;
  late String _commissionType;
  late String _countryCode;
  late Color _primaryColor;

  /// Null means "no manual override — follow the country default".
  String? _timezoneOverride;
  late String _defaultTimezoneForCountry;
  String? _logoUrl;
  String? _faviconUrl;
  String? _drawerCoverUrl;
  bool _uploadingLogo = false;
  bool _uploadingFavicon = false;
  bool _uploadingDrawerCover = false;
  bool _saving = false;

  /// Whether GSTIN labeling should apply, computed live from the dropdown
  /// selection (or the "Other" fallback field) rather than the saved
  /// company, so the Tax ID label updates before the form is saved.
  bool get _isIndiaSelected {
    final code = _countryCode == kOtherCountrySentinel
        ? _country.text.trim().toUpperCase()
        : _countryCode;
    return code == 'IN';
  }

  @override
  void initState() {
    super.initState();
    final p = widget.initial;
    _name = TextEditingController(text: p.name);
    _tradeName = TextEditingController(text: p.tradeName);
    _taxId = TextEditingController(text: p.taxId);
    _email = TextEditingController(text: p.email);
    _phone = TextEditingController(text: p.phone);
    _website = TextEditingController(text: p.website);
    _address = TextEditingController(text: p.address);
    _city = TextEditingController(text: p.city);
    _state = TextEditingController(text: p.state);
    _postalCode = TextEditingController(text: p.postalCode);
    _country = TextEditingController(text: p.country);
    _commissionRate =
        TextEditingController(text: p.defaultCommissionRate.toStringAsFixed(2));
    _commissionType = p.defaultCommissionType;

    final upperCountry = p.country.trim().toUpperCase();
    _countryCode = kTaxJurisdictions.containsKey(upperCountry)
        ? upperCountry
        : kOtherCountrySentinel;

    _timezoneOverride = p.timezone.isEmpty ? null : p.timezone;
    _defaultTimezoneForCountry = p.defaultTimezoneForCountry;

    _primaryColor = parseHexColor(p.primaryColor) ?? AppTheme.primary;
    _colorHex = TextEditingController(text: toHexColor(_primaryColor));

    _logoUrl = p.logoUrl;
    _faviconUrl = p.faviconUrl;
    _drawerCoverUrl = p.drawerCoverUrl;
  }

  @override
  void dispose() {
    for (final c in [
      _name,
      _tradeName,
      _taxId,
      _email,
      _phone,
      _website,
      _address,
      _city,
      _state,
      _postalCode,
      _country,
      _commissionRate,
      _colorHex,
    ]) {
      c.dispose();
    }
    super.dispose();
  }

  void _setPrimaryColor(Color color) {
    setState(() {
      _primaryColor = color;
      _colorHex.text = toHexColor(color);
    });
    context.read<ThemeProvider>().setColor(color);
  }

  void _onColorHexChanged(String value) {
    final parsed = parseHexColor(value);
    if (parsed == null) return;
    setState(() => _primaryColor = parsed);
    context.read<ThemeProvider>().setColor(parsed);
  }

  Future<void> _pickLogo(ImageSource source) async {
    final picked =
        await ImagePicker().pickImage(source: source, imageQuality: 85);
    if (picked == null) return;

    setState(() => _uploadingLogo = true);
    try {
      final bytes = await picked.readAsBytes();
      final url = await widget.repository.uploadLogo(bytes, picked.name);
      if (mounted) setState(() => _logoUrl = url);
    } on ApiException catch (e) {
      if (mounted)
        ScaffoldMessenger.of(context)
            .showSnackBar(SnackBar(content: Text(e.message)));
    } finally {
      if (mounted) setState(() => _uploadingLogo = false);
    }
  }

  Future<void> _removeLogo() async {
    setState(() => _uploadingLogo = true);
    try {
      await widget.repository.removeLogo();
      if (mounted) setState(() => _logoUrl = null);
    } on ApiException catch (e) {
      if (mounted)
        ScaffoldMessenger.of(context)
            .showSnackBar(SnackBar(content: Text(e.message)));
    } finally {
      if (mounted) setState(() => _uploadingLogo = false);
    }
  }

  Future<void> _pickFavicon(ImageSource source) async {
    final picked =
        await ImagePicker().pickImage(source: source, imageQuality: 85);
    if (picked == null) return;

    setState(() => _uploadingFavicon = true);
    try {
      final bytes = await picked.readAsBytes();
      final url = await widget.repository.uploadFavicon(bytes, picked.name);
      if (mounted) setState(() => _faviconUrl = url);
    } on ApiException catch (e) {
      if (mounted)
        ScaffoldMessenger.of(context)
            .showSnackBar(SnackBar(content: Text(e.message)));
    } finally {
      if (mounted) setState(() => _uploadingFavicon = false);
    }
  }

  Future<void> _removeFavicon() async {
    setState(() => _uploadingFavicon = true);
    try {
      await widget.repository.removeFavicon();
      if (mounted) setState(() => _faviconUrl = null);
    } on ApiException catch (e) {
      if (mounted)
        ScaffoldMessenger.of(context)
            .showSnackBar(SnackBar(content: Text(e.message)));
    } finally {
      if (mounted) setState(() => _uploadingFavicon = false);
    }
  }

  Future<void> _pickDrawerCover(ImageSource source) async {
    final picked =
        await ImagePicker().pickImage(source: source, imageQuality: 85);
    if (picked == null) return;

    setState(() => _uploadingDrawerCover = true);
    try {
      final bytes = await picked.readAsBytes();
      final url = await widget.repository.uploadDrawerCover(bytes, picked.name);
      if (mounted) setState(() => _drawerCoverUrl = url);
    } on ApiException catch (e) {
      if (mounted)
        ScaffoldMessenger.of(context)
            .showSnackBar(SnackBar(content: Text(e.message)));
    } finally {
      if (mounted) setState(() => _uploadingDrawerCover = false);
    }
  }

  Future<void> _removeDrawerCover() async {
    setState(() => _uploadingDrawerCover = true);
    try {
      await widget.repository.removeDrawerCover();
      if (mounted) setState(() => _drawerCoverUrl = null);
    } on ApiException catch (e) {
      if (mounted)
        ScaffoldMessenger.of(context)
            .showSnackBar(SnackBar(content: Text(e.message)));
    } finally {
      if (mounted) setState(() => _uploadingDrawerCover = false);
    }
  }

  Future<void> _save() async {
    if (_name.text.trim().isEmpty) {
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(
          content: Text(AppLocalizations.of(context).storeNameRequired)));
      return;
    }

    setState(() => _saving = true);
    try {
      await widget.repository.updateProfile(
        name: _name.text.trim(),
        tradeName: _tradeName.text.trim(),
        taxId: _taxId.text.trim(),
        email: _email.text.trim(),
        phone: _phone.text.trim(),
        website: _website.text.trim(),
        address: _address.text.trim(),
        city: _city.text.trim(),
        state: _state.text.trim(),
        postalCode: _postalCode.text.trim(),
        country: _countryCode == kOtherCountrySentinel
            ? _country.text.trim()
            : _countryCode,
        timezone: _timezoneOverride ?? '',
        primaryColor: toHexColor(_primaryColor),
        defaultCommissionRate: double.tryParse(_commissionRate.text) ?? 0,
        defaultCommissionType: _commissionType,
      );
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
            SnackBar(content: Text(AppLocalizations.of(context).profileSaved)));
      }
    } on ApiException catch (e) {
      if (mounted)
        ScaffoldMessenger.of(context)
            .showSnackBar(SnackBar(content: Text(e.message)));
    } finally {
      if (mounted) setState(() => _saving = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final l10n = AppLocalizations.of(context);

    return ListView(
      padding: const EdgeInsets.fromLTRB(16, 16, 16, 32),
      children: [
        TextField(
            controller: _name,
            decoration: InputDecoration(labelText: l10n.storeName)),
        const SizedBox(height: 12),
        TextField(
            controller: _tradeName,
            decoration: InputDecoration(labelText: l10n.tradeName)),
        const SizedBox(height: 12),
        TextField(
          controller: _taxId,
          decoration: InputDecoration(
              labelText: _isIndiaSelected ? l10n.gstin : l10n.taxId),
        ),
        const SizedBox(height: 4),
        Align(
          alignment: Alignment.centerLeft,
          child: TextButton.icon(
            onPressed: () => Navigator.of(context)
                .push(MaterialPageRoute(builder: (_) => const TaxesScreen())),
            icon: const Icon(Icons.percent, size: 16),
            label: Text(l10n.manageTaxRules),
          ),
        ),
        const SizedBox(height: 8),
        Row(children: [
          Expanded(
              child: TextField(
                  controller: _email,
                  decoration: InputDecoration(labelText: l10n.email))),
          const SizedBox(width: 12),
          Expanded(
              child: TextField(
                  controller: _phone,
                  decoration: InputDecoration(labelText: l10n.phone))),
        ]),
        const SizedBox(height: 12),
        TextField(
            controller: _website,
            decoration: InputDecoration(labelText: l10n.website)),
        const SizedBox(height: 12),
        TextField(
            controller: _address,
            decoration: InputDecoration(labelText: l10n.address)),
        const SizedBox(height: 12),
        Row(children: [
          Expanded(
              child: TextField(
                  controller: _city,
                  decoration: InputDecoration(labelText: l10n.city))),
          const SizedBox(width: 12),
          Expanded(
              child: TextField(
                  controller: _state,
                  decoration: InputDecoration(labelText: l10n.state))),
        ]),
        const SizedBox(height: 12),
        Row(children: [
          Expanded(
              child: TextField(
                  controller: _postalCode,
                  decoration: InputDecoration(labelText: l10n.postalCode))),
          const SizedBox(width: 12),
          Expanded(
            child: DropdownButtonFormField<String>(
              initialValue: _countryCode,
              decoration: InputDecoration(labelText: l10n.country),
              items: [
                for (final entry in kTaxJurisdictions.entries)
                  DropdownMenuItem(
                      value: entry.key,
                      child: Text('${entry.value} (${entry.key})',
                          overflow: TextOverflow.ellipsis)),
                DropdownMenuItem(
                    value: kOtherCountrySentinel,
                    child: Text(l10n.countryOther)),
              ],
              onChanged: (value) =>
                  setState(() => _countryCode = value ?? _countryCode),
            ),
          ),
        ]),
        if (_countryCode == kOtherCountrySentinel) ...[
          const SizedBox(height: 12),
          TextField(
            controller: _country,
            maxLength: 2,
            decoration: InputDecoration(
                labelText: l10n.countryCodeIso2, counterText: ''),
            onChanged: (_) => setState(() {}),
          ),
        ],
        const SizedBox(height: 20),
        const Divider(),
        const SizedBox(height: 12),
        Text(l10n.timezoneSectionTitle,
            style: Theme.of(context).textTheme.titleMedium),
        const SizedBox(height: 4),
        Text(l10n.timezoneSectionDescription,
            style: TextStyle(color: Colors.grey.shade600, fontSize: 12)),
        const SizedBox(height: 10),
        DropdownButtonFormField<String?>(
          initialValue: _timezoneOverride,
          isExpanded: true,
          decoration: InputDecoration(labelText: l10n.timezoneManualOverride),
          items: [
            DropdownMenuItem<String?>(
              value: null,
              child: Text(
                  l10n.timezoneUseCountryDefault(_defaultTimezoneForCountry),
                  overflow: TextOverflow.ellipsis),
            ),
            for (final tz in widget.timezones)
              DropdownMenuItem<String?>(
                  value: tz, child: Text(tz, overflow: TextOverflow.ellipsis)),
          ],
          onChanged: (value) => setState(() => _timezoneOverride = value),
        ),
        const SizedBox(height: 12),
        Row(children: [
          Expanded(
            child: TextField(
              controller: _commissionRate,
              keyboardType:
                  const TextInputType.numberWithOptions(decimal: true),
              decoration:
                  InputDecoration(labelText: l10n.defaultCommissionRate),
            ),
          ),
          const SizedBox(width: 12),
          Expanded(
            child: DropdownButtonFormField<String>(
              initialValue: _commissionType,
              decoration: InputDecoration(labelText: l10n.type),
              items: [
                DropdownMenuItem(
                    value: 'percentage',
                    child: Text(l10n.commissionPercentage)),
                DropdownMenuItem(
                    value: 'fixed', child: Text(l10n.commissionFixed)),
              ],
              onChanged: (value) =>
                  setState(() => _commissionType = value ?? _commissionType),
            ),
          ),
        ]),
        const SizedBox(height: 20),
        Text(l10n.brandColor,
            style: Theme.of(context)
                .textTheme
                .titleSmall
                ?.copyWith(fontWeight: FontWeight.bold)),
        const SizedBox(height: 4),
        Text(l10n.brandColorDescription,
            style: TextStyle(color: Colors.grey.shade600, fontSize: 12)),
        const SizedBox(height: 10),
        Wrap(
          spacing: 10,
          runSpacing: 10,
          children: [
            for (final swatch in _brandColorSwatches)
              InkWell(
                onTap: () => _setPrimaryColor(swatch),
                customBorder: const CircleBorder(),
                child: Container(
                  width: 36,
                  height: 36,
                  decoration: BoxDecoration(
                    color: swatch,
                    shape: BoxShape.circle,
                    border: swatch.toARGB32() == _primaryColor.toARGB32()
                        ? Border.all(color: Colors.black87, width: 2.5)
                        : Border.all(color: Colors.grey.shade300),
                  ),
                  child: swatch.toARGB32() == _primaryColor.toARGB32()
                      ? const Icon(Icons.check, color: Colors.white, size: 18)
                      : null,
                ),
              ),
          ],
        ),
        const SizedBox(height: 12),
        Row(
          crossAxisAlignment: CrossAxisAlignment.center,
          children: [
            Container(
              width: 40,
              height: 40,
              decoration: BoxDecoration(
                color: _primaryColor,
                shape: BoxShape.circle,
                border: Border.all(color: Colors.grey.shade300),
              ),
            ),
            const SizedBox(width: 12),
            Expanded(
              child: TextField(
                controller: _colorHex,
                decoration: InputDecoration(labelText: l10n.customHex),
                onChanged: _onColorHexChanged,
              ),
            ),
          ],
        ),
        const SizedBox(height: 24),
        Text(l10n.branding,
            style: Theme.of(context)
                .textTheme
                .titleSmall
                ?.copyWith(fontWeight: FontWeight.bold)),
        const SizedBox(height: 4),
        Text(l10n.brandingDescription,
            style: TextStyle(color: Colors.grey.shade600, fontSize: 12)),
        const SizedBox(height: 10),
        Row(
          children: [
            Expanded(
              child: _BrandImagePicker(
                label: l10n.logo,
                imageUrl: _logoUrl,
                busy: _uploadingLogo,
                onPick: (source) => _pickLogo(source),
                onRemove: _logoUrl != null ? _removeLogo : null,
              ),
            ),
            const SizedBox(width: 16),
            Expanded(
              child: _BrandImagePicker(
                label: l10n.favicon,
                imageUrl: _faviconUrl,
                busy: _uploadingFavicon,
                onPick: (source) => _pickFavicon(source),
                onRemove: _faviconUrl != null ? _removeFavicon : null,
              ),
            ),
          ],
        ),
        const SizedBox(height: 16),
        _BrandImagePicker(
          label: l10n.drawerCoverImage,
          imageUrl: _drawerCoverUrl,
          busy: _uploadingDrawerCover,
          onPick: (source) => _pickDrawerCover(source),
          onRemove: _drawerCoverUrl != null ? _removeDrawerCover : null,
        ),
        const SizedBox(height: 20),
        ElevatedButton(
          onPressed: _saving ? null : _save,
          child: _saving
              ? const SizedBox(
                  height: 20,
                  width: 20,
                  child: CircularProgressIndicator(
                      strokeWidth: 2, color: Colors.white))
              : Text(l10n.saveProfile),
        ),
      ],
    );
  }
}

/// A square preview + Camera/Gallery/Remove controls for the logo or
/// favicon — the two are identical apart from which repository call they
/// trigger, so the caller passes that in as [onPick]/[onRemove].
class _BrandImagePicker extends StatelessWidget {
  const _BrandImagePicker({
    required this.label,
    required this.imageUrl,
    required this.busy,
    required this.onPick,
    required this.onRemove,
  });

  final String label;
  final String? imageUrl;
  final bool busy;
  final ValueChanged<ImageSource> onPick;
  final VoidCallback? onRemove;

  @override
  Widget build(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(label,
            style: const TextStyle(fontSize: 12, fontWeight: FontWeight.w600)),
        const SizedBox(height: 6),
        ClipRRect(
          borderRadius: BorderRadius.circular(10),
          child: Container(
            width: 72,
            height: 72,
            color: Colors.grey.shade100,
            alignment: Alignment.center,
            child: busy
                ? const SizedBox(
                    width: 20,
                    height: 20,
                    child: CircularProgressIndicator(strokeWidth: 2))
                : (imageUrl ?? '').isNotEmpty
                    ? CachedNetworkImage(
                        imageUrl: imageUrl!,
                        fit: BoxFit.cover,
                        width: 72,
                        height: 72)
                    : Icon(Icons.image_outlined,
                        size: 28, color: Colors.grey.shade400),
          ),
        ),
        const SizedBox(height: 4),
        Wrap(
          spacing: 4,
          children: [
            IconButton(
              tooltip: AppLocalizations.of(context).camera,
              iconSize: 18,
              padding: EdgeInsets.zero,
              constraints: const BoxConstraints(),
              onPressed: busy ? null : () => onPick(ImageSource.camera),
              icon: const Icon(Icons.photo_camera_outlined),
            ),
            IconButton(
              tooltip: AppLocalizations.of(context).gallery,
              iconSize: 18,
              padding: EdgeInsets.zero,
              constraints: const BoxConstraints(),
              onPressed: busy ? null : () => onPick(ImageSource.gallery),
              icon: const Icon(Icons.photo_library_outlined),
            ),
            if (onRemove != null)
              IconButton(
                tooltip: AppLocalizations.of(context).remove,
                iconSize: 18,
                padding: EdgeInsets.zero,
                constraints: const BoxConstraints(),
                onPressed: busy ? null : onRemove,
                icon: const Icon(Icons.delete_outline, color: Colors.redAccent),
              ),
          ],
        ),
      ],
    );
  }
}

class _ReceiptsTab extends StatefulWidget {
  const _ReceiptsTab({required this.repository, required this.initial});

  final SettingsRepository repository;
  final ReceiptSettings initial;

  @override
  State<_ReceiptsTab> createState() => _ReceiptsTabState();
}

class _ReceiptsTabState extends State<_ReceiptsTab> {
  late final TextEditingController _invoicePrefix;
  late final TextEditingController _quotationPrefix;
  late final TextEditingController _invoiceTerms;
  late final TextEditingController _quoteTerms;
  late final TextEditingController _bankDetails;
  bool _saving = false;

  @override
  void initState() {
    super.initState();
    final r = widget.initial;
    _invoicePrefix = TextEditingController(text: r.invoicePrefix);
    _quotationPrefix = TextEditingController(text: r.quotationPrefix);
    _invoiceTerms = TextEditingController(text: r.invoiceTerms);
    _quoteTerms = TextEditingController(text: r.quoteTerms);
    _bankDetails = TextEditingController(text: r.bankDetails);
  }

  @override
  void dispose() {
    for (final c in [
      _invoicePrefix,
      _quotationPrefix,
      _invoiceTerms,
      _quoteTerms,
      _bankDetails
    ]) {
      c.dispose();
    }
    super.dispose();
  }

  Future<void> _save() async {
    setState(() => _saving = true);
    try {
      await widget.repository.updateReceipts(
        invoicePrefix: _invoicePrefix.text.trim(),
        quotationPrefix: _quotationPrefix.text.trim(),
        invoiceTerms: _invoiceTerms.text.trim(),
        quoteTerms: _quoteTerms.text.trim(),
        bankDetails: _bankDetails.text.trim(),
      );
      if (mounted)
        ScaffoldMessenger.of(context).showSnackBar(
            const SnackBar(content: Text('Receipt settings saved.')));
    } on ApiException catch (e) {
      if (mounted)
        ScaffoldMessenger.of(context)
            .showSnackBar(SnackBar(content: Text(e.message)));
    } finally {
      if (mounted) setState(() => _saving = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return ListView(
      padding: const EdgeInsets.fromLTRB(16, 16, 16, 32),
      children: [
        Row(children: [
          Expanded(
              child: TextField(
                  controller: _invoicePrefix,
                  decoration:
                      const InputDecoration(labelText: 'Invoice prefix'))),
          const SizedBox(width: 12),
          Expanded(
              child: TextField(
                  controller: _quotationPrefix,
                  decoration:
                      const InputDecoration(labelText: 'Quotation prefix'))),
        ]),
        const SizedBox(height: 12),
        TextField(
            controller: _invoiceTerms,
            decoration: const InputDecoration(labelText: 'Invoice terms'),
            maxLines: 3),
        const SizedBox(height: 12),
        TextField(
            controller: _quoteTerms,
            decoration: const InputDecoration(labelText: 'Quote terms'),
            maxLines: 3),
        const SizedBox(height: 12),
        TextField(
            controller: _bankDetails,
            decoration:
                const InputDecoration(labelText: 'Bank & payment details'),
            maxLines: 4),
        const SizedBox(height: 20),
        ElevatedButton(
          onPressed: _saving ? null : _save,
          child: _saving
              ? const SizedBox(
                  height: 20,
                  width: 20,
                  child: CircularProgressIndicator(
                      strokeWidth: 2, color: Colors.white))
              : const Text('Save receipt settings'),
        ),
        const SizedBox(height: 24),
        const Divider(),
        const SizedBox(height: 8),
        ListTile(
          contentPadding: EdgeInsets.zero,
          leading: const Icon(Icons.print_outlined),
          title: const Text('Printer & hardware setup'),
          subtitle: const Text(
              'Pair a Bluetooth / USB / network ESC/POS printer and set paper width'),
          trailing: const Icon(Icons.chevron_right),
          onTap: () => Navigator.of(context).push(
            MaterialPageRoute(builder: (_) => const GlobalPrinterSetupScreen()),
          ),
        ),
      ],
    );
  }
}

class _FinancialTab extends StatefulWidget {
  const _FinancialTab({required this.repository, required this.initial});

  final SettingsRepository repository;
  final FinancialSettings initial;

  @override
  State<_FinancialTab> createState() => _FinancialTabState();
}

class _FinancialTabState extends State<_FinancialTab> {
  late final TextEditingController _currency;
  late final TextEditingController _symbol;
  late final TextEditingController _decimals;
  late String _symbolPosition;
  bool _saving = false;

  @override
  void initState() {
    super.initState();
    final f = widget.initial;
    _currency = TextEditingController(text: f.currency);
    _symbol = TextEditingController(text: f.currencySymbol);
    _decimals = TextEditingController(text: f.currencyDecimals.toString());
    _symbolPosition = f.currencySymbolPosition;
  }

  @override
  void dispose() {
    _currency.dispose();
    _symbol.dispose();
    _decimals.dispose();
    super.dispose();
  }

  Future<void> _save() async {
    if (_currency.text.trim().isEmpty) {
      ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('Currency code is required.')));
      return;
    }

    setState(() => _saving = true);
    try {
      await widget.repository.updateFinancial(
        currency: _currency.text.trim(),
        currencySymbol: _symbol.text.trim(),
        currencyDecimals: int.tryParse(_decimals.text) ?? 2,
        currencySymbolPosition: _symbolPosition,
      );
      if (mounted)
        ScaffoldMessenger.of(context).showSnackBar(
            const SnackBar(content: Text('Financial settings saved.')));
    } on ApiException catch (e) {
      if (mounted)
        ScaffoldMessenger.of(context)
            .showSnackBar(SnackBar(content: Text(e.message)));
    } finally {
      if (mounted) setState(() => _saving = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return ListView(
      padding: const EdgeInsets.fromLTRB(16, 16, 16, 32),
      children: [
        Row(children: [
          Expanded(
            child: TextField(
              controller: _currency,
              maxLength: 3,
              decoration: const InputDecoration(
                  labelText: 'Currency code (e.g. USD)', counterText: ''),
            ),
          ),
          const SizedBox(width: 12),
          Expanded(
              child: TextField(
                  controller: _symbol,
                  decoration: const InputDecoration(labelText: 'Symbol'))),
        ]),
        const SizedBox(height: 12),
        Row(children: [
          Expanded(
            child: TextField(
              controller: _decimals,
              keyboardType: TextInputType.number,
              decoration: const InputDecoration(labelText: 'Decimal places'),
            ),
          ),
          const SizedBox(width: 12),
          Expanded(
            child: DropdownButtonFormField<String>(
              initialValue: _symbolPosition,
              decoration: const InputDecoration(labelText: 'Symbol position'),
              items: const [
                DropdownMenuItem(value: 'prefix', child: Text('Prefix (\$10)')),
                DropdownMenuItem(value: 'suffix', child: Text('Suffix (10\$)')),
              ],
              onChanged: (value) =>
                  setState(() => _symbolPosition = value ?? _symbolPosition),
            ),
          ),
        ]),
        const SizedBox(height: 20),
        ElevatedButton(
          onPressed: _saving ? null : _save,
          child: _saving
              ? const SizedBox(
                  height: 20,
                  width: 20,
                  child: CircularProgressIndicator(
                      strokeWidth: 2, color: Colors.white))
              : const Text('Save financial settings'),
        ),
        const SizedBox(height: 24),
        const Divider(),
        const SizedBox(height: 8),
        ListTile(
          contentPadding: EdgeInsets.zero,
          title: const Text('Payment methods'),
          subtitle:
              const Text('Manage which payment methods appear at checkout'),
          trailing: const Icon(Icons.chevron_right),
          onTap: () => Navigator.of(context).push(
            MaterialPageRoute(
                builder: (_) =>
                    PaymentMethodsScreen(repository: widget.repository)),
          ),
        ),
      ],
    );
  }
}
