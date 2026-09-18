import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../../core/api/api_client.dart';
import '../../../core/models/quotation_model.dart';
import '../../../core/utils/currency_formatter.dart';
import '../../../core/utils/responsive.dart';
import '../../../core/widgets/adaptive_sheet.dart';
import '../../../core/widgets/error_view.dart';
import '../../../core/widgets/loading_indicator.dart';
import '../../../core/widgets/responsive/desktop_content_area.dart';
import '../../auth/auth_provider.dart';
import '../quotations_provider.dart';
import '../quotations_repository.dart';
import 'quotation_detail_screen.dart';
import 'quotation_form_sheet.dart';

const _statusOptions = [
  'draft',
  'sent',
  'accepted',
  'declined',
  'expired',
  'converted'
];

/// Quotations: list/search/status-filter, create, and — via
/// [QuotationDetailScreen] — view/convert/delete. Owns a [QuotationsProvider]
/// scoped to this route.
class QuotationsScreen extends StatelessWidget {
  const QuotationsScreen({super.key});

  @override
  Widget build(BuildContext context) {
    final apiClient = context.read<ApiClient>();

    return ChangeNotifierProvider(
      create: (_) =>
          QuotationsProvider(repository: QuotationsRepository(apiClient))
            ..loadQuotations(),
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

    showAdaptiveSheet(
      context,
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
      body: DesktopContentArea(
        child: Column(
          children: [
            Padding(
              padding: const EdgeInsets.fromLTRB(16, 12, 16, 8),
              child: DesktopBoundedField(
                child: TextField(
                  controller: _searchController,
                  onChanged: quotations.setSearchQuery,
                  decoration: const InputDecoration(
                      hintText: 'Search quote # or customer',
                      prefixIcon: Icon(Icons.search)),
                ),
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
                  return ErrorView(
                      message: quotations.error ?? 'Could not load quotations.',
                      onRetry: quotations.loadQuotations);
                }

                final list = quotations.filteredQuotations;
                if (list.isEmpty) {
                  return const Center(child: Text('No quotations found.'));
                }

                void openQuote(QuotationModel quote) =>
                    Navigator.of(context).push(
                      MaterialPageRoute(
                        builder: (_) => ChangeNotifierProvider.value(
                          value: quotations,
                          child: QuotationDetailScreen(
                              quotation: quote, formatter: formatter),
                        ),
                      ),
                    );

                return RefreshIndicator(
                  onRefresh: quotations.loadQuotations,
                  child: MediaQuery.sizeOf(context).width >= Breakpoints.desktop
                      ? _DesktopQuotationsTable(
                          quotations: list,
                          formatter: formatter,
                          onTap: openQuote)
                      : ListView.separated(
                          padding: const EdgeInsets.fromLTRB(16, 8, 16, 80),
                          itemCount: list.length,
                          separatorBuilder: (_, __) =>
                              const SizedBox(height: 8),
                          itemBuilder: (context, index) {
                            final quote = list[index];
                            return _QuoteTile(
                              quote: quote,
                              formatter: formatter,
                              onTap: () => openQuote(quote),
                            );
                          },
                        ),
                );
              }),
            ),
          ],
        ),
      ),
    );
  }
}

/// Desktop composition for the quotations list: Quote # | Customer | Status
/// | Amount — falls back to [_QuoteTile] cards below the desktop breakpoint.
class _DesktopQuotationsTable extends StatelessWidget {
  const _DesktopQuotationsTable(
      {required this.quotations, required this.formatter, required this.onTap});

  final List<QuotationModel> quotations;
  final CurrencyFormatter formatter;
  final void Function(QuotationModel) onTap;

  @override
  Widget build(BuildContext context) {
    final scheme = Theme.of(context).colorScheme;
    final headerStyle = TextStyle(
        fontWeight: FontWeight.w600,
        fontSize: 12.5,
        color: scheme.onSurfaceVariant);

    return ListView.builder(
      padding: const EdgeInsets.fromLTRB(0, 4, 0, 80),
      itemCount: quotations.length + 1,
      itemBuilder: (context, index) {
        if (index == 0) {
          return Container(
            padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 10),
            decoration: BoxDecoration(
                border:
                    Border(bottom: BorderSide(color: scheme.outlineVariant))),
            child: Row(
              children: [
                Expanded(flex: 3, child: Text('QUOTE #', style: headerStyle)),
                Expanded(flex: 4, child: Text('CUSTOMER', style: headerStyle)),
                Expanded(flex: 2, child: Text('STATUS', style: headerStyle)),
                Expanded(
                    flex: 2,
                    child: Text('AMOUNT',
                        textAlign: TextAlign.end, style: headerStyle)),
                const SizedBox(width: 20),
              ],
            ),
          );
        }

        final quote = quotations[index - 1];
        return InkWell(
          onTap: () => onTap(quote),
          child: Container(
            padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 10),
            decoration: BoxDecoration(
              border: Border(
                  bottom: BorderSide(
                      color: scheme.outlineVariant.withValues(alpha: 0.5))),
            ),
            child: Row(
              children: [
                Expanded(
                  flex: 3,
                  child: Text('#${quote.quoteNumber}',
                      overflow: TextOverflow.ellipsis,
                      style: const TextStyle(
                          fontWeight: FontWeight.w600, fontSize: 13.5)),
                ),
                Expanded(
                  flex: 4,
                  child: Text(quote.customerName,
                      overflow: TextOverflow.ellipsis,
                      style: const TextStyle(fontSize: 13.5)),
                ),
                Expanded(
                  flex: 2,
                  child: _QuoteStatusBadge(status: quote.status),
                ),
                Expanded(
                  flex: 2,
                  child: Text(
                    formatter.format(quote.total),
                    textAlign: TextAlign.end,
                    style: const TextStyle(
                        fontWeight: FontWeight.w600, fontSize: 13.5),
                  ),
                ),
                SizedBox(
                    width: 20,
                    child: Icon(Icons.chevron_right,
                        size: 18, color: scheme.onSurfaceVariant)),
              ],
            ),
          ),
        );
      },
    );
  }
}

class _QuoteStatusBadge extends StatelessWidget {
  const _QuoteStatusBadge({required this.status});

  final String status;

  @override
  Widget build(BuildContext context) {
    final color = switch (status) {
      'accepted' || 'converted' => Colors.green,
      'declined' || 'expired' => Colors.red,
      'sent' => Colors.blue,
      _ => Colors.grey,
    };

    return Align(
      alignment: Alignment.centerLeft,
      child: Container(
        padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
        decoration: BoxDecoration(
            color: color.withValues(alpha: 0.12),
            borderRadius: BorderRadius.circular(20)),
        child: Text(
          status[0].toUpperCase() + status.substring(1),
          style: TextStyle(
              fontSize: 11.5,
              fontWeight: FontWeight.w600,
              color: color.shade700),
        ),
      ),
    );
  }
}

class _StatusChip extends StatelessWidget {
  const _StatusChip(
      {required this.label, required this.selected, required this.onTap});

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
  const _QuoteTile(
      {required this.quote, required this.formatter, required this.onTap});

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
                    Text('Quote #${quote.quoteNumber}',
                        style: const TextStyle(fontWeight: FontWeight.w600)),
                    const SizedBox(height: 2),
                    Text(quote.customerName,
                        style: TextStyle(
                            color: Colors.grey.shade600, fontSize: 12)),
                  ],
                ),
              ),
              Column(
                crossAxisAlignment: CrossAxisAlignment.end,
                children: [
                  Text(formatter.format(quote.total),
                      style: const TextStyle(fontWeight: FontWeight.w600)),
                  Text(quote.status,
                      style:
                          TextStyle(fontSize: 12, color: Colors.grey.shade600)),
                ],
              ),
            ],
          ),
        ),
      ),
    );
  }
}
