import 'package:flutter/material.dart';

const kDiningTableStatuses = ['available', 'occupied', 'reserved', 'billed'];

const kDiningTableStatusLabels = {
  'available': 'Available',
  'occupied': 'Occupied',
  'reserved': 'Reserved',
  'billed': 'Billed',
};

const kDiningTableStatusColors = {
  'available': Colors.green,
  'occupied': Colors.redAccent,
  'reserved': Colors.amber,
  'billed': Colors.blueAccent,
};

const kKotStatuses = ['pending', 'preparing', 'ready', 'served', 'cancelled'];

const kKotStatusLabels = {
  'pending': 'Pending',
  'preparing': 'Preparing',
  'ready': 'Ready',
  'served': 'Served',
  'cancelled': 'Cancelled',
};

/// A dining table, as returned by RestaurantApiController::presentTable().
class DiningTableModel {
  DiningTableModel({
    required this.id,
    required this.diningFloorId,
    required this.floorName,
    required this.tableNumber,
    required this.seatingCapacity,
    required this.status,
    required this.currentSaleId,
    required this.guestCount,
    this.qrToken,
    this.qrOrderUrl,
  });

  factory DiningTableModel.fromJson(Map<String, dynamic> json) {
    return DiningTableModel(
      id: json['id'].toString(),
      diningFloorId: json['dining_floor_id']?.toString(),
      floorName: json['floor_name'] as String?,
      tableNumber: json['table_number'] as String? ?? '',
      seatingCapacity: (json['seating_capacity'] as num?)?.toInt() ?? 0,
      status: json['status'] as String? ?? 'available',
      currentSaleId: json['current_sale_id']?.toString(),
      guestCount: (json['guest_count'] as num?)?.toInt() ?? 0,
      qrToken: json['qr_token'] as String?,
      qrOrderUrl: json['qr_order_url'] as String?,
    );
  }

  final String id;
  final String? diningFloorId;
  final String? floorName;
  final String tableNumber;
  final int seatingCapacity;
  final String status;
  final String? currentSaleId;
  final int guestCount;
  final String? qrToken;
  final String? qrOrderUrl;

  Color get statusColor => kDiningTableStatusColors[status] ?? Colors.grey;
  String get statusLabel => kDiningTableStatusLabels[status] ?? status;
}

/// A dining floor/area with its nested tables, as returned by
/// RestaurantApiController::presentFloor().
class DiningFloorModel {
  DiningFloorModel({required this.id, required this.name, required this.orderIndex, required this.tables});

  factory DiningFloorModel.fromJson(Map<String, dynamic> json) {
    return DiningFloorModel(
      id: json['id'].toString(),
      name: json['name'] as String? ?? '',
      orderIndex: (json['order_index'] as num?)?.toInt() ?? 0,
      tables: (json['tables'] as List? ?? [])
          .map((e) => DiningTableModel.fromJson(e as Map<String, dynamic>))
          .toList(),
    );
  }

  final String id;
  final String name;
  final int orderIndex;
  final List<DiningTableModel> tables;
}

/// One line item within a restaurant order/sale, mirroring the item shape
/// built by RestaurantApiController::sendToKitchen().
class RestaurantOrderItemModel {
  RestaurantOrderItemModel({
    this.id,
    this.productId,
    required this.name,
    required this.price,
    double? basePrice,
    required this.quantity,
    this.variant,
    this.modifiers = const [],
    this.spiceLevel,
    this.note = '',
    this.seat = 1,
  }) : basePrice = basePrice ?? price;

  factory RestaurantOrderItemModel.fromJson(Map<String, dynamic> json) {
    return RestaurantOrderItemModel(
      id: json['id']?.toString(),
      productId: json['product_id']?.toString(),
      name: json['name'] as String? ?? '',
      price: (json['price'] as num?)?.toDouble() ?? 0,
      basePrice: (json['base_price'] as num?)?.toDouble(),
      quantity: (json['quantity'] as num?)?.toDouble() ?? 1,
      variant: json['variant'] as String?,
      modifiers: (json['modifiers'] as List? ?? []).cast<Map<String, dynamic>>(),
      spiceLevel: json['spice_level'] as String?,
      note: json['note'] as String? ?? '',
      seat: (json['seat'] as num?)?.toInt() ?? 1,
    );
  }

  final String? id;
  final String? productId;
  final String name;
  final double price;
  final double basePrice;
  final double quantity;
  final String? variant;
  final List<Map<String, dynamic>> modifiers;
  final String? spiceLevel;
  final String note;
  final int seat;

  double get lineTotal => price * quantity;

  /// True once the price has been changed away from [basePrice] via a
  /// cashier's inline price override, mirroring Pos.php's is_overridden flag.
  bool get isOverridden => (price - basePrice).abs() > 0.001;

  /// Returns a copy with the given fields replaced. [basePrice] is always
  /// carried over from this instance unless explicitly passed, so bumping
  /// quantity or seat never resets the audit trail for a price override.
  RestaurantOrderItemModel copyWith({
    double? price,
    double? quantity,
    int? seat,
  }) {
    return RestaurantOrderItemModel(
      id: id,
      productId: productId,
      name: name,
      price: price ?? this.price,
      basePrice: basePrice,
      quantity: quantity ?? this.quantity,
      variant: variant,
      modifiers: modifiers,
      spiceLevel: spiceLevel,
      note: note,
      seat: seat ?? this.seat,
    );
  }

  Map<String, dynamic> toRequestJson() => {
        if (productId != null) 'product_id': productId,
        'name': name,
        'price': price,
        'base_price': basePrice,
        'quantity': quantity,
        if (variant != null) 'variant': variant,
        if (modifiers.isNotEmpty) 'modifiers': modifiers,
        if (spiceLevel != null) 'spice_level': spiceLevel,
        if (note.isNotEmpty) 'note': note,
        'seat': seat,
      };
}

/// A restaurant order/sale, as returned by RestaurantApiController::presentSale().
class RestaurantSaleModel {
  RestaurantSaleModel({
    required this.id,
    required this.saleNumber,
    this.customerId,
    required this.customerName,
    this.customerPhone,
    this.customerEmail,
    required this.status,
    required this.serviceType,
    this.diningTableId,
    this.tableName,
    required this.guestCount,
    required this.items,
    required this.discount,
    required this.total,
    required this.paidAmount,
    required this.dueAmount,
    required this.paymentStatus,
    this.paymentMethod,
    this.kotStatus,
    this.notes,
  });

  factory RestaurantSaleModel.fromJson(Map<String, dynamic> json) {
    return RestaurantSaleModel(
      id: json['id'].toString(),
      saleNumber: json['sale_number'] as String? ?? '',
      customerId: json['customer_id']?.toString(),
      customerName: json['customer_name'] as String? ?? '',
      customerPhone: json['customer_phone'] as String?,
      customerEmail: json['customer_email'] as String?,
      status: json['status'] as String? ?? 'pending',
      serviceType: json['service_type'] as String? ?? 'dine_in',
      diningTableId: json['dining_table_id']?.toString(),
      tableName: json['table_name'] as String?,
      guestCount: (json['guest_count'] as num?)?.toInt() ?? 0,
      items: (json['items'] as List? ?? [])
          .map((e) => RestaurantOrderItemModel.fromJson(Map<String, dynamic>.from(e as Map)))
          .toList(),
      discount: (json['discount'] as num?)?.toDouble() ?? 0,
      total: (json['total'] as num?)?.toDouble() ?? 0,
      paidAmount: (json['paid_amount'] as num?)?.toDouble() ?? 0,
      dueAmount: (json['due_amount'] as num?)?.toDouble() ?? 0,
      paymentStatus: json['payment_status'] as String? ?? 'pending',
      paymentMethod: json['payment_method'] as String?,
      kotStatus: json['kot_status'] as String?,
      notes: json['notes'] as String?,
    );
  }

  final String id;
  final String saleNumber;
  final String? customerId;
  final String customerName;
  final String? customerPhone;
  final String? customerEmail;
  final String status;
  final String serviceType;
  final String? diningTableId;
  final String? tableName;
  final int guestCount;
  final List<RestaurantOrderItemModel> items;
  final double discount;
  final double total;
  final double paidAmount;
  final double dueAmount;
  final String paymentStatus;
  final String? paymentMethod;
  final String? kotStatus;
  final String? notes;
}

/// A kitchen order ticket, as returned by RestaurantApiController::presentKot().
class KitchenTicketModel {
  KitchenTicketModel({
    required this.id,
    required this.saleId,
    required this.kotNumber,
    this.diningTableId,
    this.tableName,
    required this.serviceType,
    required this.status,
    this.serverName,
    required this.items,
    this.kitchenNotes,
    required this.elapsedMinutes,
    this.preparedAt,
    this.readyAt,
    this.servedAt,
    this.createdAt,
    this.prepMinutes,
    this.targetCompletionAt,
    this.isOverdue = false,
  });

  factory KitchenTicketModel.fromJson(Map<String, dynamic> json) {
    return KitchenTicketModel(
      id: json['id'].toString(),
      saleId: json['sale_id']?.toString() ?? '',
      kotNumber: json['kot_number'] as String? ?? '',
      diningTableId: json['dining_table_id']?.toString(),
      tableName: json['table_name'] as String?,
      serviceType: json['service_type'] as String? ?? 'dine_in',
      status: json['status'] as String? ?? 'pending',
      serverName: json['server_name'] as String?,
      items: (json['items'] as List? ?? [])
          .map((e) => RestaurantOrderItemModel.fromJson(Map<String, dynamic>.from(e as Map)))
          .toList(),
      kitchenNotes: json['kitchen_notes'] as String?,
      elapsedMinutes: (json['elapsed_minutes'] as num?)?.toInt() ?? 0,
      preparedAt: json['prepared_at'] != null ? DateTime.tryParse(json['prepared_at'] as String) : null,
      readyAt: json['ready_at'] != null ? DateTime.tryParse(json['ready_at'] as String) : null,
      servedAt: json['served_at'] != null ? DateTime.tryParse(json['served_at'] as String) : null,
      createdAt: json['created_at'] != null ? DateTime.tryParse(json['created_at'] as String) : null,
      prepMinutes: (json['prep_minutes'] as num?)?.toInt(),
      targetCompletionAt: json['target_completion_at'] != null ? DateTime.tryParse(json['target_completion_at'] as String) : null,
      isOverdue: json['is_overdue'] as bool? ?? false,
    );
  }

  final String id;
  final String saleId;
  final String kotNumber;
  final String? diningTableId;
  final String? tableName;
  final String serviceType;
  final String status;
  final String? serverName;
  final List<RestaurantOrderItemModel> items;
  final String? kitchenNotes;
  final int elapsedMinutes;
  final DateTime? preparedAt;
  final DateTime? readyAt;
  final DateTime? servedAt;
  final DateTime? createdAt;
  final int? prepMinutes;
  final DateTime? targetCompletionAt;
  final bool isOverdue;

  String get statusLabel => kKotStatusLabels[status] ?? status;
}

/// Tenant-configured KDS/dashboard order alert preferences (Settings >
/// Notifications > Restaurant Order Alerts), returned alongside the KOT list
/// so the KDS screen doesn't need a second round-trip to fetch them.
class KdsAlertSettings {
  const KdsAlertSettings({required this.intervalMinutes, required this.soundPreset, required this.soundUrl});

  factory KdsAlertSettings.fromJson(Map<String, dynamic>? json) {
    return KdsAlertSettings(
      intervalMinutes: (json?['interval_minutes'] as num?)?.toInt() ?? 3,
      soundPreset: json?['sound_preset'] as String? ?? 'chime',
      soundUrl: json?['sound_url'] as String? ?? '',
    );
  }

  final int intervalMinutes;
  final String soundPreset;
  final String soundUrl;
}
