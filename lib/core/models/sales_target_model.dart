/// A single salesperson's progress row, as returned in the `user_targets`
/// array of GET /sales-targets (SalesTargetApiController).
class UserTargetModel {
  UserTargetModel({
    required this.userId,
    required this.name,
    required this.targetAmount,
    required this.achievedAmount,
    required this.percentage,
    required this.salesCount,
  });

  factory UserTargetModel.fromJson(Map<String, dynamic> json) {
    return UserTargetModel(
      userId: json['user_id'].toString(),
      name: json['name'] as String? ?? '',
      targetAmount: (json['target_amount'] as num?)?.toDouble() ?? 0,
      achievedAmount: (json['achieved_amount'] as num?)?.toDouble() ?? 0,
      percentage: (json['percentage'] as num?)?.toDouble() ?? 0,
      salesCount: (json['sales_count'] as num?)?.toInt() ?? 0,
    );
  }

  final String userId;
  final String name;
  final double targetAmount;
  final double achievedAmount;
  final double percentage;
  final int salesCount;
}

/// The full GET /sales-targets response for one year/month.
class SalesTargetsBundle {
  SalesTargetsBundle({
    required this.year,
    required this.month,
    required this.companyTargetAmount,
    required this.companyNotes,
    required this.overallAchieved,
    required this.overallPercentage,
    required this.overallRemaining,
    required this.remainingDays,
    required this.dailyRunRateNeeded,
    required this.userTargets,
  });

  factory SalesTargetsBundle.fromJson(Map<String, dynamic> json) {
    final overall = json['overall_progress'] as Map<String, dynamic>? ?? {};
    return SalesTargetsBundle(
      year: (json['year'] as num?)?.toInt() ?? DateTime.now().year,
      month: (json['month'] as num?)?.toInt() ?? DateTime.now().month,
      companyTargetAmount: (json['company_target_amount'] as num?)?.toDouble() ?? 0,
      companyNotes: json['company_notes'] as String? ?? '',
      overallAchieved: (overall['achieved'] as num?)?.toDouble() ?? 0,
      overallPercentage: (overall['percentage'] as num?)?.toDouble() ?? 0,
      overallRemaining: (overall['remaining'] as num?)?.toDouble() ?? 0,
      remainingDays: (json['remaining_days'] as num?)?.toInt() ?? 1,
      dailyRunRateNeeded: (json['daily_run_rate_needed'] as num?)?.toDouble() ?? 0,
      userTargets: (json['user_targets'] as List? ?? [])
          .map((e) => UserTargetModel.fromJson(e as Map<String, dynamic>))
          .toList(),
    );
  }

  final int year;
  final int month;
  final double companyTargetAmount;
  final String companyNotes;
  final double overallAchieved;
  final double overallPercentage;
  final double overallRemaining;
  final int remainingDays;
  final double dailyRunRateNeeded;
  final List<UserTargetModel> userTargets;
}
