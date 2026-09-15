/// One row from GET /receivables/due (PosSyncApiController::dueReceivables)
/// — a single unpaid or partially-paid sale, for the dashboard's "Due
/// Payments / Receivables" panel and its full list screen.
class ReceivableModel {
  ReceivableModel({
    required this.saleId,
    required this.documentId,
    required this.documentType,
    required this.saleNumber,
    required this.customerName,
    this.phone,
    this.email,
    this.date,
    this.dueDate,
    this.dueReminderAt,
    this.dueReminderSentAt,
    required this.total,
    required this.paidAmount,
    required this.dueAmount,
    required this.status,
    this.nativeAction,
  });

  factory ReceivableModel.fromJson(Map<String, dynamic> json) {
    Map<String, dynamic>? nativeAction;
    final rawAction = json['action'] ?? json['on_tap'];
    if (rawAction is Map &&
        (rawAction['type']?.toString().toLowerCase() ==
                'show_post_sale_sheet' ||
            rawAction['action_type']?.toString().toLowerCase() ==
                'show_post_sale_sheet')) {
      nativeAction = Map<String, dynamic>.from(rawAction);
    } else if (json['post_sale_sheet'] is Map) {
      final sheet = Map<String, dynamic>.from(json['post_sale_sheet'] as Map);
      if (sheet['data'] is Map) {
        nativeAction = {
          'type': 'show_post_sale_sheet',
          'action_type': 'show_post_sale_sheet',
          'data': Map<String, dynamic>.from(sheet['data'] as Map),
        };
      }
    }

    return ReceivableModel(
      saleId: json['sale_id'].toString(),
      documentId: (json['document_id'] ?? json['sale_id']).toString(),
      documentType: json['document_type']?.toString() ??
          ((json['sale_number']?.toString().toUpperCase() ?? '')
                  .startsWith('POS-')
              ? 'sale'
              : 'invoice'),
      saleNumber: json['sale_number'] as String? ?? '',
      customerName: json['customer_name'] as String? ?? 'Walk-in',
      phone: json['phone'] as String?,
      email: json['email'] as String?,
      date: json['date'] != null
          ? DateTime.tryParse(json['date'] as String)
          : null,
      dueDate: json['due_date'] != null
          ? DateTime.tryParse(json['due_date'] as String)
          : null,
      dueReminderAt: json['due_reminder_at'] != null
          ? DateTime.tryParse(json['due_reminder_at'] as String)
          : null,
      dueReminderSentAt: json['due_reminder_sent_at'] != null
          ? DateTime.tryParse(json['due_reminder_sent_at'] as String)
          : null,
      total: (json['total'] as num?)?.toDouble() ?? 0,
      paidAmount: (json['paid_amount'] as num?)?.toDouble() ?? 0,
      dueAmount: (json['due_amount'] as num?)?.toDouble() ?? 0,
      status: json['status'] as String? ?? 'pending',
      nativeAction: nativeAction,
    );
  }

  final String saleId;
  final String documentId;
  final String documentType;
  final String saleNumber;
  final String customerName;
  final String? phone;
  final String? email;
  final DateTime? date;
  final DateTime? dueDate;
  final DateTime? dueReminderAt;
  final DateTime? dueReminderSentAt;
  final double total;
  final double paidAmount;
  final double dueAmount;
  final String status;
  final Map<String, dynamic>? nativeAction;
}
