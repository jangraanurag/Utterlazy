class Rider {
  final String id;
  final String name;
  final String email;
  final String? phone;
  final String? vehicleType;
  final String? vehicleNumber;
  final bool isOnline;
  final bool isVerified;
  final double avgRating;
  final int totalDeliveries;
  final double totalEarnings;

  Rider({
    required this.id,
    required this.name,
    required this.email,
    this.phone,
    this.vehicleType,
    this.vehicleNumber,
    this.isOnline = false,
    this.isVerified = false,
    this.avgRating = 0,
    this.totalDeliveries = 0,
    this.totalEarnings = 0,
  });

  factory Rider.fromJson(Map<String, dynamic> json) {
    return Rider(
      id: json['id'].toString(),
      name: json['name'] ?? '',
      email: json['email'] ?? '',
      phone: json['phone'],
      vehicleType: json['vehicle_type'],
      vehicleNumber: json['vehicle_number'],
      isOnline: json['is_online'] ?? false,
      isVerified: json['is_verified'] ?? false,
      avgRating: (json['avg_rating'] ?? 0).toDouble(),
      totalDeliveries: json['total_deliveries'] ?? 0,
      totalEarnings: (json['total_earnings'] ?? 0).toDouble(),
    );
  }
}
