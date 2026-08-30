import 'package:flutter/material.dart';
import 'package:intl/intl.dart';
import 'package:provider/provider.dart';

import '../../../core/api/api_client.dart';
import '../../../core/config/app_config.dart';
import '../../../core/models/quotation_model.dart';
import '../../../core/utils/currency_formatter.dart';
import '../quotations_provider.dart';

final _dateFormat = DateFormat('MMM d, y');

/// Detail view for a single quotation: line items, totals, and the
/// convert-to-sale / delete actions. Sending (email/WhatsApp) reuses the
/// existing POST /send-delivery endpoint with document_type=quotation.
class QuotationDetailScreen extends StatefulWidget {
  const QuotationDetailScreen({super.key, required this.quotation, required this.formatter});

  final QuotationModel quotation;
  final CurrencyFormatter formatter;

  @override
  State<QuotationDetailScreen> createState() => _QuotationDetailScreenState();
}

class _QuotationDetailScreenState extends State<QuotationDetailScreen> {
  bool _sending = false;

  Future<void> _convert() async {
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        title: const Text('Convert to sale?'),
        content: const Text('This creates a completed sale from this quotation and deducts stock.'),
        actions: [
          TextButton(onPressed: () => Navigator.of(context).pop(false), child: const Text('Cancel')),
          TextButton(onPressed: () => Navigator.of(context).pop(true), child: const Text('Convert')),
        ],
      ),
    );
    if (confirmed != true || !mounted) return;

    final quotations = context.read<QuotationsProvider>();
    final success = await quotations.convertToSale(widget.quotation.id);
    if (!mounted) return;
    if (success) {
      Navigator.of(context).pop();
      ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('Quotation converted to sale.')));
    } else if (quotations.actionError != null) {
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(quotations.actionError!)));
    }
  }

  Future<void> _delete() async {
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        title: const Text('Delete quotation?'),
        content: const Text('This cannot be undone.'),
        actions: [
          TextButton(onPressed: () => Navigator.of(context).pop(false), child: const Text('Cancel')),
          TextButton(onPressed: () => Navigator.of(context).pop(true), child: const Text('Delete')),
        ],
      ),
    );
    if (confirmed != true || !mounted) return;

    final quotations = context.read<QuotationsProvider>();
    final success = await quotations.deleteQuotation(widget.quotation.id);
    if (!mounted) return;
    if (success) {
      Navigator.of(context).pop();
    } else if (quotations.actionError != null) {
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(quotations.actionError!)));
    }
  }

  Future<void> _sendEmail() async {
    final controller = TextEditingController();
    final email = await showDialog<String>(
      context: context,
      builder: (context) => AlertDialog(
        title: const Text('Send via email'),
        content: TextField(
          controller: controller,
          keyboardType: TextInputType.emailAddress,
          decoration: const InputDecoration(labelText: 'Recipient email'),
        ),
        actions: [
          TextButton(onPressed: () => Navigator.of(context).pop(), child: const Text('Cancel')),
          TextButton(onPressed: () => Navigator.of(context).pop(controller.text.trim()), child: const Text('Send')),
        ],
      ),
    );
    if (email == null || email.isEmpty || !mounted) return;

    setState(() => _sending = true);
    try {
      await context.read<ApiClient>().post(ApiEndpoints.sendDelivery, data: {
        'type': 'email',
        'document_type': 'quotation',
        'recipient': email,
        'document_id': widget.quotation.id,
      });
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('Quotation sent.')));
      }
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text('Could not send: $e')));
      }
    } finally {
      if (mounted) setState(() => _sending = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final quotations = context.watch<QuotationsProvider>();
    final quote = quotations.filteredQuotations.firstWhere(
      (q) => q.id == widget.quotation.id,
      orElse: () => widget.quotation,
    );

    return Scaffold(
      appBar: AppBar(
        title: Text('Quote #${quote.quoteNumber}'),
        actions: [
          if (!quote.isConverted)
            IconButton(
              tooltip: 'Send via email',
              icon: _sending
                  ? const SizedBox(width: 18, height: 18, child: CircularProgressIndicator(strokeWidth: 2))
                  : const Icon(Icons.email_outlined),
              onPressed: _sending ? null : _sendEmail,
            ),
          IconButton(
            tooltip: 'Delete',
            icon: const Icon(Icons.delete_outline),
            onPressed: quotations.isSaving ? null : _delete,
          ),
        ],
      ),
      body: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              Text(quote.customerName, style: Theme.of(context).textTheme.titleMedium),
              Chip(label: Text(quote.status.toUpperCase())),
            ],
          ),
          if (quote.validUntil != null)
            Padding(
              padding: const EdgeInsets.only(top: 4),
              child: Text('Valid until ${_dateFormat.format(quote.validUntil!)}', style: TextStyle(color: Colors.grey.shade600)),
            ),
          const SizedBox(height: 16),
          Card(
            child: Padding(
              padding: const EdgeInsets.all(12),
              child: Column(
                children: quote.items.map((item) {
                  final qty = (item['quantity'] as num?)?.toDouble() ?? 0;
                  final price = (item['price'] as num?)?.toDouble() ?? 0;
                  return Padding(
                    padding: const EdgeInsets.symmetric(vertical: 4),
                    child: Row(
                      children: [
                        Expanded(child: Text('${item['name']} × ${qty.toStringAsFixed(qty.truncateToDouble() == qty ? 0 : 2)}')),
                        Text(widget.formatter.format(qty * price)),
                      ],
                    ),
                  );
                }).toList(),
              ),
            ),
          ),
          const SizedBox(height: 12),
          _TotalsRow(label: 'Subtotal', value: widget.formatter.format(quote.subtotal)),
          _TotalsRow(label: 'Discount', value: '-${widget.formatter.format(quote.discount)}'),
          _TotalsRow(label: 'Tax', value: widget.formatter.format(quote.tax)),
          const Divider(),
          _TotalsRow(label: 'Total', value: widget.formatter.format(quote.total), bold: true),
          if (quote.notes.isNotEmpty) ...[
            const SizedBox(height: 16),
            Text('Notes', style: Theme.of(context).textTheme.titleSmall),
            Text(quote.notes),
          ],
          if (quote.terms.isNotEmpty) ...[
            const SizedBox(height: 16),
            Text('Terms', style: Theme.of(context).textTheme.titleSmall),
            Text(quote.terms),
          ],
          const SizedBox(height: 24),
          if (!quote.isConverted)
            ElevatedButton.icon(
              onPressed: quotations.isSaving ? null : _convert,
              icon: const Icon(Icons.published_with_changes),
              label: const Text('Convert to sale'),
            ),
        ],
      ),
    );
  }
}

class _TotalsRow extends StatelessWidget {
  const _TotalsRow({required this.label, required this.value, this.bold = false});

  final String label;
  final String value;
  final bool bold;

  @override
  Widget build(BuildContext context) {
    final style = TextStyle(fontWeight: bold ? FontWeight.bold : FontWeight.normal, fontSize: bold ? 16 : 14);
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 2),
      child: Row(
        mainAxisAlignment: MainAxisAlignment.spaceBetween,
        children: [Text(label, style: style), Text(value, style: style)],
      ),
    );
  }
}
