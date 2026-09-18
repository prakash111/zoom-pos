import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../../core/api/api_client.dart';
import '../../../core/models/consignment_model.dart';
import '../../../core/utils/currency_formatter.dart';
import '../../../core/widgets/adaptive_sheet.dart';
import '../../../core/widgets/error_view.dart';
import '../../../core/widgets/loading_indicator.dart';
import '../../auth/auth_provider.dart';
import '../consignments_repository.dart';
import 'consignment_detail_screen.dart';
import 'consignment_form_sheet.dart';

const _statusOptions = ['draft', 'dispatched', 'reconciled', 'finalized'];

/// Consignments: stats, status filter, create, and — via
/// [ConsignmentDetailScreen] — dispatch/reconcile/finalize.
class ConsignmentsScreen extends StatefulWidget {
  const ConsignmentsScreen({super.key});

  @override
  State<ConsignmentsScreen> createState() => _ConsignmentsScreenState();
}

class _ConsignmentsScreenState extends State<ConsignmentsScreen> {
  late final ConsignmentsRepository _repository;
  late Future<({List<ConsignmentModel> consignments, ConsignmentStats stats})> _future;
  String? _statusFilter;

  @override
  void initState() {
    super.initState();
    _repository = ConsignmentsRepository(context.read<ApiClient>());
    _future = _repository.fetchConsignments();
  }

  void _reload() => setState(() => _future = _repository.fetchConsignments(status: _statusFilter));

  Future<void> _openForm(CurrencyFormatter formatter) async {
    final saved = await showAdaptiveSheet<bool>(
      context,
      builder: (_) => ConsignmentFormSheet(repository: _repository, formatter: formatter),
    );
    if (saved == true) _reload();
  }

  Future<void> _openDetail(ConsignmentModel consignment, CurrencyFormatter formatter) async {
    final changed = await Navigator.of(context).push<bool>(
      MaterialPageRoute(
        builder: (_) => ConsignmentDetailScreen(consignmentId: consignment.id, repository: _repository, formatter: formatter),
      ),
    );
    if (changed == true) _reload();
  }

  @override
  Widget build(BuildContext context) {
    final company = context.watch<AuthProvider>().company;
    final formatter = CurrencyFormatter(company?.currencySymbol ?? '\$');

    return Scaffold(
      appBar: AppBar(title: const Text('Consignments')),
      floatingActionButton: FloatingActionButton(onPressed: () => _openForm(formatter), child: const Icon(Icons.add)),
      body: FutureBuilder<({List<ConsignmentModel> consignments, ConsignmentStats stats})>(
        future: _future,
        builder: (context, snapshot) {
          if (snapshot.connectionState != ConnectionState.done) return const LoadingIndicator();
          if (snapshot.hasError) return ErrorView(message: 'Could not load consignments.', onRetry: _reload);

          final stats = snapshot.data!.stats;
          final consignments = snapshot.data!.consignments;

          return Column(
            children: [
              Padding(
                padding: const EdgeInsets.all(16),
                child: Row(
                  children: [
                    Expanded(child: _StatTile(label: 'Dispatched', value: '${stats.dispatched}')),
                    const SizedBox(width: 8),
                    Expanded(child: _StatTile(label: 'Reconciled', value: '${stats.reconciled}')),
                    const SizedBox(width: 8),
                    Expanded(child: _StatTile(label: 'Finalized', value: '${stats.finalized}')),
                  ],
                ),
              ),
              SizedBox(
                height: 40,
                child: ListView(
                  scrollDirection: Axis.horizontal,
                  padding: const EdgeInsets.symmetric(horizontal: 12),
                  children: [
                    ChoiceChip(
                      label: const Text('All'),
                      selected: _statusFilter == null,
                      onSelected: (_) => setState(() {
                        _statusFilter = null;
                        _reload();
                      }),
                    ),
                    for (final status in _statusOptions)
                      Padding(
                        padding: const EdgeInsets.symmetric(horizontal: 4),
                        child: ChoiceChip(
                          label: Text(status),
                          selected: _statusFilter == status,
                          onSelected: (_) => setState(() {
                            _statusFilter = status;
                            _reload();
                          }),
                        ),
                      ),
                  ],
                ),
              ),
              Expanded(
                child: consignments.isEmpty
                    ? const Center(child: Text('No consignments found.'))
                    : RefreshIndicator(
                        onRefresh: () async => _reload(),
                        child: ListView.separated(
                          padding: const EdgeInsets.fromLTRB(16, 8, 16, 80),
                          itemCount: consignments.length,
                          separatorBuilder: (_, __) => const SizedBox(height: 8),
                          itemBuilder: (context, index) {
                            final c = consignments[index];
                            return Card(
                              child: ListTile(
                                onTap: () => _openDetail(c, formatter),
                                title: Text(c.consignmentNumber),
                                subtitle: Text('${c.customerName} · ${c.status}'),
                                trailing: Text(formatter.format(c.totalDispatchedAmount), style: const TextStyle(fontWeight: FontWeight.w600)),
                              ),
                            );
                          },
                        ),
                      ),
              ),
            ],
          );
        },
      ),
    );
  }
}

class _StatTile extends StatelessWidget {
  const _StatTile({required this.label, required this.value});

  final String label;
  final String value;

  @override
  Widget build(BuildContext context) {
    return Card(
      child: Padding(
        padding: const EdgeInsets.all(12),
        child: Column(
          children: [
            Text(value, style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 18)),
            Text(label, style: TextStyle(fontSize: 12, color: Colors.grey.shade600)),
          ],
        ),
      ),
    );
  }
}
