import '../utils/url_utils.dart';

class Restaurant {
  final String id;
  final String name;
  final String? description;
  final String? logoUrl;
  final double rating;
  final String deliveryTime;
  final List<String> tags;
  final double? latitude;
  final double? longitude;
  final String? gstNumber;
  final bool isAcceptingOrders;
  final bool isSponsored;
  final double? rankingScore;
  final double? distanceKm;
  final int? avgPrepTimeMinutes;
  final int? totalOrdersCompleted;
  final Map<String, dynamic>? subScores;

  Restaurant({
    required this.id,
    required this.name,
    this.description,
    String? logoUrl,
    this.rating = 0.0,
    this.deliveryTime = '30-40 min',
    this.tags = const [],
    this.latitude,
    this.longitude,
    this.gstNumber,
    this.isAcceptingOrders = true,
    this.isSponsored = false,
    this.rankingScore,
    this.distanceKm,
    this.avgPrepTimeMinutes,
    this.totalOrdersCompleted,
    this.subScores,
  }) : logoUrl = sanitizeImageUrl(logoUrl);

  factory Restaurant.fromJson(Map<String, dynamic> json) {
    final prepMin = json['avg_prep_time_minutes'] != null
        ? int.tryParse(json['avg_prep_time_minutes'].toString())
        : null;

    return Restaurant(
      id: json['id'].toString(),
      name: json['name'] as String,
      description: json['description'] as String?,
      logoUrl: json['logo_url'] as String?,
      rating: double.tryParse(json['rating']?.toString() ?? '') ?? double.tryParse(json['avg_rating']?.toString() ?? '') ?? 0.0,
      deliveryTime: prepMin != null ? '$prepMin-${prepMin + 10} min' : (json['delivery_time'] as String? ?? json['delivery_time_minutes']?.toString() ?? '30-40 min'),
      tags: json['tags'] != null ? List<String>.from(json['tags']) : (json['cuisine_type']?.toString().split(',').map((e) => e.trim()).toList() ?? []),
      latitude: json['latitude'] != null ? double.tryParse(json['latitude'].toString()) : null,
      longitude: json['longitude'] != null ? double.tryParse(json['longitude'].toString()) : null,
      gstNumber: json['gst_number'] as String?,
      isAcceptingOrders: json['is_accepting_orders'] == true || json['is_accepting_orders'] == 1 || (json['is_accepting_orders'] == null && json['is_active'] != false),
      isSponsored: json['is_sponsored'] == true || json['is_sponsored'] == 1,
      rankingScore: json['ranking_score'] != null ? double.tryParse(json['ranking_score'].toString()) : null,
      distanceKm: json['distance_km'] != null ? double.tryParse(json['distance_km'].toString()) : null,
      avgPrepTimeMinutes: prepMin,
      totalOrdersCompleted: json['total_orders_completed'] != null ? int.tryParse(json['total_orders_completed'].toString()) : null,
      subScores: json['sub_scores'] is Map ? Map<String, dynamic>.from(json['sub_scores']) : null,
    );
  }
}
