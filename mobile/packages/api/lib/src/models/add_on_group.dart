class AddOnItem {
  final String id;
  final String name;
  final double price;
  final String taxType;
  final bool isAvailable;

  AddOnItem({
    required this.id,
    required this.name,
    required this.price,
    this.taxType = 'NONE',
    this.isAvailable = true,
  });

  factory AddOnItem.fromJson(Map<String, dynamic> json) {
    return AddOnItem(
      id: json['id'].toString(),
      name: json['name'] as String,
      price: double.parse(json['price'].toString()),
      taxType: json['tax_type'] as String? ?? 'NONE',
      isAvailable: json['is_available'] != false,
    );
  }

  Map<String, dynamic> toJson() => {
        'id': id,
        'name': name,
        'price': price,
        'tax_type': taxType,
      };
}

class AddOnGroup {
  final String id;
  final String? restaurantId;
  final String name;
  final String selectionType; // single, multiple
  final int minSelect;
  final int maxSelect;
  final List<AddOnItem> items;

  AddOnGroup({
    required this.id,
    this.restaurantId,
    required this.name,
    this.selectionType = 'multiple',
    this.minSelect = 0,
    this.maxSelect = 0,
    this.items = const [],
  });

  factory AddOnGroup.fromJson(Map<String, dynamic> json) {
    return AddOnGroup(
      id: json['id'].toString(),
      restaurantId: json['restaurant_id']?.toString(),
      name: json['name'] as String,
      selectionType: json['selection_type'] as String? ?? 'multiple',
      minSelect: json['min_select'] as int? ?? 0,
      maxSelect: json['max_select'] as int? ?? 0,
      items: (json['items'] as List<dynamic>?)
              ?.map((i) => AddOnItem.fromJson(i))
              .toList() ??
          [],
    );
  }

  Map<String, dynamic> toJson() => {
        'name': name,
        'selection_type': selectionType,
        'min_select': minSelect,
        'max_select': maxSelect,
        'items': items.map((i) => i.toJson()).toList(),
      };
}
