const kServiceOrderStatuses = [
  'received',
  'under_diagnosis',
  'waiting_parts_approval',
  'ready_for_pickup',
  'delivered_settled',
  'cancelled',
];

const kServiceOrderStatusLabels = {
  'received': 'Received',
  'under_diagnosis': 'Under Diagnosis',
  'waiting_parts_approval': 'Waiting Parts / Approval',
  'ready_for_pickup': 'Ready for Pickup',
  'delivered_settled': 'Delivered & Settled',
  'cancelled': 'Cancelled',
};

const kServiceOrderPriorities = ['low', 'normal', 'high', 'urgent'];

/// A service order (repair/warranty ticket), as returned by
/// ServiceOrderApiController.
class ServiceOrderModel {
  ServiceOrderModel({
    required this.id,
    required this.orderNumber,
    required this.customerId,
    required this.customerName,
    required this.customerPhone,
    required this.customerEmail,
    required this.equipmentName,
    required this.brandModel,
    required this.serialNumber,
    required this.reportedDefect,
    required this.technicalDiagnosis,
    required this.partsUsed,
    required this.partsTotal,
    required this.laborCost,
    required this.discount,
    required this.totalAmount,
    required this.status,
    required this.statusLabel,
    required this.priority,
    required this.warrantyPeriod,
    required this.warrantyTerms,
    required this.technicianId,
    required this.notes,
    this.extraAttributes = const {},
    this.taxAmount = 0,
    this.taxRate = 0,
    this.isTaxInclusive = false,
    this.taxBreakdown = const {},
    this.saleId,
    this.invoiceNumber,
    this.showPostSaleSheet = false,
    this.postSaleSheet,
  });

  factory ServiceOrderModel.fromJson(Map<String, dynamic> json) {
    return ServiceOrderModel(
      id: json['id'].toString(),
      orderNumber: json['order_number'] as String? ?? '',
      customerId: json['customer_id']?.toString(),
      customerName: json['customer_name'] as String? ?? '',
      customerPhone: json['customer_phone'] as String? ?? '',
      customerEmail: json['customer_email'] as String? ?? '',
      equipmentName: json['equipment_name'] as String? ?? '',
      brandModel: json['brand_model'] as String? ?? '',
      serialNumber: json['serial_number'] as String? ?? '',
      reportedDefect: json['reported_defect'] as String? ?? '',
      technicalDiagnosis: json['technical_diagnosis'] as String? ?? '',
      partsUsed: (json['parts_used'] as List? ?? [])
          .map((e) => Map<String, dynamic>.from(e as Map)
            ..['quantity'] = ((e['quantity'] as num?) ?? 1).toDouble()
            ..['unit_price'] = ((e['unit_price'] as num?) ?? 0).toDouble())
          .toList(),
      partsTotal: (json['parts_total'] as num?)?.toDouble() ?? 0,
      laborCost: (json['labor_cost'] as num?)?.toDouble() ?? 0,
      discount: (json['discount'] as num?)?.toDouble() ?? 0,
      totalAmount: (json['total_amount'] as num?)?.toDouble() ?? 0,
      status: json['status'] as String? ?? 'received',
      statusLabel: json['status_label'] as String? ?? '',
      priority: json['priority'] as String? ?? 'normal',
      warrantyPeriod: json['warranty_period'] as String? ?? '',
      warrantyTerms: json['warranty_terms'] as String? ?? '',
      technicianId: json['technician_id']?.toString(),
      notes: json['notes'] as String?,
      extraAttributes: json['extra_attributes'] is Map
          ? Map<String, dynamic>.from(json['extra_attributes'] as Map)
          : const {},
      taxAmount: (json['tax_amount'] as num?)?.toDouble() ?? 0,
      taxRate: (json['tax_rate'] as num?)?.toDouble() ?? 0,
      isTaxInclusive: (json['is_tax_inclusive'] as bool?) ?? false,
      taxBreakdown: json['tax_breakdown'] is Map
          ? Map<String, dynamic>.from(json['tax_breakdown'] as Map)
          : const {},
      saleId: json['sale_id']?.toString(),
      invoiceNumber: json['invoice_number'] as String?,
      showPostSaleSheet: (json['show_post_sale_sheet'] as bool?) ?? false,
      postSaleSheet: json['post_sale_sheet'] is Map
          ? Map<String, dynamic>.from(json['post_sale_sheet'] as Map)
          : null,
    );
  }

  final String id;
  final String orderNumber;
  final String? customerId;
  final String customerName;
  final String customerPhone;
  final String customerEmail;
  final String equipmentName;
  final String brandModel;
  final String serialNumber;
  final String reportedDefect;
  final String technicalDiagnosis;
  final List<Map<String, dynamic>> partsUsed;
  final double partsTotal;
  final double laborCost;
  final double discount;
  final double totalAmount;
  final String status;
  final String statusLabel;
  final String priority;
  final String warrantyPeriod;
  final String warrantyTerms;
  final String? technicianId;
  final String? notes;
  final Map<String, dynamic> extraAttributes;
  final double taxAmount;
  final double taxRate;
  final bool isTaxInclusive;
  final Map<String, dynamic> taxBreakdown;
  final String? saleId;
  final String? invoiceNumber;
  final bool showPostSaleSheet;
  final Map<String, dynamic>? postSaleSheet;
}
