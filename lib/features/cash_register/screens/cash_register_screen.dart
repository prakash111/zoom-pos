import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../../core/api/api_client.dart';
import '../../../core/utils/currency_formatter.dart';
import '../../../core/widgets/error_view.dart';
import '../../../core/widgets/loading_indicator.dart';
import '../../auth/auth_provider.dart';
import '../cash_register_provider.dart';
import '../cash_register_repository.dart';
import '../widgets/cash_register_status_card.dart';
import 'cash_movement_sheet.dart';
import 'close_register_sheet.dart';
import 'open_register_sheet.dart';
import 'register_history_screen.dart';

/// Cash Register: current shift status, open/close, cash-in/cash-out
/// movements, and a link to shift history. Owns a [CashRegisterProvider]
/// scoped to this route.
class CashRegisterScreen extends StatelessWidget {
  const CashRegisterScreen({super.key});

  @override
  Widget build(BuildContext context) {
    final apiClient = context.read<ApiClient>();

    return ChangeNotifierProvider(
      create: (_) =>
          CashRegisterProvider(repository: CashRegisterRepository(apiClient))
            ..loadCurrent(),
      child: const _CashRegisterScreenBody(),
    );
  }
}

class _CashRegisterScreenBody extends StatefulWidget {
  const _CashRegisterScreenBody();

  @override
  State<_CashRegisterScreenBody> createState() =>
      _CashRegisterScreenBodyState();
}

class _CashRegisterScreenBodyState extends State<_CashRegisterScreenBody> {
  Map<String, dynamic>? _metrics;

  Future<void> _refreshMetrics(CashRegisterProvider register) async {
    final current = register.current;
    if (current == null) {
      setState(() => _metrics = null);
      return;
    }
    final detail = await CashRegisterRepository(context.read<ApiClient>())
        .fetchDetail(current.id);
    if (mounted) setState(() => _metrics = detail.metrics);
  }

  @override
  Widget build(BuildContext context) {
    final register = context.watch<CashRegisterProvider>();
    final company = context.watch<AuthProvider>().company;
    final formatter = CurrencyFormatter(company?.currencySymbol ?? '\$');

    if (_metrics == null && register.current != null) {
      WidgetsBinding.instance
          .addPostFrameCallback((_) => _refreshMetrics(register));
    }

    return Scaffold(
      appBar: AppBar(
        title: const Text('Cash Register'),
        actions: [
          IconButton(
            tooltip: 'History',
            icon: const Icon(Icons.history),
            onPressed: () => Navigator.of(context).push(
              MaterialPageRoute(
                builder: (_) => ChangeNotifierProvider.value(
                  value: register,
                  child: RegisterHistoryScreen(formatter: formatter),
                ),
              ),
            ),
          ),
        ],
      ),
      body: Builder(builder: (context) {
        if (register.status == CashRegisterStatus.loading) {
          return const LoadingIndicator();
        }
        if (register.status == CashRegisterStatus.error) {
          return ErrorView(
              message: register.error ?? 'Could not load cash register.',
              onRetry: register.loadCurrent);
        }

        final current = register.current;
        if (current == null) {
          return Center(
            child: Padding(
              padding: const EdgeInsets.all(24),
              child: Column(
                mainAxisSize: MainAxisSize.min,
                children: [
                  const Icon(Icons.point_of_sale_outlined,
                      size: 48, color: Colors.grey),
                  const SizedBox(height: 12),
                  const Text('No cash register is open.'),
                  const SizedBox(height: 16),
                  ElevatedButton.icon(
                    icon: const Icon(Icons.lock_open),
                    label: const Text('Open register'),
                    onPressed: () => showDialog(
                      context: context,
                      builder: (_) => ChangeNotifierProvider.value(
                          value: register, child: const OpenRegisterSheet()),
                    ),
                  ),
                ],
              ),
            ),
          );
        }

        final expectedCash = (_metrics?['expected_cash'] as num?)?.toDouble() ??
            current.openingBalance;
        final cashIn = (_metrics?['cash_in'] as num?)?.toDouble() ?? 0;
        final cashOut = (_metrics?['cash_out'] as num?)?.toDouble() ?? 0;
        final totalSales = (_metrics?['total_sales'] as num?)?.toDouble() ?? 0;

        return RefreshIndicator(
          onRefresh: () async {
            await register.loadCurrent();
            await _refreshMetrics(register);
          },
          child: ListView(
            padding: const EdgeInsets.all(16),
            children: [
              // The card used a fixed light-green fill (Colors.green.shade50)
              // with theme-default text — legible in light mode, but in dark
              // mode the card stayed pale while the surrounding text/icon
              // colors flipped light-on-light, making every value here
              // unreadable. CashRegisterStatusCard drives its colors off the
              // current brightness instead.
              CashRegisterStatusCard(
                terminalId: current.terminalId,
                openingFloat: formatter.format(current.openingBalance),
                salesThisShift: formatter.format(totalSales),
                cashIn: formatter.format(cashIn),
                cashOut: formatter.format(cashOut),
                expectedCash: formatter.format(expectedCash),
              ),
              const SizedBox(height: 16),
              Row(
                children: [
                  Expanded(
                    child: OutlinedButton.icon(
                      icon: const Icon(Icons.add),
                      label: const Text('Cash in'),
                      onPressed: () => showDialog(
                        context: context,
                        builder: (_) => ChangeNotifierProvider.value(
                            value: register,
                            child: const CashMovementSheet(type: 'cash_in')),
                      ).then((_) => _refreshMetrics(register)),
                    ),
                  ),
                  const SizedBox(width: 12),
                  Expanded(
                    child: OutlinedButton.icon(
                      icon: const Icon(Icons.remove),
                      label: const Text('Cash out'),
                      onPressed: () => showDialog(
                        context: context,
                        builder: (_) => ChangeNotifierProvider.value(
                            value: register,
                            child: const CashMovementSheet(type: 'cash_out')),
                      ).then((_) => _refreshMetrics(register)),
                    ),
                  ),
                ],
              ),
              const SizedBox(height: 16),
              ElevatedButton.icon(
                icon: const Icon(Icons.lock_outline),
                label: const Text('Close register'),
                onPressed: () => showDialog(
                  context: context,
                  builder: (_) => ChangeNotifierProvider.value(
                    value: register,
                    child: CloseRegisterSheet(
                        expectedCash: expectedCash, formatter: formatter),
                  ),
                ).then((_) => setState(() => _metrics = null)),
              ),
            ],
          ),
        );
      }),
    );
  }
}
