import 'package:flutter/material.dart';

import '../../../core/widgets/dashboard_kit.dart';
import '../../../core/widgets/dashboard_shell.dart';

/// Windows desktop dashboard layout built to the reference admin
/// screenshots: a dark top nav bar, a light slate page, and centred white
/// cards (`DashCard`) with indigo primary actions, status pills, toggle rows,
/// selectable option cards and a plain data table.
///
/// Self-contained and provider-free so it can be previewed or dropped into a
/// route as-is; wire real data into the cards to make it live.
class DashboardLayoutScreen extends StatefulWidget {
  const DashboardLayoutScreen({super.key});

  @override
  State<DashboardLayoutScreen> createState() => _DashboardLayoutScreenState();
}

class _DashboardLayoutScreenState extends State<DashboardLayoutScreen> {
  int _tab = 0;

  @override
  Widget build(BuildContext context) {
    return DashboardShell(
      brand: 'Sales & Inventory',
      selectedIndex: _tab,
      onSelect: (i) => setState(() => _tab = i),
      destinations: const [
        DashNavItem('Overview', icon: Icons.dashboard_outlined),
        DashNavItem('Platform', icon: Icons.tune),
        DashNavItem('Licenses', icon: Icons.vpn_key_outlined),
      ],
      trailing: [
        TextButton(
          onPressed: () {},
          style: TextButton.styleFrom(foregroundColor: Colors.white),
          child: const Text('Sign out'),
        ),
      ],
      child: switch (_tab) {
        2 => const _LicensesPage(),
        1 => const _PlatformPage(),
        _ => const _OverviewPage(),
      },
    );
  }
}

// ---------------------------------------------------------------------------
// Overview
// ---------------------------------------------------------------------------

class _OverviewPage extends StatelessWidget {
  const _OverviewPage();

  @override
  Widget build(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        const _PageTitle('Overview'),
        const SizedBox(height: 20),
        LayoutBuilder(
          builder: (context, c) {
            final cols = c.maxWidth >= 900 ? 4 : (c.maxWidth >= 560 ? 2 : 1);
            const stats = [
              ('Today\'s Sales', '\$4,182', '↑ 12% vs yesterday'),
              ('Open Tickets', '7', '3 awaiting parts'),
              ('Low Stock Items', '14', 'across 3 branches'),
              ('Active Registers', '5', 'all synced'),
            ];
            return Wrap(
              spacing: 16,
              runSpacing: 16,
              children: [
                for (final (label, value, hint) in stats)
                  SizedBox(
                    width: (c.maxWidth - (cols - 1) * 16) / cols,
                    child: DashCard(
                      padding: const EdgeInsets.all(20),
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(label,
                              style: const TextStyle(
                                  fontSize: 12.5,
                                  fontWeight: FontWeight.w700,
                                  color: DashTokens.muted)),
                          const SizedBox(height: 8),
                          Text(value,
                              style: const TextStyle(
                                  fontSize: 26,
                                  fontWeight: FontWeight.w900,
                                  color: DashTokens.ink)),
                          const SizedBox(height: 6),
                          Text(hint,
                              style: const TextStyle(
                                  fontSize: 11.5, color: DashTokens.faint)),
                        ],
                      ),
                    ),
                  ),
              ],
            );
          },
        ),
        const SizedBox(height: 20),
        DashCard(
          emoji: '🧾',
          title: 'Recent Sales',
          subtitle: 'Last transactions across every branch',
          child: DashTable(
            columns: const [
              'Invoice',
              'Branch',
              'Customer',
              'Total',
              'Status',
              'Actions'
            ],
            rows: [
              _sale('INV-2026-0912', 'Central', 'Ramu K.', '\$41.60', 'Paid'),
              _sale('INV-2026-0911', 'Airport', 'Walk-in', '\$8.90', 'Paid'),
              _sale('INV-2026-0910', 'Central', 'Metro Cafe', '\$182.00',
                  'Partial',
                  tone: DashStatusTone.warn),
            ],
          ),
        ),
      ],
    );
  }

  List<Widget> _sale(
      String inv, String branch, String cust, String total, String status,
      {DashStatusTone tone = DashStatusTone.ok}) {
    return [
      Text(inv,
          style: const TextStyle(
              fontFeatures: [FontFeature.tabularFigures()],
              fontWeight: FontWeight.w700,
              color: DashTokens.ink)),
      Text(branch, style: const TextStyle(color: DashTokens.muted)),
      Text(cust, style: const TextStyle(color: DashTokens.ink)),
      Text(total,
          style: const TextStyle(
              fontWeight: FontWeight.w800, color: DashTokens.ink)),
      DashStatusPill(status, tone: tone),
      Row(mainAxisSize: MainAxisSize.min, children: [
        DashRowAction('View', onPressed: () {}),
        const SizedBox(width: 8),
        DashRowAction('Refund', onPressed: () {}, danger: true),
      ]),
    ];
  }
}

// ---------------------------------------------------------------------------
// Platform  (SuperAdmin module-governance style)
// ---------------------------------------------------------------------------

class _PlatformPage extends StatefulWidget {
  const _PlatformPage();

  @override
  State<_PlatformPage> createState() => _PlatformPageState();
}

class _PlatformPageState extends State<_PlatformPage> {
  bool _poweredBy = false;
  bool _aiImages = false;
  final _modules = <String, bool>{
    'Retail': true,
    'Cafe & Restaurant': true,
    'Pharmacy POS': true,
    'Service & Salon': true,
    'Repair & Service Workbench': true,
  };
  static const _moduleMeta = {
    'Retail': ('RETAIL', 'Shops, electronics, general stores'),
    'Cafe & Restaurant': ('RESTAURANT', 'Tables, KOT, kitchen display'),
    'Pharmacy POS': ('PHARMACY', 'Batches, expiry dates, medicines'),
    'Service & Salon': ('SERVICE_BOOKING', 'Appointments, stylist bookings'),
    'Repair & Service Workbench': (
      'REPAIR_TECHNICIAN',
      'Tickets, technician workbench, spare parts billing'
    ),
  };

  int get _activeModules => _modules.values.where((v) => v).length;

  @override
  Widget build(BuildContext context) {
    return Stack(
      children: [
        Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            const _PageTitle('Platform Settings'),
            const SizedBox(height: 20),
            DashCard(
              child: DashToggleRow(
                emoji: '🏷️',
                title: '"Powered By" Receipt Branding',
                description:
                    'When enabled, receipts, invoices and quotations across '
                    'every tenant show a "Powered by" footer crediting the '
                    'platform.',
                value: _poweredBy,
                onChanged: (v) => setState(() => _poweredBy = v),
              ),
            ),
            const SizedBox(height: 16),
            DashCard(
              emoji: '🧩',
              title: 'Module Governance & Registration Modes',
              subtitle:
                  'Globally enable or disable the store types available during '
                  'new tenant signups on web and mobile.',
              trailing: DashPill('$_activeModules Active'),
              child: LayoutBuilder(
                builder: (context, c) {
                  final two = c.maxWidth >= 640;
                  final w = two ? (c.maxWidth - 14) / 2 : c.maxWidth;
                  return Wrap(
                    spacing: 14,
                    runSpacing: 14,
                    children: [
                      for (final name in _modules.keys)
                        SizedBox(
                          width: w,
                          child: DashSelectableCard(
                            title: name,
                            tag: _moduleMeta[name]!.$1,
                            subtitle: _moduleMeta[name]!.$2,
                            selected: _modules[name]!,
                            onTap: () => setState(
                                () => _modules[name] = !_modules[name]!),
                          ),
                        ),
                    ],
                  );
                },
              ),
            ),
            const SizedBox(height: 16),
            DashCard(
              child: DashToggleRow(
                emoji: '✨',
                title: 'Platform AI Product Image Generation',
                description:
                    'When enabled, tenants without their own AI API key can '
                    'still generate product images using this platform-wide key.',
                value: _aiImages,
                onChanged: (v) => setState(() => _aiImages = v),
              ),
            ),
            const SizedBox(height: 88),
          ],
        ),
        Positioned(
          right: 0,
          bottom: 12,
          child: DashPrimaryButton(
            emoji: '💾',
            label: 'Save Platform Settings',
            floating: true,
            onPressed: () {},
          ),
        ),
      ],
    );
  }
}

// ---------------------------------------------------------------------------
// Licenses  (License Manager style)
// ---------------------------------------------------------------------------

class _LicensesPage extends StatelessWidget {
  const _LicensesPage();

  @override
  Widget build(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        const _PageTitle('License Manager'),
        const SizedBox(height: 20),
        DashCard(
          emoji: '🔑',
          title: 'Issue a license',
          child: LayoutBuilder(
            builder: (context, c) {
              final cols = c.maxWidth >= 900 ? 4 : (c.maxWidth >= 560 ? 2 : 1);
              double w(int span) =>
                  (c.maxWidth - (cols - 1) * 16) / cols * span +
                  (span - 1) * 16;
              return Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Wrap(
                    spacing: 16,
                    runSpacing: 16,
                    children: [
                      _field('Product', w(1), value: 'Main SaaS Script (core)'),
                      _field('Client email', w(1), hint: ''),
                      _field('Bind domain (optional)', w(1),
                          hint: 'acme.example.com'),
                      _field('Valid until (optional)', w(1),
                          hint: 'mm/dd/yyyy'),
                      _field('Plan (optional)', w(1), hint: 'regular'),
                      _field('Key (optional)', w(1), hint: 'auto-generated'),
                    ],
                  ),
                  const SizedBox(height: 18),
                  Align(
                    alignment: Alignment.centerLeft,
                    child: DashPrimaryButton(
                        label: 'Issue license', onPressed: () {}),
                  ),
                ],
              );
            },
          ),
        ),
        const SizedBox(height: 16),
        DashCard(
          emoji: '📋',
          title: 'Licenses',
          child: DashTable(
            columns: const [
              'Key',
              'Product',
              'Client',
              'Domain',
              'Status',
              'Valid until',
              'Last seen',
              'Actions',
            ],
            rows: [
              [
                Text('aa',
                    style: const TextStyle(
                        fontFeatures: [FontFeature.tabularFigures()],
                        fontWeight: FontWeight.w700)),
                const DashPill('pharmacy'),
                const Text('ramu@gmail.com',
                    style: TextStyle(color: DashTokens.ink)),
                const Text('unbound',
                    style: TextStyle(color: DashTokens.faint)),
                const DashStatusPill('active'),
                const Text('2026-09-30',
                    style: TextStyle(color: DashTokens.muted)),
                const Text('—', style: TextStyle(color: DashTokens.faint)),
                Row(mainAxisSize: MainAxisSize.min, children: [
                  DashRowAction('Suspend', onPressed: () {}),
                  const SizedBox(width: 8),
                  DashRowAction('Revoke', onPressed: () {}),
                  const SizedBox(width: 8),
                  DashRowAction('Reset domain', onPressed: () {}),
                  const SizedBox(width: 8),
                  DashRowAction('Delete', onPressed: () {}, danger: true),
                ]),
              ],
            ],
          ),
        ),
      ],
    );
  }

  Widget _field(String label, double width, {String? value, String? hint}) {
    return SizedBox(
      width: width,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        mainAxisSize: MainAxisSize.min,
        children: [
          Text(label,
              style: const TextStyle(
                  fontSize: 12,
                  fontWeight: FontWeight.w800,
                  color: DashTokens.ink)),
          const SizedBox(height: 6),
          TextField(
            controller:
                value == null ? null : TextEditingController(text: value),
            decoration: InputDecoration(
              isDense: true,
              hintText: hint,
              contentPadding:
                  const EdgeInsets.symmetric(horizontal: 12, vertical: 12),
              filled: true,
              fillColor: Colors.white,
              enabledBorder: OutlineInputBorder(
                borderRadius: BorderRadius.circular(10),
                borderSide: const BorderSide(color: DashTokens.cardBorder),
              ),
              border: OutlineInputBorder(
                borderRadius: BorderRadius.circular(10),
                borderSide: const BorderSide(color: DashTokens.cardBorder),
              ),
            ),
          ),
        ],
      ),
    );
  }
}

class _PageTitle extends StatelessWidget {
  const _PageTitle(this.text);
  final String text;

  @override
  Widget build(BuildContext context) => Align(
        alignment: Alignment.centerLeft,
        child: Text(text,
            style: const TextStyle(
                fontSize: 24,
                fontWeight: FontWeight.w900,
                color: DashTokens.ink)),
      );
}
