import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../../core/api/api_client.dart';
import '../../../core/api/api_exception.dart';
import '../../../core/models/service_order_model.dart';
import '../../../core/utils/currency_formatter.dart';
import '../../../core/widgets/error_view.dart';
import '../../../core/widgets/loading_indicator.dart';
import '../../auth/auth_provider.dart';
import '../service_orders_repository.dart';
import 'service_order_form_sheet.dart';

/// Service Orders (repairs/warranty): list with status/priority filters and
/// counts, create/edit, and a quick inline status-change menu.
class ServiceOrdersScreen extends StatefulWidget {
  const ServiceOrdersScreen({super.key});

  @override
  State<ServiceOrdersScreen> createState() => _ServiceOrdersScreenState();
}

class _ServiceOrdersScreenState extends State<ServiceOrdersScreen> {
  late final ServiceOrdersRepository _repository;
  late Future<({List<ServiceOrderModel> orders, Map<String, int> counts})> _future;
  String? _statusFilter;

  @override
  void initState() {
    super.initState();
    _repository = ServiceOrdersRepository(context.read<ApiClient>());
    _future = _repository.fetchOrders();
  }

  void _reload() => setState(() => _future = _repository.fetchOrders(status: _statusFilter));

  Future<void> _openForm(CurrencyFormatter formatter, {ServiceOrderModel? order}) async {
    final saved = await showModalBottomSheet<bool>(
      context: context,
      isScrollControlled: true,
      shape: const RoundedRectangleBorder(borderRadius: BorderRadius.vertical(top: Radius.circular(16))),
      builder: (_) => ServiceOrderFormSheet(repository: _repository, formatter: formatter, order: order),
    );
    if (saved == true) _reload();
  }

  Future<void> _changeStatus(ServiceOrderModel order, String status) async {
    try {
      await _repository.updateStatus(order.id, status);
      _reload();
    } on ApiException catch (e) {
      if (mounted) ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(e.message)));
    }
  }

  Future<void> _delete(ServiceOrderModel order) async {
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        title: Text('Delete order #${order.orderNumber}?'),
        content: const Text('This cannot be undone.'),
        actions: [
          TextButton(onPressed: () => Navigator.of(context).pop(false), child: const Text('Cancel')),
          TextButton(onPressed: () => Navigator.of(context).pop(true), child: const Text('Delete')),
        ],
      ),
    );
    if (confirmed != true) return;

    try {
      await _repository.deleteOrder(order.id);
      _reload();
    } on ApiException catch (e) {
      if (mounted) ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(e.message)));
    }
  }

  @override
  Widget build(BuildContext context) {
    final company = context.watch<AuthProvider>().company;
    final formatter = CurrencyFormatter(company?.currencySymbol ?? '\$');

    return Scaffold(
      appBar: AppBar(title: const Text('Service Orders')),
      floatingActionButton: FloatingActionButton(onPressed: () => _openForm(formatter), child: const Icon(Icons.add)),
      body: FutureBuilder<({List<ServiceOrderModel> orders, Map<String, int> counts})>(
        future: _future,
        builder: (context, snapshot) {
          if (snapshot.connectionState != ConnectionState.done) return const LoadingIndicator();
          if (snapshot.hasError) return ErrorView(message: 'Could not load service orders.', onRetry: _reload);

          final counts = snapshot.data!.counts;
          final orders = snapshot.data!.orders;

          return Column(
            children: [
              SizedBox(
                height: 44,
                child: ListView(
                  scrollDirection: Axis.horizontal,
                  padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 6),
                  children: [
                    _FilterChip(
                      label: 'All (${counts['all'] ?? 0})',
                      selected: _statusFilter == null,
                      onTap: () => setState(() {
                        _statusFilter = null;
                        _reload();
                      }),
                    ),
                    for (final status in kServiceOrderStatuses)
                      _FilterChip(
                        label: '${kServiceOrderStatusLabels[status]} (${counts[status] ?? 0})',
                        selected: _statusFilter == status,
                        onTap: () => setState(() {
                          _statusFilter = status;
                          _reload();
                        }),
                      ),
                  ],
                ),
              ),
              Expanded(
                child: orders.isEmpty
                    ? const Center(child: Text('No service orders found.'))
                    : RefreshIndicator(
                        onRefresh: () async => _reload(),
                        child: ListView.separated(
                          padding: const EdgeInsets.fromLTRB(16, 8, 16, 80),
                          itemCount: orders.length,
                          separatorBuilder: (_, __) => const SizedBox(height: 8),
                          itemBuilder: (context, index) {
                            final order = orders[index];
                            return Card(
                              child: ListTile(
                                onTap: () => _openForm(formatter, order: order),
                                title: Text('#${order.orderNumber} · ${order.equipmentName}'),
                                subtitle: Text('${order.customerName} · ${order.statusLabel} · ${order.priority}'),
                                trailing: Column(
                                  mainAxisAlignment: MainAxisAlignment.center,
                                  crossAxisAlignment: CrossAxisAlignment.end,
                                  children: [
                                    Text(formatter.format(order.totalAmount), style: const TextStyle(fontWeight: FontWeight.w600)),
                                    PopupMenuButton<String>(
                                      icon: const Icon(Icons.more_vert, size: 18),
                                      onSelected: (value) => value == 'delete' ? _delete(order) : _changeStatus(order, value),
                                      itemBuilder: (context) => [
                                        for (final s in kServiceOrderStatuses)
                                          if (s != order.status)
                                            PopupMenuItem(value: s, child: Text('Mark ${kServiceOrderStatusLabels[s]}')),
                                        const PopupMenuDivider(),
                                        const PopupMenuItem(value: 'delete', child: Text('Delete')),
                                      ],
                                    ),
                                  ],
                                ),
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

class _FilterChip extends StatelessWidget {
  const _FilterChip({required this.label, required this.selected, required this.onTap});

  final String label;
  final bool selected;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.symmetric(horizontal: 4),
      child: ChoiceChip(label: Text(label), selected: selected, onSelected: (_) => onTap()),
    );
  }
}
