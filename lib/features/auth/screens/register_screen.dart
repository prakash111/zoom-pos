import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import 'package:timezone/data/latest.dart' as tzdata;
import 'package:timezone/timezone.dart' as tz;

import '../../../core/api/api_client.dart';
import '../../../core/config/app_config.dart';
import '../../../core/config/countries.dart';
import '../../../core/sdui/sdui_icon_registry.dart';
import '../auth_provider.dart';
import '../widgets/auth_scaffold.dart';
import '../widgets/auth_widgets.dart';
import 'verify_otp_screen.dart';

/// Curated ISO-4217 set for the sign-up currency picker — the codes a new
/// store is realistically opened in. Any other code can still be set later
/// under Settings ▸ Financial.
const Map<String, String> _kRegistrationCurrencies = {
  'USD': 'US Dollar',
  'EUR': 'Euro',
  'GBP': 'British Pound',
  'AUD': 'Australian Dollar',
  'CAD': 'Canadian Dollar',
  'NZD': 'New Zealand Dollar',
  'CHF': 'Swiss Franc',
  'JPY': 'Japanese Yen',
  'CNY': 'Chinese Yuan',
  'HKD': 'Hong Kong Dollar',
  'SGD': 'Singapore Dollar',
  'INR': 'Indian Rupee',
  'PKR': 'Pakistani Rupee',
  'BDT': 'Bangladeshi Taka',
  'LKR': 'Sri Lankan Rupee',
  'NPR': 'Nepalese Rupee',
  'AED': 'UAE Dirham',
  'SAR': 'Saudi Riyal',
  'QAR': 'Qatari Riyal',
  'KWD': 'Kuwaiti Dinar',
  'BHD': 'Bahraini Dinar',
  'OMR': 'Omani Rial',
  'JOD': 'Jordanian Dinar',
  'ILS': 'Israeli Shekel',
  'TRY': 'Turkish Lira',
  'EGP': 'Egyptian Pound',
  'NGN': 'Nigerian Naira',
  'GHS': 'Ghanaian Cedi',
  'KES': 'Kenyan Shilling',
  'TZS': 'Tanzanian Shilling',
  'UGX': 'Ugandan Shilling',
  'ZAR': 'South African Rand',
  'MAD': 'Moroccan Dirham',
  'BRL': 'Brazilian Real',
  'MXN': 'Mexican Peso',
  'ARS': 'Argentine Peso',
  'CLP': 'Chilean Peso',
  'COP': 'Colombian Peso',
  'PEN': 'Peruvian Sol',
  'IDR': 'Indonesian Rupiah',
  'MYR': 'Malaysian Ringgit',
  'THB': 'Thai Baht',
  'PHP': 'Philippine Peso',
  'VND': 'Vietnamese Dong',
  'KRW': 'South Korean Won',
  'RUB': 'Russian Ruble',
  'UAH': 'Ukrainian Hryvnia',
  'PLN': 'Polish Zloty',
  'CZK': 'Czech Koruna',
  'HUF': 'Hungarian Forint',
  'RON': 'Romanian Leu',
  'SEK': 'Swedish Krona',
  'NOK': 'Norwegian Krone',
  'DKK': 'Danish Krone',
};

typedef _Option = ({String value, String label});

class RegisterScreen extends StatefulWidget {
  const RegisterScreen({super.key});

  @override
  State<RegisterScreen> createState() => _RegisterScreenState();
}

class _RegisterScreenState extends State<RegisterScreen> {
  static const _stepTitles = <String>[
    'Account credentials',
    'Store profile',
    'Localization & currency',
  ];

  final _step1Key = GlobalKey<FormState>();
  final _step2Key = GlobalKey<FormState>();
  final _step3Key = GlobalKey<FormState>();

  final _ownerNameController = TextEditingController();
  final _emailController = TextEditingController();
  final _passwordController = TextEditingController();
  final _phoneController = TextEditingController();
  final _storeNameController = TextEditingController();

  bool _obscurePassword = true;
  int _step = 0;

  String _posMode = '';
  List<Map<String, dynamic>> _registrationModes = [];
  bool _loadingModules = true;
  String? _moduleLoadError;

  String _countryCode = '';
  String _currency = 'USD';
  String _timezone = 'UTC';
  late final List<String> _timezones;

  @override
  void initState() {
    super.initState();

    tzdata.initializeTimeZones();
    final zones = tz.timeZoneDatabase.locations.keys.toList()..sort();
    _timezones = zones.isEmpty ? const ['UTC'] : zones;

    final client = context.read<ApiClient>();
    Future<Map<String, dynamic>> fetch() async {
      try {
        return await client.getAbsolute(ApiEndpoints.registrationMetaAbsolute);
      } catch (_) {
        return await client.get(ApiEndpoints.registrationConfig);
      }
    }

    fetch().then((response) {
      if (!mounted) return;
      final rawModes =
          response['registration_modes'] ?? response['active_modules'];
      if (rawModes is List && rawModes.isNotEmpty) {
        final parsed = rawModes
            .whereType<Map>()
            .map((m) => Map<String, dynamic>.from(m))
            .where((m) =>
                (m['key']?.toString() ?? m['id']?.toString() ?? '').isNotEmpty)
            .toList();
        if (parsed.isEmpty) {
          setState(() {
            _loadingModules = false;
            _moduleLoadError =
                'No valid registration modules were returned by the server.';
          });
          return;
        }
        setState(() {
          _registrationModes = parsed;
          final defaultMode = response['default_mode']?.toString() ??
              (parsed.first['key'] ?? parsed.first['id']).toString();
          _posMode = defaultMode;
          _loadingModules = false;
          _moduleLoadError = null;
        });
      } else {
        setState(() {
          _loadingModules = false;
          _moduleLoadError = 'No registration modules are currently available.';
        });
      }
    }).catchError((_) {
      if (!mounted) return;
      setState(() {
        _loadingModules = false;
        _moduleLoadError =
            'Unable to load store types. Check the server connection and retry.';
      });
    });
  }

  @override
  void dispose() {
    _ownerNameController.dispose();
    _emailController.dispose();
    _passwordController.dispose();
    _phoneController.dispose();
    _storeNameController.dispose();
    super.dispose();
  }

  bool _validateCurrentStep() {
    switch (_step) {
      case 0:
        return _step1Key.currentState?.validate() ?? false;
      case 1:
        final formOk = _step2Key.currentState?.validate() ?? false;
        if (!formOk) return false;
        if (_loadingModules) {
          _snack('Store types are still loading — try again in a moment.');
          return false;
        }
        if (_moduleLoadError != null) {
          _snack(_moduleLoadError!);
          return false;
        }
        if (_posMode.isEmpty) {
          _snack('Select a store type before continuing.');
          return false;
        }
        return true;
      default:
        return _step3Key.currentState?.validate() ?? false;
    }
  }

  void _snack(String message) {
    ScaffoldMessenger.of(context)
        .showSnackBar(SnackBar(content: Text(message)));
  }

  void _next() {
    if (!_validateCurrentStep()) return;
    if (_step < 2) setState(() => _step++);
  }

  void _back() {
    if (_step > 0) setState(() => _step--);
  }

  Future<void> _submit() async {
    if (!_validateCurrentStep()) return;

    final auth = context.read<AuthProvider>();
    final result = await auth.register(
      storeName: _storeNameController.text.trim(),
      ownerName: _ownerNameController.text.trim(),
      email: _emailController.text.trim(),
      password: _passwordController.text,
      phone: _phoneController.text.trim(),
      posMode: _posMode,
      currency: _currency,
      country: _countryCode,
      timezone: _timezone,
    );

    if (!mounted) return;

    if (result != null) {
      if (result.requiresOtp) {
        final email = result.email ?? _emailController.text.trim();
        Navigator.of(context).pushReplacement(
          MaterialPageRoute(
            builder: (ctx) => VerifyOtpScreen(
              email: email,
              tokenExpiry: result.expiresIn,
            ),
          ),
        );
        return;
      }

      Navigator.of(context).pop();
      return;
    }

    if (auth.errorMessage != null) {
      _snack(auth.errorMessage!);
    }
  }

  @override
  Widget build(BuildContext context) {
    final auth = context.watch<AuthProvider>();
    final isLastStep = _step == 2;

    return AuthScaffold(
      brandHeadline: 'Create your account',
      brandSubline: 'Start managing your business today.',
      headerTrailing: TextButton.icon(
        onPressed: auth.isBusy ? null : () => Navigator.of(context).maybePop(),
        icon: const Icon(Icons.arrow_back, size: 16),
        label: const Text('Back to login'),
        style: TextButton.styleFrom(
          foregroundColor: AuthColors.link,
          textStyle: const TextStyle(fontWeight: FontWeight.w700, fontSize: 13),
        ),
      ),
      maxCardWidth: 520,
      form: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        mainAxisSize: MainAxisSize.min,
        children: [
          _StepIndicator(step: _step, total: 3),
          const SizedBox(height: 10),
          Text(
            'Step ${_step + 1} of 3',
            style: TextStyle(
              fontSize: 12,
              fontWeight: FontWeight.w600,
              color: Theme.of(context)
                  .colorScheme
                  .onSurface
                  .withValues(alpha: 0.6),
            ),
          ),
          const SizedBox(height: 2),
          Text(
            _stepTitles[_step],
            style: const TextStyle(fontSize: 17, fontWeight: FontWeight.w800),
          ),
          const SizedBox(height: 18),
          AnimatedSwitcher(
            duration: const Duration(milliseconds: 200),
            switchInCurve: Curves.easeOut,
            switchOutCurve: Curves.easeIn,
            child: KeyedSubtree(
              key: ValueKey<int>(_step),
              child: switch (_step) {
                0 => _stepOne(),
                1 => _stepTwo(context),
                _ => _stepThree(),
              },
            ),
          ),
          const SizedBox(height: 22),
          Row(
            children: [
              if (_step > 0) ...[
                Expanded(
                  child: OutlinedButton(
                    style: OutlinedButton.styleFrom(
                      minimumSize: const Size.fromHeight(54),
                      foregroundColor: AuthColors.ink,
                      side: const BorderSide(
                          color: AuthColors.border, width: 1.2),
                      shape: RoundedRectangleBorder(
                        borderRadius: BorderRadius.circular(14),
                      ),
                    ),
                    onPressed: auth.isBusy ? null : _back,
                    child: const Text('Back'),
                  ),
                ),
                const SizedBox(width: 12),
              ],
              Expanded(
                child: AuthPrimaryButton(
                  label: isLastStep ? 'Create store' : 'Continue',
                  busy: auth.isBusy,
                  onPressed:
                      auth.isBusy ? null : (isLastStep ? _submit : _next),
                ),
              ),
            ],
          ),
        ],
      ),
      belowCard: Column(
        children: [
          AuthInfoCard(
            icon: Icons.person_add_alt_1_outlined,
            title: const Text('Already have an account?'),
            subtitle: const Text('Sign in to your existing workspace'),
            trailing: const Icon(Icons.arrow_forward, color: AuthColors.link),
            onTap: auth.isBusy ? null : () => Navigator.of(context).maybePop(),
          ),
          const SizedBox(height: 16),
          const Text(
            'Together, we build better businesses.',
            textAlign: TextAlign.center,
            style: TextStyle(fontSize: 12.5, color: AuthColors.muted),
          ),
        ],
      ),
    );
  }

  // --- Step 1: account credentials -----------------------------------------

  Widget _stepOne() {
    return Form(
      key: _step1Key,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        mainAxisSize: MainAxisSize.min,
        children: [
          const AuthFieldLabel('Your name'),
          TextFormField(
            controller: _ownerNameController,
            textInputAction: TextInputAction.next,
            decoration: authInputDecoration(
              hint: 'Enter your full name',
              icon: Icons.person_outline,
            ),
            validator: (value) =>
                (value == null || value.trim().isEmpty) ? 'Required' : null,
          ),
          const SizedBox(height: 16),
          const AuthFieldLabel('Email address'),
          TextFormField(
            controller: _emailController,
            keyboardType: TextInputType.emailAddress,
            autocorrect: false,
            textInputAction: TextInputAction.next,
            decoration: authInputDecoration(
              hint: 'Enter your email address',
              icon: Icons.mail_outline,
            ),
            validator: (value) {
              if (value == null || value.trim().isEmpty) return 'Required';
              if (!value.contains('@')) return 'Enter a valid email';
              return null;
            },
          ),
          const SizedBox(height: 16),
          const AuthFieldLabel('Password'),
          TextFormField(
            controller: _passwordController,
            obscureText: _obscurePassword,
            textInputAction: TextInputAction.next,
            decoration: authInputDecoration(
              hint: 'Create a password',
              icon: Icons.lock_outline,
              suffixIcon: IconButton(
                icon: Icon(
                  _obscurePassword
                      ? Icons.visibility_outlined
                      : Icons.visibility_off_outlined,
                  size: 20,
                  color: AuthColors.faint,
                ),
                onPressed: () =>
                    setState(() => _obscurePassword = !_obscurePassword),
              ),
            ),
            validator: (value) => (value == null || value.length < 6)
                ? 'At least 6 characters'
                : null,
          ),
          const SizedBox(height: 16),
          const AuthFieldLabel('Phone number'),
          TextFormField(
            controller: _phoneController,
            keyboardType: TextInputType.phone,
            decoration: authInputDecoration(
              hint: 'Enter your phone (optional)',
              icon: Icons.phone_outlined,
            ),
          ),
        ],
      ),
    );
  }

  // --- Step 2: store profile ---------------------------------------------

  Widget _stepTwo(BuildContext context) {
    return Form(
      key: _step2Key,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        mainAxisSize: MainAxisSize.min,
        children: [
          const AuthFieldLabel('Business name'),
          TextFormField(
            controller: _storeNameController,
            textInputAction: TextInputAction.next,
            decoration: authInputDecoration(
              hint: 'Enter your business name',
              icon: Icons.storefront_outlined,
            ),
            validator: (value) =>
                (value == null || value.trim().isEmpty) ? 'Required' : null,
          ),
          const SizedBox(height: 18),
          const AuthFieldLabel('Store type'),
          const SizedBox(height: 2),
          if (_loadingModules)
            const Center(child: CircularProgressIndicator())
          else if (_moduleLoadError != null)
            Text(
              _moduleLoadError!,
              style: TextStyle(color: Theme.of(context).colorScheme.error),
            )
          else
            LayoutBuilder(
              builder: (context, constraints) {
                final cardWidth = constraints.maxWidth > 320
                    ? (constraints.maxWidth - 10) / 2
                    : constraints.maxWidth;
                return Wrap(
                  spacing: 10,
                  runSpacing: 10,
                  children: [
                    for (final mod in _registrationModes)
                      SizedBox(
                        width: _registrationModes.length == 1
                            ? constraints.maxWidth
                            : cardWidth,
                        child: _StoreTypeCard(
                          icon:
                              SduiIconRegistry.resolve(mod['icon']?.toString()),
                          label: mod['title']?.toString() ??
                              mod['key']?.toString() ??
                              mod['id']?.toString() ??
                              '',
                          description: mod['subtitle']?.toString() ??
                              mod['description']?.toString() ??
                              '',
                          selected: _posMode ==
                              (mod['key']?.toString() ??
                                  mod['id']?.toString() ??
                                  ''),
                          onTap: () => setState(() =>
                              _posMode = (mod['key'] ?? mod['id']).toString()),
                        ),
                      ),
                  ],
                );
              },
            ),
        ],
      ),
    );
  }

  // --- Step 3: localization & currency ----------------------------------

  Widget _stepThree() {
    final countryOptions = <_Option>[
      for (final e in countryPickerOptions())
        (value: e.key, label: '${e.value} (${e.key})'),
    ];
    final currencyOptions = <_Option>[
      for (final e in _kRegistrationCurrencies.entries)
        (value: e.key, label: '${e.key} — ${e.value}'),
    ];
    final timezoneOptions = <_Option>[
      for (final z in _timezones) (value: z, label: z),
    ];

    return Form(
      key: _step3Key,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        mainAxisSize: MainAxisSize.min,
        children: [
          const AuthFieldLabel('Country'),
          _SearchablePicker(
            label: 'Country',
            icon: Icons.public_outlined,
            value: _countryCode,
            options: countryOptions,
            onChanged: (v) => setState(() => _countryCode = v),
            validator: (v) =>
                (v == null || v.isEmpty) ? 'Select a country' : null,
          ),
          const SizedBox(height: 16),
          const AuthFieldLabel('Currency'),
          _SearchablePicker(
            label: 'Currency',
            icon: Icons.payments_outlined,
            value: _currency,
            options: currencyOptions,
            onChanged: (v) => setState(() => _currency = v),
          ),
          const SizedBox(height: 16),
          const AuthFieldLabel('Timezone'),
          _SearchablePicker(
            label: 'Timezone',
            icon: Icons.schedule_outlined,
            value: _timezone,
            options: timezoneOptions,
            onChanged: (v) => setState(() => _timezone = v),
          ),
        ],
      ),
    );
  }
}

/// Three-segment progress bar for the sign-up wizard.
class _StepIndicator extends StatelessWidget {
  const _StepIndicator({required this.step, required this.total});

  final int step;
  final int total;

  @override
  Widget build(BuildContext context) {
    final scheme = Theme.of(context).colorScheme;
    return Row(
      children: [
        for (var i = 0; i < total; i++) ...[
          if (i > 0) const SizedBox(width: 6),
          Expanded(
            child: AnimatedContainer(
              duration: const Duration(milliseconds: 200),
              height: 5,
              decoration: BoxDecoration(
                color: i <= step
                    ? scheme.primary
                    : scheme.outlineVariant.withValues(alpha: 0.5),
                borderRadius: BorderRadius.circular(3),
              ),
            ),
          ),
        ],
      ],
    );
  }
}

/// A tappable field that opens an autofocused, case-insensitive searchable
/// list — used for the long Country / Currency / Timezone option sets. Wired
/// as a [FormField] so per-step validation can require a selection.
class _SearchablePicker extends FormField<String> {
  _SearchablePicker({
    required String label,
    required IconData icon,
    required String value,
    required List<_Option> options,
    required ValueChanged<String> onChanged,
    super.validator,
  }) : super(
          initialValue: value,
          builder: (state) {
            final current = options.cast<_Option?>().firstWhere(
                  (o) => o!.value == state.value,
                  orElse: () => null,
                );
            final display = current?.label ??
                (state.value == null || state.value!.isEmpty
                    ? ''
                    : state.value!);
            final hasValue = display.isNotEmpty;

            return InkWell(
              key: ValueKey<String>('picker_$label'),
              borderRadius: BorderRadius.circular(14),
              onTap: () async {
                FocusScope.of(state.context).unfocus();
                final picked = await showModalBottomSheet<String>(
                  context: state.context,
                  isScrollControlled: true,
                  builder: (_) => _PickerSheet(
                    title: label,
                    options: options,
                    selected: state.value,
                  ),
                );
                if (picked != null) {
                  state.didChange(picked);
                  onChanged(picked);
                }
              },
              child: InputDecorator(
                isEmpty: !hasValue,
                decoration:
                    authInputDecoration(hint: label, icon: icon).copyWith(
                  errorText: state.errorText,
                  suffixIcon: const Icon(Icons.expand_more, size: 22),
                ),
                child: hasValue
                    ? Text(
                        display,
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                        style: const TextStyle(
                          fontSize: 14.5,
                          color: Color(0xFF0F172A),
                        ),
                      )
                    : const SizedBox(height: 20),
              ),
            );
          },
        );
}

class _PickerSheet extends StatefulWidget {
  const _PickerSheet({
    required this.title,
    required this.options,
    this.selected,
  });

  final String title;
  final List<_Option> options;
  final String? selected;

  @override
  State<_PickerSheet> createState() => _PickerSheetState();
}

class _PickerSheetState extends State<_PickerSheet> {
  String _query = '';

  @override
  Widget build(BuildContext context) {
    final q = _query.trim().toLowerCase();
    final filtered = q.isEmpty
        ? widget.options
        : widget.options
            .where((o) =>
                o.label.toLowerCase().contains(q) ||
                o.value.toLowerCase().contains(q))
            .toList();

    return Padding(
      padding:
          EdgeInsets.only(bottom: MediaQuery.of(context).viewInsets.bottom),
      child: SizedBox(
        height: MediaQuery.of(context).size.height * 0.72,
        child: Column(
          children: [
            const SizedBox(height: 10),
            Container(
              width: 40,
              height: 4,
              decoration: BoxDecoration(
                color: Theme.of(context).colorScheme.outlineVariant,
                borderRadius: BorderRadius.circular(2),
              ),
            ),
            Padding(
              padding: const EdgeInsets.fromLTRB(16, 12, 16, 8),
              child: TextField(
                autofocus: true,
                onChanged: (v) => setState(() => _query = v),
                decoration: InputDecoration(
                  hintText: 'Search ${widget.title.toLowerCase()}',
                  prefixIcon: const Icon(Icons.search),
                  suffixIcon: _query.isEmpty
                      ? null
                      : IconButton(
                          icon: const Icon(Icons.clear),
                          onPressed: () => setState(() => _query = ''),
                        ),
                  isDense: true,
                  border: const OutlineInputBorder(),
                ),
              ),
            ),
            Expanded(
              child: filtered.isEmpty
                  ? const Center(child: Text('No matches'))
                  : ListView.builder(
                      itemCount: filtered.length,
                      itemBuilder: (_, i) {
                        final o = filtered[i];
                        final selected = o.value == widget.selected;
                        return ListTile(
                          dense: true,
                          selected: selected,
                          title: Text(o.label),
                          trailing: selected
                              ? Icon(Icons.check,
                                  color: Theme.of(context).colorScheme.primary)
                              : null,
                          onTap: () => Navigator.of(context).pop(o.value),
                        );
                      },
                    ),
            ),
          ],
        ),
      ),
    );
  }
}

class _StoreTypeCard extends StatelessWidget {
  const _StoreTypeCard({
    required this.icon,
    required this.label,
    required this.description,
    required this.selected,
    required this.onTap,
  });

  final IconData icon;
  final String label;
  final String description;
  final bool selected;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final primaryColor = Theme.of(context).colorScheme.primary;

    return InkWell(
      borderRadius: BorderRadius.circular(14),
      onTap: onTap,
      child: AnimatedContainer(
        duration: const Duration(milliseconds: 150),
        padding: const EdgeInsets.all(12),
        decoration: BoxDecoration(
          color: selected
              ? primaryColor.withValues(alpha: 0.08)
              : AuthColors.fieldFill,
          borderRadius: BorderRadius.circular(14),
          border: Border.all(
              color: selected ? primaryColor : AuthColors.border,
              width: selected ? 2 : 1.2),
        ),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Icon(icon, color: selected ? primaryColor : AuthColors.muted),
            const SizedBox(height: 6),
            Text(label,
                style: TextStyle(
                    fontWeight: FontWeight.bold,
                    color: selected ? primaryColor : AuthColors.ink)),
            const SizedBox(height: 2),
            Text(description,
                style: const TextStyle(fontSize: 11, color: AuthColors.muted)),
          ],
        ),
      ),
    );
  }
}
