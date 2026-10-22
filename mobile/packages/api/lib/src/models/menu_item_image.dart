import '../utils/url_utils.dart';

class MenuItemImage {
  final String id;
  final String imageUrl;
  final bool isCover;
  final int sortOrder;

  MenuItemImage({
    required this.id,
    required String? imageUrl,
    this.isCover = false,
    this.sortOrder = 0,
  }) : imageUrl = sanitizeImageUrl(imageUrl) ?? '';

  factory MenuItemImage.fromJson(Map<String, dynamic> json) {
    return MenuItemImage(
      id: json['id'].toString(),
      imageUrl: json['image_url'] as String?,
      isCover: json['is_cover'] == true || json['is_cover'] == 1,
      sortOrder: json['sort_order'] as int? ?? 0,
    );
  }
}
