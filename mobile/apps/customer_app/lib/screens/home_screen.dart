import 'dart:async';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:ui/ui.dart';
import 'package:api/api.dart';
import 'package:core/core.dart';
import 'restaurant_menu_screen.dart';
import 'search_screen.dart';
import 'cart_screen.dart';
import 'profile_screen.dart';
import 'order_history_screen.dart';
import 'location_selection_screen.dart';

class HomeScreen extends ConsumerStatefulWidget {
  final int initialNavIndex;
  const HomeScreen({super.key, this.initialNavIndex = 0});

  @override
  ConsumerState<HomeScreen> createState() => _HomeScreenState();
}

class _HomeScreenState extends ConsumerState<HomeScreen> {
  List<Restaurant> _restaurants = [];
  bool _isLoading = true;
  String? _selectedCategory;
  bool _gstOnly = false;
  final ScrollController _scrollController = ScrollController();
  int _currentNavIndex = 0;
  final Set<String> _loggedImpressions = {};

  List<Map<String, String>> _categories = [
    {'name': 'Biryani', 'image': 'https://images.unsplash.com/photo-1589302168068-964664d93dc0?auto=format&fit=crop&w=300&q=80'},
    {'name': 'Burgers', 'image': 'https://images.unsplash.com/photo-1571091718767-18b5b1457add?auto=format&fit=crop&w=300&q=80'},
    {'name': 'Pizza', 'image': 'https://images.unsplash.com/photo-1513104890138-7c749659a591?auto=format&fit=crop&w=300&q=80'},
    {'name': 'North Indian', 'image': 'https://images.unsplash.com/photo-1585937421612-70a008356fbe?auto=format&fit=crop&w=300&q=80'},
    {'name': 'Chinese', 'image': 'https://images.unsplash.com/photo-1552611052-33e04de081de?auto=format&fit=crop&w=300&q=80'},
  ];

  Timer? _pollingTimer;

  @override
  void initState() {
    super.initState();
    _currentNavIndex = widget.initialNavIndex;
    _loadData();
    _startPolling();
  }

  void _startPolling() {
    _pollingTimer = Timer.periodic(const Duration(seconds: 12), (timer) {
      if (mounted && _currentNavIndex == 0) {
        _loadData();
      }
    });
  }

  @override
  void dispose() {
    _pollingTimer?.cancel();
    _scrollController.dispose();
    super.dispose();
  }

  Future<void> _loadData({String? category, bool? gstReady}) async {
    final showGstOnly = gstReady ?? _gstOnly;
    if (category != null || gstReady != null) {
      setState(() {
        _isLoading = true;
      });
    }
    try {
      final apiClient = ref.read(apiClientProvider);
      final repository = RestaurantRepository(apiClient);
      final restaurants = await repository.getRestaurants(
        category: category ?? _selectedCategory,
        gstReady: showGstOnly,
      );
      
      // Sort: sponsored first, then online first, offline last
      restaurants.sort((a, b) {
        if (a.isSponsored && !b.isSponsored) return -1;
        if (!a.isSponsored && b.isSponsored) return 1;
        if (a.isAcceptingOrders && !b.isAcceptingOrders) return -1;
        if (!a.isAcceptingOrders && b.isAcceptingOrders) return 1;
        return 0;
      });
      
      // Load categories from API
      if (category == null) {
        try {
          final catResponse = await apiClient.dio.get('/global-categories');
          if (catResponse.statusCode == 200 && catResponse.data != null) {
            final List<dynamic> list = catResponse.data['categories'] ?? [];
            final mapped = list.map((item) {
              final rawImage = item['image_url'] as String?;
              final sanitizedImage = sanitizeImageUrl(rawImage) ?? 'https://images.unsplash.com/photo-1589302168068-964664d93dc0?auto=format&fit=crop&w=300&q=80';
              return {
                'name': (item['name'] ?? '').toString(),
                'image': sanitizedImage,
              };
            }).toList();
            if (mapped.isNotEmpty && mounted) {
              setState(() {
                _categories = mapped;
              });
            }
          }
        } catch (catError) {
          // Ignore category load error, use fallback
        }
      }

      if (mounted) {
        setState(() {
          _restaurants = restaurants;
          _isLoading = false;
        });
      }

      // Fetch sponsored results
      try {
        final spRes = await repository.getSponsoredResults();
        final sponsored = (spRes['sponsored'] as List?) ?? [];
        // In production, track listing_id from sponsored results mapping.
        if (sponsored.isNotEmpty) {
          debugPrint('Sponsored results: ${sponsored.length} listings');
        }
      } catch (_) {
        // ignore sponsored fetch errors
      }
    } catch (e) {
      debugPrint('HomeScreen._loadData error: $e');
      if (mounted) setState(() => _isLoading = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final screens = [
      _buildHomeContent(),
      const SearchScreen(),
      const CartScreen(),
      OrderHistoryScreen(),
      const ProfileScreen(),
    ];

    return Scaffold(
      body: screens[_currentNavIndex],
      bottomNavigationBar: _buildBottomNav(),
    );
  }

  Widget _buildHomeContent() {
    return _isLoading
        ? const Center(child: CircularProgressIndicator(color: WeloColors.sovereignOrange))
        : RefreshIndicator(
            onRefresh: () => _loadData(),
            color: WeloColors.sovereignOrange,
            child: CustomScrollView(
              controller: _scrollController,
              physics: const AlwaysScrollableScrollPhysics(),
              slivers: [
                _buildSwiggyAppBar(),
                SliverToBoxAdapter(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      _buildCategoriesSection(),
                      const Divider(thickness: 1, color: Color(0xFFF1F1F1)),
                      _buildTopBrandsSection(),
                      _buildSponsorshipCarousel(),
                      Padding(
                        padding: const EdgeInsets.fromLTRB(24, 32, 24, 16),
                        child: Row(
                          mainAxisAlignment: MainAxisAlignment.spaceBetween,
                          children: [
                            Flexible(
                              child: Text(
                                _selectedCategory != null ? '$_selectedCategory near you' : 'Restaurants with online food delivery',
                                style: Theme.of(context).textTheme.displaySmall,
                              ),
                            ),
                            if (_selectedCategory != null)
                              GestureDetector(
                                onTap: () {
                                  setState(() => _selectedCategory = null);
                                  _loadData();
                                },
                                child: Text('CLEAR', style: Theme.of(context).textTheme.labelMedium?.copyWith(color: WeloColors.sovereignOrange)),
                              ),
                          ],
                        ),
                      ),
                      Padding(
                        padding: const EdgeInsets.symmetric(horizontal: 24, vertical: 8),
                        child: SingleChildScrollView(
                          scrollDirection: Axis.horizontal,
                          child: Row(
                            children: [
                              FilterChip(
                                label: const Text('GST Ready 🧾', style: TextStyle(fontSize: 11, fontWeight: FontWeight.bold)),
                                selected: _gstOnly,
                                selectedColor: WeloColors.sovereignOrange.withValues(alpha: 0.15),
                                checkmarkColor: WeloColors.sovereignOrange,
                                onSelected: (selected) {
                                  setState(() {
                                    _gstOnly = selected;
                                  });
                                  _loadData(category: _selectedCategory, gstReady: selected);
                                },
                              ),
                              const SizedBox(width: 8),
                              FilterChip(
                                label: const Text('Ratings 4.0+', style: TextStyle(fontSize: 11)),
                                selected: false,
                                onSelected: (_) {},
                              ),
                              const SizedBox(width: 8),
                              FilterChip(
                                label: const Text('Pure Veg', style: TextStyle(fontSize: 11)),
                                selected: false,
                                onSelected: (_) {},
                              ),
                            ],
                          ),
                        ),
                      ),
                    ],
                  ),
                ),
                SliverPadding(
                  padding: const EdgeInsets.symmetric(horizontal: 24),
                  sliver: _restaurants.isEmpty
                      ? SliverToBoxAdapter(
                          child: Padding(
                            padding: const EdgeInsets.symmetric(vertical: 60),
                            child: Column(
                              children: [
                                Icon(Icons.store_mall_directory_outlined, size: 48, color: Colors.grey[300]),
                                const SizedBox(height: 16),
                                Text('No restaurants available', style: TextStyle(color: Colors.grey[400], fontSize: 16)),
                                const SizedBox(height: 8),
                                Text('Pull down to refresh', style: TextStyle(color: Colors.grey[300], fontSize: 13)),
                              ],
                            ),
                          ),
                        )
                      : SliverList(
                          delegate: SliverChildBuilderDelegate(
                            (context, index) {
                              if (index >= _restaurants.length) return null;
                              return _buildRestaurantListCard(_restaurants[index]);
                            },
                            childCount: _restaurants.length,
                          ),
                        ),
                ),
                const SliverToBoxAdapter(child: SizedBox(height: 100)),
              ],
            ),
          );
  }

  Widget _buildBottomNav() {
    return Container(
      decoration: const BoxDecoration(
        color: Colors.white,
        boxShadow: [
          BoxShadow(color: Colors.black12, blurRadius: 10, offset: Offset(0, -2)),
        ],
      ),
      child: SafeArea(
        child: Padding(
          padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 8),
          child: Row(
            mainAxisAlignment: MainAxisAlignment.spaceAround,
            children: [
              _buildNavItem(0, Icons.home_filled, 'Home'),
              _buildNavItem(1, Icons.search, 'Search'),
              _buildNavItem(2, Icons.shopping_bag_outlined, 'Cart'),
              _buildNavItem(3, Icons.receipt_long_outlined, 'Orders'),
              _buildNavItem(4, Icons.person_outline, 'Profile'),
            ],
          ),
        ),
      ),
    );
  }

  Widget _buildNavItem(int index, IconData icon, String label) {
    final isActive = _currentNavIndex == index;
    return GestureDetector(
      onTap: () => setState(() => _currentNavIndex = index),
      behavior: HitTestBehavior.opaque,
      child: AnimatedContainer(
        duration: const Duration(milliseconds: 200),
        padding: EdgeInsets.symmetric(horizontal: isActive ? 16 : 12, vertical: 8),
        decoration: BoxDecoration(
          color: isActive ? WeloColors.sovereignOrange.withValues(alpha: 0.098) : Colors.transparent,
          borderRadius: BorderRadius.circular(12),
        ),
        child: Row(
          mainAxisSize: MainAxisSize.min,
          children: [
            Icon(icon, size: 22, color: isActive ? WeloColors.sovereignOrange : Colors.black26),
            if (isActive) ...[
              const SizedBox(width: 6),
              Text(label, style: Theme.of(context).textTheme.labelLarge?.copyWith(color: WeloColors.sovereignOrange)),
            ],
          ],
        ),
      ),
    );
  }

  Widget _buildSwiggyAppBar() {
    final locationState = ref.watch(locationProvider);
    return SliverAppBar(
      floating: true,
      backgroundColor: Colors.white,
      toolbarHeight: 80,
      title: GestureDetector(
        onTap: () => Navigator.push(context, MaterialPageRoute(builder: (context) => LocationSelectionScreen())),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                const Icon(Icons.location_on, size: 18, color: WeloColors.sovereignOrange),
                const SizedBox(width: 4),
                Flexible(child: Text(locationState.name, style: Theme.of(context).textTheme.labelLarge, overflow: TextOverflow.ellipsis)),
                const Icon(Icons.expand_more, size: 20, color: WeloColors.sovereignOrange),
              ],
            ),
            Text(
              locationState.address, 
              style: Theme.of(context).textTheme.bodySmall,
              maxLines: 1,
              overflow: TextOverflow.ellipsis,
            ),
          ],
        ),
      ),
      actions: [
        IconButton(
          icon: const CircleAvatar(backgroundColor: Color(0xFFF1F1F1), child: Icon(Icons.person_outline, color: Colors.black)),
          onPressed: () => setState(() => _currentNavIndex = 4),
        ),
        const SizedBox(width: 12),
      ],
    );
  }

  Widget _buildCategoriesSection() {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Padding(
          padding: const EdgeInsets.fromLTRB(24, 24, 24, 16),
          child: Text("What's on your mind?", style: Theme.of(context).textTheme.displaySmall),
        ),
        SizedBox(
          height: 140,
          child: ListView.builder(
            scrollDirection: Axis.horizontal,
            padding: const EdgeInsets.symmetric(horizontal: 16),
            itemCount: _categories.length,
            itemBuilder: (context, index) {
              final cat = _categories[index];
              final isSelected = _selectedCategory == cat['name'];
              return GestureDetector(
                onTap: () {
                  if (_selectedCategory == cat['name']) {
                    setState(() {
                      _selectedCategory = null;
                    });
                    _loadData();
                  } else {
                    setState(() {
                      _selectedCategory = cat['name'];
                    });
                    _loadData(category: cat['name']);
                    // Scroll to list
                    _scrollController.animateTo(
                      550, // Approximate offset to restaurant list
                      duration: const Duration(milliseconds: 500),
                      curve: Curves.easeInOut,
                    );
                  }
                },
                child: Padding(
                  padding: const EdgeInsets.symmetric(horizontal: 8.0),
                  child: Column(
                    children: [
                      Container(
                        decoration: BoxDecoration(
                          shape: BoxShape.circle,
                          border: isSelected ? Border.all(color: WeloColors.sovereignOrange, width: 3) : null,
                        ),
                        padding: isSelected ? const EdgeInsets.all(3) : null,
                        child: ClipOval(
                          child: Image.network(
                            cat['image']!,
                            width: 90,
                            height: 90,
                            fit: BoxFit.cover,
                            errorBuilder: (context, error, stackTrace) => Container(
                              width: 90,
                              height: 90,
                              color: const Color(0xFFF8F8F8),
                              alignment: Alignment.center,
                              child: const Icon(Icons.fastfood, color: Colors.black26),
                            ),
                          ),
                        ),
                      ),
                      const SizedBox(height: 8),
                      Text(
                        cat['name']!, 
                        style: TextStyle(
                          fontSize: 11, 
                          fontWeight: isSelected ? FontWeight.w900 : FontWeight.w700, 
                          color: isSelected ? WeloColors.sovereignOrange : Colors.black54
                        )
                      ),
                    ],
                  ),
                ),
              );
            },
          ),
        ),
      ],
    );
  }

  Widget _buildTopBrandsSection() {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Padding(
          padding: const EdgeInsets.fromLTRB(24, 32, 24, 16),
          child: Text("Top brands for you", style: Theme.of(context).textTheme.displaySmall),
        ),
        SizedBox(
          height: 220,
          child: ListView.builder(
            scrollDirection: Axis.horizontal,
            padding: const EdgeInsets.symmetric(horizontal: 16),
            itemCount: _restaurants.length > 5 ? 5 : _restaurants.length,
            itemBuilder: (context, index) {
              final res = _restaurants[index];
              return GestureDetector(
      onTap: () {
        if (res.isSponsored) _logSponsoredClick(res.id);
        Navigator.push(context, MaterialPageRoute(builder: (context) => RestaurantMenuScreen(restaurant: res)));
      },
                child: Container(
                  width: 160,
                  margin: const EdgeInsets.symmetric(horizontal: 8),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      ClipRRect(
                        borderRadius: BorderRadius.circular(20),
                        child: Stack(
                          children: [
                            ColorFiltered(
                              colorFilter: ColorFilter.mode(
                                res.isAcceptingOrders ? Colors.transparent : Colors.grey,
                                BlendMode.saturation,
                              ),
                              child: Image.network(
                                res.logoUrl ?? '', 
                                height: 140, 
                                width: 160, 
                                fit: BoxFit.cover,
                                errorBuilder: (context, error, stackTrace) => Container(
                                  height: 140,
                                  width: 160,
                                  color: const Color(0xFFF8F8F8),
                                  alignment: Alignment.center,
                                  child: const Icon(Icons.restaurant, color: Colors.black26, size: 36),
                                ),
                              ),
                            ),
                            if (!res.isAcceptingOrders)
                              Positioned.fill(
                                child: Container(
                                  color: Colors.black.withValues(alpha: 0.4),
                                  alignment: Alignment.center,
                                  child: Container(
                                    padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
                                    decoration: BoxDecoration(
                                      color: Colors.red,
                                      borderRadius: BorderRadius.circular(6),
                                    ),
                                    child: const Text(
                                      'CLOSED',
                                      style: TextStyle(
                                        color: Colors.white,
                                        fontSize: 10,
                                        fontWeight: FontWeight.bold,
                                        letterSpacing: 0.5,
                                      ),
                                    ),
                                  ),
                                ),
                              ),
                          ],
                        ),
                      ),
                      const SizedBox(height: 12),
                      Text(res.name, style: const TextStyle(fontSize: 14, fontWeight: FontWeight.w900), maxLines: 1, overflow: TextOverflow.ellipsis),
                      Row(
                        children: [
                          const Icon(Icons.stars, size: 14, color: Colors.green),
                          const SizedBox(width: 4),
                          Text('${res.rating} • ${res.deliveryTime}', style: const TextStyle(fontSize: 12, fontWeight: FontWeight.w600, color: Colors.black54)),
                        ],
                      ),
                    ],
                  ),
                ),
              );
            },
          ),
        ),
      ],
    );
  }

  Widget _buildRestaurantListCard(Restaurant res) {
    if (res.isSponsored) _logSponsoredImpression(res.id);
    return GestureDetector(
      onTap: () => Navigator.push(context, MaterialPageRoute(builder: (context) => RestaurantMenuScreen(restaurant: res))),
      child: Container(
        margin: const EdgeInsets.only(bottom: 32),
        child: Row(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Stack(
              children: [
                ClipRRect(
                  borderRadius: BorderRadius.circular(24),
                  child: ColorFiltered(
                    colorFilter: ColorFilter.mode(
                      res.isAcceptingOrders ? Colors.transparent : Colors.grey,
                      BlendMode.saturation,
                    ),
                    child: Image.network(
                      res.logoUrl ?? '', 
                      height: 160, 
                      width: 140, 
                      fit: BoxFit.cover,
                      errorBuilder: (context, error, stackTrace) => Container(
                        height: 160,
                        width: 140,
                        color: const Color(0xFFF8F8F8),
                        alignment: Alignment.center,
                        child: const Icon(Icons.restaurant, color: Colors.black26, size: 36),
                      ),
                    ),
                  ),
                ),
                if (!res.isAcceptingOrders)
                  Positioned.fill(
                    child: Container(
                      decoration: BoxDecoration(
                        color: Colors.black.withValues(alpha: 0.4),
                        borderRadius: BorderRadius.circular(24),
                      ),
                      alignment: Alignment.center,
                      child: Container(
                        padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
                        decoration: BoxDecoration(
                          color: Colors.red,
                          borderRadius: BorderRadius.circular(6),
                        ),
                        child: const Text(
                          'CLOSED',
                          style: TextStyle(
                            color: Colors.white,
                            fontSize: 10,
                            fontWeight: FontWeight.bold,
                            letterSpacing: 0.5,
                          ),
                        ),
                      ),
                    ),
                  )
                else
                  Positioned(
                    bottom: 8, left: 8,
                    child: Container(
                      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
                      decoration: BoxDecoration(color: Colors.white, borderRadius: BorderRadius.circular(8)),
                      child: Text('₹100 OFF', style: Theme.of(context).textTheme.labelSmall?.copyWith(color: WeloColors.sovereignOrange)),
                    ),
                  ),
              ],
            ),
            const SizedBox(width: 20),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  const SizedBox(height: 8),
                  Row(
                    children: [
                      Flexible(child: Text(res.name, style: Theme.of(context).textTheme.titleSmall, overflow: TextOverflow.ellipsis)),
                      if (res.rankingScore != null) ...[
                        const SizedBox(width: 6),
                        Container(
                          padding: const EdgeInsets.symmetric(horizontal: 5, vertical: 1),
                          decoration: BoxDecoration(
                            color: Colors.green.shade50,
                            borderRadius: BorderRadius.circular(4),
                          ),
                          child: Text('${res.rankingScore!.toStringAsFixed(0)}', style: TextStyle(fontSize: 10, color: Colors.green.shade700, fontWeight: FontWeight.bold)),
                        ),
                      ],
                    ],
                  ),
                  if (res.isSponsored) ...[
                    const SizedBox(height: 4),
                    Text('Sponsored', style: TextStyle(fontSize: 10, color: Colors.grey.shade500, letterSpacing: 0.5)),
                  ],
                  const SizedBox(height: 4),
                  Row(
                    children: [
                      const Icon(Icons.stars, size: 14, color: Colors.green),
                      const SizedBox(width: 4),
                      Flexible(child: Text('${res.rating} • ${res.deliveryTime}', style: Theme.of(context).textTheme.bodyMedium, overflow: TextOverflow.ellipsis)),
                      if (res.distanceKm != null) ...[
                        const SizedBox(width: 8),
                        Text('${res.distanceKm!.toStringAsFixed(1)} km', style: Theme.of(context).textTheme.bodySmall?.copyWith(color: Colors.grey)),
                      ],
                    ],
                  ),
                  const SizedBox(height: 8),
                  Text(res.description ?? '', style: Theme.of(context).textTheme.bodySmall, maxLines: 2, overflow: TextOverflow.ellipsis),
                  const SizedBox(height: 12),
                  Row(
                    children: [
                      const Icon(Icons.verified, size: 14, color: Colors.blue),
                      const SizedBox(width: 4),
                      Flexible(child: Text('SMART COUPON AUTO-APPLIED', style: Theme.of(context).textTheme.labelSmall, overflow: TextOverflow.ellipsis)),
                    ],
                  ),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildSponsorshipCarousel() {
    final sponsored = _restaurants.where((r) => r.isSponsored).toList();
    if (sponsored.isEmpty) return const SizedBox.shrink();

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        const Padding(
          padding: EdgeInsets.fromLTRB(24, 32, 24, 16),
          child: Text('Featured Near You', style: TextStyle(fontSize: 18, fontWeight: FontWeight.w900)),
        ),
        SizedBox(
          height: 180,
          child: ListView.builder(
            scrollDirection: Axis.horizontal,
            padding: const EdgeInsets.symmetric(horizontal: 16),
            itemCount: sponsored.length,
            itemBuilder: (context, index) {
              final res = sponsored[index];
              _logSponsoredImpression(res.id);
              return GestureDetector(
                onTap: () {
                  _logSponsoredClick(res.id);
                  Navigator.push(context, MaterialPageRoute(builder: (context) => RestaurantMenuScreen(restaurant: res)));
                },
                child: Container(
                  width: 280,
                  margin: const EdgeInsets.symmetric(horizontal: 8),
                  decoration: BoxDecoration(
                    borderRadius: BorderRadius.circular(16),
                    image: DecorationImage(
                      image: NetworkImage(res.logoUrl ?? 'https://images.unsplash.com/photo-1555396273-367ea4eb4db5?auto=format&fit=crop&w=400&q=80'),
                      fit: BoxFit.cover,
                      colorFilter: ColorFilter.mode(Colors.black.withValues(alpha: 0.3), BlendMode.darken),
                    ),
                  ),
                  child: Stack(
                    children: [
                      Positioned(
                        top: 12, left: 12,
                        child: Container(
                          padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
                          decoration: BoxDecoration(color: Colors.black.withValues(alpha: 0.6), borderRadius: BorderRadius.circular(4)),
                          child: const Text('Ad', style: TextStyle(color: Colors.white, fontSize: 10, fontWeight: FontWeight.bold)),
                        ),
                      ),
                      Positioned(
                        bottom: 16, left: 16, right: 16,
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Text(res.name, style: const TextStyle(color: Colors.white, fontSize: 18, fontWeight: FontWeight.bold)),
                            const SizedBox(height: 4),
                            Row(
                              children: [
                                const Icon(Icons.star, color: Colors.amber, size: 14),
                                const SizedBox(width: 4),
                                Text('${res.rating}', style: const TextStyle(color: Colors.white, fontSize: 12)),
                                const SizedBox(width: 8),
                                Text('• ${res.deliveryTime}', style: const TextStyle(color: Colors.white70, fontSize: 12)),
                              ],
                            ),
                          ],
                        ),
                      ),
                    ],
                  ),
                ),
              );
            },
          ),
        ),
      ],
    );
  }

  void _logSponsoredImpression(String restaurantId) {
    if (_loggedImpressions.contains(restaurantId)) return;
    _loggedImpressions.add(restaurantId);
    // Log via sponsored_listing IDs. We use restaurant ID as proxy for now.
    // In production, track listing_id from _sponsoredIds mapping.
    Future.microtask(() async {
      try {
        final repo = RestaurantRepository(ref.read(apiClientProvider));
        await repo.logAdImpression(restaurantId);
      } catch (_) {}
    });
  }

  void _logSponsoredClick(String restaurantId) {
    Future.microtask(() async {
      try {
        final repo = RestaurantRepository(ref.read(apiClientProvider));
        await repo.logAdClick(restaurantId);
      } catch (_) {}
    });
  }
}
