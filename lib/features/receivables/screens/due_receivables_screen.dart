import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../../core/api/api_client.dart';
import '../../../core/api/api_exception.dart';
import '../../../core/models/receivable_model.dart';
import '../../../core/sdui/sdui_action_dispatcher.dart';
import '../../../core/utils/currency_formatter.dart';
import '../../../core/widgets/error_view.dart';
import '../../../core/widgets/loading_indicator.dart';
import '../../auth/auth_provider.dart';
import '../receivables_repository.dart';

/// Due Payments / Receivables: every unpaid or partially-paid sale, with a
/// per-row scheduled push reminder and registry-driven dispatch actions.
class DueReceivablesScreen extends StatefulWidget {
  const DueReceivablesScreen({super.key});

  @override
  State<DueReceivablesScreen> createState() => _DueReceivablesScreenState();
}

class _DueReceivablesScreenState extends State<DueReceivablesScreen> {
  late Future<List<ReceivableModel>> _future;
  late ReceivablesRepository _repository;

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    _repository = ReceivablesRepository(context.read<ApiClient>());
    _future = _repository.fetchDueReceivables();
  }

  void _refresh() {
    setState(() => _future = _repository.fetchDueReceivables());
  }

  Future<void> _openReminderSheet(ReceivableModel receivable) async {
    if (!mounted) return;

    final dispatcher = SduiActionDispatcher(
      resolveApiClient: () => context.read<ApiClient>(),
      formKey: GlobalKey<FormState>(),
      formValues: <String, dynamic>{},
      setFormValue: (_, __) {},
      onReload: _refresh,
      showToast: (message, {isError = false}) {
        if (!mounted) return;
        ScaffoldMessenger.of(context).showSnackBar(SnackBar(
          content: Text(message),
          backgroundColor: isError ? Colors.red.shade700 : null,
        ));
      },
    );

    // The endpoint returns the complete SDUI sheet. The dispatcher renders
    // every configured channel (SMS, WhatsApp Business, SMTP, webhooks, and
    // future tenant-defined channels) without a client-side tile list.
    await dispatcher.dispatch(context, {
      'type': 'OPEN_BOTTOM_SHEET',
      'action_type': 'OPEN_BOTTOM_SHEET',
      'title': 'Send Payment Reminder',
      'endpoint':
          '/api/v1/tenant/receivables/${Uri.encodeComponent(receivable.saleId)}/reminder-sheet',
    });
  }

  Future<void> _scheduleReminder(ReceivableModel receivable) async {
    var dueDate = receivable.dueDate?.toLocal() ??
        DateTime.now().add(const Duration(days: 7));
    var reminderAt = receivable.dueReminderAt?.toLocal() ??
        DateTime(dueDate.year, dueDate.month, dueDate.day, 9);

    final selected = await showDialog<(DateTime, DateTime)>(
      context: context,
      builder: (dialogContext) => StatefulBuilder(
        builder: (context, setDialogState) => AlertDialog(
          title: const Text('Schedule push reminder'),
          content: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              ListTile(
                contentPadding: EdgeInsets.zero,
                leading: const Icon(Icons.event),
                title: const Text('Invoice due date'),
                subtitle: Text(
                    '${dueDate.year}-${dueDate.month.toString().padLeft(2, '0')}-${dueDate.day.toString().padLeft(2, '0')}'),
                onTap: () async {
                  final picked = await showDatePicker(
                      context: context,
                      initialDate: dueDate,
                      firstDate: DateTime(2020),
                      lastDate: DateTime.now().add(const Duration(days: 3650)));
                  if (picked != null) setDialogState(() => dueDate = picked);
                },
              ),
              ListTile(
                contentPadding: EdgeInsets.zero,
                leading: const Icon(Icons.alarm),
                title: const Text('Reminder date & time'),
                subtitle: Text(
                    '${reminderAt.year}-${reminderAt.month.toString().padLeft(2, '0')}-${reminderAt.day.toString().padLeft(2, '0')} ${TimeOfDay.fromDateTime(reminderAt).format(context)}'),
                onTap: () async {
                  final day = await showDatePicker(
                      context: context,
                      initialDate: reminderAt,
                      firstDate: DateTime.now(),
                      lastDate: DateTime.now().add(const Duration(days: 3650)));
                  if (day == null || !context.mounted) return;
                  final time = await showTimePicker(
                      context: context,
                      initialTime: TimeOfDay.fromDateTime(reminderAt));
                  if (time != null)
                    setDialogState(() => reminderAt = DateTime(
                        day.year, day.month, day.day, time.hour, time.minute));
                },
              ),
            ],
          ),
          actions: [
            TextButton(
                onPressed: () => Navigator.pop(dialogContext),
                child: const Text('Cancel')),
            FilledButton(
                onPressed: () =>
                    Navigator.pop(dialogContext, (dueDate, reminderAt)),
                child: const Text('Schedule')),
          ],
        ),
      ),
    );
    if (selected == null || !mounted) return;

    try {
      await _repository.scheduleReminder(receivable.saleId,
          dueDate: selected.$1, reminderAt: selected.$2);
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('Push reminder scheduled.')));
      _refresh();
    } on ApiException catch (e) {
      if (mounted)
        ScaffoldMessenger.of(context)
            .showSnackBar(SnackBar(content: Text(e.message)));
    }
  }

  @override
  Widget build(BuildContext context) {
    final company = context.watch<AuthProvider>().company;
    final formatter = CurrencyFormatter(company?.currencySymbol ?? '\$');

    return Scaffold(
      appBar: AppBar(title: const Text('Due Payments / Receivables')),
      body: FutureBuilder<List<ReceivableModel>>(
        future: _future,
        builder: (context, snapshot) {
          if (snapshot.connectionState == ConnectionState.waiting) {
            return const LoadingIndicator();
          }
          if (snapshot.hasError) {
            return ErrorView(
                message: snapshot.error.toString(), onRetry: _refresh);
          }

          final receivables = snapshot.data ?? [];
          if (receivables.isEmpty) {
            return const Center(child: Text('No outstanding dues. 🎉'));
          }

          return RefreshIndicator(
            onRefresh: () async => _refresh(),
            child: ListView.separated(
              padding: const EdgeInsets.all(12),
              itemCount: receivables.length,
              separatorBuilder: (_, __) => const SizedBox(height: 8),
              itemBuilder: (context, index) {
                final r = receivables[index];
                return Card(
                  child: ListTile(
                    title: Row(
                      children: [
                        Expanded(
                            child: Text(r.customerName,
                                style: const TextStyle(
                                    fontWeight: FontWeight.bold))),
                        Text(formatter.format(r.dueAmount),
                            style: TextStyle(
                                color: Colors.red.shade600,
                                fontWeight: FontWeight.bold)),
                      ],
                    ),
                    subtitle: Text(
                        '${r.saleNumber} • Total ${formatter.format(r.total)} • Paid ${formatter.format(r.paidAmount)}${r.dueReminderAt == null ? '' : '\n🔔 ${r.dueReminderAt!.toLocal()}'}',
                        style: const TextStyle(fontSize: 12)),
                    trailing: PopupMenuButton<String>(
                      tooltip: 'Reminder actions',
                      onSelected: (action) {
                        if (action == 'schedule') {
                          _scheduleReminder(r);
                        } else {
                          _openReminderSheet(r);
                        }
                      },
                      itemBuilder: (_) => const [
                        PopupMenuItem(
                            value: 'schedule',
                            child: Text('Schedule push reminder')),
                        PopupMenuItem(
                            value: 'send', child: Text('Send Reminder')),
                      ],
                    ),
                  ),
                );
              },
            ),
          );
        },
      ),
    );
  }
}
