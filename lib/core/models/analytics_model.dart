/// GET /analytics response (PosSyncApiController::analytics) — dashboard
/// KPIs, payment-method breakdown, a 7-day revenue trend, and top products.
class AnalyticsModel {
  AnalyticsModel({
    required this.todayRevenue,
    required this.todayOrders,
    required this.monthRevenue,
    required this.monthOrders,
    required this.allTimeRevenue,
    required this.allTimeOrders,
    required this.averageOrderValue,
    required this.totalReceivables,
    required this.lowStockCount,
    required this.paymentBreakdown,
    required this.revenueTrend,
    required this.topProducts,
  });

  factory AnalyticsModel.fromJson(Map<String, dynamic> json) {
    final kpis = json['kpis'] is Map ? Map<String, dynamic>.from(json['kpis'] as Map) : <String, dynamic>{};
    return AnalyticsModel(
      todayRevenue: (kpis['today_revenue'] as num?)?.toDouble() ?? 0,
      todayOrders: (kpis['today_orders'] as num?)?.toInt() ?? 0,
      monthRevenue: (kpis['month_revenue'] as num?)?.toDouble() ?? 0,
      monthOrders: (kpis['month_orders'] as num?)?.toInt() ?? 0,
      allTimeRevenue: (kpis['all_time_revenue'] as num?)?.toDouble() ?? 0,
      allTimeOrders: (kpis['all_time_orders'] as num?)?.toInt() ?? 0,
      averageOrderValue: (kpis['average_order_value'] as num?)?.toDouble() ?? 0,
      totalReceivables: (kpis['total_receivables'] as num?)?.toDouble() ?? 0,
      lowStockCount: (kpis['low_stock_count'] as num?)?.toInt() ?? 0,
      paymentBreakdown: (json['payment_breakdown'] as List? ?? [])
          .whereType<Map>()
          .map((e) => PaymentBreakdownEntry.fromJson(Map<String, dynamic>.from(e)))
          .toList(),
      revenueTrend: (json['revenue_trend'] as List? ?? [])
          .whereType<Map>()
          .map((e) => RevenueTrendPoint.fromJson(Map<String, dynamic>.from(e)))
          .toList(),
      topProducts: (json['top_products'] as List? ?? [])
          .whereType<Map>()
          .map((e) => TopProductEntry.fromJson(Map<String, dynamic>.from(e)))
          .toList(),
    );
  }

  final double todayRevenue;
  final int todayOrders;
  final double monthRevenue;
  final int monthOrders;
  final double allTimeRevenue;
  final int allTimeOrders;
  final double averageOrderValue;
  final double totalReceivables;
  final int lowStockCount;
  final List<PaymentBreakdownEntry> paymentBreakdown;
  final List<RevenueTrendPoint> revenueTrend;
  final List<TopProductEntry> topProducts;
}

class PaymentBreakdownEntry {
  PaymentBreakdownEntry({required this.method, required this.count, required this.total});

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
  RevenueTrendPoint({required this.date, required this.day, required this.revenue});

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

class TopProductEntry {
  TopProductEntry({required this.name, required this.unitsSold, required this.revenue});

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
