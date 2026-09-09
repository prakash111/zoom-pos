/// GET /analytics response (PosSyncApiController::analytics) — dashboard
/// KPIs, payment-method breakdown, a 7-day revenue trend, top products, and
/// (for the redesigned dashboard) monthly purchase activity, popular tags,
/// recent transactions and recent customers.
class AnalyticsModel {
  AnalyticsModel({
    required this.todayRevenue,
    required this.todayOrders,
    required this.monthRevenue,
    required this.monthOrders,
    required this.prevMonthRevenue,
    required this.prevMonthOrders,
    required this.allTimeRevenue,
    required this.allTimeOrders,
    required this.averageOrderValue,
    required this.totalReceivables,
    required this.lowStockCount,
    required this.productCount,
    required this.customerCount,
    required this.paymentBreakdown,
    required this.revenueTrend,
    required this.monthlyActivity,
    required this.popularTags,
    required this.recentTransactions,
    required this.recentCustomers,
    required this.topProducts,
  });

  factory AnalyticsModel.fromJson(Map<String, dynamic> json) {
    final kpis = json['kpis'] is Map
        ? Map<String, dynamic>.from(json['kpis'] as Map)
        : <String, dynamic>{};
    List<Map<String, dynamic>> list(String key) => (json[key] as List? ?? [])
        .whereType<Map>()
        .map((e) => Map<String, dynamic>.from(e))
        .toList();

    return AnalyticsModel(
      todayRevenue: (kpis['today_revenue'] as num?)?.toDouble() ?? 0,
      todayOrders: (kpis['today_orders'] as num?)?.toInt() ?? 0,
      monthRevenue: (kpis['month_revenue'] as num?)?.toDouble() ?? 0,
      monthOrders: (kpis['month_orders'] as num?)?.toInt() ?? 0,
      prevMonthRevenue: (kpis['prev_month_revenue'] as num?)?.toDouble() ?? 0,
      prevMonthOrders: (kpis['prev_month_orders'] as num?)?.toInt() ?? 0,
      allTimeRevenue: (kpis['all_time_revenue'] as num?)?.toDouble() ?? 0,
      allTimeOrders: (kpis['all_time_orders'] as num?)?.toInt() ?? 0,
      averageOrderValue: (kpis['average_order_value'] as num?)?.toDouble() ?? 0,
      totalReceivables: (kpis['total_receivables'] as num?)?.toDouble() ?? 0,
      lowStockCount: (kpis['low_stock_count'] as num?)?.toInt() ?? 0,
      productCount: (kpis['product_count'] as num?)?.toInt() ?? 0,
      customerCount: (kpis['customer_count'] as num?)?.toInt() ?? 0,
      paymentBreakdown: list('payment_breakdown')
          .map(PaymentBreakdownEntry.fromJson)
          .toList(),
      revenueTrend:
          list('revenue_trend').map(RevenueTrendPoint.fromJson).toList(),
      monthlyActivity:
          list('monthly_activity').map(MonthlyActivityPoint.fromJson).toList(),
      popularTags: (json['popular_tags'] as List? ?? [])
          .map((e) => e.toString())
          .where((e) => e.trim().isNotEmpty)
          .toList(),
      recentTransactions:
          list('recent_transactions').map(TransactionEntry.fromJson).toList(),
      recentCustomers:
          list('recent_customers').map(RecentContactEntry.fromJson).toList(),
      topProducts: list('top_products').map(TopProductEntry.fromJson).toList(),
    );
  }

  final double todayRevenue;
  final int todayOrders;
  final double monthRevenue;
  final int monthOrders;
  final double prevMonthRevenue;
  final int prevMonthOrders;
  final double allTimeRevenue;
  final int allTimeOrders;
  final double averageOrderValue;
  final double totalReceivables;
  final int lowStockCount;
  final int productCount;
  final int customerCount;
  final List<PaymentBreakdownEntry> paymentBreakdown;
  final List<RevenueTrendPoint> revenueTrend;
  final List<MonthlyActivityPoint> monthlyActivity;
  final List<String> popularTags;
  final List<TransactionEntry> recentTransactions;
  final List<RecentContactEntry> recentCustomers;
  final List<TopProductEntry> topProducts;

  /// Month-over-month revenue change as a fraction (0.2046 = +20.46%).
  double get revenueDelta => prevMonthRevenue <= 0
      ? (monthRevenue > 0 ? 1 : 0)
      : (monthRevenue - prevMonthRevenue) / prevMonthRevenue;

  double get ordersDelta => prevMonthOrders <= 0
      ? (monthOrders > 0 ? 1 : 0)
      : (monthOrders - prevMonthOrders) / prevMonthOrders;
}

class PaymentBreakdownEntry {
  PaymentBreakdownEntry(
      {required this.method, required this.count, required this.total});

  factory PaymentBreakdownEntry.fromJson(Map<String, dynamic> json) {
    return PaymentBreakdownEntry(
      method: json['method'] as String? ?? 'Cash',
      count: (json['count'] as num?)?.toInt() ?? 0,
      total: (json['total'] as num?)?.toDouble() ?? 0,
    );
  }

  final String method;
  final int count;
  final double total;
}

class RevenueTrendPoint {
  RevenueTrendPoint(
      {required this.date, required this.day, required this.revenue});

  factory RevenueTrendPoint.fromJson(Map<String, dynamic> json) {
    return RevenueTrendPoint(
      date: json['date'] as String? ?? '',
      day: json['day'] as String? ?? '',
      revenue: (json['revenue'] as num?)?.toDouble() ?? 0,
    );
  }

  final String date;
  final String day;
  final double revenue;
}

/// One month's bucket on the "Purchase Activity" chart.
class MonthlyActivityPoint {
  MonthlyActivityPoint({
    required this.month,
    required this.year,
    required this.completed,
    required this.pending,
  });

  factory MonthlyActivityPoint.fromJson(Map<String, dynamic> json) {
    return MonthlyActivityPoint(
      month: json['month'] as String? ?? '',
      year: (json['year'] as num?)?.toInt() ?? 0,
      completed: (json['completed'] as num?)?.toInt() ?? 0,
      pending: (json['pending'] as num?)?.toInt() ?? 0,
    );
  }

  final String month;
  final int year;
  final int completed;
  final int pending;
}

/// A row in the "Latest Transactions" table.
class TransactionEntry {
  TransactionEntry({
    required this.id,
    required this.reference,
    required this.customer,
    required this.date,
    required this.status,
    required this.amount,
  });

  factory TransactionEntry.fromJson(Map<String, dynamic> json) {
    return TransactionEntry(
      id: json['id']?.toString() ?? '',
      reference: json['reference'] as String? ?? '',
      customer: json['customer'] as String? ?? '',
      date: json['date'] as String? ?? '',
      status: json['status'] as String? ?? 'Completed',
      amount: (json['amount'] as num?)?.toDouble() ?? 0,
    );
  }

  final String id;
  final String reference;
  final String customer;
  final String date;
  final String status;
  final double amount;

  bool get isCompleted => status.toLowerCase() == 'completed';
}

/// A recent customer, shown in the dashboard's "Recent Customers" panel.
class RecentContactEntry {
  RecentContactEntry({
    required this.id,
    required this.name,
    required this.detail,
    required this.time,
  });

  factory RecentContactEntry.fromJson(Map<String, dynamic> json) {
    return RecentContactEntry(
      id: json['id']?.toString() ?? '',
      name: json['name'] as String? ?? 'Customer',
      detail: json['detail'] as String? ?? '',
      time: json['time'] as String? ?? '',
    );
  }

  final String id;
  final String name;
  final String detail;
  final String time;

  String get initials {
    final parts =
        name.trim().split(RegExp(r'\s+')).where((p) => p.isNotEmpty).toList();
    if (parts.isEmpty) return '?';
    if (parts.length == 1) return parts.first.substring(0, 1).toUpperCase();
    return (parts.first.substring(0, 1) + parts.last.substring(0, 1))
        .toUpperCase();
  }
}

class TopProductEntry {
  TopProductEntry(
      {required this.name, required this.unitsSold, required this.revenue});

  factory TopProductEntry.fromJson(Map<String, dynamic> json) {
    return TopProductEntry(
      name: json['name'] as String? ?? '',
      unitsSold: (json['units_sold'] as num?)?.toDouble() ?? 0,
      revenue: (json['revenue'] as num?)?.toDouble() ?? 0,
    );
  }

  final String name;
  final double unitsSold;
  final double revenue;
}
