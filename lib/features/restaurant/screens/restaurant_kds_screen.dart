import 'dart:async';

import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../../core/api/api_client.dart';
import '../../../core/api/api_exception.dart';
import '../../../core/models/restaurant_models.dart';
import '../../../core/services/sound_alert_service.dart';
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
  Future<({List<KitchenTicketModel> tickets, List<KitchenTicketModel> completedTickets, Map<String, int> counts, KdsAlertSettings alertSettings})>?
      _future;
  Timer? _pollTimer;
  Timer? _overdueChimeTimer;
  Set<String> _knownTicketIds = {};
  KdsAlertSettings _alertSettings = const KdsAlertSettings(intervalMinutes: 3, soundPreset: 'chime', soundUrl: '');

  @override
  void initState() {
    super.initState();
    _repository = RestaurantRepository(context.read<ApiClient>());
    _reload();
    _pollTimer = Timer.periodic(const Duration(seconds: 5), (_) => _reload(showLoading: false));
  }

  @override
  void dispose() {
    _pollTimer?.cancel();
    _overdueChimeTimer?.cancel();
    super.dispose();
  }

  void _restartOverdueChimeTimer() {
    _overdueChimeTimer?.cancel();
    _overdueChimeTimer = Timer.periodic(Duration(minutes: _alertSettings.intervalMinutes), (_) {
      final tickets = _future;
      if (tickets == null) return;
      tickets.then((result) {
        if (result.tickets.any((t) => t.isOverdue)) {
          SoundAlertService.instance.play(preset: _alertSettings.soundPreset, soundUrl: _alertSettings.soundUrl);
        }
      });
    });
  }

  void _handleResult(({List<KitchenTicketModel> tickets, List<KitchenTicketModel> completedTickets, Map<String, int> counts, KdsAlertSettings alertSettings}) result) {
    final currentIds = result.tickets.map((t) => t.id).toSet();
    final hasNewTicket = _knownTicketIds.isNotEmpty && currentIds.difference(_knownTicketIds).isNotEmpty;
    if (hasNewTicket) {
      SoundAlertService.instance.play(preset: result.alertSettings.soundPreset, soundUrl: result.alertSettings.soundUrl);
    }
    _knownTicketIds = currentIds;
    if (_alertSettings.intervalMinutes != result.alertSettings.intervalMinutes || _overdueChimeTimer == null) {
      _alertSettings = result.alertSettings;
      _restartOverdueChimeTimer();
    } else {
      _alertSettings = result.alertSettings;
    }
  }

  void _reload({bool showLoading = true}) {
    final future = _repository.fetchKot();
    if (showLoading) {
      future.then((result) {
        if (mounted) _handleResult(result);
      }).catchError((_) {});
      setState(() => _future = future);
    } else {
      future.then((result) {
        if (mounted) {
          _handleResult(result);
          setState(() => _future = Future.value(result));
        }
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
                    if (ticket.targetCompletionAt != null) ...[
                      const SizedBox(height: 4),
                      _CountdownBadge(target: ticket.targetCompletionAt!),
                    ],
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

/// Ticks every second to show a live "Due in Xm Ys" / "Overdue by Xm Ys"
/// badge against a KOT's `target_completion_at`.
class _CountdownBadge extends StatefulWidget {
  const _CountdownBadge({required this.target});

  final DateTime target;

  @override
  State<_CountdownBadge> createState() => _CountdownBadgeState();
}

class _CountdownBadgeState extends State<_CountdownBadge> {
  Timer? _timer;

  @override
  void initState() {
    super.initState();
    _timer = Timer.periodic(const Duration(seconds: 1), (_) => setState(() {}));
  }

  @override
  void dispose() {
    _timer?.cancel();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final remaining = widget.target.difference(DateTime.now());
    final overdue = remaining.isNegative;
    final abs = remaining.abs();
    final label = abs.inMinutes > 0 ? '${abs.inMinutes}m ${abs.inSeconds % 60}s' : '${abs.inSeconds}s';

    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 2),
      decoration: BoxDecoration(
        color: overdue ? Colors.red.shade50 : Colors.blue.shade50,
        borderRadius: BorderRadius.circular(20),
      ),
      child: Text(
        overdue ? '⚠️ Overdue by $label' : '🎯 Due in $label',
        style: TextStyle(fontSize: 10, fontWeight: FontWeight.bold, color: overdue ? Colors.red.shade700 : Colors.blue.shade700),
      ),
    );
  }
}
