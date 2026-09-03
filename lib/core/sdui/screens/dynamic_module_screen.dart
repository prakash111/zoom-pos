import 'package:flutter/material.dart';
import '../models/sdui_models.dart';
import '../sdui_icon_registry.dart';

/// Agnostic layout shell for server-driven business modules.
///
/// Renders pluggable business modes delivered dynamically from the Laravel
/// backend (e.g. Pharmacy POS, Service/Salon POS, Laundry, Hotel, etc.)
/// without requiring mobile client recompilation.
class DynamicModuleScreen extends StatelessWidget {
  const DynamicModuleScreen({
    super.key,
    required this.module,
    this.onAction,
  });

  final ModuleSchema module;
  final void Function(String actionKey)? onAction;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final iconData = SduiIconRegistry.resolve(module.icon, fallback: Icons.apps);

    return Scaffold(
      appBar: AppBar(
        title: Row(
          children: [
            Icon(iconData, size: 22),
            const SizedBox(width: 8),
            Expanded(child: Text(module.title, overflow: TextOverflow.ellipsis)),
          ],
        ),
      ),
      body: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          Card(
            elevation: 0,
            color: theme.colorScheme.primaryContainer.withValues(alpha: 0.3),
            shape: RoundedRectangleBorder(
              borderRadius: BorderRadius.circular(16),
              side: BorderSide(color: theme.colorScheme.primary.withValues(alpha: 0.2)),
            ),
            child: Padding(
              padding: const EdgeInsets.all(20),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Row(
                    children: [
                      CircleAvatar(
                        radius: 24,
                        backgroundColor: theme.colorScheme.primary,
                        child: Icon(iconData, color: Colors.white, size: 26),
                      ),
                      const SizedBox(width: 16),
                      Expanded(
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Text(
                              module.title,
                              style: theme.textTheme.titleMedium?.copyWith(
                                fontWeight: FontWeight.bold,
                              ),
                            ),
                            if (module.description.isNotEmpty)
                              Padding(
                                padding: const EdgeInsets.only(top: 4),
                                child: Text(
                                  module.description,
                                  style: theme.textTheme.bodyMedium?.copyWith(
                                    color: theme.colorScheme.onSurfaceVariant,
                                  ),
                                ),
                              ),
                          ],
                        ),
                      ),
                    ],
                  ),
                  const SizedBox(height: 16),
                  const Divider(),
                  const SizedBox(height: 12),
                  Text(
                    'Active Module Capabilities',
                    style: theme.textTheme.labelLarge?.copyWith(fontWeight: FontWeight.bold),
                  ),
                  const SizedBox(height: 8),
                  Wrap(
                    spacing: 8,
                    runSpacing: 8,
                    children: [
                      for (final entry in module.features.entries)
                        if (entry.value == true)
                          Chip(
                            avatar: const Icon(Icons.check_circle, size: 16, color: Colors.green),
                            label: Text(
                              _formatFeatureKey(entry.key),
                              style: const TextStyle(fontSize: 12),
                            ),
                            backgroundColor: Colors.white,
                          ),
                    ],
                  ),
                ],
              ),
            ),
          ),
          const SizedBox(height: 20),
          Text(
            'Quick Actions',
            style: theme.textTheme.titleSmall?.copyWith(fontWeight: FontWeight.bold),
          ),
          const SizedBox(height: 10),
          _ModuleActionTile(
            title: 'Launch Point of Sale',
            subtitle: 'Open terminal configured for ${module.title}',
            icon: Icons.point_of_sale_outlined,
            onTap: () => onAction?.call('pos'),
          ),
          const SizedBox(height: 8),
          _ModuleActionTile(
            title: 'Catalog & Inventory',
            subtitle: 'Manage items, pricing, and stock',
            icon: Icons.inventory_2_outlined,
            onTap: () => onAction?.call('inventory'),
          ),
          const SizedBox(height: 8),
          _ModuleActionTile(
            title: 'Transaction History',
            subtitle: 'View invoices, tickets, and receipts',
            icon: Icons.receipt_long_outlined,
            onTap: () => onAction?.call('sales'),
          ),
        ],
      ),
    );
  }

  static String _formatFeatureKey(String key) {
    return key
        .replaceAll('has_', '')
        .replaceAll('_', ' ')
        .split(' ')
        .map((w) => w.isNotEmpty ? '${w[0].toUpperCase()}${w.substring(1)}' : '')
        .join(' ');
  }
}

class _ModuleActionTile extends StatelessWidget {
  const _ModuleActionTile({
    required this.title,
    required this.subtitle,
    required this.icon,
    required this.onTap,
  });

  final String title;
  final String subtitle;
  final IconData icon;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return Card(
      child: ListTile(
        leading: CircleAvatar(
          backgroundColor: Theme.of(context).colorScheme.surfaceContainerHighest,
          child: Icon(icon, color: Theme.of(context).colorScheme.primary),
        ),
        title: Text(title, style: const TextStyle(fontWeight: FontWeight.w600)),
        subtitle: Text(subtitle, style: const TextStyle(fontSize: 12)),
        trailing: const Icon(Icons.arrow_forward_ios, size: 14),
        onTap: onTap,
      ),
    );
  }
}
