class Address {
  final String id;
  final String label;
  final String? flatBuilding;
  final String? street;
  final String? landmark;
  final String city;
  final String state;
  final String? pincode;
  final double? latitude;
  final double? longitude;
  final String? locationName;
  final bool isDefault;

  Address({
    required this.id,
    required this.label,
    this.flatBuilding,
    this.street,
    this.landmark,
    required this.city,
    required this.state,
    this.pincode,
    this.latitude,
    this.longitude,
    this.locationName,
    this.isDefault = false,
  });

  factory Address.fromJson(Map<String, dynamic> json) {
    return Address(
      id: json['id'].toString(),
      label: json['label'] as String,
      flatBuilding: json['flat_building'] as String?,
      street: json['street'] as String?,
      landmark: json['landmark'] as String?,
      city: (json['city'] ?? 'Unknown City') as String,
      state: (json['state'] ?? 'Unknown State') as String,
      pincode: json['pincode'] as String?,
      latitude: json['latitude'] != null ? double.parse(json['latitude'].toString()) : null,
      longitude: json['longitude'] != null ? double.parse(json['longitude'].toString()) : null,
      locationName: json['location_name'] as String?,
      isDefault: json['is_default'] == 1 || json['is_default'] == true,
    );
  }

  Map<String, dynamic> toJson() {
    return {
      'id': id,
      'label': label,
      'flat_building': flatBuilding,
      'street': street,
      'landmark': landmark,
      'city': city,
      'state': state,
      'pincode': pincode,
      'latitude': latitude,
      'longitude': longitude,
      'location_name': locationName,
      'is_default': isDefault,
    };
  }
}
