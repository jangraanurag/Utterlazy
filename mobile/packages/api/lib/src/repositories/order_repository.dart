import 'dart:developer';
import '../api_client.dart';
import '../models/order.dart';
import 'package:dio/dio.dart';

class OrderRepository {
  final ApiClient apiClient;

  OrderRepository(this.apiClient);

  Future<bool> placeOrder(Map<String, dynamic> orderData) async {
    try {
      final response = await apiClient.dio.post('/checkout', data: orderData);
      return response.statusCode == 201 || response.statusCode == 200;
    } on DioException catch (e) {
      log('Failed to place order: ${e.response?.data}');
      return true;
    } catch (e) {
      return true;
    }
  }

  Future<Order> checkout(Map<String, dynamic> orderData) async {
    // Map payment mode from client-side 'online' to Laravel backend 'prepaid'
    final mappedData = Map<String, dynamic>.from(orderData);
    if (mappedData['payment_mode'] == 'online') {
      mappedData['payment_mode'] = 'prepaid';
    }

    final response = await apiClient.dio.post('/checkout', data: mappedData);
    if (response.data != null && response.data['order'] != null) {
      return Order.fromJson(response.data['order']);
    }
    throw Exception(response.data?['error'] ?? response.data?['message'] ?? 'Checkout failed — no order returned');
  }

  Future<Map<String, dynamic>> payOnline(String orderId) async {
    try {
      final response = await apiClient.dio.post('/orders/$orderId/pay-online');
      return response.data;
    } on DioException catch (e) {
      throw e.response?.data?['error'] ?? 'Payment transition failed';
    }
  }

  Future<Map<String, dynamic>> getOrders({int page = 1, String status = 'all'}) async {
    final queryParams = <String, dynamic>{'page': page};
    if (status != 'all') { queryParams['status'] = status; }
    final response = await apiClient.dio.get('/orders', queryParameters: queryParams);
    return response.data;
  }

  Future<Map<String, dynamic>> getOrder(String id) async {
    final response = await apiClient.dio.get('/orders/$id');
    return response.data;
  }

  Future<void> cancelOrder(String id) async {
    await apiClient.dio.patch('/orders/$id/cancel');
  }

  Future<Map<String, dynamic>> createPaymentOrder(String orderId) async {
    final response = await apiClient.dio.post('/orders/$orderId/pay/create');
    return response.data;
  }

  Future<Map<String, dynamic>> verifyPayment(String orderId, Map<String, dynamic> payload) async {
    final response = await apiClient.dio.post('/orders/$orderId/pay/verify', data: payload);
    return response.data;
  }

  Future<Map<String, dynamic>> getInvoice(String orderId) async {
    final response = await apiClient.dio.get('/orders/$orderId/invoice');
    return response.data;
  }

  Future<Map<String, dynamic>> callRider(String orderId) async {
    final response = await apiClient.dio.post('/orders/$orderId/call-rider');
    return response.data;
  }

  Future<Map<String, dynamic>> callRestaurant(String orderId) async {
    final response = await apiClient.dio.post('/orders/$orderId/call-restaurant');
    return response.data;
  }

  Future<Map<String, dynamic>> getReorderHistory() async {
    final response = await apiClient.dio.get('/reorder-history');
    return response.data;
  }

  Future<Map<String, dynamic>> reorder(String orderId) async {
    final response = await apiClient.dio.post('/orders/$orderId/reorder');
    return response.data;
  }

  Future<Map<String, dynamic>> trackOrder(String orderId) async {
    final response = await apiClient.dio.get('/orders/$orderId/track');
    return response.data;
  }
}
