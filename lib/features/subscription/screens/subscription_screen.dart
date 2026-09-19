import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import 'package:intl/intl.dart';
import 'package:razorpay_flutter/razorpay_flutter.dart';

import '../../../core/api/api_client.dart';
import '../../../core/api/api_exception.dart';
import '../../../core/models/subscription_model.dart';
import '../../../core/widgets/error_view.dart';
import '../../../core/widgets/loading_indicator.dart';
import '../../../core/widgets/webview_screen.dart';
import '../subscription_repository.dart';

final _dateFormat = DateFormat('MMM d, y');

/// Plan status, activation-code redemption, and plan purchase — GET
/// /subscription, POST /subscription/redeem, and the plan
/// activation/purchase endpoints (PosSyncApiController::subscription*).
/// Purchases mirror the web Billing page: Razorpay uses its hosted-checkout
/// SDK (card/UPI data never touches this app's code), Mercado Pago uses a
/// hosted-redirect WebView. Both require a gateway to actually be enabled
/// server-side (Super Admin > Payment Gateways) — see `enabledGateways`.
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

  Razorpay? _razorpay;
  String? _pendingRazorpayPlan;
  String? _purchasingPlanName;

  @override
  void initState() {
    super.initState();
    _repository = SubscriptionRepository(context.read<ApiClient>());
    _future = _repository.fetchSubscription();
  }

  @override
  void dispose() {
    _codeController.dispose();
    _razorpay?.clear();
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

  void _showPurchaseSuccess(DateTime? expiresAt) {
    if (!mounted) return;
    showDialog(
      context: context,
      builder: (ctx) => AlertDialog(
        title: const Text('Plan activated 🎉'),
        content: Text(
          expiresAt != null
              ? 'Your subscription is now active until ${_dateFormat.format(expiresAt)}.'
              : 'Your subscription is now active.',
        ),
        actions: [ElevatedButton(onPressed: () => Navigator.of(ctx).pop(), child: const Text('OK'))],
      ),
    );
  }

  Future<void> _activateFree(SubscriptionPlan plan) async {
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (ctx) => AlertDialog(
        title: Text('Activate ${plan.displayName}?'),
        content: const Text('This plan is free — no payment required.'),
        actions: [
          TextButton(onPressed: () => Navigator.of(ctx).pop(false), child: const Text('Cancel')),
          ElevatedButton(onPressed: () => Navigator.of(ctx).pop(true), child: const Text('Activate')),
        ],
      ),
    );
    if (confirmed != true) return;

    setState(() => _purchasingPlanName = plan.name);
    try {
      final expiresAt = await _repository.activateFreePlan(plan.name);
      if (!mounted) return;
      _showPurchaseSuccess(expiresAt);
      _reload();
    } on ApiException catch (e) {
      if (mounted) ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(e.message)));
    } finally {
      if (mounted) setState(() => _purchasingPlanName = null);
    }
  }

  Future<String?> _pickGateway(List<String> gateways) {
    return showModalBottomSheet<String>(
      context: context,
      builder: (ctx) => SafeArea(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            if (gateways.contains('razorpay'))
              ListTile(
                leading: const Icon(Icons.payment),
                title: const Text('Pay with Razorpay'),
                onTap: () => Navigator.of(ctx).pop('razorpay'),
              ),
            if (gateways.contains('mercadopago'))
              ListTile(
                leading: const Icon(Icons.payment),
                title: const Text('Pay with Mercado Pago'),
                onTap: () => Navigator.of(ctx).pop('mercadopago'),
              ),
            const SizedBox(height: 8),
          ],
        ),
      ),
    );
  }

  Future<bool> _confirmPurchase(SubscriptionPlan plan, String gateway) async {
    // Preview only — the server computes and returns the exact charge total
    // (same 18% rate the web Billing page already uses) before any gateway
    // checkout actually opens.
    final previewTotal = plan.price * 1.18;
    final gatewayLabel = gateway == 'razorpay' ? 'Razorpay' : 'Mercado Pago';
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (ctx) => AlertDialog(
        title: Text('Buy ${plan.displayName}?'),
        content: Text('You will be charged approximately ${plan.currency} ${previewTotal.toStringAsFixed(2)} (incl. tax) via $gatewayLabel.'),
        actions: [
          TextButton(onPressed: () => Navigator.of(ctx).pop(false), child: const Text('Cancel')),
          ElevatedButton(onPressed: () => Navigator.of(ctx).pop(true), child: const Text('Continue')),
        ],
      ),
    );
    return confirmed == true;
  }

  Future<void> _buyPlan(SubscriptionPlan plan, SubscriptionModel subscription) async {
    if (plan.price <= 0) {
      await _activateFree(plan);
      return;
    }
    if (subscription.enabledGateways.isEmpty) return;

    final gateway = subscription.enabledGateways.length == 1
        ? subscription.enabledGateways.first
        : await _pickGateway(subscription.enabledGateways);
    if (gateway == null) return;

    if (!await _confirmPurchase(plan, gateway)) return;

    if (gateway == 'razorpay') {
      await _buyWithRazorpay(plan);
    } else if (gateway == 'mercadopago') {
      await _buyWithMercadoPago(plan);
    }
  }

  Razorpay _ensureRazorpay() {
    return _razorpay ??= Razorpay()
      ..on(Razorpay.EVENT_PAYMENT_SUCCESS, _onRazorpaySuccess)
      ..on(Razorpay.EVENT_PAYMENT_ERROR, _onRazorpayError)
      ..on(Razorpay.EVENT_EXTERNAL_WALLET, _onRazorpayExternalWallet);
  }

  Future<void> _buyWithRazorpay(SubscriptionPlan plan) async {
    setState(() => _purchasingPlanName = plan.name);
    try {
      final order = await _repository.createRazorpayOrder(plan.name);
      _pendingRazorpayPlan = plan.name;
      _ensureRazorpay().open({
        'key': order.keyId,
        'order_id': order.orderId,
        'amount': order.amount,
        'currency': order.currency,
        'name': order.companyName,
        'description': order.description,
        'prefill': {'contact': order.userPhone, 'email': order.userEmail, 'name': order.userName},
        'theme': {'color': order.color},
      });
    } on ApiException catch (e) {
      _pendingRazorpayPlan = null;
      if (mounted) {
        setState(() => _purchasingPlanName = null);
        ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(e.message)));
      }
    }
  }

  void _onRazorpaySuccess(PaymentSuccessResponse response) async {
    final planName = _pendingRazorpayPlan;
    _pendingRazorpayPlan = null;
    if (planName == null) return;

    try {
      final expiresAt = await _repository.verifyRazorpayPayment(
        planName: planName,
        paymentId: response.paymentId ?? '',
        orderId: response.orderId ?? '',
        signature: response.signature ?? '',
      );
      if (!mounted) return;
      _showPurchaseSuccess(expiresAt);
      _reload();
    } on ApiException catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(SnackBar(
          content: Text(
            'Payment succeeded but activation failed: ${e.message}. '
            'Contact support with payment ID ${response.paymentId}.',
          ),
          duration: const Duration(seconds: 8),
        ));
      }
    } finally {
      if (mounted) setState(() => _purchasingPlanName = null);
    }
  }

  void _onRazorpayError(PaymentFailureResponse response) {
    _pendingRazorpayPlan = null;
    if (!mounted) return;
    setState(() => _purchasingPlanName = null);
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(content: Text('Payment failed: ${response.message ?? 'Cancelled'}')),
    );
  }

  void _onRazorpayExternalWallet(ExternalWalletResponse response) {
    _pendingRazorpayPlan = null;
    if (mounted) setState(() => _purchasingPlanName = null);
  }

  Future<void> _buyWithMercadoPago(SubscriptionPlan plan) async {
    setState(() => _purchasingPlanName = plan.name);
    try {
      final checkoutUrl = await _repository.createMercadoPagoPreference(plan.name);
      if (!mounted) return;

      final result = await Navigator.of(context).push<Map<String, String>>(
        MaterialPageRoute(
          builder: (_) => WebViewScreen(url: checkoutUrl, resultMarker: 'mp_status=', title: 'Mercado Pago'),
        ),
      );
      if (result == null) return; // closed manually before completing

      final status = result['mp_status'];
      final paymentId = result['payment_id'] ?? result['collection_id'];
      if (status == 'success' && paymentId != null && paymentId.isNotEmpty) {
        final expiresAt = await _repository.verifyMercadoPagoPayment(planName: plan.name, paymentId: paymentId);
        if (!mounted) return;
        _showPurchaseSuccess(expiresAt);
        _reload();
      } else if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(SnackBar(
          content: Text(status == 'pending' ? 'Payment is pending confirmation.' : 'Payment was not completed.'),
        ));
      }
    } on ApiException catch (e) {
      if (mounted) ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(e.message)));
    } finally {
      if (mounted) setState(() => _purchasingPlanName = null);
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
            // Constrained and centered so the activation-code field and plan
            // cards don't stretch edge-to-edge on a wide desktop window.
            child: Center(
              child: ConstrainedBox(
                constraints: const BoxConstraints(maxWidth: 600),
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
                        _PlanOptionCard(
                          plan: plan,
                          isCurrent: plan.name == subscription.planName,
                          canPurchase: subscription.enabledGateways.isNotEmpty,
                          busy: _purchasingPlanName == plan.name,
                          onBuy: () => _buyPlan(plan, subscription),
                        ),
                        const SizedBox(height: 8),
                      ],
                    ],
                  ],
                ),
              ),
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
  const _PlanOptionCard({
    required this.plan,
    required this.isCurrent,
    required this.canPurchase,
    required this.busy,
    required this.onBuy,
  });

  final SubscriptionPlan plan;
  final bool isCurrent;

  /// Whether a paid plan can actually be bought — false when no payment
  /// gateway is enabled server-side, in which case no button is shown at
  /// all (redeem-code stays the only path, same as before this feature).
  final bool canPurchase;
  final bool busy;
  final VoidCallback onBuy;

  @override
  Widget build(BuildContext context) {
    final isFree = plan.price <= 0;
    final showButton = !isCurrent && (isFree || canPurchase);

    return Card(
      shape: isCurrent
          ? RoundedRectangleBorder(
              borderRadius: BorderRadius.circular(12),
              side: BorderSide(color: Theme.of(context).colorScheme.primary, width: 2),
            )
          : null,
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            Row(
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
                  isFree ? 'Free' : '${plan.currency} ${plan.price.toStringAsFixed(2)}',
                  style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 16),
                ),
              ],
            ),
            const SizedBox(height: 10),
            // Dynamic Limits Chips
            Wrap(
              spacing: 6,
              runSpacing: 4,
              children: [
                _badgeChip(
                  context,
                  plan.invoiceLimit == -1 ? 'Unlimited Invoices' : '${plan.invoiceLimit} Invoices/mo',
                  Icons.receipt_long,
                ),
                _badgeChip(
                  context,
                  plan.deviceLimit == -1 ? 'Unlimited POS' : '${plan.deviceLimit} Devices',
                  Icons.point_of_sale,
                ),
                _badgeChip(
                  context,
                  plan.staffLimit == -1 ? 'Unlimited Staff' : '${plan.staffLimit} Staff',
                  Icons.people_alt_outlined,
                ),
              ],
            ),
            if (plan.extensions.isNotEmpty) ...[
              const SizedBox(height: 8),
              Wrap(
                spacing: 6,
                runSpacing: 4,
                children: [
                  for (final ext in plan.extensions)
                    Container(
                      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
                      decoration: BoxDecoration(
                        color: Theme.of(context).colorScheme.primary.withValues(alpha: 0.1),
                        borderRadius: BorderRadius.circular(6),
                        border: Border.all(
                          color: Theme.of(context).colorScheme.primary.withValues(alpha: 0.3),
                        ),
                      ),
                      child: Text(
                        ext.replaceAll('_', ' ').toUpperCase(),
                        style: TextStyle(
                          fontSize: 10,
                          fontWeight: FontWeight.bold,
                          color: Theme.of(context).colorScheme.primary,
                        ),
                      ),
                    ),
                ],
              ),
            ],
            if (plan.features.isNotEmpty) ...[
              const SizedBox(height: 10),
              Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  for (final feat in plan.features.take(5))
                    Padding(
                      padding: const EdgeInsets.only(bottom: 4),
                      child: Row(
                        children: [
                          const Icon(Icons.check_circle_outline, size: 14, color: Color(0xFF10B981)),
                          const SizedBox(width: 6),
                          Expanded(
                            child: Text(
                              feat,
                              style: const TextStyle(fontSize: 12),
                            ),
                          ),
                        ],
                      ),
                    ),
                ],
              ),
            ],
            if (showButton) ...[
              const SizedBox(height: 12),
              SizedBox(
                width: double.infinity,
                child: OutlinedButton(
                  onPressed: busy ? null : onBuy,
                  child: busy
                      ? const SizedBox(height: 18, width: 18, child: CircularProgressIndicator(strokeWidth: 2))
                      : Text(isFree ? 'Activate' : 'Buy'),
                ),
              ),
            ],
          ],
        ),
      ),
    );
  }

  Widget _badgeChip(BuildContext context, String text, IconData icon) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 7, vertical: 3),
      decoration: BoxDecoration(
        color: Colors.grey.withValues(alpha: 0.12),
        borderRadius: BorderRadius.circular(6),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          Icon(icon, size: 12, color: Colors.grey.shade700),
          const SizedBox(width: 4),
          Text(
            text,
            style: TextStyle(fontSize: 11, fontWeight: FontWeight.w600, color: Colors.grey.shade800),
          ),
        ],
      ),
    );
  }
}
