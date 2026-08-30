import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import 'package:intl/intl.dart';

import '../../../core/api/api_client.dart';
import '../../../core/api/api_exception.dart';
import '../../../core/models/subscription_model.dart';
import '../../../core/widgets/error_view.dart';
import '../../../core/widgets/loading_indicator.dart';
import '../subscription_repository.dart';

final _dateFormat = DateFormat('MMM d, y');

/// Plan status and activation-code redemption — GET /subscription and
/// POST /subscription/redeem (PosSyncApiController::subscription /
/// subscriptionRedeem).
class SubscriptionScreen extends StatefulWidget {
  const SubscriptionScreen({super.key});

  @override
  State<SubscriptionScreen> createState() => _SubscriptionScreenState();
}

class _SubscriptionScreenState extends State<SubscriptionScreen> {
  late final SubscriptionRepository _repository;
  late Future<SubscriptionModel> _future;
  final _codeController = TextEditingController();
  bool _isRedeeming = false;
  String? _redeemError;

  @override
  void initState() {
    super.initState();
    _repository = SubscriptionRepository(context.read<ApiClient>());
    _future = _repository.fetchSubscription();
  }

  @override
  void dispose() {
    _codeController.dispose();
    super.dispose();
  }

  void _reload() {
    setState(() => _future = _repository.fetchSubscription());
  }

  Future<void> _redeem() async {
    final code = _codeController.text.trim();
    if (code.isEmpty) return;

    setState(() {
      _isRedeeming = true;
      _redeemError = null;
    });

    try {
      final message = await _repository.redeemCode(code);
      if (!mounted) return;
      _codeController.clear();
      setState(() => _isRedeeming = false);
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(message)));
      _reload();
    } on ApiException catch (e) {
      setState(() {
        _redeemError = e.message;
        _isRedeeming = false;
      });
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Subscription')),
      body: FutureBuilder<SubscriptionModel>(
        future: _future,
        builder: (context, snapshot) {
          if (snapshot.connectionState != ConnectionState.done) {
            return const LoadingIndicator();
          }
          if (snapshot.hasError) {
            final message = snapshot.error is ApiException
                ? (snapshot.error as ApiException).message
                : 'Could not load subscription details.';
            return ErrorView(message: message, onRetry: _reload);
          }

          final subscription = snapshot.data!;
          return RefreshIndicator(
            onRefresh: () async => _reload(),
            child: ListView(
              padding: const EdgeInsets.all(16),
              children: [
                _PlanCard(subscription: subscription),
                const SizedBox(height: 16),
                Card(
                  child: Padding(
                    padding: const EdgeInsets.all(16),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text('Usage', style: Theme.of(context).textTheme.titleMedium),
                        const SizedBox(height: 12),
                        _UsageRow(label: 'Products', count: subscription.productsCount, limit: subscription.productsLimit),
                        const SizedBox(height: 8),
                        _UsageRow(label: 'Users', count: subscription.usersCount, limit: subscription.usersLimit),
                      ],
                    ),
                  ),
                ),
                const SizedBox(height: 16),
                Card(
                  child: Padding(
                    padding: const EdgeInsets.all(16),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text('Redeem activation code', style: Theme.of(context).textTheme.titleMedium),
                        const SizedBox(height: 12),
                        TextField(
                          controller: _codeController,
                          textCapitalization: TextCapitalization.characters,
                          decoration: const InputDecoration(labelText: 'Activation code'),
                        ),
                        if (_redeemError != null) ...[
                          const SizedBox(height: 8),
                          Text(_redeemError!, style: TextStyle(color: Colors.red.shade400)),
                        ],
                        const SizedBox(height: 12),
                        ElevatedButton(
                          onPressed: _isRedeeming ? null : _redeem,
                          child: _isRedeeming
                              ? const SizedBox(
                                  height: 20,
                                  width: 20,
                                  child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white),
                                )
                              : const Text('Redeem'),
                        ),
                      ],
                    ),
                  ),
                ),
                if (subscription.availablePlans.isNotEmpty) ...[
                  const SizedBox(height: 24),
                  Text('Available plans', style: Theme.of(context).textTheme.titleMedium),
                  const SizedBox(height: 8),
                  for (final plan in subscription.availablePlans) ...[
                    _PlanOptionCard(plan: plan, isCurrent: plan.name == subscription.planName),
                    const SizedBox(height: 8),
                  ],
                ],
              ],
            ),
          );
        },
      ),
    );
  }
}

class _PlanCard extends StatelessWidget {
  const _PlanCard({required this.subscription});

  final SubscriptionModel subscription;

  @override
  Widget build(BuildContext context) {
    return Card(
      color: subscription.isExpired ? Colors.red.shade50 : Theme.of(context).colorScheme.primaryContainer,
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              mainAxisAlignment: MainAxisAlignment.spaceBetween,
              children: [
                Text(subscription.displayName, style: const TextStyle(fontSize: 20, fontWeight: FontWeight.bold)),
                Chip(
                  label: Text(subscription.isExpired ? 'Expired' : 'Active'),
                  backgroundColor: subscription.isExpired ? Colors.red.shade100 : Colors.green.shade100,
                ),
              ],
            ),
            const SizedBox(height: 8),
            if (subscription.isLifetime)
              const Text('No expiration')
            else if (subscription.expiresAt != null)
              Text(
                'Expires ${_dateFormat.format(subscription.expiresAt!)}'
                '${subscription.daysRemaining != null ? ' (${subscription.daysRemaining} days left)' : ''}',
              ),
          ],
        ),
      ),
    );
  }
}

class _UsageRow extends StatelessWidget {
  const _UsageRow({required this.label, required this.count, required this.limit});

  final String label;
  final int count;
  final dynamic limit;

  @override
  Widget build(BuildContext context) {
    final limitText = limit == null || (limit is String) ? (limit?.toString() ?? 'Unlimited') : limit.toString();
    return Row(
      mainAxisAlignment: MainAxisAlignment.spaceBetween,
      children: [
        Text(label),
        Text('$count / $limitText', style: const TextStyle(fontWeight: FontWeight.w600)),
      ],
    );
  }
}

class _PlanOptionCard extends StatelessWidget {
  const _PlanOptionCard({required this.plan, required this.isCurrent});

  final SubscriptionPlan plan;
  final bool isCurrent;

  @override
  Widget build(BuildContext context) {
    return Card(
      shape: isCurrent
          ? RoundedRectangleBorder(
              borderRadius: BorderRadius.circular(12),
              side: BorderSide(color: Theme.of(context).colorScheme.primary, width: 2),
            )
          : null,
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Row(
          children: [
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Row(
                    children: [
                      Text(plan.displayName, style: const TextStyle(fontWeight: FontWeight.w600)),
                      if (isCurrent) ...[
                        const SizedBox(width: 8),
                        const Chip(
                          label: Text('Current', style: TextStyle(fontSize: 11)),
                          visualDensity: VisualDensity.compact,
                          materialTapTargetSize: MaterialTapTargetSize.shrinkWrap,
                        ),
                      ],
                    ],
                  ),
                  if (plan.billingCycle != null) Text('Billed ${plan.billingCycle}', style: TextStyle(color: Colors.grey.shade600, fontSize: 12)),
                ],
              ),
            ),
            Text(
              plan.price <= 0 ? 'Free' : '${plan.currency} ${plan.price.toStringAsFixed(2)}',
              style: const TextStyle(fontWeight: FontWeight.bold),
            ),
          ],
        ),
      ),
    );
  }
}
