import 'dart:async';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:api/api.dart';
import 'package:core/core.dart';
import 'package:ui/ui.dart';
import 'checkout_screen.dart';

class RestaurantMenuScreen extends ConsumerStatefulWidget {
  final Restaurant restaurant;
  const RestaurantMenuScreen({super.key, required this.restaurant});

  @override
  ConsumerState<RestaurantMenuScreen> createState() => _RestaurantMenuScreenState();
}

class _RestaurantMenuScreenState extends ConsumerState<RestaurantMenuScreen> {
  late Restaurant _restaurant;
  List<MenuItem> _menuItems = [];
  bool _isLoading = true;
  bool _isVegOnly = false;
  bool _isBestsellerOnly = false;
  bool _isMustTryOnly = false;
  final bool _isFastingOnly = false;
  List<dynamic> _reviews = [];
  double _avgRating = 0;
  int _totalReviews = 0;
  Timer? _pollingTimer;

  @override
  void initState() {
    super.initState();
    _restaurant = widget.restaurant;
    _loadMenu();
    _startPolling();
    _loadReviews();
  }

  void _startPolling() {
    _pollingTimer = Timer.periodic(const Duration(seconds: 10), (timer) {
      _refreshRestaurantStatus();
    });
  }

  Future<void> _refreshRestaurantStatus() async {
    try {
      final apiClient = ref.read(apiClientProvider);
      final repository = RestaurantRepository(apiClient);
      final updated = await repository.getRestaurant(_restaurant.id);
      if (mounted) {
        setState(() {
          _restaurant = updated;
        });
      }
    } catch (_) {
      // Ignore background errors
    }
  }

  Future<void> _loadMenu() async {
    try {
      final apiClient = ref.read(apiClientProvider);
      final repository = RestaurantRepository(apiClient);
      final items = await repository.getMenuItems(_restaurant.id);
      if (mounted) {
        setState(() {
          _menuItems = items;
          _isLoading = false;
        });
      }
    } catch (e) {
      if (mounted) { setState(() => _isLoading = false); }
    }
  }

  Future<void> _handleRefresh() async {
    await Future.wait([
      _loadMenu(),
      _refreshRestaurantStatus(),
    ]);
  }

  Future<void> _loadReviews() async {
    try {
      final apiClient = ref.read(apiClientProvider);
      final res = await apiClient.dio.get('/restaurants/${_restaurant.id}/reviews');
      final data = res.data['data'] ?? res.data;
      final items = data is List ? data : (data['data'] ?? data['reviews'] ?? []);
      final avg = data is Map ? (data['average_rating'] ?? 0) : 0;
      final total = data is Map ? (data['total'] ?? items.length) : items.length;
      if (mounted) {
        setState(() {
          _reviews = items.take(5).toList();
          _avgRating = (avg is num) ? avg.toDouble() : 0.0;
          _totalReviews = total is int ? total : items.length;
        });
      }
    } catch (_) {}
  }

  @override
  void dispose() {
    _pollingTimer?.cancel();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final cartState = ref.watch(cartProvider);
    final cartCount = cartState.totalItems;
    final cartTotal = cartState.totalPrice;

    return Scaffold(
      backgroundColor: Colors.white,
      appBar: AppBar(
        leading: IconButton(icon: const Icon(Icons.arrow_back), onPressed: () => Navigator.pop(context)),
        title: Text(_restaurant.name, style: const TextStyle(fontSize: 16, fontWeight: FontWeight.bold)),
        actions: [
          IconButton(icon: const Icon(Icons.share_outlined), onPressed: () {}),
          IconButton(icon: const Icon(Icons.search), onPressed: () {}),
        ],
      ),
      body: _isLoading
          ? const Center(child: CircularProgressIndicator(color: WeloColors.sovereignOrange))
          : Stack(
              children: [
                RefreshIndicator(
                  onRefresh: _handleRefresh,
                  color: WeloColors.sovereignOrange,
                  child: SingleChildScrollView(
                    physics: const AlwaysScrollableScrollPhysics(),
                    padding: const EdgeInsets.symmetric(horizontal: 20),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        const SizedBox(height: 10),
                        Text(_restaurant.name.toUpperCase(), style: const TextStyle(fontSize: 24, fontWeight: FontWeight.w900, letterSpacing: -0.5)),
                        const SizedBox(height: 8),
                        Text(_restaurant.description ?? '', style: const TextStyle(fontSize: 13, color: Colors.black45, fontWeight: FontWeight.w500)),
                        const SizedBox(height: 16),
                        Row(
                          children: [
                            _buildInfoChip(Icons.stars, '${_restaurant.rating} (1K+)', Colors.green),
                            const SizedBox(width: 12),
                            _buildInfoChip(Icons.timer, _restaurant.deliveryTime, Colors.black),
                          ],
                        ),
                        const SizedBox(height: 24),
                        if (!_restaurant.isAcceptingOrders) ...[
                          Container(
                            width: double.infinity,
                            padding: const EdgeInsets.symmetric(vertical: 12, horizontal: 16),
                            decoration: BoxDecoration(
                              color: Colors.red.withValues(alpha: 0.1),
                              borderRadius: BorderRadius.circular(12),
                              border: Border.all(color: Colors.red.withValues(alpha: 0.2)),
                            ),
                            child: const Row(
                              children: [
                                Icon(Icons.error_outline, color: Colors.red),
                                SizedBox(width: 12),
                                Expanded(
                                  child: Text(
                                    'This restaurant is currently closed and not accepting orders.',
                                    style: TextStyle(
                                      color: Colors.red,
                                      fontSize: 13,
                                      fontWeight: FontWeight.bold,
                                    ),
                                  ),
                                ),
                              ],
                            ),
                          ),
                          const SizedBox(height: 16),
                        ],
                        const Divider(color: Color(0xFFF1F1F1), thickness: 1),
                        _buildOffersSlider(),
                        const Divider(color: Color(0xFFF1F1F1), thickness: 1),
                        const SizedBox(height: 16),
                        SingleChildScrollView(
                          scrollDirection: Axis.horizontal,
                          child: Row(
                            children: [
                              FilterChip(
                                label: const Text('Veg', style: TextStyle(fontWeight: FontWeight.bold, fontSize: 13)),
                                selected: _isVegOnly,
                                selectedColor: Colors.green.withValues(alpha: 0.15),
                                checkmarkColor: Colors.green,
                                onSelected: (v) => setState(() => _isVegOnly = v),
                                backgroundColor: Colors.white,
                                shape: RoundedRectangleBorder(
                                  borderRadius: BorderRadius.circular(8),
                                  side: BorderSide(color: _isVegOnly ? Colors.green : Colors.grey.shade300),
                                ),
                              ),
                              const SizedBox(width: 8),
                              FilterChip(
                                label: const Text('Bestseller', style: TextStyle(fontWeight: FontWeight.bold, fontSize: 13)),
                                selected: _isBestsellerOnly,
                                selectedColor: WeloColors.sovereignOrange.withValues(alpha: 0.15),
                                checkmarkColor: WeloColors.sovereignOrange,
                                onSelected: (v) => setState(() => _isBestsellerOnly = v),
                                backgroundColor: Colors.white,
                                shape: RoundedRectangleBorder(
                                  borderRadius: BorderRadius.circular(8),
                                  side: BorderSide(color: _isBestsellerOnly ? WeloColors.sovereignOrange : Colors.grey.shade300),
                                ),
                              ),
                              const SizedBox(width: 8),
                              FilterChip(
                                label: const Text('Must Try', style: TextStyle(fontWeight: FontWeight.bold, fontSize: 13)),
                                selected: _isMustTryOnly,
                                selectedColor: Colors.purple.withValues(alpha: 0.15),
                                checkmarkColor: Colors.purple,
                                onSelected: (v) => setState(() => _isMustTryOnly = v),
                                backgroundColor: Colors.white,
                                shape: RoundedRectangleBorder(
                                  borderRadius: BorderRadius.circular(8),
                                  side: BorderSide(color: _isMustTryOnly ? Colors.purple : Colors.grey.shade300),
                                ),
                              ),
                            ],
                          ),
                        ),
                        const SizedBox(height: 16),
                        _buildMenuList(),
                        if (_reviews.isNotEmpty) ...[
                          const SizedBox(height: 24),
                          _buildReviewsSection(),
                        ],
                        const SizedBox(height: 120),
                      ],
                    ),
                  ),
                ),
                if (cartCount > 0)
                  Positioned(
                    bottom: 30, left: 20, right: 20,
                    child: _buildFloatingCart(cartCount, cartTotal),
                  ),
              ],
            ),
    );
  }

  Widget _buildInfoChip(IconData icon, String text, Color color) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 6),
      decoration: BoxDecoration(color: color.withValues(alpha: 0.05), borderRadius: BorderRadius.circular(10)),
      child: Row(
        children: [
          Icon(icon, size: 14, color: color),
          const SizedBox(width: 6),
          Text(text, style: TextStyle(fontSize: 11, fontWeight: FontWeight.w900, color: color)),
        ],
      ),
    );
  }

  Widget _buildOffersSlider() {
    return SizedBox(
      height: 110,
      child: ListView(
        scrollDirection: Axis.horizontal,
        padding: const EdgeInsets.symmetric(vertical: 8),
        children: [
          _buildOfferCard('50% OFF UPTO ₹100', 'USE UTTERLAZY50'),
          _buildOfferCard('FLAT ₹125 OFF', 'ABOVE ₹499'),
        ],
      ),
    );
  }

  Widget _buildOfferCard(String title, String code) {
    return Container(
      width: 200,
      margin: const EdgeInsets.only(right: 12),
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        border: Border.all(color: Colors.orange.withValues(alpha: 0.1)),
        borderRadius: BorderRadius.circular(16),
        color: Colors.orange.withValues(alpha: 0.02),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        mainAxisAlignment: MainAxisAlignment.center,
        children: [
          Text(title, style: const TextStyle(fontSize: 11, fontWeight: FontWeight.w900)),
          const SizedBox(height: 2),
          Text(code, style: const TextStyle(fontSize: 9, fontWeight: FontWeight.w900, color: Colors.black38)),
        ],
      ),
    );
  }

  Widget _buildMenuList() {
    final filtered = _menuItems.where((i) {
      if (_isVegOnly && !i.isVeg) return false;
      if (_isBestsellerOnly && !i.isBestseller) return false;
      if (_isMustTryOnly && !i.isMustTry) return false;
      if (_isFastingOnly && !i.fastingSuitable) return false;
      return true;
    }).toList();

    if (filtered.isEmpty) {
      return const Padding(
        padding: EdgeInsets.all(32.0),
        child: Center(child: Text('No items match your filters.', style: TextStyle(color: Colors.grey))),
      );
    }

    final grouped = <String, List<MenuItem>>{};
    for (final item in filtered) {
      grouped.putIfAbsent(item.category, () => []).add(item);
    }

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: grouped.entries.map((entry) {
        return Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Padding(
              padding: const EdgeInsets.symmetric(vertical: 16),
              child: Text(entry.key, style: const TextStyle(fontSize: 18, fontWeight: FontWeight.w900)),
            ),
            ListView.separated(
              shrinkWrap: true,
              physics: const NeverScrollableScrollPhysics(),
              itemCount: entry.value.length,
              separatorBuilder: (context, index) => const Divider(height: 48, color: Color(0xFFF1F1F1)),
              itemBuilder: (context, index) {
                final item = entry.value[index];
                return Row(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Row(
                            children: [
                              _buildVegIndicator(item.isVeg),
                              const SizedBox(width: 4),
                              Text(item.prepType ?? (item.isVeg ? 'Veg' : 'Non-Veg'),
                                style: TextStyle(fontSize: 10, fontWeight: FontWeight.w600, color: item.isVeg ? Colors.green : Colors.red)),
                              if (item.isBestseller) ...[
                                const SizedBox(width: 8),
                                Container(
                                  padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 2),
                                  decoration: BoxDecoration(color: WeloColors.sovereignOrange.withValues(alpha: 0.1), borderRadius: BorderRadius.circular(4)),
                                  child: const Text('Bestseller', style: TextStyle(fontSize: 10, color: WeloColors.sovereignOrange, fontWeight: FontWeight.bold)),
                                ),
                              ],
                              if (item.isMustTry) ...[
                                const SizedBox(width: 8),
                                Container(
                                  padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 2),
                                  decoration: BoxDecoration(color: Colors.purple.withValues(alpha: 0.1), borderRadius: BorderRadius.circular(4)),
                                  child: const Text('Must Try', style: TextStyle(fontSize: 10, color: Colors.purple, fontWeight: FontWeight.bold)),
                                ),
                              ],
                            ],
                          ),
                          const SizedBox(height: 8),
                          Text(item.name, style: const TextStyle(fontSize: 16, fontWeight: FontWeight.w900, letterSpacing: -0.2)),
                          const SizedBox(height: 4),
                          Text('₹${item.price}', style: const TextStyle(fontSize: 14, fontWeight: FontWeight.w700)),
                          if (item.servesCount != null) ...[
                            const SizedBox(height: 2),
                            Text('Serves ${item.servesCount}', style: const TextStyle(fontSize: 11, color: Colors.black45)),
                          ],
                          const SizedBox(height: 6),
                          Text(item.description ?? '', style: const TextStyle(fontSize: 12, color: Colors.black45, height: 1.4), maxLines: 2, overflow: TextOverflow.ellipsis),
                        ],
                      ),
                    ),
                    const SizedBox(width: 20),
                    Stack(
                      clipBehavior: Clip.none,
                      children: [
                        ClipRRect(
                          borderRadius: BorderRadius.circular(16),
                          child: Image.network(
                            item.imageUrl ?? '', 
                            width: 110, 
                            height: 110, 
                            fit: BoxFit.cover,
                            errorBuilder: (context, error, stackTrace) => Container(
                              width: 110,
                              height: 110,
                              color: const Color(0xFFF8F8F8),
                              child: const Icon(Icons.fastfood, color: Colors.black12, size: 32),
                            ),
                          ),
                        ),
                        Positioned(
                          bottom: -10, left: 0, right: 0,
                          child: Center(
                            child: _buildAddButton(item),
                          ),
                        ),
                      ],
                    ),
                  ],
                );
              },
            ),
            const SizedBox(height: 24),
          ],
        );
      }).toList(),
    );
  }

  Widget _buildVegIndicator(bool isVeg) {
    return Container(
      width: 14, height: 14,
      decoration: BoxDecoration(border: Border.all(color: isVeg ? Colors.green : Colors.red, width: 1.5), borderRadius: BorderRadius.circular(3)),
      padding: const EdgeInsets.all(2),
      child: Container(decoration: BoxDecoration(color: isVeg ? Colors.green : Colors.red, shape: BoxShape.circle)),
    );
  }

  Widget _buildAddButton(MenuItem item) {
    if (!_restaurant.isAcceptingOrders) {
      return Container(
        height: 36,
        alignment: Alignment.center,
        decoration: BoxDecoration(
          color: Colors.grey.shade100,
          borderRadius: BorderRadius.circular(10),
          border: Border.all(color: Colors.grey.shade300),
        ),
        child: Text(
          'CLOSED',
          style: TextStyle(color: Colors.grey.shade500, fontSize: 11, fontWeight: FontWeight.w900),
        ),
      );
    }

    final quantity = ref.watch(cartProvider.notifier).getQuantity(item.id);

    if (quantity > 0) {
      return Container(
        height: 36,
        decoration: BoxDecoration(color: Colors.white, borderRadius: BorderRadius.circular(10), border: Border.all(color: Colors.green.withValues(alpha: 0.2)), boxShadow: [BoxShadow(color: Colors.black.withValues(alpha: 0.1), blurRadius: 10, offset: const Offset(0, 5))]),
        child: Row(
          mainAxisAlignment: MainAxisAlignment.spaceEvenly,
          children: [
            GestureDetector(onTap: () => ref.read(cartProvider.notifier).removeItem(item.id), child: const Icon(Icons.remove, size: 16, color: Colors.green)),
            Text('$quantity', style: const TextStyle(fontSize: 12, fontWeight: FontWeight.w900, color: Colors.green)),
            GestureDetector(onTap: () => ref.read(cartProvider.notifier).addItem(item, restaurantId: _restaurant.id, restaurantName: _restaurant.name), child: const Icon(Icons.add, size: 16, color: Colors.green)),
          ],
        ),
      );
    }

    if (item.hasVariants || item.hasAddOns) {
      return GestureDetector(
        onTap: () => _showItemDetailSheet(item),
        child: Container(
          height: 36,
          alignment: Alignment.center,
          decoration: BoxDecoration(color: Colors.white, borderRadius: BorderRadius.circular(10), border: Border.all(color: Colors.green.withValues(alpha: 0.2)), boxShadow: [BoxShadow(color: Colors.black.withValues(alpha: 0.1), blurRadius: 10, offset: const Offset(0, 5))]),
          child: const Text('ADD', style: TextStyle(color: Colors.green, fontSize: 13, fontWeight: FontWeight.w900)),
        ),
      );
    }

    return GestureDetector(
      onTap: () => ref.read(cartProvider.notifier).addItem(item, restaurantId: _restaurant.id, restaurantName: _restaurant.name),
      child: Container(
        height: 36,
        alignment: Alignment.center,
        decoration: BoxDecoration(color: Colors.white, borderRadius: BorderRadius.circular(10), border: Border.all(color: Colors.green.withValues(alpha: 0.2)), boxShadow: [BoxShadow(color: Colors.black.withValues(alpha: 0.1), blurRadius: 10, offset: const Offset(0, 5))]),
        child: const Text('ADD', style: TextStyle(color: Colors.green, fontSize: 13, fontWeight: FontWeight.w900)),
      ),
    );
  }

  Widget _buildReviewsSection() {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Row(
          children: [
            const Icon(Icons.star_rounded, color: WeloColors.sovereignGold, size: 20),
            const SizedBox(width: 4),
            Text('${_avgRating.toStringAsFixed(1)}', style: const TextStyle(fontWeight: FontWeight.w900, fontSize: 16)),
            const SizedBox(width: 4),
            Text('($_totalReviews reviews)', style: const TextStyle(color: WeloColors.textBody, fontSize: 13)),
          ],
        ),
        const SizedBox(height: 12),
        ..._reviews.map((review) => Padding(
          padding: const EdgeInsets.only(bottom: 12),
          child: WeloCard(
            padding: const EdgeInsets.all(12),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Row(
                  mainAxisAlignment: MainAxisAlignment.spaceBetween,
                  children: [
                    Flexible(
                      child: Text(review['customer_name'] ?? 'Customer', style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 13), overflow: TextOverflow.ellipsis),
                    ),
                    Row(
                      children: List.generate(5, (i) => Icon(
                        i < ((review['rating'] ?? 0) as int) ? Icons.star_rounded : Icons.star_outline_rounded,
                        size: 14,
                        color: WeloColors.sovereignGold,
                      )),
                    ),
                  ],
                ),
                if (review['title'] != null && (review['title'] as String).isNotEmpty) ...[
                  const SizedBox(height: 4),
                  Text(review['title'], style: const TextStyle(fontWeight: FontWeight.w600, fontSize: 13)),
                ],
                const SizedBox(height: 4),
                Text(review['comment'] ?? '', style: const TextStyle(color: WeloColors.textBody, fontSize: 12), maxLines: 2, overflow: TextOverflow.ellipsis),
                if (review['photos'] != null && (review['photos'] as List).isNotEmpty) ...[
                  const SizedBox(height: 8),
                  SizedBox(
                    height: 60,
                    child: ListView.builder(
                      scrollDirection: Axis.horizontal,
                      itemCount: (review['photos'] as List).length,
                      itemBuilder: (context, idx) => Padding(
                        padding: const EdgeInsets.only(right: 8),
                        child: ClipRRect(
                          borderRadius: BorderRadius.circular(8),
                          child: Image.network(
                            (review['photos'] as List)[idx],
                            width: 60, height: 60, fit: BoxFit.cover,
                          ),
                        ),
                      ),
                    ),
                  ),
                ],
                const SizedBox(height: 12),
                Row(
                  children: [
                    GestureDetector(
                      onTap: () {
                        // Optimistic UI update or API call to /reviews/{id}/helpful
                        ScaffoldMessenger.of(context).showSnackBar(
                          const SnackBar(content: Text('Marked as helpful'), duration: Duration(seconds: 1)),
                        );
                      },
                      child: Row(
                        children: [
                          Icon(Icons.thumb_up_outlined, size: 14, color: Colors.grey.shade600),
                          const SizedBox(width: 4),
                          Text('Helpful (${review['helpful_count'] ?? 0})', style: TextStyle(fontSize: 11, color: Colors.grey.shade600, fontWeight: FontWeight.w600)),
                        ],
                      ),
                    ),
                  ],
                ),
              ],
            ),
          ),
        )),
        const SizedBox(height: 8),
        Center(
          child: Text('See all reviews', style: TextStyle(color: WeloColors.sovereignOrange, fontWeight: FontWeight.bold, fontSize: 13)),
        ),
      ],
    );
  }

  Widget _buildFloatingCart(int count, double total) {
    return GestureDetector(
      onTap: () => Navigator.push(context, MaterialPageRoute(builder: (context) => const CheckoutScreen())),
      child: Container(
        height: 56,
        padding: const EdgeInsets.symmetric(horizontal: 20),
        decoration: BoxDecoration(color: Colors.green[600], borderRadius: BorderRadius.circular(16), boxShadow: [BoxShadow(color: Colors.green.withValues(alpha: 0.3), blurRadius: 20, offset: const Offset(0, 10))]),
        child: Row(
          children: [
            Column(
              mainAxisAlignment: MainAxisAlignment.center,
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text('$count ITEM${count > 1 ? 'S' : ''}', style: const TextStyle(color: Colors.white, fontSize: 10, fontWeight: FontWeight.w900)),
                Text('₹${total.toInt()}', style: const TextStyle(color: Colors.white, fontSize: 14, fontWeight: FontWeight.w900)),
              ],
            ),
            const Spacer(),
            const Text('VIEW CART', style: TextStyle(color: Colors.white, fontSize: 13, fontWeight: FontWeight.w900, letterSpacing: 0.5)),
            const SizedBox(width: 8),
            const Icon(Icons.shopping_bag, color: Colors.white, size: 18),
          ],
        ),
      ),
    );
  }

  void _showItemDetailSheet(MenuItem item) {
    Variant? selectedVariant;
    final selectedAddOns = <AddOnItem>[];

    showModalBottomSheet(
      context: context,
      isScrollControlled: true,
      shape: const RoundedRectangleBorder(borderRadius: BorderRadius.vertical(top: Radius.circular(24))),
      builder: (ctx) => StatefulBuilder(
        builder: (ctx, setSheetState) {
          double itemPrice = item.price;
          if (selectedVariant != null) { itemPrice += selectedVariant!.priceImpact; }
          double addOnTotal = selectedAddOns.fold(0.0, (s, a) => s + a.price);

          return Padding(
            padding: EdgeInsets.fromLTRB(24, 24, 24, MediaQuery.of(ctx).viewInsets.bottom + 24),
            child: Column(
              mainAxisSize: MainAxisSize.min,
              children: [
                Center(child: Container(width: 40, height: 4, decoration: BoxDecoration(color: Colors.grey[300], borderRadius: BorderRadius.circular(2)))),
                const SizedBox(height: 16),
                if (item.images.isNotEmpty)
                  ClipRRect(
                    borderRadius: BorderRadius.circular(16),
                    child: SizedBox(width: double.infinity, height: 160,
                      child: Image.network(item.images.first.imageUrl, fit: BoxFit.cover, errorBuilder: (_, _, _) => const SizedBox.shrink())),
                  ),
                const SizedBox(height: 16),
                Row(
                  children: [
                    _buildVegIndicator(item.isVeg),
                    const SizedBox(width: 8),
                    Expanded(child: Text(item.name, style: const TextStyle(fontSize: 20, fontWeight: FontWeight.w900))),
                  ],
                ),
                if (item.description != null) ...[
                  const SizedBox(height: 8),
                  Text(item.description!, style: const TextStyle(color: Colors.black54, fontSize: 13)),
                ],
                if (item.servesCount != null) ...[
                  const SizedBox(height: 4),
                  Text('Serves ${item.servesCount}', style: const TextStyle(color: Colors.black45, fontSize: 12)),
                ],
                const SizedBox(height: 16),

                // Variant selector
                if (item.hasVariants) ...[
                  const Text('Select Option', style: TextStyle(fontWeight: FontWeight.bold, fontSize: 14)),
                  const SizedBox(height: 8),
                  ...item.variantType!.variants.map((v) {
                    final isSel = selectedVariant?.id == v.id;
                    return InkWell(
                      onTap: () => setSheetState(() => selectedVariant = v),
                      child: Container(
                        margin: const EdgeInsets.only(bottom: 8),
                        padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
                        decoration: BoxDecoration(
                          border: Border.all(color: isSel ? Colors.green : Colors.grey.shade300, width: isSel ? 2 : 1),
                          borderRadius: BorderRadius.circular(12),
                          color: isSel ? Colors.green.withValues(alpha: 0.05) : null,
                        ),
                        child: Row(
                          children: [
                            Icon(isSel ? Icons.radio_button_checked : Icons.radio_button_off,
                              size: 20, color: isSel ? Colors.green : Colors.grey),
                            const SizedBox(width: 12),
                            Expanded(child: Text(v.label, style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 14))),
                            Text('₹${(item.price + v.priceImpact).toInt()}',
                              style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 14)),
                          ],
                        ),
                      ),
                    );
                  }),
                  const SizedBox(height: 12),
                ],

                // Add-on groups
                if (item.hasAddOns) ...[
                  ...item.addOnGroups.map((group) => Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(group.name, style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 14)),
                      const SizedBox(height: 8),
                      ...group.items.map((addOn) {
                        final isSel = selectedAddOns.any((a) => a.id == addOn.id);
                        return InkWell(
                          onTap: () {
                            setSheetState(() {
                              if (group.selectionType == 'single') {
                                selectedAddOns.removeWhere((a) => group.items.any((gi) => gi.id == a.id));
                              }
                              if (isSel) {
                                selectedAddOns.removeWhere((a) => a.id == addOn.id);
                              } else {
                                if (group.maxSelect == 0 || selectedAddOns.length < group.maxSelect) {
                                  selectedAddOns.add(addOn);
                                }
                              }
                            });
                          },
                          child: Container(
                            margin: const EdgeInsets.only(bottom: 8),
                            padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
                            decoration: BoxDecoration(border: Border.all(color: isSel ? Colors.green : Colors.grey.shade300), borderRadius: BorderRadius.circular(12)),
                            child: Row(
                              children: [
                                Icon(isSel ? Icons.check_box : Icons.check_box_outline_blank, size: 20, color: isSel ? Colors.green : Colors.grey),
                                const SizedBox(width: 12),
                                Expanded(child: Text(addOn.name, style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 14))),
                                Text('+₹${addOn.price.toInt()}', style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 13, color: Colors.green)),
                              ],
                            ),
                          ),
                        );
                      }),
                      const SizedBox(height: 12),
                    ],
                  )),
                ],

                const Divider(),
                Row(
                  mainAxisAlignment: MainAxisAlignment.spaceBetween,
                  children: [
                    Text('Total: ₹${(itemPrice + addOnTotal).toInt()}',
                      style: const TextStyle(fontSize: 18, fontWeight: FontWeight.w900)),
                    const SizedBox(width: 16),
                    ElevatedButton(
                      style: ElevatedButton.styleFrom(
                        backgroundColor: Colors.green,
                        foregroundColor: Colors.white,
                        padding: const EdgeInsets.symmetric(horizontal: 32, vertical: 14),
                        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                      ),
                      onPressed: () {
                        ref.read(cartProvider.notifier).addItem(
                          item,
                          selectedVariant: selectedVariant,
                          selectedAddOns: selectedAddOns,
                          restaurantId: _restaurant.id,
                          restaurantName: _restaurant.name,
                        );
                        Navigator.pop(ctx);
                        ScaffoldMessenger.of(context).showSnackBar(
                          const SnackBar(content: Text('Added to cart'), duration: Duration(seconds: 1)),
                        );
                      },
                      child: const Text('ADD TO CART', style: TextStyle(fontWeight: FontWeight.w900, fontSize: 13)),
                    ),
                  ],
                ),
              ],
            ),
          );
        },
      ),
    );
  }
}
