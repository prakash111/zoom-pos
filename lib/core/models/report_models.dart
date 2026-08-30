/// Model shapes returned by ReportsApiController — one class per tab on the
/// web Reports page (Sales Summary, DRE/P&L, Payment Methods, Till Closings,
/// Commissions, Aging).
class GrowthStat {
  GrowthStat({required this.value, required this.direction, required this.isPositive});

  factory GrowthStat.fromJson(Map<String, dynamic> json) {
    return GrowthStat(
      value: (json['value'] as num?)?.toDouble() ?? 0,
      direction: json['direction'] as String? ?? 'up',
      isPositive: json['is_positive'] as bool? ?? true,
    );
  }

  final double value;
  final String direction;
  final bool isPositive;

  String get label => '${isPositive ? '+' : '-'}${value.toStringAsFixed(1)}%';
}

class ReportKpis {
  ReportKpis({
    required this.totalRevenue,
    required this.revenueGrowth,
    required this.transactionsCount,
    required this.transactionsGrowth,
    required this.aov,
    required this.aovGrowth,
  });

  factory ReportKpis.fromJson(Map<String, dynamic> json) {
    return ReportKpis(
      totalRevenue: (json['total_revenue'] as num?)?.toDouble() ?? 0,
      revenueGrowth: GrowthStat.fromJson(json['revenue_growth'] as Map<String, dynamic>? ?? {}),
      transactionsCount: (json['transactions_count'] as num?)?.toInt() ?? 0,
      transactionsGrowth: GrowthStat.fromJson(json['transactions_growth'] as Map<String, dynamic>? ?? {}),
      aov: (json['aov'] as num?)?.toDouble() ?? 0,
      aovGrowth: GrowthStat.fromJson(json['aov_growth'] as Map<String, dynamic>? ?? {}),
    );
  }

  final double totalRevenue;
  final GrowthStat revenueGrowth;
  final int transactionsCount;
  final GrowthStat transactionsGrowth;
  final double aov;
  final GrowthStat aovGrowth;
}

class SalesSummary {
  SalesSummary({
    required this.grossSales,
    required this.netSales,
    required this.totalDiscount,
    required this.totalTax,
    required this.totalPaid,
    required this.totalDue,
    required this.ordersCount,
    required this.aov,
    required this.itemsSoldCount,
    required this.cancelledCount,
    required this.cancelledValue,
  });

  factory SalesSummary.fromJson(Map<String, dynamic> json) {
    return SalesSummary(
      grossSales: (json['gross_sales'] as num?)?.toDouble() ?? 0,
      netSales: (json['net_sales'] as num?)?.toDouble() ?? 0,
      totalDiscount: (json['total_discount'] as num?)?.toDouble() ?? 0,
      totalTax: (json['total_tax'] as num?)?.toDouble() ?? 0,
      totalPaid: (json['total_paid'] as num?)?.toDouble() ?? 0,
      totalDue: (json['total_due'] as num?)?.toDouble() ?? 0,
      ordersCount: (json['orders_count'] as num?)?.toInt() ?? 0,
      aov: (json['aov'] as num?)?.toDouble() ?? 0,
      itemsSoldCount: (json['items_sold_count'] as num?)?.toDouble() ?? 0,
      cancelledCount: (json['cancelled_count'] as num?)?.toInt() ?? 0,
      cancelledValue: (json['cancelled_value'] as num?)?.toDouble() ?? 0,
    );
  }

  final double grossSales;
  final double netSales;
  final double totalDiscount;
  final double totalTax;
  final double totalPaid;
  final double totalDue;
  final int ordersCount;
  final double aov;
  final double itemsSoldCount;
  final int cancelledCount;
  final double cancelledValue;
}

class TopProductStat {
  TopProductStat({required this.name, required this.qty, required this.revenue});

  factory TopProductStat.fromJson(Map<String, dynamic> json) {
    return TopProductStat(
      name: json['name'] as String? ?? 'Item',
      qty: (json['qty'] as num?)?.toDouble() ?? 0,
      revenue: (json['revenue'] as num?)?.toDouble() ?? 0,
    );
  }

  final String name;
  final double qty;
  final double revenue;
}

class DreStatement {
  DreStatement({
    required this.grossRevenue,
    required this.discounts,
    required this.taxes,
    required this.netRevenue,
    required this.cogs,
    required this.grossProfit,
    required this.grossMargin,
    required this.cardFees,
    required this.commissions,
    required this.cashExpenses,
    required this.operatingExpenses,
    required this.ebitda,
    required this.netMargin,
    required this.ordersCount,
  });

  factory DreStatement.fromJson(Map<String, dynamic> json) {
    return DreStatement(
      grossRevenue: (json['gross_revenue'] as num?)?.toDouble() ?? 0,
      discounts: (json['discounts'] as num?)?.toDouble() ?? 0,
      taxes: (json['taxes'] as num?)?.toDouble() ?? 0,
      netRevenue: (json['net_revenue'] as num?)?.toDouble() ?? 0,
      cogs: (json['cogs'] as num?)?.toDouble() ?? 0,
      grossProfit: (json['gross_profit'] as num?)?.toDouble() ?? 0,
      grossMargin: (json['gross_margin'] as num?)?.toDouble() ?? 0,
      cardFees: (json['card_fees'] as num?)?.toDouble() ?? 0,
      commissions: (json['commissions'] as num?)?.toDouble() ?? 0,
      cashExpenses: (json['cash_expenses'] as num?)?.toDouble() ?? 0,
      operatingExpenses: (json['operating_expenses'] as num?)?.toDouble() ?? 0,
      ebitda: (json['ebitda'] as num?)?.toDouble() ?? 0,
      netMargin: (json['net_margin'] as num?)?.toDouble() ?? 0,
      ordersCount: (json['orders_count'] as num?)?.toInt() ?? 0,
    );
  }

  final double grossRevenue;
  final double discounts;
  final double taxes;
  final double netRevenue;
  final double cogs;
  final double grossProfit;
  final double grossMargin;
  final double cardFees;
  final double commissions;
  final double cashExpenses;
  final double operatingExpenses;
  final double ebitda;
  final double netMargin;
  final int ordersCount;
}

class PaymentMethodStat {
  PaymentMethodStat({required this.method, required this.amount, required this.count, required this.share});

  factory PaymentMethodStat.fromJson(Map<String, dynamic> json) {
    return PaymentMethodStat(
      method: json['method'] as String? ?? 'cash',
      amount: (json['amount'] as num?)?.toDouble() ?? 0,
      count: (json['count'] as num?)?.toInt() ?? 0,
      share: (json['share'] as num?)?.toDouble() ?? 0,
    );
  }

  final String method;
  final double amount;
  final int count;
  final double share;
}

class TillClosingStat {
  TillClosingStat({
    required this.id,
    required this.terminalId,
    required this.openedAt,
    required this.closedAt,
    required this.openedByName,
    required this.closedByName,
    required this.openingBalance,
    required this.expectedClosingBalance,
    required this.countedClosingBalance,
    required this.cashDifference,
  });

  factory TillClosingStat.fromJson(Map<String, dynamic> json) {
    return TillClosingStat(
      id: json['id'].toString(),
      terminalId: json['terminal_id'] as String? ?? 'Main POS',
      openedAt: DateTime.tryParse(json['opened_at'] as String? ?? ''),
      closedAt: DateTime.tryParse(json['closed_at'] as String? ?? ''),
      openedByName: json['opened_by_name'] as String? ?? '',
      closedByName: json['closed_by_name'] as String? ?? '',
      openingBalance: (json['opening_balance'] as num?)?.toDouble() ?? 0,
      expectedClosingBalance: (json['expected_closing_balance'] as num?)?.toDouble() ?? 0,
      countedClosingBalance: (json['counted_closing_balance'] as num?)?.toDouble() ?? 0,
      cashDifference: (json['cash_difference'] as num?)?.toDouble() ?? 0,
    );
  }

  final String id;
  final String terminalId;
  final DateTime? openedAt;
  final DateTime? closedAt;
  final String openedByName;
  final String closedByName;
  final double openingBalance;
  final double expectedClosingBalance;
  final double countedClosingBalance;
  final double cashDifference;
}

class CommissionStat {
  CommissionStat({
    required this.userId,
    required this.name,
    required this.role,
    required this.salesCount,
    required this.totalRevenue,
    required this.formattedRate,
    required this.totalCommission,
  });

  factory CommissionStat.fromJson(Map<String, dynamic> json) {
    return CommissionStat(
      userId: json['user_id']?.toString() ?? '',
      name: json['name'] as String? ?? 'Unassigned',
      role: json['role'] as String? ?? 'Staff',
      salesCount: (json['sales_count'] as num?)?.toInt() ?? 0,
      totalRevenue: (json['total_revenue'] as num?)?.toDouble() ?? 0,
      formattedRate: json['formatted_rate'] as String? ?? '',
      totalCommission: (json['total_commission'] as num?)?.toDouble() ?? 0,
    );
  }

  final String userId;
  final String name;
  final String role;
  final int salesCount;
  final double totalRevenue;
  final String formattedRate;
  final double totalCommission;
}

class AgingBracket {
  AgingBracket({required this.key, required this.label, required this.amount, required this.count});

  final String key;
  final String label;
  final double amount;
  final int count;
}

class AgingCustomer {
  AgingCustomer({
    required this.customerId,
    required this.name,
    required this.phone,
    required this.totalDue,
    required this.daysOverdue,
    required this.invoicesCount,
  });

  factory AgingCustomer.fromJson(Map<String, dynamic> json) {
    return AgingCustomer(
      customerId: json['customer_id']?.toString() ?? '',
      name: json['name'] as String? ?? 'Walk-in Client',
      phone: json['phone'] as String? ?? '',
      totalDue: (json['total_due'] as num?)?.toDouble() ?? 0,
      daysOverdue: (json['days_overdue'] as num?)?.toInt() ?? 0,
      invoicesCount: (json['invoices_count'] as num?)?.toInt() ?? 0,
    );
  }

  final String customerId;
  final String name;
  final String phone;
  final double totalDue;
  final int daysOverdue;
  final int invoicesCount;
}

class AgingReport {
  AgingReport({required this.totalReceivables, required this.brackets, required this.customers});

  factory AgingReport.fromJson(Map<String, dynamic> json) {
    final rawBrackets = (json['brackets'] as Map<String, dynamic>? ?? {});
    return AgingReport(
      totalReceivables: (json['total_receivables'] as num?)?.toDouble() ?? 0,
      brackets: rawBrackets.entries
          .map((e) => AgingBracket(
                key: e.key,
                label: (e.value as Map<String, dynamic>)['label'] as String? ?? e.key,
                amount: ((e.value as Map<String, dynamic>)['amount'] as num?)?.toDouble() ?? 0,
                count: ((e.value as Map<String, dynamic>)['count'] as num?)?.toInt() ?? 0,
              ))
          .toList(),
      customers: (json['customers'] as List? ?? [])
          .map((c) => AgingCustomer.fromJson(c as Map<String, dynamic>))
          .toList(),
    );
  }

  final double totalReceivables;
  final List<AgingBracket> brackets;
  final List<AgingCustomer> customers;
}
