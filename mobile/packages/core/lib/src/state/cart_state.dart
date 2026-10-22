import 'dart:math';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:api/api.dart';

class CartItem {
  final MenuItem menuItem;
  final int quantity;
  final Variant? selectedVariant;
  final List<AddOnItem> selectedAddOns;
  final String? specialInstructions;

  CartItem({
    required this.menuItem,
    this.quantity = 1,
    this.selectedVariant,
    this.selectedAddOns = const [],
    this.specialInstructions,
  });

  CartItem copyWith({
    MenuItem? menuItem,
    int? quantity,
    Variant? selectedVariant,
    List<AddOnItem>? selectedAddOns,
    String? specialInstructions,
  }) {
    return CartItem(
      menuItem: menuItem ?? this.menuItem,
      quantity: quantity ?? this.quantity,
      selectedVariant: selectedVariant ?? this.selectedVariant,
      selectedAddOns: selectedAddOns ?? this.selectedAddOns,
      specialInstructions: specialInstructions ?? this.specialInstructions,
    );
  }

  double get variantPrice {
    final base = menuItem.price;
    final impact = selectedVariant?.priceImpact ?? 0;
    return base + impact;
  }

  double get addOnPrice =>
      selectedAddOns.fold(0.0, (sum, item) => sum + item.price);

  double get totalPrice => (variantPrice + addOnPrice) * quantity;

  bool hasSameOptions(CartItem other) {
    final thisVariantId = selectedVariant?.id ?? '';
    final otherVariantId = other.selectedVariant?.id ?? '';
    final sameVariant = thisVariantId == otherVariantId;
    final thisAddOns = selectedAddOns.map((a) => a.id).join(',');
    final otherAddOns = other.selectedAddOns.map((a) => a.id).join(',');
    return sameVariant && thisAddOns == otherAddOns;
  }

  Map<String, dynamic> toCheckoutJson() {
    final instructions = specialInstructions;
    final variant = selectedVariant;
    return {
      'menu_item_id': menuItem.id,
      'quantity': quantity,
      if (variant != null) 'variant_id': variant.id,
      if (selectedAddOns.isNotEmpty)
        'add_on_item_ids': selectedAddOns.map((a) => a.id).toList(),
      if (instructions != null && instructions.isNotEmpty)
        'special_instructions': instructions,
    };
  }
}

class CartState {
  final Map<String, List<CartItem>> items;
  final String? restaurantId;
  final String? restaurantName;
  final String? appliedCouponCode;
  final double discountAmount;
  final String? discountLabel;
  final String? discountType;

  CartState({
    this.items = const {},
    this.restaurantId,
    this.restaurantName,
    this.appliedCouponCode,
    this.discountAmount = 0,
    this.discountLabel,
    this.discountType,
  });

  int get totalItems =>
      items.values.fold(0, (sum, list) => sum + list.fold(0, (s, i) => s + i.quantity));

  double get totalPrice => items.values.fold(
      0.0, (sum, list) => sum + list.fold(0.0, (s, i) => s + i.totalPrice));

  bool get isEmpty => items.isEmpty;
  bool get isNotEmpty => items.isNotEmpty;

  List<CartItem> get allItems =>
      items.values.expand((list) => list).toList();

  double get discountedTotal => max(0, totalPrice - discountAmount);

  CartState copyWith({
    Map<String, List<CartItem>>? items,
    String? restaurantId,
    String? restaurantName,
    bool clearRestaurant = false,
    String? appliedCouponCode,
    double? discountAmount,
    String? discountLabel,
    String? discountType,
    bool clearDiscount = false,
  }) {
    return CartState(
      items: items ?? this.items,
      restaurantId: clearRestaurant ? null : (restaurantId ?? this.restaurantId),
      restaurantName: clearRestaurant ? null : (restaurantName ?? this.restaurantName),
      appliedCouponCode: clearDiscount ? null : (appliedCouponCode ?? this.appliedCouponCode),
      discountAmount: clearDiscount ? 0 : (discountAmount ?? this.discountAmount),
      discountLabel: clearDiscount ? null : (discountLabel ?? this.discountLabel),
      discountType: clearDiscount ? null : (discountType ?? this.discountType),
    );
  }
}

class CartNotifier extends StateNotifier<CartState> {
  CartNotifier() : super(CartState());

  void applyDiscount({
    String? couponCode,
    double? amount,
    String? label,
    String? type,
  }) {
    state = state.copyWith(
      appliedCouponCode: couponCode ?? state.appliedCouponCode,
      discountAmount: amount ?? state.discountAmount,
      discountLabel: label ?? state.discountLabel,
      discountType: type ?? state.discountType,
    );
  }

  void removeDiscount() {
    state = state.copyWith(clearDiscount: true);
  }

  void addItem(
    MenuItem item, {
    int quantity = 1,
    Variant? selectedVariant,
    List<AddOnItem> selectedAddOns = const [],
    String? specialInstructions,
    String? restaurantId,
    String? restaurantName,
  }) {
    if (state.restaurantId != null &&
        state.restaurantId != restaurantId &&
        state.isNotEmpty) {
      clearCart();
    }

    final currentItems = Map<String, List<CartItem>>.from(state.items);
    final newItem = CartItem(
      menuItem: item,
      quantity: quantity,
      selectedVariant: selectedVariant,
      selectedAddOns: selectedAddOns,
      specialInstructions: specialInstructions,
    );

    if (currentItems.containsKey(item.id)) {
      final variants = currentItems[item.id]!;
      final existingIndex = variants.indexWhere((ci) => ci.hasSameOptions(newItem));
      if (existingIndex >= 0) {
        variants[existingIndex] = variants[existingIndex].copyWith(
          quantity: variants[existingIndex].quantity + quantity,
        );
      } else {
        variants.add(newItem);
      }
    } else {
      currentItems[item.id] = [newItem];
    }

    state = state.copyWith(
      items: currentItems,
      restaurantId: restaurantId,
      restaurantName: restaurantName,
    );
  }

  void removeItem(String menuItemId, {int? variantIndex}) {
    final currentItems = Map<String, List<CartItem>>.from(state.items);
    if (!currentItems.containsKey(menuItemId)) return;

    final variants = currentItems[menuItemId]!;
    if (variantIndex != null && variantIndex < variants.length) {
      if (variants[variantIndex].quantity > 1) {
        variants[variantIndex] = variants[variantIndex].copyWith(
          quantity: variants[variantIndex].quantity - 1,
        );
      } else {
        variants.removeAt(variantIndex);
      }
    } else {
      // Decrement the last item or remove it
      if (variants.last.quantity > 1) {
        variants.last = variants.last.copyWith(
          quantity: variants.last.quantity - 1,
        );
      } else {
        variants.removeLast();
      }
    }

    if (variants.isEmpty) {
      currentItems.remove(menuItemId);
    }

    state = state.copyWith(items: currentItems);
  }

  void updateQuantity(String menuItemId, int quantity, {int? variantIndex}) {
    final currentItems = Map<String, List<CartItem>>.from(state.items);
    if (!currentItems.containsKey(menuItemId)) return;

    final variants = currentItems[menuItemId]!;
    final idx = variantIndex ?? variants.length - 1;

    if (idx >= variants.length) return;

    if (quantity <= 0) {
      variants.removeAt(idx);
    } else {
      variants[idx] = variants[idx].copyWith(quantity: quantity);
    }

    if (variants.isEmpty) {
      currentItems.remove(menuItemId);
    }

    state = state.copyWith(items: currentItems);
  }

  void clearCart() {
    state = CartState();
  }

  int getQuantity(String itemId) {
    final list = state.items[itemId];
    if (list == null) return 0;
    return list.fold(0, (sum, ci) => sum + ci.quantity);
  }
}

final cartProvider = StateNotifierProvider<CartNotifier, CartState>((ref) {
  return CartNotifier();
});

final apiClientProvider = Provider<ApiClient>((ref) {
  return ApiClient();
});

final couponRepositoryProvider = Provider<CouponRepository>((ref) {
  return CouponRepository(ref.read(apiClientProvider));
});
