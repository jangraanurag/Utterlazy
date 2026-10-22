class User {
  final String id;
  final String name;
  final String email;
  final String? phone;
  final double khaaloCoins;

  User({
    required this.id,
    required this.name,
    required this.email,
    this.phone,
    this.khaaloCoins = 0,
  });

  factory User.fromJson(Map<String, dynamic> json) {
    return User(
      id: json['id'].toString(),
      name: json['name'] ?? '',
      email: json['email'] ?? '',
      phone: json['phone'],
      khaaloCoins: (json['khaalo_coins'] ?? 0).toDouble(),
    );
  }
}
