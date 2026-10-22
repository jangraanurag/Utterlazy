import 'dart:developer';
import '../api_client.dart';
import '../models/restaurant.dart';
import '../models/menu_item.dart';

class RestaurantRepository {
  final ApiClient apiClient;

  RestaurantRepository(this.apiClient);

  Future<List<Restaurant>> getRestaurants({double? lat, double? lng, String? category, bool gstReady = false}) async {
    try {
      final queryParams = <String, dynamic>{};
      if (lat != null) queryParams['lat'] = lat;
      if (lng != null) queryParams['lng'] = lng;
      if (category != null && category.isNotEmpty) queryParams['category'] = category;
      if (gstReady) queryParams['gst_ready'] = true;
      final response = await apiClient.dio.get('/restaurants/ranked', queryParameters: queryParams);
      final List<dynamic> data = response.data['data'] ?? [];
      return data.map((json) => Restaurant.fromJson(json)).toList();
    } catch (e) {
      log('RestaurantRepository.getRestaurants error: $e');
      return [];
    }
  }

  Future<Restaurant> getRestaurant(String id) async {
    final response = await apiClient.dio.get('/restaurants/$id');
    return Restaurant.fromJson(response.data['data']);
  }

  Future<List<MenuItem>> getMenuItems(String restaurantId) async {
    try {
      final response = await apiClient.dio.get('/menu-items');
      final List<dynamic> data = response.data['data'] ?? [];
      return data
          .where((json) => json['restaurant_id'] == restaurantId)
          .map((json) => MenuItem.fromJson(json))
          .toList();
    } catch (e) {
      return [];
    }
  }

  Future<Map<String, dynamic>> searchCatalog(String query) async {
    final response = await apiClient.dio.get('/search', queryParameters: {'q': query});
    return response.data;
  }

  Future<Map<String, dynamic>> voiceSearch(String text) async {
    final response = await apiClient.dio.post('/search/voice', data: {'text': text});
    return response.data;
  }

  Future<List<dynamic>> getGlobalCategories() async {
    try {
      final response = await apiClient.dio.get('/global-categories');
      return response.data['categories'] ?? [];
    } catch (e) {
      return [];
    }
  }

  Future<List<dynamic>> getFavorites() async {
    final response = await apiClient.dio.get('/favorites');
    return response.data['data'] ?? [];
  }

  Future<Map<String, dynamic>> toggleFavorite(String restaurantId) async {
    final response = await apiClient.dio.post('/favorites/toggle', data: {'restaurant_id': restaurantId});
    return response.data;
  }

  Future<List<dynamic>> getRestaurantRatings(String restaurantId) async {
    final response = await apiClient.dio.get('/restaurants/$restaurantId/ratings');
    return response.data['ratings'] ?? [];
  }

  Future<Map<String, dynamic>> rateRestaurant(String restaurantId, int rating, {String? feedback, String? orderId}) async {
    final response = await apiClient.dio.post('/ratings', data: {
      'restaurant_id': restaurantId,
      'rating': rating,
      'feedback': feedback,
      'order_id': orderId,
    });
    return response.data;
  }

  Future<List<dynamic>> getRestaurantReviews(String restaurantId) async {
    final response = await apiClient.dio.get('/restaurants/$restaurantId/reviews');
    return response.data['reviews'] ?? [];
  }

  Future<Map<String, dynamic>> submitReview(Map<String, dynamic> data) async {
    final response = await apiClient.dio.post('/reviews', data: data);
    return response.data;
  }

  Future<List<dynamic>> getScheduledOrders() async {
    try {
      final response = await apiClient.dio.get('/scheduled-orders');
      return response.data['data'] ?? [];
    } catch (e) {
      return [];
    }
  }

  Future<Map<String, dynamic>> scheduleOrder(Map<String, dynamic> data) async {
    final response = await apiClient.dio.post('/scheduled-orders', data: data);
    return response.data;
  }

  Future<Map<String, dynamic>> createGroupOrder(Map<String, dynamic> data) async {
    final response = await apiClient.dio.post('/group-orders', data: data);
    return response.data;
  }

  Future<Map<String, dynamic>> joinGroupOrder(String inviteCode) async {
    final response = await apiClient.dio.get('/group-orders/$inviteCode/join');
    return response.data;
  }

  Future<Map<String, dynamic>> getSponsoredResults({String placement = 'listings'}) async {
    final params = <String, dynamic>{'placement': placement};
    final response = await apiClient.dio.get('/sponsored-results', queryParameters: params);
    return response.data;
  }

  Future<void> logAdImpression(String listingId) async {
    try {
      await apiClient.dio.post('/sponsored-impression', data: {'listing_id': listingId});
    } catch (_) {}
  }

  Future<void> logAdClick(String listingId) async {
    final sessionId = DateTime.now().millisecondsSinceEpoch.toString();
    try {
      await apiClient.dio.post('/sponsored-click', data: {'listing_id': listingId, 'session_id': sessionId});
    } catch (_) {}
  }
}
