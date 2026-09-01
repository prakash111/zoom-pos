/// One tender row in a checkout — either the sole payment for a simple
/// (non-split) sale, or one row of a multi-method split payment.
class PaymentEntry {
  PaymentEntry({
    required this.methodCode,
    required this.amount,
    this.tendered,
    this.changeReturned = 0,
    this.referenceNo,
  });

  String methodCode;
  double amount;
  double? tendered;
  double changeReturned;
  String? referenceNo;

  Map<String, dynamic> toJson() {
    return {
      'payment_method': methodCode,
      'amount': amount,
      if (tendered != null) 'tendered': tendered,
      'change_returned': changeReturned,
      if (referenceNo != null && referenceNo!.isNotEmpty) 'reference_number': referenceNo,
    };
  }
}
