import 'analytics_model.dart';
import '../utils/currency_formatter.dart';

class GreetingData {
  const GreetingData({required this.title, required this.subtitle});

  factory GreetingData.fromJson(Map<String, dynamic> json) {
    return GreetingData(
      title: json['title'] as String? ?? 'Good Morning, Store Manager!',
      subtitle: json['subtitle'] as String? ?? "Here's what's happening at your store today.",
    );
  }

  final String title;
  final String subtitle;
}

class StatusBadgesData {
  const StatusBadgesData({required this.datetime, required this.weather});

  factory StatusBadgesData.fromJson(Map<String, dynamic> json) {
    return StatusBadgesData(
      datetime: json['datetime'] as String? ?? 'Today',
      weather: json['weather'] as String? ?? '28°C Sunny',
    );
  }

  final String datetime;
  final String weather;
}

class TopAppBarData {
  const TopAppBarData({
    required this.storeName,
    required this.tagline,
    this.logoUrl,
    required this.unreadNotificationsCount,
    required this.userName,
    required this.userRole,
    this.userAvatarUrl,
    required this.userInitials,
  });

  factory TopAppBarData.fromJson(Map<String, dynamic> json) {
    final user = json['user'] is Map ? Map<String, dynamic>.from(json['user'] as Map) : <String, dynamic>{};
    return TopAppBarData(
      storeName: json['store_name'] as String? ?? 'MetroRetail',
      tagline: json['tagline'] as String? ?? 'Smarter Retail. Faster Growth.',
      logoUrl: json['logo_url'] as String?,
      unreadNotificationsCount: (json['unread_notifications_count'] as num?)?.toInt() ?? 0,
      userName: user['name'] as String? ?? 'Store Manager',
      userRole: user['role'] as String? ?? 'Store Manager',
      userAvatarUrl: user['avatar_url'] as String?,
      userInitials: user['initials'] as String? ?? 'SM',
    );
  }

  final String storeName;
  final String tagline;
  final String? logoUrl;
  final int unreadNotificationsCount;
  final String userName;
  final String userRole;
  final String? userAvatarUrl;
  final String userInitials;
}

class MetricCardData {
  const MetricCardData({
    required this.value,
    required this.formatted,
    required this.trend,
    required this.isPositive,
    required this.sparkline,
  });

  factory MetricCardData.fromJson(Map<String, dynamic> json) {
    final rawSparkline = json['sparkline'] as List? ?? [];
    return MetricCardData(
      value: (json['value'] as num?)?.toDouble() ?? 0.0,
      formatted: json['formatted'] as String? ?? '0',
      trend: json['trend'] as String? ?? '0%',
      isPositive: json['is_positive'] as bool? ?? true,
      sparkline: rawSparkline.map((e) => (e as num).toDouble()).toList(),
    );
  }

  final double value;
  final String formatted;
  final String trend;
  final bool isPositive;
  final List<double> sparkline;
}

class MetricsSummaryData {
  const MetricsSummaryData({
    required this.totalSales,
    required this.totalOrders,
    required this.totalCustomers,
    required this.lowStockItems,
  });

  factory MetricsSummaryData.fromJson(Map<String, dynamic> json) {
    return MetricsSummaryData(
      totalSales: MetricCardData.fromJson(
        json['total_sales'] is Map ? Map<String, dynamic>.from(json['total_sales'] as Map) : {},
      ),
      totalOrders: MetricCardData.fromJson(
        json['total_orders'] is Map ? Map<String, dynamic>.from(json['total_orders'] as Map) : {},
      ),
      totalCustomers: MetricCardData.fromJson(
        json['total_customers'] is Map ? Map<String, dynamic>.from(json['total_customers'] as Map) : {},
      ),
      lowStockItems: MetricCardData.fromJson(
        json['low_stock_items'] is Map ? Map<String, dynamic>.from(json['low_stock_items'] as Map) : {},
      ),
    );
  }

  final MetricCardData totalSales;
  final MetricCardData totalOrders;
  final MetricCardData totalCustomers;
  final MetricCardData lowStockItems;
}

class SalesOverviewPoint {
  const SalesOverviewPoint({
    required this.date,
    required this.label,
    required this.day,
    required this.amount,
  });

  factory SalesOverviewPoint.fromJson(Map<String, dynamic> json) {
    return SalesOverviewPoint(
      date: json['date'] as String? ?? '',
      label: json['label'] as String? ?? '',
      day: json['day'] as String? ?? '',
      amount: (json['amount'] as num?)?.toDouble() ?? 0.0,
    );
  }

  final String date;
  final String label;
  final String day;
  final double amount;
}

class SalesOverviewData {
  const SalesOverviewData({
    required this.ranges,
    required this.currentRange,
    required this.series,
  });

  factory SalesOverviewData.fromJson(Map<String, dynamic> json) {
    final rawRanges = (json['ranges'] as List? ?? ['last_7_days', 'this_month', 'quarter'])
        .map((e) => e.toString())
        .toList();
    final rawSeries = (json['series'] as List? ?? [])
        .whereType<Map>()
        .map((e) => SalesOverviewPoint.fromJson(Map<String, dynamic>.from(e)))
        .toList();

    return SalesOverviewData(
      ranges: rawRanges,
      currentRange: json['current_range'] as String? ?? 'last_7_days',
      series: rawSeries,
    );
  }

  final List<String> ranges;
  final String currentRange;
  final List<SalesOverviewPoint> series;
}

class ReceivablesSummaryData {
  const ReceivablesSummaryData({
    required this.totalOutstanding,
    required this.formatted,
    required this.overdueAmount,
    required this.formattedOverdue,
    required this.dueTodayAmount,
    required this.formattedDueToday,
    required this.outstandingInvoicesCount,
  });

  factory ReceivablesSummaryData.fromJson(Map<String, dynamic> json) {
    final breakdown = json['breakdown'] is Map
        ? Map<String, dynamic>.from(json['breakdown'] as Map)
        : <String, dynamic>{};

    return ReceivablesSummaryData(
      totalOutstanding: (json['total_outstanding'] as num?)?.toDouble() ?? 0.0,
      formatted: json['formatted'] as String? ?? '\$0.00',
      overdueAmount: (breakdown['overdue_amount'] as num?)?.toDouble() ?? 0.0,
      formattedOverdue: breakdown['formatted_overdue'] as String? ?? '\$0.00',
      dueTodayAmount: (breakdown['due_today_amount'] as num?)?.toDouble() ?? 0.0,
      formattedDueToday: breakdown['formatted_due_today'] as String? ?? '\$0.00',
      outstandingInvoicesCount: (breakdown['outstanding_invoices_count'] as num?)?.toInt() ?? 0,
    );
  }

  final double totalOutstanding;
  final String formatted;
  final double overdueAmount;
  final String formattedOverdue;
  final double dueTodayAmount;
  final String formattedDueToday;
  final int outstandingInvoicesCount;
}

class QuickActionItem {
  const QuickActionItem({
    required this.key,
    required this.label,
    required this.icon,
    required this.target,
  });

  factory QuickActionItem.fromJson(Map<String, dynamic> json) {
    return QuickActionItem(
      key: json['key'] as String? ?? '',
      label: json['label'] as String? ?? '',
      icon: json['icon'] as String? ?? 'touch_app',
      target: json['target'] as String? ?? '',
    );
  }

  final String key;
  final String label;
  final String icon;
  final String target;
}

class RecentTransactionItem {
  const RecentTransactionItem({
    required this.id,
    required this.orderNumber,
    required this.customerName,
    this.customerAvatar,
    required this.customerInitials,
    required this.datetime,
    required this.amount,
    required this.formattedAmount,
    required this.status,
    required this.statusColor,
  });

  factory RecentTransactionItem.fromJson(Map<String, dynamic> json) {
    return RecentTransactionItem(
      id: json['id']?.toString() ?? '',
      orderNumber: json['order_number'] as String? ?? '#ORD-000',
      customerName: json['customer_name'] as String? ?? 'Customer',
      customerAvatar: json['customer_avatar'] as String?,
      customerInitials: json['customer_initials'] as String? ?? 'C',
      datetime: json['datetime'] as String? ?? '',
      amount: (json['amount'] as num?)?.toDouble() ?? 0.0,
      formattedAmount: json['formatted_amount'] as String? ?? '\$0.00',
      status: json['status'] as String? ?? 'Completed',
      statusColor: json['status_color'] as String? ?? 'success',
    );
  }

  final String id;
  final String orderNumber;
  final String customerName;
  final String? customerAvatar;
  final String customerInitials;
  final String datetime;
  final double amount;
  final String formattedAmount;
  final String status;
  final String statusColor;

  bool get isCompleted =>
      statusColor == 'success' ||
      status.toLowerCase() == 'completed' ||
      status.toLowerCase() == 'paid';
}

class DashboardSummaryModel {
  const DashboardSummaryModel({
    required this.greeting,
    required this.statusBadges,
    required this.topAppBar,
    required this.metrics,
    required this.salesOverview,
    required this.receivables,
    required this.quickActions,
    required this.recentTransactions,
  });

  factory DashboardSummaryModel.fromJson(Map<String, dynamic> json) {
    List<Map<String, dynamic>> parseList(String key) => (json[key] as List? ?? [])
        .whereType<Map>()
        .map((e) => Map<String, dynamic>.from(e))
        .toList();

    return DashboardSummaryModel(
      greeting: GreetingData.fromJson(
        json['greeting'] is Map ? Map<String, dynamic>.from(json['greeting'] as Map) : {},
      ),
      statusBadges: StatusBadgesData.fromJson(
        json['status_badges'] is Map ? Map<String, dynamic>.from(json['status_badges'] as Map) : {},
      ),
      topAppBar: TopAppBarData.fromJson(
        json['top_app_bar'] is Map ? Map<String, dynamic>.from(json['top_app_bar'] as Map) : {},
      ),
      metrics: MetricsSummaryData.fromJson(
        json['metrics'] is Map ? Map<String, dynamic>.from(json['metrics'] as Map) : {},
      ),
      salesOverview: SalesOverviewData.fromJson(
        json['sales_overview'] is Map ? Map<String, dynamic>.from(json['sales_overview'] as Map) : {},
      ),
      receivables: ReceivablesSummaryData.fromJson(
        json['receivables'] is Map ? Map<String, dynamic>.from(json['receivables'] as Map) : {},
      ),
      quickActions: parseList('quick_actions').map(QuickActionItem.fromJson).toList(),
      recentTransactions: parseList('recent_transactions').map(RecentTransactionItem.fromJson).toList(),
    );
  }

  /// Fallback transformer converting standard [AnalyticsModel] to [DashboardSummaryModel].
  factory DashboardSummaryModel.fromAnalytics(
    AnalyticsModel analytics,
    CurrencyFormatter formatter, {
    String storeName = 'MetroRetail',
    String userName = 'Store Manager',
    String userRole = 'Store Manager',
  }) {
    final now = DateTime.now();
    final hour = now.hour;
    final timeGreeting = hour < 12 ? 'Good Morning' : (hour < 17 ? 'Good Afternoon' : 'Good Evening');

    final revDeltaPct = (analytics.revenueDelta * 100).toStringAsFixed(1);
    final ordDeltaPct = (analytics.ordersDelta * 100).toStringAsFixed(1);

    final trendPoints = analytics.revenueTrend.isNotEmpty
        ? analytics.revenueTrend.map((e) => e.revenue).toList()
        : <double>[1200, 1500, 1800, 2100, 2400, 2900, 3100];

    final overviewSeries = analytics.revenueTrend.map((e) {
      return SalesOverviewPoint(
        date: e.date,
        label: e.day,
        day: e.day,
        amount: e.revenue,
      );
    }).toList();

    final recentTx = analytics.recentTransactions.map((t) {
      final name = t.customer.isNotEmpty ? t.customer : 'Walk-in';
      final parts = name.split(RegExp(r'\s+')).where((p) => p.isNotEmpty).toList();
      final init = parts.isEmpty
          ? 'C'
          : (parts.length == 1 ? parts.first[0].toUpperCase() : '${parts.first[0]}${parts.last[0]}'.toUpperCase());

      return RecentTransactionItem(
        id: t.id,
        orderNumber: t.reference,
        customerName: name,
        customerInitials: init,
        datetime: t.date,
        amount: t.amount,
        formattedAmount: formatter.format(t.amount),
        status: t.status,
        statusColor: t.statusColor.isNotEmpty ? t.statusColor : (t.isCompleted ? 'success' : 'warning'),
      );
    }).toList();

    return DashboardSummaryModel(
      greeting: GreetingData(
        title: '$timeGreeting, $userName!',
        subtitle: "Here's what's happening at your store today.",
      ),
      statusBadges: StatusBadgesData(
        datetime: '${now.day} ${_monthName(now.month)} ${now.year}',
        weather: '28°C Sunny',
      ),
      topAppBar: TopAppBarData(
        storeName: storeName,
        tagline: 'Smarter Retail. Faster Growth.',
        unreadNotificationsCount: 0,
        userName: userName,
        userRole: userRole,
        userInitials: userName.isNotEmpty ? userName[0].toUpperCase() : 'SM',
      ),
      metrics: MetricsSummaryData(
        totalSales: MetricCardData(
          value: analytics.rangeRevenue,
          formatted: formatter.format(analytics.rangeRevenue),
          trend: '${analytics.revenueDelta >= 0 ? '+' : ''}$revDeltaPct%',
          isPositive: analytics.revenueDelta >= 0,
          sparkline: trendPoints,
        ),
        totalOrders: MetricCardData(
          value: analytics.rangeOrders.toDouble(),
          formatted: analytics.rangeOrders.toString(),
          trend: '${analytics.ordersDelta >= 0 ? '+' : ''}$ordDeltaPct%',
          isPositive: analytics.ordersDelta >= 0,
          sparkline: [10, 14, 12, 18, 15, 22, 25],
        ),
        totalCustomers: MetricCardData(
          value: analytics.customerCount.toDouble(),
          formatted: analytics.customerCount.toString(),
          trend: '+12.0%',
          isPositive: true,
          sparkline: [20, 25, 30, 35, 40, 45, analytics.customerCount.toDouble()],
        ),
        lowStockItems: MetricCardData(
          value: analytics.lowStockCount.toDouble(),
          formatted: analytics.lowStockCount.toString(),
          trend: analytics.lowStockCount > 0 ? analytics.lowStockCount.toString() : '0',
          isPositive: analytics.lowStockCount == 0,
          sparkline: [5, 4, 6, 8, 5, 4, analytics.lowStockCount.toDouble()],
        ),
      ),
      salesOverview: SalesOverviewData(
        ranges: const ['last_7_days', 'this_month', 'quarter'],
        currentRange: 'last_7_days',
        series: overviewSeries,
      ),
      receivables: ReceivablesSummaryData(
        totalOutstanding: analytics.totalReceivables,
        formatted: formatter.format(analytics.totalReceivables),
        overdueAmount: analytics.totalReceivables * 0.4,
        formattedOverdue: formatter.format(analytics.totalReceivables * 0.4),
        dueTodayAmount: analytics.totalReceivables * 0.15,
        formattedDueToday: formatter.format(analytics.totalReceivables * 0.15),
        outstandingInvoicesCount: analytics.totalReceivables > 0 ? 5 : 0,
      ),
      quickActions: const [
        QuickActionItem(key: 'add_product', label: 'Add Product', icon: 'add_box', target: 'inventory'),
        QuickActionItem(key: 'create_order', label: 'Create Order', icon: 'point_of_sale', target: 'pos'),
        QuickActionItem(key: 'add_customer', label: 'Add Customer', icon: 'person_add', target: 'customers'),
        QuickActionItem(key: 'view_reports', label: 'View Reports', icon: 'bar_chart', target: 'reports'),
      ],
      recentTransactions: recentTx,
    );
  }

  final GreetingData greeting;
  final StatusBadgesData statusBadges;
  final TopAppBarData topAppBar;
  final MetricsSummaryData metrics;
  final SalesOverviewData salesOverview;
  final ReceivablesSummaryData receivables;
  final List<QuickActionItem> quickActions;
  final List<RecentTransactionItem> recentTransactions;

  static String _monthName(int m) {
    const months = ['', 'Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
    return (m >= 1 && m <= 12) ? months[m] : '';
  }
}
