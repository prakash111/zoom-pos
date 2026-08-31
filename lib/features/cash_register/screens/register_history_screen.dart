import 'package:flutter/material.dart';
import 'package:intl/intl.dart';
import 'package:provider/provider.dart';

import '../../../core/utils/currency_formatter.dart';
import '../../../core/widgets/error_view.dart';
import '../../../core/widgets/loading_indicator.dart';
import '../cash_register_provider.dart';

final _dateFormat = DateFormat('MMM d, y · h:mm a');

/// Past closed cash register shifts (GET /cash-register/history).
class RegisterHistoryScreen extends StatefulWidget {
  const RegisterHistoryScreen({super.key, required this.formatter});

  final CurrencyFormatter formatter;

  @override
  State<RegisterHistoryScreen> createState() => _RegisterHistoryScreenState();
}

class _RegisterHistoryScreenState extends State<RegisterHistoryScreen> {
  @override
  void initState() {
    super.initState();
    context.read<CashRegisterProvider>().loadHistory();
  }

  @override
  Widget build(BuildContext context) {
    final register = context.watch<CashRegisterProvider>();

    Widget body;
    switch (register.historyStatus) {
      case HistoryStatus.loading:
        body = const LoadingIndicator();
        break;
      case HistoryStatus.error:
        body = ErrorView(
          message: register.historyError ?? 'Could not load register history.',
          onRetry: register.loadHistory,
        );
        break;
      case HistoryStatus.loaded:
        if (register.history.isEmpty) {
          body = RefreshIndicator(
            onRefresh: register.loadHistory,
            child: ListView(
              padding: const EdgeInsets.all(16),
              children: const [
                SizedBox(height: 120),
                Center(child: Text('No past shifts yet')),
              ],
            ),
          );
        } else {
          body = RefreshIndicator(
            onRefresh: register.loadHistory,
            child: ListView.separated(
              padding: const EdgeInsets.all(16),
              itemCount: register.history.length,
              separatorBuilder: (_, __) => const SizedBox(height: 8),
              itemBuilder: (context, index) {
                final r = register.history[index];
                final diff = r.cashDifference ?? 0;

                return Card(
                  child: ListTile(
                    title: Text(r.terminalId),
                    subtitle: Text(r.openedAt != null ? _dateFormat.format(r.openedAt!) : ''),
                    trailing: Column(
                      mainAxisAlignment: MainAxisAlignment.center,
                      crossAxisAlignment: CrossAxisAlignment.end,
                      children: [
                        Text(widget.formatter.format(r.countedClosingBalance ?? 0)),
                        Text(
                          diff == 0 ? 'Matched' : (diff > 0 ? '+${widget.formatter.format(diff)}' : widget.formatter.format(diff)),
                          style: TextStyle(fontSize: 12, color: diff == 0 ? Colors.green : Colors.orange.shade800),
                        ),
                      ],
                    ),
                  ),
                );
              },
            ),
          );
        }
        break;
    }

    return Scaffold(
      appBar: AppBar(title: const Text('Register history')),
      body: body,
    );
  }
}
