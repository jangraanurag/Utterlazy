import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:ui/ui.dart';
import 'package:api/api.dart';
import 'package:core/core.dart';
import 'restaurant_menu_screen.dart';

class FavoritesScreen extends ConsumerStatefulWidget {
  const FavoritesScreen({super.key});

  @override
  ConsumerState<FavoritesScreen> createState() => _FavoritesScreenState();
}

class _FavoritesScreenState extends ConsumerState<FavoritesScreen> {
  List<dynamic> _favorites = [];
  bool _isLoading = true;

  @override
  void initState() {
    super.initState();
    _loadFavorites();
  }

  Future<void> _loadFavorites() async {
    try {
      final apiClient = ref.read(apiClientProvider);
      final res = await apiClient.dio.get('/favorites');
      if (mounted) {
        setState(() {
          _favorites = res.data['data'] ?? [];
          _isLoading = false;
        });
      }
    } catch (e) {
      if (mounted) setState(() => _isLoading = false);
    }
  }

  Future<void> _removeFavorite(String restaurantId) async {
    try {
      final apiClient = ref.read(apiClientProvider);
      await apiClient.dio.post('/favorites/toggle', data: {'restaurant_id': restaurantId});
      _loadFavorites();
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('Failed to remove favorite')),
        );
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: const WeloAppBar(title: 'Your Favorites'),
      body: _isLoading
          ? const Center(child: CircularProgressIndicator(color: WeloColors.sovereignOrange))
          : _favorites.isEmpty
              ? _buildEmptyState()
              : ListView.builder(
                  padding: const EdgeInsets.all(16),
                  itemCount: _favorites.length,
                  itemBuilder: (context, index) {
                    final fav = _favorites[index];
                    final restaurant = fav['restaurant'] ?? fav;
                    return _buildFavoriteCard(restaurant);
                  },
                ),
    );
  }

  Widget _buildEmptyState() {
    return Center(
      child: Column(
        mainAxisAlignment: MainAxisAlignment.center,
        children: [
          Icon(Icons.favorite_border, size: 80, color: WeloColors.sovereignText.withValues(alpha: 0.098)),
          const SizedBox(height: 24),
          const Text('No favorites yet', style: TextStyle(fontSize: 22, fontWeight: FontWeight.bold)),
          const SizedBox(height: 8),
          Text(
            'Tap the heart icon on any restaurant to save them here.',
            style: TextStyle(color: WeloColors.textBody, fontSize: 14),
          ),
        ],
      ),
    );
  }

  Widget _buildFavoriteCard(dynamic restaurant) {
    return WeloCard(
      margin: const EdgeInsets.only(bottom: 12),
      onTap: () {
        final r = Restaurant(
          id: restaurant['id'] ?? '',
          name: restaurant['name'] ?? '',
          description: restaurant['description'],
          logoUrl: restaurant['logo_url'],
          rating: (restaurant['rating'] ?? 0).toDouble(),
          deliveryTime: restaurant['delivery_time'] ?? '',
        );
        Navigator.push(context, MaterialPageRoute(builder: (_) => RestaurantMenuScreen(restaurant: r)));
      },
      child: Row(
        children: [
          ClipRRect(
            borderRadius: BorderRadius.circular(16),
            child: Image.network(
              restaurant['logo_url'] ?? '',
              width: 80,
              height: 80,
              fit: BoxFit.cover,
              errorBuilder: (_, _, _) => Container(
                width: 80,
                height: 80,
                color: WeloColors.sovereignOrange.withValues(alpha: 0.098),
                child: const Icon(Icons.restaurant, color: WeloColors.sovereignOrange),
              ),
            ),
          ),
          const SizedBox(width: 16),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  restaurant['name'] ?? '',
                  style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 16),
                ),
                const SizedBox(height: 4),
                Text(
                  restaurant['description'] ?? '',
                  style: TextStyle(color: WeloColors.textBody, fontSize: 12),
                  maxLines: 2,
                  overflow: TextOverflow.ellipsis,
                ),
                const SizedBox(height: 4),
                Row(
                  children: [
                    const Icon(Icons.star, size: 14, color: Colors.green),
                    const SizedBox(width: 4),
                    Text('${restaurant['rating'] ?? 4.5}', style: const TextStyle(fontSize: 12, fontWeight: FontWeight.w600)),
                  ],
                ),
              ],
            ),
          ),
          IconButton(
            icon: const Icon(Icons.favorite, color: Colors.red),
            onPressed: () => _removeFavorite(restaurant['id']),
          ),
        ],
      ),
    );
  }
}
