/// A single row from GET /customers/{id}/ledger — either a `type: 'invoice'`
/// (a sale, with total/paid/due) or a `type: 'payment'` (money applied
/// against one or more invoices, with a flat [amount]). See
/// PosSyncApiController::customerLedger.
class LedgerEntryModel {
  LedgerEntryModel({
    required this.type,
    required this.id,
    required this.saleNumber,
    required this.date,
    this.total,
    this.paidAmount,
    this.dueAmount,
    this.amount,
    this.paymentMethod,
    this.status,
    this.itemsCount,
    this.reference,
    this.notes,
  });

  factory LedgerEntryModel.fromJson(Map<String, dynamic> json) {
    return LedgerEntryModel(
      type: json['type'] as String? ?? 'invoice',
      id: json['id'].toString(),
      saleNumber: json['sale_number'] as String? ?? '',
      date: DateTime.tryParse(json['date'] as String? ?? ''),
      total: (json['total'] as num?)?.toDouble(),
      paidAmount: (json['paid_amount'] as num?)?.toDouble(),
      dueAmount: (json['due_amount'] as num?)?.toDouble(),
      amount: (json['amount'] as num?)?.toDouble(),
      paymentMethod: json['payment_method'] as String?,
      status: json['status'] as String?,
      itemsCount: (json['items_count'] as num?)?.toInt(),
      reference: json['reference'] as String?,
      notes: json['notes'] as String?,
    );
  }

  final String type;
  final String id;
  final String saleNumber;
  final DateTime? date;
  final double? total;
  final double? paidAmount;
  final double? dueAmount;
  final double? amount;
  final String? paymentMethod;
  final String? status;
  final int? itemsCount;
  final String? reference;
  final String? notes;

  bool get isInvoice => type == 'invoice';
}
