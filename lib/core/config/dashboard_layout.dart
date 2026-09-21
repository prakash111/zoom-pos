import 'package:flutter/material.dart';

/// Which visual treatment the authenticated dashboard home renders. All
/// layouts are bound to the exact same `GET /analytics` payload — only the
/// presentation changes. Chosen in Settings ▸ Appearance / App Preferences and
/// persisted per device.
enum DashboardLayout {
  /// Light "PoshPointHub" cards.
  posh,

  /// Dark, glossy "OroitOash" analytics board.
  oroit,

  /// Default dashboard with sparklines, sales area chart & receivables split.
  redesigned;

  static DashboardLayout fromName(String? name) =>
      DashboardLayout.values.firstWhere((l) => l.name == name,
          orElse: () => DashboardLayout.redesigned);

  String get label => switch (this) {
        DashboardLayout.posh => 'Cards (light)',
        DashboardLayout.oroit => 'Analytics board (dark)',
        DashboardLayout.redesigned => 'Metro Retail (dark)',
      };

  String get description => switch (this) {
        DashboardLayout.posh =>
          'Balance, statistics, activity, tags & transactions',
        DashboardLayout.oroit =>
          'Glossy dark board — order statistics, overview lines & tracking',
        DashboardLayout.redesigned =>
          'Metric cards with sparklines, sales overview area chart & receivables split',
      };

  IconData get icon => switch (this) {
        DashboardLayout.posh => Icons.dashboard_outlined,
        DashboardLayout.oroit => Icons.space_dashboard_outlined,
        DashboardLayout.redesigned => Icons.analytics_outlined,
      };
}
