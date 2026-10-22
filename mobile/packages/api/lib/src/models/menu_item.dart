import '../utils/url_utils.dart';
import 'variant_type.dart';
import 'add_on_group.dart';
import 'menu_item_image.dart';

class MenuItem {
  final String id;
  final String restaurantId;
  final String name;
  final String? description;
  final double price;
  final String? imageUrl;
  final bool isAvailable;
  final String category;

  // New fields
  final String? prepType; // Veg, Non-Veg, Vegan, Jain, Vaishno, Egg
  final bool fastingSuitable;
  final String taxType; // GST_5, NONE, GST_18
  final bool taxIncluded;
  final int? servesCount;
  final VariantType? variantType;
  final List<AddOnGroup> addOnGroups;
  final List<MenuItemImage> images;
  final String? coverImageUrl;

  final bool isBestseller;
  final bool isMustTry;

  MenuItem({
    required this.id,
    required this.restaurantId,
    required this.name,
    this.description,
    required this.price,
    String? imageUrl,
    this.isAvailable = true,
    this.category = 'Recommended',
    this.prepType,
    this.fastingSuitable = false,
    this.taxType = 'NONE',
    this.taxIncluded = false,
    this.servesCount,
    this.variantType,
    this.addOnGroups = const [],
    this.images = const [],
    this.coverImageUrl,
    this.isBestseller = false,
    this.isMustTry = false,
  }) : imageUrl = sanitizeImageUrl(imageUrl ?? coverImageUrl);

  bool get isVeg {
    if (prepType != null) {
      return prepType == 'Veg' || prepType == 'Vegan' || prepType == 'Jain';
    }
    // Fallback: keyword detection for backward compatibility
    final nameLower = name.toLowerCase();
    final descLower = (description ?? '').toLowerCase();
    final nonVegKeywords = [
      'chicken', 'mutton', 'beef', 'fish', 'pork', 'egg',
      'shrimp', 'crab', 'meat', 'bacon', 'ham', 'lamb', 'prawn',
      'salmon', 'tuna', 'pepperoni', 'salami', 'kabab', 'kebab', 'tikka',
    ];
    for (final keyword in nonVegKeywords) {
      if (nameLower.contains(keyword) || descLower.contains(keyword)) {
        return false;
      }
    }
    return true;
  }

  bool get hasVariants => variantType != null && variantType!.variants.isNotEmpty;
  bool get hasAddOns => addOnGroups.isNotEmpty;
  bool get hasImages => images.isNotEmpty;

  factory MenuItem.fromJson(Map<String, dynamic> json) {
    return MenuItem(
      id: json['id'].toString(),
      restaurantId: json['restaurant_id'].toString(),
      name: json['name'] as String,
      description: json['description'] as String?,
      price: double.parse(json['price'].toString()),
      imageUrl: json['image_url'] as String?,
      isAvailable: json['is_available'] == 1 || json['is_available'] == true,
      category: json['category_name'] as String? ?? json['category'] as String? ?? 'Recommended',
      prepType: json['prep_type'] as String?,
      fastingSuitable: json['fasting_suitable'] == true || json['fasting_suitable'] == 1,
      taxType: json['tax_type'] as String? ?? 'NONE',
      taxIncluded: json['tax_included'] == true || json['tax_included'] == 1,
      servesCount: json['serves_count'] as int?,
      variantType: json['variant_type'] != null
          ? VariantType.fromJson(json['variant_type'])
          : null,
      addOnGroups: (json['add_on_groups'] as List<dynamic>?)
              ?.map((g) => AddOnGroup.fromJson(g))
              .toList() ??
          [],
      images: (json['images'] as List<dynamic>?)
              ?.map((img) => MenuItemImage.fromJson(img))
              .toList() ??
          [],
      coverImageUrl: json['cover_image_url'] as String?,
      isBestseller: json['is_bestseller'] == true || json['is_bestseller'] == 1,
      isMustTry: json['is_must_try'] == true || json['is_must_try'] == 1,
    );
  }
}
