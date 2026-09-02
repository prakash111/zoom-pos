import '../../core/api/api_client.dart';
import '../../core/config/app_config.dart';
import '../../core/models/restaurant_models.dart';

/// Talks to RestaurantApiController: floors/tables CRUD, send-to-kitchen,
/// settle bill, and the Kitchen Display System (KOT) endpoints.
class RestaurantRepository {
  RestaurantRepository(this._client);

  final ApiClient _client;

  Future<List<DiningFloorModel>> fetchFloors() async {
    final response = await _client.get(ApiEndpoints.restaurantFloors);
    return (response['floors'] as List? ?? [])
        .map((e) => DiningFloorModel.fromJson(e as Map<String, dynamic>))
        .toList();
  }

  Future<DiningFloorModel> saveFloor({String? id, required String name, int? orderIndex}) async {
    final data = {
      'name': name,
      if (orderIndex != null) 'order_index': orderIndex,
    };
    final response = id == null
        ? await _client.post(ApiEndpoints.restaurantFloors, data: data)
        : await _client.put(ApiEndpoints.restaurantFloor(id), data: data);
    return DiningFloorModel.fromJson(response['floor'] as Map<String, dynamic>);
  }

  Future<void> deleteFloor(String id) => _client.delete(ApiEndpoints.restaurantFloor(id));

  Future<DiningTableModel> saveTable({
    String? id,
    required String tableNumber,
    String? diningFloorId,
    required int seatingCapacity,
    String? status,
  }) async {
    final data = {
      'table_number': tableNumber,
      if (diningFloorId != null) 'dining_floor_id': diningFloorId,
      'seating_capacity': seatingCapacity,
      if (status != null) 'status': status,
    };
    final response = id == null
        ? await _client.post(ApiEndpoints.restaurantTables, data: data)
        : await _client.put(ApiEndpoints.restaurantTable(id), data: data);
    return DiningTableModel.fromJson(response['table'] as Map<String, dynamic>);
  }

  Future<DiningTableModel> setTableStatus(String id, String status) async {
    final response = await _client.post(ApiEndpoints.restaurantTableStatus(id), data: {'status': status});
    return DiningTableModel.fromJson(response['table'] as Map<String, dynamic>);
  }

  Future<void> deleteTable(String id) => _client.delete(ApiEndpoints.restaurantTable(id));

  Future<({DiningTableModel table, RestaurantSaleModel? openOrder})> fetchTable(String id) async {
    final response = await _client.get(ApiEndpoints.restaurantTable(id));
    return (
      table: DiningTableModel.fromJson(response['table'] as Map<String, dynamic>),
      openOrder: response['open_order'] is Map
          ? RestaurantSaleModel.fromJson(Map<String, dynamic>.from(response['open_order'] as Map))
          : null,
    );
  }

  Future<({RestaurantSaleModel sale, KitchenTicketModel kot})> sendToKitchen({
    required String serviceType,
    String? tableId,
    String? saleId,
    int? guestCount,
    String? customerName,
    String? customerPhone,
    String? pickupTime,
    String? deliveryAddress,
    String? driverName,
    String? driverPhone,
    double? discount,
    String? notes,
    int? prepMinutes,
    required List<RestaurantOrderItemModel> items,
  }) async {
    final data = {
      'service_type': serviceType,
      if (tableId != null) 'table_id': tableId,
      if (saleId != null) 'sale_id': saleId,
      if (guestCount != null) 'guest_count': guestCount,
      if (customerName != null && customerName.isNotEmpty) 'customer_name': customerName,
      if (customerPhone != null && customerPhone.isNotEmpty) 'customer_phone': customerPhone,
      if (pickupTime != null && pickupTime.isNotEmpty) 'pickup_time': pickupTime,
      if (deliveryAddress != null && deliveryAddress.isNotEmpty) 'delivery_address': deliveryAddress,
      if (driverName != null && driverName.isNotEmpty) 'driver_name': driverName,
      if (driverPhone != null && driverPhone.isNotEmpty) 'driver_phone': driverPhone,
      if (discount != null) 'discount': discount,
      if (notes != null && notes.isNotEmpty) 'notes': notes,
      if (prepMinutes != null) 'prep_minutes': prepMinutes,
      'items': items.map((e) => e.toRequestJson()).toList(),
    };

    final response = await _client.post(ApiEndpoints.restaurantSendToKitchen, data: data);
    return (
      sale: RestaurantSaleModel.fromJson(response['sale'] as Map<String, dynamic>),
      kot: KitchenTicketModel.fromJson(response['kot'] as Map<String, dynamic>),
    );
  }

  Future<RestaurantSaleModel> settle({
    required String saleId,
    String? paymentMethod,
    double? discount,
    String? notes,
    double? cashTendered,
    bool isSplitPayment = false,
    List<Map<String, dynamic>>? splitPayments,
    String? dueDate,
    String? customerId,
  }) async {
    final data = {
      if (paymentMethod != null) 'payment_method': paymentMethod,
      if (discount != null) 'discount': discount,
      if (notes != null && notes.isNotEmpty) 'notes': notes,
      if (cashTendered != null) 'cash_tendered': cashTendered,
      'is_split_payment': isSplitPayment,
      if (splitPayments != null) 'split_payments': splitPayments,
      if (dueDate != null) 'due_date': dueDate,
      if (customerId != null) 'customer_id': int.tryParse(customerId),
    };
    final response = await _client.post(ApiEndpoints.restaurantSettle(saleId), data: data);
    return RestaurantSaleModel.fromJson(response['sale'] as Map<String, dynamic>);
  }

  Future<({List<KitchenTicketModel> tickets, List<KitchenTicketModel> completedTickets, Map<String, int> counts, KdsAlertSettings alertSettings})>
      fetchKot({
    String? status,
    String? serviceType,
  }) async {
    final response = await _client.get(ApiEndpoints.restaurantKot, query: {
      if (status != null && status.isNotEmpty) 'status': status,
      if (serviceType != null && serviceType.isNotEmpty) 'service_type': serviceType,
    });
    return (
      tickets: (response['tickets'] as List? ?? []).map((e) => KitchenTicketModel.fromJson(e as Map<String, dynamic>)).toList(),
      completedTickets:
          (response['completed_tickets'] as List? ?? []).map((e) => KitchenTicketModel.fromJson(e as Map<String, dynamic>)).toList(),
      counts: (response['counts'] as Map<String, dynamic>? ?? {}).map((k, v) => MapEntry(k, (v as num).toInt())),
      alertSettings: KdsAlertSettings.fromJson(response['alert_settings'] as Map<String, dynamic>?),
    );
  }

  Future<KitchenTicketModel> updateKotStatus(String id, String status) async {
    final response = await _client.post(ApiEndpoints.restaurantKotStatus(id), data: {'status': status});
    return KitchenTicketModel.fromJson(response['kot'] as Map<String, dynamic>);
  }
}
