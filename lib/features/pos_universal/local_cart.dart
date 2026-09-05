import 'package:flutter/foundation.dart';

/// One line in the lightweight cart [LocalCart] keeps client-side. Unlike
/// Retail's `CartItem` (tied to `ProductModel`), this mirrors the generic
/// catalog item shape the `pos_screen` JSON contract emits, so it works for
/// any module (pharmacy batch, repair part, ...) without a typed model per
/// vertical.
class LocalCartLine {
  LocalCartLine({
    required this.id,
    this.batchId,
    required this.title,
    this.subtitle,
    required this.unitPrice,
    required this.quantity,
    this.maxQuantity,
  });

  /// Product id as sent by the backend — kept as-is (int or string) so it
  /// round-trips unchanged into the checkout payload.
  final dynamic id;
  final dynamic batchId;
  final String title;
  final String? subtitle;
  final double unitPrice;
  int quantity;
  final int? maxQuantity;

  double get lineTotal => unitPrice * quantity;

  /// Distinguishes the same product added with different batches.
  String get lineKey => '$id::${batchId ?? ''}';

  Map<String, dynamic> toApiItem() => {
        'product_id': id,
        if (batchId != null) 'batch_id': batchId,
        'quantity': quantity,
        'unit_price': unitPrice,
      };

  Map<String, dynamic> toPreview() => {
        'title': title,
        'qty': quantity,
        'price': unitPrice,
      };
}

/// Client-side cart for [UniversalPosScreen]. Deliberately minimal: no
/// discount/payment/split-payment state lives here — all of that is
/// rendered server-side in the checkout sheet (see
/// `PosScreenBuilder::checkoutSheet` on the backend). This only tracks
/// what's needed to drive the floating cart bar and to build the
/// authoritative `items` array merged into the checkout form on submit.
class LocalCart extends ChangeNotifier {
  final Map<String, LocalCartLine> _lines = {};

  List<LocalCartLine> get lines => _lines.values.toList(growable: false);

  int get count => _lines.values.fold(0, (sum, l) => sum + l.quantity);

  double get total => _lines.values.fold(0.0, (sum, l) => sum + l.lineTotal);

  bool get isEmpty => _lines.isEmpty;

  void add({
    required dynamic id,
    dynamic batchId,
    required String title,
    String? subtitle,
    required double unitPrice,
    int quantity = 1,
    int? maxQuantity,
  }) {
    final key = '$id::${batchId ?? ''}';
    final existing = _lines[key];
    if (existing != null) {
      existing.quantity = _clamp(existing.quantity + quantity, maxQuantity);
    } else {
      _lines[key] = LocalCartLine(
        id: id,
        batchId: batchId,
        title: title,
        subtitle: subtitle,
        unitPrice: unitPrice,
        quantity: _clamp(quantity, maxQuantity),
        maxQuantity: maxQuantity,
      );
    }
    notifyListeners();
  }

  void updateQuantity(String lineKey, int quantity) {
    final line = _lines[lineKey];
    if (line == null) return;
    if (quantity <= 0) {
      _lines.remove(lineKey);
    } else {
      line.quantity = _clamp(quantity, line.maxQuantity);
    }
    notifyListeners();
  }

  static int _clamp(int value, int? max) =>
      max == null ? value : value.clamp(1, max).toInt();

  void remove(String lineKey) {
    if (_lines.remove(lineKey) != null) notifyListeners();
  }

  void clear() {
    if (_lines.isEmpty) return;
    _lines.clear();
    notifyListeners();
  }

  List<Map<String, dynamic>> toApiItems() =>
      _lines.values.map((l) => l.toApiItem()).toList();

  List<Map<String, dynamic>> toPreview() =>
      _lines.values.map((l) => l.toPreview()).toList();
}
