/// A completed sale, as returned in the `sales` array of GET /sync-pull
/// (PosSyncApiController::syncPull) — the only endpoint that surfaces sales
/// history back to a client, since sync-push/sync-batch are outbound-only.
class SaleModel {
  SaleModel({
    required this.id,
    required this.saleNumber,
    required this.customerName,
    required this.items,
    required this.total,
    required this.discount,
    required this.tax,
    required this.paymentMethod,
    required this.paymentStatus,
    required this.paidAmount,
    required this.dueAmount,
    required this.status,
    required this.createdAt,
  });

  factory SaleModel.fromJson(Map<String, dynamic> json) {
    return SaleModel(
      id: json['id'].toString(),
      saleNumber: json['sale_number'] as String? ?? '',
      customerName: json['customer_name'] as String?,
      items: (json['items'] as List? ?? [])
          .whereType<Map>()
          .map((e) => Map<String, dynamic>.from(e))
          .toList(),
      total: (json['total'] as num?)?.toDouble() ?? 0,
      discount: (json['discount'] as num?)?.toDouble() ?? 0,
      tax: (json['tax'] as num?)?.toDouble() ?? 0,
      paymentMethod: json['payment_method'] as String? ?? 'cash',
      paymentStatus: json['payment_status'] as String? ?? 'paid',
      paidAmount: (json['paid_amount'] as num?)?.toDouble() ?? 0,
      dueAmount: (json['due_amount'] as num?)?.toDouble() ?? 0,
      status: json['status'] as String? ?? 'completed',
      createdAt: DateTime.tryParse(json['createdAt'] as String? ?? ''),
    );
  }

  final String id;
  final String saleNumber;
  final String? customerName;
  final List<Map<String, dynamic>> items;
  final double total;
  final double discount;
  final double tax;
  final String paymentMethod;
  final String paymentStatus;
  final double paidAmount;
  final double dueAmount;
  final String status;
  final DateTime? createdAt;

  bool get isCancelled => status == 'cancelled';
}
