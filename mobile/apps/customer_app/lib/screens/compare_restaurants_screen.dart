import 'dart:async';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:ui/ui.dart';
import 'package:api/api.dart';
import 'package:core/core.dart';

class CompareRestaurantsScreen extends ConsumerStatefulWidget {
  const CompareRestaurantsScreen({super.key});

  @override
  ConsumerState<CompareRestaurantsScreen> createState() => _CompareRestaurantsScreenState();
}

class _CompareRestaurantsScreenState extends ConsumerState<CompareRestaurantsScreen> {
  List<Restaurant> _restaurants = [];
  bool _isLoading = true;

  @override
  void initState() {
    super.initState();
    _loadRestaurants();
  }

  Future<void> _loadRestaurants() async {
    try {
      final restaurantRepo = RestaurantRepository(ref.read(apiClientProvider));
      final restaurants = await restaurantRepo.getRestaurants();
      if (mounted) {
        setState(() {
          _restaurants = restaurants;
          _isLoading = false;
        });
      }
    } catch (_) {
      if (mounted) setState(() => _isLoading = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: const WeloAppBar(title: 'Compare Restaurants'),
      body: _isLoading
          ? const Center(child: CircularProgressIndicator(color: WeloColors.sovereignOrange))
          : _restaurants.isEmpty
              ? const Center(child: Text('No restaurants available to compare'))
              : SingleChildScrollView(
                  padding: const EdgeInsets.all(16),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      const Text('Select restaurants to compare', style: TextStyle(color: WeloColors.textBody)),
                      const SizedBox(height: 16),
                      ..._restaurants.take(5).map((r) => Padding(
                            padding: const EdgeInsets.only(bottom: 12),
                            child: _buildCompareCard(restaurant: r),
                          )),
                    ],
                  ),
                ),
    );
  }

  Widget _buildCompareCard({required Restaurant restaurant}) {
    return WeloCard(
      padding: const EdgeInsets.all(16),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(restaurant.name, style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 16)),
          const SizedBox(height: 8),
          Text(restaurant.tags.join(', '), style: const TextStyle(color: WeloColors.textBody, fontSize: 13)),
          const SizedBox(height: 8),
          Row(
            children: [
              _buildStat(Icons.star, restaurant.rating.toStringAsFixed(1), WeloColors.sovereignGold),
              const SizedBox(width: 16),
              _buildStat(Icons.timer, restaurant.deliveryTime, WeloColors.sovereignOrange),
            ],
          ),
        ],
      ),
    );
  }

  Widget _buildStat(IconData icon, String text, Color color) {
    return Row(
      mainAxisSize: MainAxisSize.min,
      children: [
        Icon(icon, size: 14, color: color),
        const SizedBox(width: 4),
        Flexible(child: Text(text, style: const TextStyle(fontSize: 12), overflow: TextOverflow.ellipsis)),
      ],
    );
  }
}
