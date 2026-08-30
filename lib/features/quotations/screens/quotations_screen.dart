import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../../core/api/api_client.dart';
import '../../../core/models/quotation_model.dart';
import '../../../core/utils/currency_formatter.dart';
import '../../../core/widgets/error_view.dart';
import '../../../core/widgets/loading_indicator.dart';
import '../../auth/auth_provider.dart';
import '../quotations_provider.dart';
import '../quotations_repository.dart';
import 'quotation_detail_screen.dart';
import 'quotation_form_sheet.dart';

const _statusOptions = ['draft', 'sent', 'accepted', 'declined', 'expired', 'converted'];

/// Quotations: list/search/status-filter, create, and — via
/// [QuotationDetailScreen] — view/convert/delete. Owns a [QuotationsProvider]
/// scoped to this route.
class QuotationsScreen extends StatelessWidget {
  const QuotationsScreen({super.key});

  @override
  Widget build(BuildContext context) {
    final apiClient = context.read<ApiClient>();

    return ChangeNotifierProvider(
      create: (_) => QuotationsProvider(repository: QuotationsRepository(apiClient))..loadQuotations(),
      child: const _QuotationsScreenBody(),
    );
  }
}

class _QuotationsScreenBody extends StatefulWidget {
  const _QuotationsScreenBody();

  @override
  State<_QuotationsScreenBody> createState() => _QuotationsScreenBodyState();
}

class _QuotationsScreenBodyState extends State<_QuotationsScreenBody> {
  final _searchController = TextEditingController();

  @override
  void dispose() {
    _searchController.dispose();
    super.dispose();
  }

  void _openForm(BuildContext context) {
    final quotations = context.read<QuotationsProvider>();
    final company = context.read<AuthProvider>().company;
    final formatter = CurrencyFormatter(company?.currencySymbol ?? '\$');

    showModalBottomSheet(
      context: context,
      isScrollControlled: true,
      shape: const RoundedRectangleBorder(borderRadius: BorderRadius.vertical(top: Radius.circular(16))),
      builder: (_) => ChangeNotifierProvider.value(
        value: quotations,
        child: QuotationFormSheet(formatter: formatter),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final quotations = context.watch<QuotationsProvider>();
    final company = context.watch<AuthProvider>().company;
    final formatter = CurrencyFormatter(company?.currencySymbol ?? '\$');

    return Scaffold(
      appBar: AppBar(title: const Text('Quotations')),
      floatingActionButton: FloatingActionButton(
        onPressed: () => _openForm(context),
        child: const Icon(Icons.add),
      ),
      body: Column(
        children: [
          Padding(
            padding: const EdgeInsets.fromLTRB(16, 12, 16, 8),
            child: TextField(
              controller: _searchController,
              onChanged: quotations.setSearchQuery,
              decoration: const InputDecoration(hintText: 'Search quote # or customer', prefixIcon: Icon(Icons.search)),
            ),
          ),
          SizedBox(
            height: 40,
            child: ListView(
              scrollDirection: Axis.horizontal,
              padding: const EdgeInsets.symmetric(horizontal: 12),
              children: [
                _StatusChip(
                  label: 'All',
                  selected: quotations.statusFilter == null,
                  onTap: () => quotations.setStatusFilter(null),
                ),
                for (final status in _statusOptions)
                  _StatusChip(
                    label: status,
                    selected: quotations.statusFilter == status,
                    onTap: () => quotations.setStatusFilter(status),
                  ),
              ],
            ),
          ),
          Expanded(
            child: Builder(builder: (context) {
              if (quotations.status == QuotationsStatus.loading) {
                return const LoadingIndicator();
              }
              if (quotations.status == QuotationsStatus.error) {
                return ErrorView(message: quotations.error ?? 'Could not load quotations.', onRetry: quotations.loadQuotations);
              }

              final list = quotations.filteredQuotations;
              if (list.isEmpty) {
                return const Center(child: Text('No quotations found.'));
              }

              return RefreshIndicator(
                onRefresh: quotations.loadQuotations,
                child: ListView.separated(
                  padding: const EdgeInsets.fromLTRB(16, 8, 16, 80),
                  itemCount: list.length,
                  separatorBuilder: (_, __) => const SizedBox(height: 8),
                  itemBuilder: (context, index) {
                    final quote = list[index];
                    return _QuoteTile(
                      quote: quote,
                      formatter: formatter,
                      onTap: () => Navigator.of(context).push(
                        MaterialPageRoute(
                          builder: (_) => ChangeNotifierProvider.value(
                            value: quotations,
                            child: QuotationDetailScreen(quotation: quote, formatter: formatter),
                          ),
                        ),
                      ),
                    );
                  },
                ),
              );
            }),
          ),
        ],
      ),
    );
  }
}

class _StatusChip extends StatelessWidget {
  const _StatusChip({required this.label, required this.selected, required this.onTap});

  final String label;
  final bool selected;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.symmetric(horizontal: 4),
      child: ChoiceChip(
        label: Text(label[0].toUpperCase() + label.substring(1)),
        selected: selected,
        onSelected: (_) => onTap(),
      ),
    );
  }
}

class _QuoteTile extends StatelessWidget {
  const _QuoteTile({required this.quote, required this.formatter, required this.onTap});

  final QuotationModel quote;
  final CurrencyFormatter formatter;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return Card(
      child: InkWell(
        borderRadius: BorderRadius.circular(12),
        onTap: onTap,
        child: Padding(
          padding: const EdgeInsets.all(12),
          child: Row(
            children: [
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text('Quote #${quote.quoteNumber}', style: const TextStyle(fontWeight: FontWeight.w600)),
                    const SizedBox(height: 2),
                    Text(quote.customerName, style: TextStyle(color: Colors.grey.shade600, fontSize: 12)),
                  ],
                ),
              ),
              Column(
                crossAxisAlignment: CrossAxisAlignment.end,
                children: [
                  Text(formatter.format(quote.total), style: const TextStyle(fontWeight: FontWeight.w600)),
                  Text(quote.status, style: TextStyle(fontSize: 12, color: Colors.grey.shade600)),
                ],
              ),
            ],
          ),
        ),
      ),
    );
  }
}
