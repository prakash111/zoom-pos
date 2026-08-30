/// A cash register shift, as returned by the CashRegisterApiController
/// endpoints (current/open/close/history/show).
class CashRegisterModel {
  CashRegisterModel({
    required this.id,
    required this.terminalId,
    required this.status,
    required this.openedAt,
    required this.closedAt,
    required this.openingBalance,
    required this.expectedClosingBalance,
    required this.countedClosingBalance,
    required this.cashDifference,
    required this.notes,
    required this.openingNotes,
  });

  factory CashRegisterModel.fromJson(Map<String, dynamic> json) {
    return CashRegisterModel(
      id: json['id'].toString(),
      terminalId: json['terminal_id'] as String? ?? 'Main POS',
      status: json['status'] as String? ?? 'closed',
      openedAt: DateTime.tryParse(json['opened_at'] as String? ?? ''),
      closedAt: DateTime.tryParse(json['closed_at'] as String? ?? ''),
      openingBalance: (json['opening_balance'] as num?)?.toDouble() ?? 0,
      expectedClosingBalance: (json['expected_closing_balance'] as num?)?.toDouble(),
      countedClosingBalance: (json['counted_closing_balance'] as num?)?.toDouble(),
      cashDifference: (json['cash_difference'] as num?)?.toDouble(),
      notes: json['notes'] as String?,
      openingNotes: json['opening_notes'] as String?,
    );
  }

  final String id;
  final String terminalId;
  final String status;
  final DateTime? openedAt;
  final DateTime? closedAt;
  final double openingBalance;
  final double? expectedClosingBalance;
  final double? countedClosingBalance;
  final double? cashDifference;
  final String? notes;
  final String? openingNotes;

  bool get isOpen => status == 'open';
}

/// One cash-in/cash-out movement recorded against an open register.
class CashRegisterTransactionModel {
  CashRegisterTransactionModel({
    required this.id,
    required this.voucherNumber,
    required this.type,
    required this.category,
    required this.categoryLabel,
    required this.amount,
    required this.balanceBefore,
    required this.balanceAfter,
    required this.reason,
    required this.createdAt,
  });

  factory CashRegisterTransactionModel.fromJson(Map<String, dynamic> json) {
    return CashRegisterTransactionModel(
      id: json['id'].toString(),
      voucherNumber: json['voucher_number'] as String? ?? '',
      type: json['type'] as String? ?? 'cash_in',
      category: json['category'] as String? ?? 'other',
      categoryLabel: json['category_label'] as String? ?? '',
      amount: (json['amount'] as num?)?.toDouble() ?? 0,
      balanceBefore: (json['balance_before'] as num?)?.toDouble() ?? 0,
      balanceAfter: (json['balance_after'] as num?)?.toDouble() ?? 0,
      reason: json['reason'] as String?,
      createdAt: DateTime.tryParse(json['created_at'] as String? ?? ''),
    );
  }

  final String id;
  final String voucherNumber;
  final String type;
  final String category;
  final String categoryLabel;
  final double amount;
  final double balanceBefore;
  final double balanceAfter;
  final String? reason;
  final DateTime? createdAt;

  bool get isCashIn => type == 'cash_in';
}
