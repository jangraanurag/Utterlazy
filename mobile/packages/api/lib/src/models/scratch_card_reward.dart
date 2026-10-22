class ScratchCardReward {
  final String brand;
  final String title;
  final String description;
  final String code;
  final String link;
  final String expiry;

  ScratchCardReward({
    required this.brand,
    required this.title,
    required this.description,
    required this.code,
    required this.link,
    required this.expiry,
  });

  factory ScratchCardReward.fromJson(Map<String, dynamic> json) {
    return ScratchCardReward(
      brand: json['brand'] ?? '',
      title: json['title'] ?? '',
      description: json['description'] ?? '',
      code: json['code'] ?? '',
      link: json['link'] ?? '',
      expiry: json['expiry'] ?? '',
    );
  }

  Map<String, dynamic> toJson() {
    return {
      'brand': brand,
      'title': title,
      'description': description,
      'code': code,
      'link': link,
      'expiry': expiry,
    };
  }
}
