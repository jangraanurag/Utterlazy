class CartItem {
  final String id;
  final String name;
  final double price;
  final String? imageUrl;
  final bool isVeg;
  int quantity;

  CartItem({
    required this.id,
    required this.name,
    required this.price,
    this.imageUrl,
    this.isVeg = true,
    this.quantity = 1,
  });

  double get totalPrice => price * quantity;
}
