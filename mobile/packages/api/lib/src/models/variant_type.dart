class Variant {
  final String id;
  final String variantTypeId;
  final String label;
  final String? unit;
  final double priceImpact;
  final bool isDefault;

  Variant({
    required this.id,
    required this.variantTypeId,
    required this.label,
    this.unit,
    this.priceImpact = 0,
    this.isDefault = false,
  });

  factory Variant.fromJson(Map<String, dynamic> json) {
    return Variant(
      id: json['id'].toString(),
      variantTypeId: json['variant_type_id']?.toString() ?? '',
      label: json['label'] as String,
      unit: json['unit'] as String?,
      priceImpact: double.parse(json['price_impact'].toString()),
      isDefault: json['is_default'] == true || json['is_default'] == 1,
    );
  }

  Map<String, dynamic> toJson() => {
        'label': label,
        'unit': unit,
        'price_impact': priceImpact,
        'is_default': isDefault,
      };
}

class VariantType {
  final String id;
  final String menuItemId;
  final String axis; // size, volume, quantity
  final String? unit; // inch, cm, ml, ltr (null for quantity)
  final List<Variant> variants;

  VariantType({
    required this.id,
    required this.menuItemId,
    required this.axis,
    this.unit,
    this.variants = const [],
  });

  factory VariantType.fromJson(Map<String, dynamic> json) {
    return VariantType(
      id: json['id'].toString(),
      menuItemId: json['menu_item_id']?.toString() ?? '',
      axis: json['axis'] as String,
      unit: json['unit'] as String?,
      variants: (json['variants'] as List<dynamic>?)
              ?.map((v) => Variant.fromJson(v))
              .toList() ??
          [],
    );
  }

  Variant? get defaultVariant =>
      variants.where((v) => v.isDefault).firstOrNull ?? variants.firstOrNull;

  Map<String, dynamic> toJson() => {
        'axis': axis,
        'unit': unit,
        'variants': variants.map((v) => v.toJson()).toList(),
      };
}
