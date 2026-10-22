class Order {
  final String id;
  final String? assignmentId;
  final String restaurantId;
  final String restaurantName;
  final String customerAddress;
  final double totalAmount;
  final String status;
  final String paymentMode;
  final String? paymentStatus;
  final double subtotal;
  final double taxAmount;
  final double deliveryFee;
  final int? coinsEarned;
  final String? acceptedAt;
  final int prepTimeMinutes;
  final String? prepDeadlineAt;
  final String? readyMarkedAt;
  final int? prepDelayMinutes;
  final String? riderArrivedAt;
  final String? riderAtCustomerAt;
  final int? handoverDelayMinutes;
  final bool riderAssigned;
  final String? riderName;
  final String? riderPhone;
  final double? riderDistanceToRestaurant;
  final bool riderArrived;
  final String? handoverDeadlineAt;
  final String? deliveryOtp;
  final String? createdAt;
  final bool isBusinessOrder;
  final String? companyName;
  final String? companyGst;
  final String? customerName;
  final String? customerPhone;
  final int itemsCount;
  final String itemSummary;
  final List<dynamic> rawItems;

  // Rider notification: distance + ETA info
  final String? restaurantAddress;
  final double? riderToRestaurantDistanceKm;
  final int? riderToRestaurantDistanceM;
  final int? riderToRestaurantEtaMin;
  final double? restaurantToCustomerDistanceKm;
  final int? restaurantToCustomerDistanceM;
  final int? restaurantToCustomerEtaMin;
  final double? restaurantLatitude;
  final double? restaurantLongitude;
  final double? customerLatitude;
  final double? customerLongitude;

  Order({
    required this.id,
    this.assignmentId,
    required this.restaurantId,
    required this.restaurantName,
    required this.customerAddress,
    required this.totalAmount,
    required this.status,
    this.paymentMode = 'cod',
    this.paymentStatus,
    this.subtotal = 0,
    this.taxAmount = 0,
    this.deliveryFee = 0,
    this.coinsEarned,
    this.acceptedAt,
    this.prepTimeMinutes = 0,
    this.prepDeadlineAt,
    this.readyMarkedAt,
    this.prepDelayMinutes,
    this.riderArrivedAt,
    this.riderAtCustomerAt,
    this.handoverDelayMinutes,
    this.riderAssigned = false,
    this.riderName,
    this.riderPhone,
    this.riderDistanceToRestaurant,
    this.riderArrived = false,
    this.handoverDeadlineAt,
    this.deliveryOtp,
    this.createdAt,
    this.isBusinessOrder = false,
    this.companyName,
    this.companyGst,
    this.customerName,
    this.customerPhone,
    this.itemsCount = 0,
    this.itemSummary = '',
    this.rawItems = const [],
    this.restaurantAddress,
    this.riderToRestaurantDistanceKm,
    this.riderToRestaurantDistanceM,
    this.riderToRestaurantEtaMin,
    this.restaurantToCustomerDistanceKm,
    this.restaurantToCustomerDistanceM,
    this.restaurantToCustomerEtaMin,
    this.restaurantLatitude,
    this.restaurantLongitude,
    this.customerLatitude,
    this.customerLongitude,
  });

  factory Order.fromJson(Map<String, dynamic> json) {
    // Restaurant name: either from relationship or direct field
    String restaurantName = 'Restaurant';
    if (json['restaurant'] != null && json['restaurant']['name'] != null) {
      restaurantName = json['restaurant']['name'];
    } else if (json['restaurant_name'] != null) {
      restaurantName = json['restaurant_name'];
    }

    // Address: may come from relationship or be absent
    String customerAddress = 'Your address';
    if (json['delivery_address_text'] != null && json['delivery_address_text'].toString().isNotEmpty) {
      customerAddress = json['delivery_address_text'];
    } else if (json['address'] != null) {
      final addr = json['address'];
      customerAddress = [
        addr['flat_building'],
        addr['street'],
        addr['city'],
      ].where((s) => s != null && s.toString().isNotEmpty).join(', ');
    } else if (json['customer_address'] != null) {
      customerAddress = json['customer_address'];
    }

    // Total: backend uses grand_total
    final total = _parseDouble(json['grand_total'] ?? json['total_amount'] ?? 0);

    return Order(
      id: json['id'].toString(),
      restaurantId: json['restaurant_id']?.toString() ?? '',
      restaurantName: restaurantName,
      customerAddress: customerAddress,
      totalAmount: total,
      status: json['status'] ?? 'pending',
      paymentMode: json['payment_mode'] ?? 'cod',
      paymentStatus: json['payment_status'],
      subtotal: _parseDouble(json['subtotal'] ?? 0),
      taxAmount: _parseDouble(json['tax_amount'] ?? 0),
      deliveryFee: _parseDouble(json['delivery_fee'] ?? json['delivery_fee_customer'] ?? 0),
      coinsEarned: json['coins_earned'],
      acceptedAt: json['accepted_at'],
      prepTimeMinutes: json['prep_time_minutes'] ?? 0,
      prepDeadlineAt: json['prep_deadline_at'],
      readyMarkedAt: json['ready_marked_at'],
      prepDelayMinutes: json['prep_delay_minutes'],
      riderArrivedAt: json['rider_arrived_at'],
      riderAtCustomerAt: json['rider_at_customer_at'],
      handoverDelayMinutes: json['handover_delay_minutes'],
      riderAssigned: json['rider_assigned'] == 1 || json['rider_assigned'] == true,
      riderName: json['rider_name'],
      riderPhone: json['rider_phone'],
      riderDistanceToRestaurant: json['rider_distance_to_restaurant'] != null
          ? double.tryParse(json['rider_distance_to_restaurant'].toString())
          : null,
      riderArrived: json['rider_arrived'] == 1 || json['rider_arrived'] == true,
      handoverDeadlineAt: json['handover_deadline_at'],
      deliveryOtp: json['delivery_otp'] != null ? json['delivery_otp'].toString() : null,
      createdAt: json['created_at'],
      isBusinessOrder: json['is_business_order'] == 1 || json['is_business_order'] == true,
      companyName: json['company_name'] as String?,
      companyGst: json['company_gst'] as String?,
      customerName: json['user']?['name'] ?? json['customer_name'] as String?,
      customerPhone: json['user']?['phone'] ?? json['customer_phone'] as String?,
      itemsCount: json['items'] != null && json['items'] is List ? (json['items'] as List).length : 0,
      itemSummary: json['items'] != null && json['items'] is List
          ? (json['items'] as List).map((item) {
              final name = item['menu_item']?['name'] ?? item['menu_item_name'] ?? 'Item';
              final qty = item['quantity'] ?? 1;
              final variant = item['variant_snapshot'] is Map ? item['variant_snapshot']['label'] : null;
              final addons = item['addons_snapshot'] is List
                  ? (item['addons_snapshot'] as List).map((a) => a is Map ? a['name'] : '').where((s) => s.isNotEmpty).join(', ')
                  : null;
              String summary = '$name x$qty';
              if (variant != null) summary += ' ($variant)';
              if (addons != null && addons.isNotEmpty) summary += ' + $addons';
              return summary;
            }).join(', ')
          : '',
      rawItems: json['items'] != null && json['items'] is List ? json['items'] as List : [],
      restaurantAddress: json['restaurant_address'] ?? json['pickup_address'] as String?,
      riderToRestaurantDistanceKm: _parseDoubleNullable(json['rider_to_restaurant_distance_km'] ?? json['distance_eta']?['rider_to_restaurant_distance_km']),
      riderToRestaurantDistanceM: _parseIntNullable(json['rider_to_restaurant_distance_m'] ?? json['distance_eta']?['rider_to_restaurant_distance_m']),
      riderToRestaurantEtaMin: _parseIntNullable(json['rider_to_restaurant_eta_min'] ?? json['distance_eta']?['rider_to_restaurant_eta_min']),
      restaurantToCustomerDistanceKm: _parseDoubleNullable(json['restaurant_to_customer_distance_km'] ?? json['distance_eta']?['restaurant_to_customer_distance_km']),
      restaurantToCustomerDistanceM: _parseIntNullable(json['restaurant_to_customer_distance_m'] ?? json['distance_eta']?['restaurant_to_customer_distance_m']),
      restaurantToCustomerEtaMin: _parseIntNullable(json['restaurant_to_customer_eta_min'] ?? json['distance_eta']?['restaurant_to_customer_eta_min']),
      restaurantLatitude: _toDoubleNullable(json['restaurant']?['latitude']),
      restaurantLongitude: _toDoubleNullable(json['restaurant']?['longitude']),
      customerLatitude: _toDoubleNullable(json['delivery_address_lat']),
      customerLongitude: _toDoubleNullable(json['delivery_address_lng']),
    );
  }

  static double _parseDouble(dynamic value) {
    if (value == null) return 0.0;
    if (value is num) return value.toDouble();
    return double.tryParse(value.toString()) ?? 0.0;
  }

  static double? _parseDoubleNullable(dynamic value) {
    if (value == null) return null;
    if (value is num) return value.toDouble();
    return double.tryParse(value.toString());
  }

  static int? _parseIntNullable(dynamic value) {
    if (value == null) return null;
    if (value is int) return value;
    if (value is num) return value.toInt();
    return int.tryParse(value.toString());
  }

  static double? _toDoubleNullable(dynamic value) {
    if (value == null) return null;
    if (value is num) return value.toDouble();
    return double.tryParse(value.toString());
  }
}
