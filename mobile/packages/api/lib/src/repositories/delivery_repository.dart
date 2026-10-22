import 'dart:developer';
import 'package:dio/dio.dart';
import '../api_client.dart';
import '../models/order.dart';

class DeliveryRepository {
  final ApiClient _apiClient;

  DeliveryRepository(this._apiClient);

  Future<void> setRiderStatus(bool isOnline) async {
    await _apiClient.dio.patch('/rider/toggle-online', data: {
      'is_online': isOnline ? 1 : 0,
    });
  }

  Future<Order?> getPendingOrder() async {
    try {
      final response = await _apiClient.dio.get('/rider/current-assignment');
      final data = response.data;
      if (data == null) {
        log('[DeliveryRepo] response.data is null');
        return null;
      }

      final assignment = data['assignment'];

      // Check for pending assignment first, then active order (already accepted)
      var orderData = assignment != null ? assignment['order'] : null;
      if (orderData == null && data['active_order'] != null) {
        orderData = data['active_order'];
      }

      if (orderData == null) {
        return null;
      }

      final eta = (assignment != null ? assignment['distance_eta'] : null) ?? <String, dynamic>{};
      final restaurant = orderData['restaurant'] ?? <String, dynamic>{};

      orderData['assignment_id'] = assignment?['id']?.toString();
      orderData['restaurant_name'] = restaurant['name'];
      orderData['restaurant_address'] = restaurant['address'];
      orderData['restaurant_latitude'] = restaurant['latitude'];
      orderData['restaurant_longitude'] = restaurant['longitude'];
      orderData['delivery_address_text'] ??= orderData['delivery_address'] ?? orderData['address']?['street'];
      orderData['distance_eta'] = eta;

      final order = Order.fromJson(orderData);
      log('[DeliveryRepo] Order created: ${order.id}, status=${order.status}, total=${order.totalAmount}');
      return order;
    } on DioException catch (e) {
      log('[DeliveryRepo] DioException: ${e.response?.statusCode} ${e.message}');
      return null;
    } catch (e) {
      log('[DeliveryRepo] Unexpected error: $e');
      return null;
    }
  }

  Future<void> acceptOrder(String orderId) async {
    await _apiClient.dio.post('/rider/assignment/$orderId/accept');
  }

  Future<void> rejectOrder(String assignmentId) async {
    await _apiClient.dio.post('/rider/assignment/$assignmentId/reject');
  }

  Future<void> updateOrderStatus(String orderId, String status, {String? deliveryOtp}) async {
    await _apiClient.dio.patch('/orders/$orderId/status', data: {
      'status': status,
      'delivery_otp': deliveryOtp,
    });
  }

  Future<void> markRiderArrived(String orderId) async {
    await _apiClient.dio.post('/orders/$orderId/rider-arrived');
  }

  Future<void> markRiderAtCustomer(String orderId) async {
    await _apiClient.dio.post('/orders/$orderId/rider-at-customer');
  }

  Future<void> updateLocation(double lat, double lng) async {
    await _apiClient.dio.patch('/rider/location', data: {
      'latitude': lat,
      'longitude': lng,
    });
  }

  Future<Map<String, dynamic>> callCustomer(String orderId) async {
    final response = await _apiClient.dio.post('/rider/orders/$orderId/call-customer');
    return response.data;
  }

  Future<Map<String, dynamic>> callRestaurant(String orderId) async {
    final response = await _apiClient.dio.post('/rider/orders/$orderId/call-restaurant');
    return response.data;
  }

  Future<Map<String, dynamic>> getDocuments() async {
    final response = await _apiClient.dio.get('/rider/documents');
    return response.data;
  }

  Future<Map<String, dynamic>> uploadDocument(String documentType, String filePath, {String? documentNumber}) async {
    final formData = FormData.fromMap({
      'document_type': documentType,
      'document_number': documentNumber,
      'file': await MultipartFile.fromFile(filePath),
    });
    final response = await _apiClient.dio.post('/rider/documents/upload', data: formData);
    return response.data;
  }

  Future<Map<String, dynamic>> getTrainingModules() async {
    final response = await _apiClient.dio.get('/rider/training/modules');
    return response.data;
  }

  Future<void> markLessonComplete(String moduleId, String lessonId) async {
    await _apiClient.dio.post('/rider/training/modules/$moduleId/lessons/$lessonId/complete');
  }

  Future<Map<String, dynamic>> submitQuiz(String moduleId, List<int> answers) async {
    final response = await _apiClient.dio.post('/rider/training/modules/$moduleId/quiz', data: {
      'answers': answers,
    });
    return response.data;
  }

  Future<Map<String, dynamic>> getTrainingProgress() async {
    final response = await _apiClient.dio.get('/rider/training/progress');
    return response.data;
  }

  // ─── Dashboard (§2) ───
  Future<Map<String, dynamic>> getDashboard({String period = 'week', String? startDate, String? endDate}) async {
    final params = <String, dynamic>{'period': period};
    if (startDate != null) params['start_date'] = startDate;
    if (endDate != null) params['end_date'] = endDate;
    final response = await _apiClient.dio.get('/rider/dashboard', queryParameters: params);
    return response.data;
  }

  // ─── History (§3) ───
  Future<Map<String, dynamic>> getHistory({int page = 1}) async {
    final response = await _apiClient.dio.get('/rider/history', queryParameters: {'page': page});
    return response.data;
  }

  // ─── Payout Cycles (§5) ───
  Future<Map<String, dynamic>> getPayoutCycles({int page = 1}) async {
    final response = await _apiClient.dio.get('/rider/payout-cycles', queryParameters: {'page': page});
    return response.data;
  }

  Future<Map<String, dynamic>> getPayoutCycleDetail(String cycleId) async {
    final response = await _apiClient.dio.get('/rider/payout-cycles/$cycleId');
    return response.data;
  }

  // ─── Pay Calculator ───
  Future<Map<String, dynamic>> calculatePay(double distanceKm) async {
    final response = await _apiClient.dio.post('/rider/calculate-pay', data: {'distance_km': distanceKm});
    return response.data;
  }
}
