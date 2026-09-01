import 'dart:async';

import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../../core/api/api_client.dart';
import '../../../core/api/api_exception.dart';
import '../../../core/models/restaurant_models.dart';
import '../../../core/widgets/error_view.dart';
import '../../../core/widgets/loading_indicator.dart';
import '../restaurant_repository.dart';

/// Kitchen Display System: live board of open kitchen tickets grouped by
/// status, refreshed on a short interval so the kitchen sees new orders
/// without manually pulling to refresh.
class RestaurantKdsScreen extends StatefulWidget {
  const RestaurantKdsScreen({super.key});

  @override
  State<RestaurantKdsScreen> createState() => _RestaurantKdsScreenState();
}

class _RestaurantKdsScreenState extends State<RestaurantKdsScreen> {
  late final RestaurantRepository _repository;
  Future<({List<KitchenTicketModel> tickets, List<KitchenTicketModel> completedTickets, Map<String, int> counts})>? _future;
  Timer? _pollTimer;

  @override
  void initState() {
    super.initState();
    _repository = RestaurantRepository(context.read<ApiClient>());
    _reload();
    _pollTimer = Timer.periodic(const Duration(seconds: 15), (_) => _reload(showLoading: false));
  }

  @override
  void dispose() {
    _pollTimer?.cancel();
    super.dispose();
  }

  void _reload({bool showLoading = true}) {
    final future = _repository.fetchKot();
    if (showLoading) {
      setState(() => _future = future);
    } else {
      future.then((result) {
        if (mounted) setState(() => _future = Future.value(result));
      }).catchError((_) {
        // Silent — the periodic background refresh shouldn't surface errors
        // over whatever's already on screen; pull-to-refresh still reports them.
      });
    }
  }

  Future<void> _advance(KitchenTicketModel ticket) async {
    final next = switch (ticket.status) {
      'pending' => 'preparing',
      'preparing' => 'ready',
      'ready' => 'served',
      _ => null,
    };
    if (next == null) return;

    try {
      await _repository.updateKotStatus(ticket.id, next);
      _reload(showLoading: false);
    } on ApiException catch (e) {
      if (mounted) ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(e.message)));
    }
  }

  Future<void> _cancel(KitchenTicketModel ticket) async {
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        title: Text('Cancel ${ticket.kotNumber}?'),
        content: const Text('This cannot be undone.'),
        actions: [
          TextButton(onPressed: () => Navigator.of(context).pop(false), child: const Text('No')),
          TextButton(onPressed: () => Navigator.of(context).pop(true), child: const Text('Yes, Cancel')),
        ],
      ),
    );
    if (confirmed != true) return;

    try {
      await _repository.updateKotStatus(ticket.id, 'cancelled');
      _reload(showLoading: false);
    } on ApiException catch (e) {
      if (mounted) ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(e.message)));
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Kitchen Display')),
      body: FutureBuilder(
        future: _future,
        builder: (context, snapshot) {
          if (snapshot.connectionState != ConnectionState.done || _future == null) return const LoadingIndicator();
          if (snapshot.hasError) return ErrorView(message: 'Could not load kitchen tickets.', onRetry: _reload);

          final data = snapshot.data;
          if (data == null) return const LoadingIndicator();

          final pending = data.tickets.where((t) => t.status == 'pending').toList();
          final preparing = data.tickets.where((t) => t.status == 'preparing').toList();
          final ready = data.tickets.where((t) => t.status == 'ready').toList();

          return RefreshIndicator(
            onRefresh: () async => _reload(),
            child: ListView(
              padding: const EdgeInsets.fromLTRB(16, 12, 16, 24),
              children: [
                _KotSection(title: 'Pending (${pending.length})', tickets: pending, onAdvance: _advance, onCancel: _cancel),
                const SizedBox(height: 16),
                _KotSection(title: 'Preparing (${preparing.length})', tickets: preparing, onAdvance: _advance, onCancel: _cancel),
                const SizedBox(height: 16),
                _KotSection(title: 'Ready (${ready.length})', tickets: ready, onAdvance: _advance, onCancel: _cancel),
              ],
            ),
          );
        },
      ),
    );
  }
}

class _KotSection extends StatelessWidget {
  const _KotSection({required this.title, required this.tickets, required this.onAdvance, required this.onCancel});

  final String title;
  final List<KitchenTicketModel> tickets;
  final ValueChanged<KitchenTicketModel> onAdvance;
  final ValueChanged<KitchenTicketModel> onCancel;

  String _actionLabel(String status) => switch (status) {
        'pending' => 'Start Preparing',
        'preparing' => 'Mark Ready',
        'ready' => 'Mark Served',
        _ => '',
      };

  @override
  Widget build(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(title, style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 15)),
        const SizedBox(height: 8),
        if (tickets.isEmpty)
          Padding(
            padding: const EdgeInsets.symmetric(vertical: 8),
            child: Text('Nothing here.', style: TextStyle(color: Colors.grey.shade500)),
          )
        else
          for (final ticket in tickets)
            Card(
              margin: const EdgeInsets.only(bottom: 8),
              child: Padding(
                padding: const EdgeInsets.all(12),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Row(
                      children: [
                        Expanded(
                          child: Text(ticket.kotNumber, style: const TextStyle(fontWeight: FontWeight.bold)),
                        ),
                        Container(
                          padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
                          decoration: BoxDecoration(
                            color: ticket.elapsedMinutes >= 15 ? Colors.red.shade50 : Colors.grey.shade100,
                            borderRadius: BorderRadius.circular(20),
                          ),
                          child: Text(
                            '${ticket.elapsedMinutes} min',
                            style: TextStyle(
                              fontSize: 11,
                              fontWeight: FontWeight.bold,
                              color: ticket.elapsedMinutes >= 15 ? Colors.red.shade700 : Colors.grey.shade700,
                            ),
                          ),
                        ),
                      ],
                    ),
                    Text(
                      '${ticket.tableName ?? ticket.serviceType} · ${ticket.serviceType}',
                      style: TextStyle(color: Colors.grey.shade600, fontSize: 12),
                    ),
                    const SizedBox(height: 6),
                    for (final item in ticket.items)
                      Text('${item.quantity.toStringAsFixed(item.quantity == item.quantity.roundToDouble() ? 0 : 1)} × ${item.name}'),
                    if ((ticket.kitchenNotes ?? '').isNotEmpty) ...[
                      const SizedBox(height: 4),
                      Text('Note: ${ticket.kitchenNotes}', style: const TextStyle(fontStyle: FontStyle.italic, fontSize: 12)),
                    ],
                    const SizedBox(height: 10),
                    Row(
                      children: [
                        Expanded(
                          child: OutlinedButton(
                            onPressed: () => onCancel(ticket),
                            child: const Text('Cancel'),
                          ),
                        ),
                        const SizedBox(width: 8),
                        Expanded(
                          flex: 2,
                          child: FilledButton(
                            onPressed: () => onAdvance(ticket),
                            child: Text(_actionLabel(ticket.status)),
                          ),
                        ),
                      ],
                    ),
                  ],
                ),
              ),
            ),
      ],
    );
  }
}
