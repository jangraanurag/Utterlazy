import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:core/core.dart';
import 'package:ui/ui.dart';
import 'checkout_screen.dart';

class CartScreen extends ConsumerWidget {
  const CartScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final cartState = ref.watch(cartProvider);
    final cartNotifier = ref.read(cartProvider.notifier);

    if (cartState.isEmpty) {
      return Scaffold(
        appBar: const WeloAppBar(title: 'Cart'),
        body: const WeloEmptyState(
          icon: Icons.shopping_bag_outlined,
          title: 'Your cart is empty',
          subtitle: 'Add items from a restaurant to get started',
        ),
      );
    }

    final allItems = cartState.allItems;

    return Scaffold(
      appBar: WeloAppBar(
        title: 'Cart \u2022 ${cartState.restaurantName ?? ""}',
        actions: [
          TextButton(
            onPressed: () => cartNotifier.clearCart(),
            child: const Text('Clear All', style: TextStyle(color: WeloColors.error, fontSize: 13)),
          ),
        ],
      ),
      body: Column(
        children: [
          Expanded(
            child: ListView.builder(
              padding: const EdgeInsets.all(16),
              itemCount: allItems.length,
              itemBuilder: (context, index) {
                final ci = allItems[index];
                return WeloCard(
                  margin: const EdgeInsets.only(bottom: 10),
                  padding: const EdgeInsets.all(14),
                  child: Row(
                    children: [
                      Container(
                        width: 14, height: 14,
                        decoration: BoxDecoration(
                          border: Border.all(color: ci.menuItem.isVeg ? WeloColors.success : WeloColors.error, width: 1.5),
                          borderRadius: BorderRadius.circular(3),
                        ),
                        padding: const EdgeInsets.all(2),
                        child: Container(
                          decoration: BoxDecoration(color: ci.menuItem.isVeg ? WeloColors.success : WeloColors.error, shape: BoxShape.circle),
                        ),
                      ),
                      const SizedBox(width: 12),
                      Expanded(
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Text(ci.menuItem.name, style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 14)),
                            if (ci.selectedVariant != null) ...[
                              const SizedBox(height: 2),
                              Text(ci.selectedVariant!.label, style: const TextStyle(color: WeloColors.sovereignOrange, fontSize: 12, fontWeight: FontWeight.w600)),
                            ],
                            if (ci.selectedAddOns.isNotEmpty) ...[
                              const SizedBox(height: 2),
                              Text(ci.selectedAddOns.map((a) => a.name).join(', '),
                                style: const TextStyle(color: WeloColors.textBody, fontSize: 11), maxLines: 1, overflow: TextOverflow.ellipsis),
                            ],
                            const SizedBox(height: 4),
                            Text('\u20b9${ci.variantPrice.toInt()}', style: const TextStyle(color: WeloColors.textBody, fontSize: 13)),
                          ],
                        ),
                      ),
                      Container(
                        decoration: BoxDecoration(
                          border: Border.all(color: WeloColors.success.withValues(alpha: 0.314)),
                          borderRadius: BorderRadius.circular(10),
                        ),
                        child: Row(
                          mainAxisSize: MainAxisSize.min,
                          children: [
                            IconButton(
                              icon: const Icon(Icons.remove, size: 16),
                              color: WeloColors.success,
                              onPressed: () => cartNotifier.removeItem(ci.menuItem.id),
                            ),
                            Text('${ci.quantity}', style: const TextStyle(fontWeight: FontWeight.bold)),
                            IconButton(
                              icon: const Icon(Icons.add, size: 16),
                              color: WeloColors.success,
                              onPressed: () => cartNotifier.addItem(ci.menuItem,
                                selectedVariant: ci.selectedVariant,
                                selectedAddOns: ci.selectedAddOns,
                                restaurantId: cartState.restaurantId,
                                restaurantName: cartState.restaurantName),
                            ),
                          ],
                        ),
                      ),
                    ],
                  ),
                );
              },
            ),
          ),
          Container(
            padding: const EdgeInsets.all(16),
            decoration: const BoxDecoration(
              color: Colors.white,
              boxShadow: [BoxShadow(color: Colors.black12, blurRadius: 10, offset: Offset(0, -5))],
            ),
            child: Column(
              children: [
                Row(
                  mainAxisAlignment: MainAxisAlignment.spaceBetween,
                  children: [
                    Text('${cartState.totalItems} items', style: const TextStyle(color: WeloColors.textBody)),
                    Text('\u20b9${cartState.totalPrice.toStringAsFixed(0)}', style: const TextStyle(fontWeight: FontWeight.bold)),
                  ],
                ),
                const SizedBox(height: 8),
                Row(
                  mainAxisAlignment: MainAxisAlignment.spaceBetween,
                  children: [
                    const Text('Delivery Fee', style: TextStyle(color: WeloColors.textBody)),
                    Text(cartState.totalPrice >= 500 ? 'FREE' : '\u20b930', style: TextStyle(
                      fontWeight: FontWeight.bold,
                      color: cartState.totalPrice >= 500 ? WeloColors.success : WeloColors.textDark,
                    )),
                  ],
                ),
                const Divider(height: 20),
                Row(
                  mainAxisAlignment: MainAxisAlignment.spaceBetween,
                  children: [
                    const Text('To Pay', style: TextStyle(fontWeight: FontWeight.bold, fontSize: 16)),
                    Text(
                      '\u20b9${(cartState.totalPrice + (cartState.totalPrice >= 500 ? 0 : 30)).toStringAsFixed(0)}',
                      style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 16, color: WeloColors.sovereignOrange),
                    ),
                  ],
                ),
                const SizedBox(height: 12),
                SizedBox(
                  width: double.infinity, height: 56,
                  child: WeloPrimaryButton(
                    text: 'Proceed to Checkout',
                    onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => const CheckoutScreen())),
                  ),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}
