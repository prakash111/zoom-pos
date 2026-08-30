/// A consignment item — a product dispatched on consignment to a customer.
class ConsignmentItemModel {
  ConsignmentItemModel({
    required this.id,
    required this.productId,
    required this.productName,
    required this.dispatchedQuantity,
    required this.returnedQuantity,
    required this.soldQuantity,
    required this.unitPrice,
    required this.soldTotal,
  });

  factory ConsignmentItemModel.fromJson(Map<String, dynamic> json) {
    return ConsignmentItemModel(
      id: json['id'].toString(),
      productId: json['product_id']?.toString(),
      productName: json['product_name'] as String? ?? 'Item',
      dispatchedQuantity: (json['dispatched_quantity'] as num?)?.toDouble() ?? 0,
      returnedQuantity: (json['returned_quantity'] as num?)?.toDouble() ?? 0,
      soldQuantity: (json['sold_quantity'] as num?)?.toDouble() ?? 0,
      unitPrice: (json['unit_price'] as num?)?.toDouble() ?? 0,
      soldTotal: (json['sold_total'] as num?)?.toDouble() ?? 0,
    );
  }

  final String id;
  final String? productId;
  final String productName;
  final double dispatchedQuantity;
  final double returnedQuantity;
  final double soldQuantity;
  final double unitPrice;
  final double soldTotal;
}

/// A consignment, as returned by ConsignmentApiController. Lifecycle:
/// draft -> dispatched -> reconciled -> finalized (generates a Sale).
class ConsignmentModel {
  ConsignmentModel({
    required this.id,
    required this.consignmentNumber,
    required this.customerId,
    required this.customerName,
    required this.status,
    required this.dispatchedAt,
    required this.dueDate,
    required this.reconciledAt,
    required this.totalDispatchedAmount,
    required this.totalSoldAmount,
    required this.totalReturnedAmount,
    required this.notes,
    this.items = const [],
  });

  factory ConsignmentModel.fromJson(Map<String, dynamic> json) {
    return ConsignmentModel(
      id: json['id'].toString(),
      consignmentNumber: json['consignment_number'] as String? ?? '',
      customerId: json['customer_id']?.toString(),
      customerName: json['customer_name'] as String? ?? 'Customer',
      status: json['status'] as String? ?? 'draft',
      dispatchedAt: DateTime.tryParse(json['dispatched_at'] as String? ?? ''),
      dueDate: DateTime.tryParse(json['due_date'] as String? ?? ''),
      reconciledAt: DateTime.tryParse(json['reconciled_at'] as String? ?? ''),
      totalDispatchedAmount: (json['total_dispatched_amount'] as num?)?.toDouble() ?? 0,
      totalSoldAmount: (json['total_sold_amount'] as num?)?.toDouble() ?? 0,
      totalReturnedAmount: (json['total_returned_amount'] as num?)?.toDouble() ?? 0,
      notes: json['notes'] as String?,
      items: (json['items'] as List? ?? []).map((e) => ConsignmentItemModel.fromJson(e as Map<String, dynamic>)).toList(),
    );
  }

  final String id;
  final String consignmentNumber;
  final String? customerId;
  final String customerName;
  final String status;
  final DateTime? dispatchedAt;
  final DateTime? dueDate;
  final DateTime? reconciledAt;
  final double totalDispatchedAmount;
  final double totalSoldAmount;
  final double totalReturnedAmount;
  final String? notes;
  final List<ConsignmentItemModel> items;

  bool get isDraft => status == 'draft';
  bool get isDispatched => status == 'dispatched';
  bool get isReconciled => status == 'reconciled';
  bool get isFinalized => status == 'finalized';
}
