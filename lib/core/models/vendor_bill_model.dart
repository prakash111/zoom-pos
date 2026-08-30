/// A vendor bill (Accounts Payable), as returned by PayablesApiController.
class VendorBillModel {
  VendorBillModel({
    required this.id,
    required this.supplierId,
    required this.vendorName,
    required this.billNumber,
    required this.category,
    required this.title,
    required this.amount,
    required this.taxAmount,
    required this.paidAmount,
    required this.dueAmount,
    required this.status,
    required this.billDate,
    required this.dueDate,
    required this.isOverdue,
    required this.notes,
  });

  factory VendorBillModel.fromJson(Map<String, dynamic> json) {
    return VendorBillModel(
      id: json['id'].toString(),
      supplierId: json['supplier_id']?.toString(),
      vendorName: json['vendor_name'] as String? ?? 'Vendor',
      billNumber: json['bill_number'] as String? ?? '',
      category: json['category'] as String? ?? 'other',
      title: json['title'] as String?,
      amount: (json['amount'] as num?)?.toDouble() ?? 0,
      taxAmount: (json['tax_amount'] as num?)?.toDouble() ?? 0,
      paidAmount: (json['paid_amount'] as num?)?.toDouble() ?? 0,
      dueAmount: (json['due_amount'] as num?)?.toDouble() ?? 0,
      status: json['status'] as String? ?? 'pending',
      billDate: DateTime.tryParse(json['bill_date'] as String? ?? ''),
      dueDate: DateTime.tryParse(json['due_date'] as String? ?? ''),
      isOverdue: json['is_overdue'] as bool? ?? false,
      notes: json['notes'] as String?,
    );
  }

  final String id;
  final String? supplierId;
  final String vendorName;
  final String billNumber;
  final String category;
  final String? title;
  final double amount;
  final double taxAmount;
  final double paidAmount;
  final double dueAmount;
  final String status;
  final DateTime? billDate;
  final DateTime? dueDate;
  final bool isOverdue;
  final String? notes;

  double get totalAmount => amount + taxAmount;
  bool get isPaid => status == 'paid';
}
