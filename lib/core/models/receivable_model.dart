/// One row from GET /receivables/due (PosSyncApiController::dueReceivables)
/// — a single unpaid or partially-paid sale, for the dashboard's "Due
/// Payments / Receivables" panel and its full list screen.
class ReceivableModel {
  ReceivableModel({
    required this.saleId,
    required this.saleNumber,
    required this.customerName,
    this.phone,
    this.email,
    this.date,
    this.dueDate,
    required this.total,
    required this.paidAmount,
    required this.dueAmount,
    required this.status,
  });

  factory ReceivableModel.fromJson(Map<String, dynamic> json) {
    return ReceivableModel(
      saleId: json['sale_id'].toString(),
      saleNumber: json['sale_number'] as String? ?? '',
      customerName: json['customer_name'] as String? ?? 'Walk-in',
      phone: json['phone'] as String?,
      email: json['email'] as String?,
      date: json['date'] != null ? DateTime.tryParse(json['date'] as String) : null,
      dueDate: json['due_date'] != null ? DateTime.tryParse(json['due_date'] as String) : null,
      total: (json['total'] as num?)?.toDouble() ?? 0,
      paidAmount: (json['paid_amount'] as num?)?.toDouble() ?? 0,
      dueAmount: (json['due_amount'] as num?)?.toDouble() ?? 0,
      status: json['status'] as String? ?? 'pending',
    );
  }

  final String saleId;
  final String saleNumber;
  final String customerName;
  final String? phone;
  final String? email;
  final DateTime? date;
  final DateTime? dueDate;
  final double total;
  final double paidAmount;
  final double dueAmount;
  final String status;
}
