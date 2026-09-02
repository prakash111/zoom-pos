/// A quotation, as returned by GET/POST /quotations (QuotationApiController).
class QuotationModel {
  QuotationModel({
    required this.id,
    required this.quoteNumber,
    required this.customerId,
    required this.customerName,
    this.customerPhone,
    this.customerEmail,
    required this.items,
    required this.discount,
    required this.tax,
    required this.total,
    required this.notes,
    required this.terms,
    required this.validUntil,
    required this.status,
    required this.updatedAt,
  });

  factory QuotationModel.fromJson(Map<String, dynamic> json) {
    return QuotationModel(
      id: json['id'].toString(),
      quoteNumber: json['quote_number'] as String? ?? '',
      customerId: json['customer_id']?.toString(),
      customerName: json['customer_name'] as String? ?? 'Customer',
      customerPhone: json['customer_phone'] as String?,
      customerEmail: json['customer_email'] as String?,
      items: (json['items'] as List? ?? []).cast<Map<String, dynamic>>(),
      discount: (json['discount'] as num?)?.toDouble() ?? 0,
      tax: (json['tax'] as num?)?.toDouble() ?? 0,
      total: (json['total'] as num?)?.toDouble() ?? 0,
      notes: json['notes'] as String? ?? '',
      terms: json['terms'] as String? ?? '',
      validUntil: DateTime.tryParse(json['valid_until'] as String? ?? ''),
      status: json['status'] as String? ?? 'draft',
      updatedAt: DateTime.tryParse(json['updated_at'] as String? ?? ''),
    );
  }

  final String id;
  final String quoteNumber;
  final String? customerId;
  final String customerName;
  final String? customerPhone;
  final String? customerEmail;
  final List<Map<String, dynamic>> items;
  final double discount;
  final double tax;
  final double total;
  final String notes;
  final String terms;
  final DateTime? validUntil;
  final String status;
  final DateTime? updatedAt;

  bool get isConverted => status == 'converted';
  double get subtotal => items.fold(0.0, (sum, item) {
        final qty = (item['quantity'] as num?)?.toDouble() ?? 0;
        final price = (item['price'] as num?)?.toDouble() ?? 0;
        return sum + (qty * price);
      });
}
