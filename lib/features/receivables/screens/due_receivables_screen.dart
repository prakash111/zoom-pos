import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import 'package:url_launcher/url_launcher.dart';

import '../../../core/api/api_client.dart';
import '../../../core/api/api_exception.dart';
import '../../../core/models/receivable_model.dart';
import '../../../core/utils/currency_formatter.dart';
import '../../../core/widgets/error_view.dart';
import '../../../core/widgets/loading_indicator.dart';
import '../../auth/auth_provider.dart';
import '../receivables_repository.dart';

/// Due Payments / Receivables: every unpaid or partially-paid sale, with a
/// per-row "Send Reminder" action (WhatsApp / Email / Custom channel).
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

  Future<void> _pickChannelAndRemind(ReceivableModel receivable) async {
    final channel = await showModalBottomSheet<String>(
      context: context,
      shape: const RoundedRectangleBorder(borderRadius: BorderRadius.vertical(top: Radius.circular(16))),
      builder: (sheetCtx) => SafeArea(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            const Padding(
              padding: EdgeInsets.all(16),
              child: Text('Send Reminder', style: TextStyle(fontWeight: FontWeight.bold, fontSize: 16)),
            ),
            ListTile(
              leading: const Icon(Icons.chat, color: Colors.green),
              title: const Text('WhatsApp'),
              onTap: () => Navigator.of(sheetCtx).pop('whatsapp'),
            ),
            ListTile(
              leading: const Icon(Icons.email_outlined, color: Colors.blue),
              title: const Text('Email'),
              onTap: () => Navigator.of(sheetCtx).pop('email'),
            ),
            ListTile(
              leading: const Icon(Icons.webhook_outlined, color: Colors.deepPurple),
              title: const Text('Custom Notification Channel'),
              onTap: () => Navigator.of(sheetCtx).pop('custom'),
            ),
            const SizedBox(height: 8),
          ],
        ),
      ),
    );

    if (channel == null || !mounted) return;

    final messenger = ScaffoldMessenger.of(context);
    try {
      final result = await _repository.sendReminder(receivable.saleId, channel: channel);
      if (!mounted) return;

      final fallbackUrl = result['fallback_url'] as String?;
      final fallbackMailto = result['fallback_mailto'] as String?;

      if (fallbackUrl != null) {
        final uri = Uri.parse(fallbackUrl);
        if (await canLaunchUrl(uri)) await launchUrl(uri, mode: LaunchMode.externalApplication);
      } else if (fallbackMailto != null) {
        final uri = Uri.parse(fallbackMailto);
        if (await canLaunchUrl(uri)) await launchUrl(uri);
      } else {
        messenger.showSnackBar(const SnackBar(content: Text('Reminder sent.')));
      }
    } on ApiException catch (e) {
      messenger.showSnackBar(SnackBar(content: Text(e.message)));
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
            return ErrorView(message: snapshot.error.toString(), onRetry: _refresh);
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
                    title: Text(r.customerName, style: const TextStyle(fontWeight: FontWeight.bold)),
                    subtitle: Text(
                      '${r.saleNumber} • Total ${formatter.format(r.total)} • Paid ${formatter.format(r.paidAmount)}',
                      style: const TextStyle(fontSize: 12),
                    ),
                    trailing: Column(
                      mainAxisAlignment: MainAxisAlignment.center,
                      crossAxisAlignment: CrossAxisAlignment.end,
                      children: [
                        Text(formatter.format(r.dueAmount), style: TextStyle(color: Colors.red.shade600, fontWeight: FontWeight.bold)),
                        TextButton(
                          onPressed: () => _pickChannelAndRemind(r),
                          child: const Text('Send Reminder', style: TextStyle(fontSize: 11)),
                        ),
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
