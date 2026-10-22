class Coupon {
  final String id;
  final String code;
  final String title;
  final String type;
  final double value;
  final double minOrder;
  final double? maxDiscount;
  final int? usageLimit;
  final int? perUserLimit;
  final String? validFrom;
  final String? validUntil;

  Coupon({
    required this.id,
    required this.code,
    required this.title,
    required this.type,
    required this.value,
    this.minOrder = 0,
    this.maxDiscount,
    this.usageLimit,
    this.perUserLimit,
    this.validFrom,
    this.validUntil,
  });

  factory Coupon.fromJson(Map<String, dynamic> json) {
    return Coupon(
      id: json['id'].toString(),
      code: json['code'] ?? '',
      title: json['title'] ?? '',
      type: json['type'] ?? 'percentage',
      value: (json['value'] ?? 0).toDouble(),
      minOrder: (json['min_order'] ?? 0).toDouble(),
      maxDiscount: json['max_discount']?.toDouble(),
      usageLimit: json['usage_limit'],
      perUserLimit: json['per_user_limit'],
      validFrom: json['valid_from'],
      validUntil: json['valid_until'],
    );
  }

  double calculateDiscount(double orderTotal) {
    if (orderTotal < minOrder) return 0;
    double discount = type == 'percentage' ? orderTotal * value / 100 : value;
    if (maxDiscount != null && discount > maxDiscount!) {
      discount = maxDiscount!;
    }
    return discount;
  }
}
