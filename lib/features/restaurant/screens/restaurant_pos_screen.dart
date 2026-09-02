import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../../core/api/api_client.dart';
import '../../../l10n/app_localizations.dart';
import '../restaurant_repository.dart';
import 'restaurant_order_screen.dart';
import 'restaurant_tables_screen.dart';

/// Restaurant mode's primary POS entry point — mirrors the web tenant
/// sidebar's "Restaurant POS Terminal" nav item (Dine-In, Takeaway &
/// Delivery). Dine-In hands off to the floor plan to pick a table; Takeaway
/// and Delivery jump straight into order entry with no table attached.
///
/// This is deliberately a separate screen from [RestaurantTablesScreen] (the
/// "Floor Plan & Tables" nav item): the web sidebar lists them as two
/// distinct destinations, so the mobile drawer does too.
class RestaurantPosScreen extends StatelessWidget {
  const RestaurantPosScreen({super.key});

  Future<void> _startTakeawayOrDelivery(BuildContext context, String serviceType) async {
    final repository = RestaurantRepository(context.read<ApiClient>());
    await Navigator.of(context).push<bool>(
      MaterialPageRoute(builder: (_) => RestaurantOrderScreen(repository: repository, serviceType: serviceType)),
    );
  }

  @override
  Widget build(BuildContext context) {
    final l10n = AppLocalizations.of(context);
    final colors = Theme.of(context).colorScheme;

    return Scaffold(
      appBar: AppBar(title: Text(l10n.featureRestaurantPos)),
      body: Center(
        child: ConstrainedBox(
          constraints: const BoxConstraints(maxWidth: 420),
          child: Padding(
            padding: const EdgeInsets.all(20),
            child: Column(
              mainAxisAlignment: MainAxisAlignment.center,
              children: [
                Icon(Icons.restaurant_outlined, size: 56, color: colors.primary),
                const SizedBox(height: 12),
                Text(
                  l10n.restaurantPosStartOrder,
                  style: Theme.of(context).textTheme.titleLarge,
                  textAlign: TextAlign.center,
                ),
                const SizedBox(height: 4),
                Text(
                  l10n.restaurantPosSubtitle,
                  style: Theme.of(context).textTheme.bodyMedium?.copyWith(color: colors.onSurfaceVariant),
                  textAlign: TextAlign.center,
                ),
                const SizedBox(height: 28),
                _ServiceTypeButton(
                  icon: Icons.table_restaurant_outlined,
                  label: l10n.restaurantPosDineIn,
                  subtitle: l10n.restaurantPosDineInSubtitle,
                  onTap: () => Navigator.of(context).push(
                    MaterialPageRoute(builder: (_) => const RestaurantTablesScreen()),
                  ),
                ),
                const SizedBox(height: 12),
                _ServiceTypeButton(
                  icon: Icons.shopping_bag_outlined,
                  label: l10n.restaurantPosTakeaway,
                  subtitle: l10n.restaurantPosTakeawaySubtitle,
                  onTap: () => _startTakeawayOrDelivery(context, 'takeaway'),
                ),
                const SizedBox(height: 12),
                _ServiceTypeButton(
                  icon: Icons.delivery_dining_outlined,
                  label: l10n.restaurantPosDelivery,
                  subtitle: l10n.restaurantPosDeliverySubtitle,
                  onTap: () => _startTakeawayOrDelivery(context, 'delivery'),
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }
}

class _ServiceTypeButton extends StatelessWidget {
  const _ServiceTypeButton({
    required this.icon,
    required this.label,
    required this.subtitle,
    required this.onTap,
  });

  final IconData icon;
  final String label;
  final String subtitle;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final colors = Theme.of(context).colorScheme;
    return Card(
      margin: EdgeInsets.zero,
      child: ListTile(
        contentPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
        leading: CircleAvatar(
          backgroundColor: colors.primaryContainer,
          child: Icon(icon, color: colors.onPrimaryContainer),
        ),
        title: Text(label, style: const TextStyle(fontWeight: FontWeight.bold)),
        subtitle: Text(subtitle),
        trailing: const Icon(Icons.chevron_right),
        onTap: onTap,
      ),
    );
  }
}
