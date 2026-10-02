import 'package:flutter/material.dart';

import '../../../core/models/analytics_model.dart';
import '../../../core/utils/currency_formatter.dart';
import '../../../features/dashboard/widgets/posh_dashboard.dart';

/// Cards Dashboard Layout ("Cards light" / "Cards dark").
///
/// Refactored to completely remove the legacy Total Balance card
/// and align remaining sections cleanly without awkward whitespace.
class CardsDashboardLayout extends StatelessWidget {
  const CardsDashboardLayout({
    super.key,
    required this.analytics,
    required this.formatter,
    this.onAddProduct,
    this.onOpenTransactions,
    this.onOpenCustomers,
    this.onFilter,
    this.onTagTap,
  });

  final AnalyticsModel analytics;
  final CurrencyFormatter formatter;
  final VoidCallback? onAddProduct;
  final VoidCallback? onOpenTransactions;
  final VoidCallback? onOpenCustomers;
  final VoidCallback? onFilter;
  final void Function(String tag)? onTagTap;

  @override
  Widget build(BuildContext context) {
    // -------------------------------------------------------------------------
    // REMOVE THIS BLOCK:
    // TotalBalanceCard(
    //   balance: dashboardProvider.totalBalance,
    //   sparkline: dashboardProvider.balanceTrend,
    // ),
    // -------------------------------------------------------------------------

    return PoshDashboardHome(
      analytics: analytics,
      formatter: formatter,
      onAddProduct: onAddProduct,
      onOpenTransactions: onOpenTransactions,
      onOpenCustomers: onOpenCustomers,
      onFilter: onFilter,
      onTagTap: onTagTap,
    );
  }
}

/// Fallback / Deprecated TotalBalanceCard:
/// Permanently neutralized to return [SizedBox.shrink] so that any legacy
/// invocation or schema reference safely consumes zero layout space.
class TotalBalanceCard extends StatelessWidget {
  const TotalBalanceCard({
    super.key,
    this.balance,
    this.sparkline,
    this.analytics,
    this.formatter,
  });

  final dynamic balance;
  final dynamic sparkline;
  final AnalyticsModel? analytics;
  final CurrencyFormatter? formatter;

  @override
  Widget build(BuildContext context) {
    return const SizedBox.shrink();
  }
}
