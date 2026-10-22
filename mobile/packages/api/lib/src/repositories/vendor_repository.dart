import '../api_client.dart';
import '../exceptions/auth_exception.dart';
import '../models/order.dart';
import 'package:dio/dio.dart';
import 'dart:io';

class VendorRepository {
  final ApiClient apiClient;

  VendorRepository(this.apiClient);

  Future<Map<String, dynamic>> login(String email, String password) async {
    try {
      final response = await apiClient.dio.post('/vendor/login', data: {
        'email': email,
        'password': password,
      });
      return response.data;
    } on DioException catch (e) {
      throw AuthException(_extractAuthError(e));
    }
  }

  Future<Map<String, dynamic>> getProfile() async {
    final response = await apiClient.dio.get('/vendor/me');
    return response.data;
  }

  Future<Map<String, dynamic>> toggleOnline() async {
    final response = await apiClient.dio.patch('/vendor/toggle-online');
    return response.data;
  }

  Future<Map<String, dynamic>> pauseOrders(int minutes) async {
    final response = await apiClient.dio.post('/vendor/pause', data: {
      'pause_duration': minutes,
    });
    return response.data;
  }

  Future<Map<String, dynamic>> resumeOrders() async {
    final response = await apiClient.dio.post('/vendor/resume');
    return response.data;
  }

  Future<Map<String, dynamic>> goOffline(String option, {String? customDatetime}) async {
    final response = await apiClient.dio.post('/vendor/offline', data: {
      'option': option,
      'custom_datetime': customDatetime,
    });
    return response.data;
  }

  Future<List<Order>> getActiveOrders() async {
    try {
      final response = await apiClient.dio.get('/vendor/orders/active');
      if (response.data['orders'] != null) {
        final List<dynamic> list = response.data['orders'];
        return list.map((json) => Order.fromJson(json)).toList();
      }
      return [];
    } catch (e) {
      return [];
    }
  }

  Future<void> acceptOrder(String orderId, {int extraMinutes = 0}) async {
    await apiClient.dio.patch('/vendor/orders/$orderId/accept', data: {
      'extra_minutes': extraMinutes,
    });
  }

  Future<void> rejectOrder(String orderId, String reason) async {
    await apiClient.dio.patch('/vendor/orders/$orderId/reject', data: {
      'reason': reason,
    });
  }

  Future<void> updateOrderStatus(String orderId, String status) async {
    await apiClient.dio.patch('/vendor/orders/$orderId/status', data: {
      'status': status,
    });
  }

  Future<void> reportKitchenFull(String orderId, int extraMinutes, {int action = 0, int? pauseDuration}) async {
    await apiClient.dio.post('/vendor/orders/$orderId/help/kitchen-full', data: {
      'extra_minutes': extraMinutes,
      'action': action,
      'pause_duration': pauseDuration,
    });
  }

  Future<void> reportItemOutOfStock(String orderId, String orderItemId, String action) async {
    await apiClient.dio.post('/vendor/orders/$orderId/help/out-of-stock', data: {
      'order_item_id': orderItemId,
      'action': action,
    });
  }

  Future<Map<String, dynamic>> getMenu() async {
    try {
      final response = await apiClient.dio.get('/vendor/menu');
      return response.data;
    } catch (e) {
      return {'categories': [], 'uncategorized': [], 'total_items': 0, 'active_items': 0};
    }
  }

  Future<Map<String, dynamic>> getEarnings(String period) async {
    try {
      final response = await apiClient.dio.get('/vendor/earnings', queryParameters: {
        'period': period,
      });
      return response.data;
    } catch (e) {
      return {
        'revenue': 0.0,
        'active_orders': 0,
        'completed_orders': 0,
        'today_earnings': 0.0,
        'orders_count': 0,
      };
    }
  }

  Future<Map<String, dynamic>> updateProfile(Map<String, dynamic> data) async {
    final response = await apiClient.dio.patch('/vendor/profile', data: data);
    return response.data;
  }

  Future<Map<String, dynamic>> updateOperatingHours(Map<String, dynamic> hours) async {
    final response = await apiClient.dio.patch('/vendor/operating-hours', data: {
      'operating_hours': hours,
    });
    return response.data;
  }

  Future<List<Order>> getAllOrders({String? status, int page = 1}) async {
    try {
      final queryParams = <String, dynamic>{'page': page};
      if (status != null && status != 'all') queryParams['status'] = status;
      final response = await apiClient.dio.get('/vendor/orders', queryParameters: queryParams);
      final dynamic ordersData = response.data['orders'];
      if (ordersData != null) {
        final List<dynamic> list = ordersData is Map ? (ordersData['data'] ?? []) : ordersData;
        return list.map((json) => Order.fromJson(json)).toList();
      }
      return [];
    } catch (e) {
      return [];
    }
  }

  Future<Map<String, dynamic>> addMenuItem(Map<String, dynamic> data) async {
    final response = await apiClient.dio.post('/vendor/menu/items', data: data);
    return response.data;
  }

  Future<Map<String, dynamic>> editMenuItem(String id, Map<String, dynamic> data) async {
    final response = await apiClient.dio.patch('/vendor/menu/items/$id', data: data);
    return response.data;
  }

  Future<void> removeMenuItem(String id) async {
    await apiClient.dio.delete('/vendor/menu/items/$id');
  }

  Future<Map<String, dynamic>> addCategory(Map<String, dynamic> data) async {
    final response = await apiClient.dio.post('/vendor/menu/categories', data: data);
    return response.data;
  }

  Future<Map<String, dynamic>> editCategory(String id, Map<String, dynamic> data) async {
    final response = await apiClient.dio.patch('/vendor/menu/categories/$id', data: data);
    return response.data;
  }

  Future<void> removeCategory(String id) async {
    await apiClient.dio.delete('/vendor/menu/categories/$id');
  }

  Future<Map<String, dynamic>> getStatement(int month, int year) async {
    final response = await apiClient.dio.get('/vendor/earnings/statement', queryParameters: {
      'month': month,
      'year': year,
    });
    return response.data;
  }

  Future<List<int>> downloadGstReport(int year, int month) async {
    final response = await apiClient.dio.get(
      '/vendor/gst/monthly/$year/$month',
      options: Options(responseType: ResponseType.bytes),
    );
    return response.data;
  }

  Future<List<int>> downloadAccountingReport(int year, int month) async {
    final response = await apiClient.dio.get(
      '/vendor/accounting/report/$year/$month',
      options: Options(responseType: ResponseType.bytes),
    );
    return response.data;
  }

  Future<List<dynamic>> getInvoices() async {
    final response = await apiClient.dio.get('/vendor/invoices');
    return response.data['invoices'] ?? response.data;
  }

  Future<List<dynamic>> getPayments() async {
    final response = await apiClient.dio.get('/vendor/payments');
    return response.data['payments'] ?? response.data;
  }

  // ─── Analytics ───────────────────────────────────────────────────────

  Future<Map<String, dynamic>> getAnalytics() async {
    try {
      final response = await apiClient.dio.get('/vendor/analytics');
      return response.data;
    } catch (e) {
      return {
        'today': {'revenue': 0.0, 'orders': 0, 'total_orders': 0, 'cancelled': 0, 'avg_order_value': 0, 'avg_rating': 0},
        'this_week': 0.0,
        'this_month': 0.0,
        'week_change': 0,
        'month_change': 0,
        'top_items': [],
        'hourly_breakdown': [],
        'daily_trend': [],
      };
    }
  }

  // ─── Inventory ───────────────────────────────────────────────────────

  Future<List<dynamic>> getInventoryItems() async {
    try {
      final response = await apiClient.dio.get('/vendor/inventory');
      return response.data['inventory'] ?? [];
    } catch (e) {
      return [];
    }
  }

  Future<Map<String, dynamic>> addInventoryItem(Map<String, dynamic> data) async {
    final response = await apiClient.dio.post('/vendor/inventory', data: data);
    return response.data;
  }

  Future<Map<String, dynamic>> updateInventoryItem(String id, Map<String, dynamic> data) async {
    final response = await apiClient.dio.patch('/vendor/inventory/$id', data: data);
    return response.data;
  }

  Future<List<dynamic>> getLowStockItems() async {
    try {
      final response = await apiClient.dio.get('/vendor/inventory/low-stock');
      return response.data['low_stock_items'] ?? [];
    } catch (e) {
      return [];
    }
  }

  // ─── Staff ───────────────────────────────────────────────────────────

  Future<List<dynamic>> getStaffMembers() async {
    try {
      final response = await apiClient.dio.get('/vendor/staff');
      return response.data['staff'] ?? [];
    } catch (e) {
      return [];
    }
  }

  Future<Map<String, dynamic>> addStaffMember(Map<String, dynamic> data) async {
    final response = await apiClient.dio.post('/vendor/staff', data: data);
    return response.data;
  }

  Future<Map<String, dynamic>> updateStaffMember(String id, Map<String, dynamic> data) async {
    final response = await apiClient.dio.patch('/vendor/staff/$id', data: data);
    return response.data;
  }

  Future<void> removeStaffMember(String id) async {
    await apiClient.dio.delete('/vendor/staff/$id');
  }

  // ─── Combo Offers ────────────────────────────────────────────────────

  Future<List<dynamic>> getComboOffers() async {
    try {
      final response = await apiClient.dio.get('/vendor/combos');
      return response.data['combo_offers'] ?? [];
    } catch (e) {
      return [];
    }
  }

  Future<Map<String, dynamic>> addComboOffer(Map<String, dynamic> data) async {
    final response = await apiClient.dio.post('/vendor/combos', data: data);
    return response.data;
  }

  Future<Map<String, dynamic>> updateComboOffer(String id, Map<String, dynamic> data) async {
    final response = await apiClient.dio.patch('/vendor/combos/$id', data: data);
    return response.data;
  }

  Future<void> removeComboOffer(String id) async {
    await apiClient.dio.delete('/vendor/combos/$id');
  }

  // ─── Offers ──────────────────────────────────────────────────────────

  Future<List<dynamic>> getOffers() async {
    try {
      final response = await apiClient.dio.get('/vendor/offers');
      return response.data['offers'] ?? [];
    } catch (e) {
      return [];
    }
  }

  Future<Map<String, dynamic>> addOffer(Map<String, dynamic> data) async {
    final response = await apiClient.dio.post('/vendor/offers', data: data);
    return response.data;
  }

  Future<Map<String, dynamic>> updateOffer(String id, Map<String, dynamic> data) async {
    final response = await apiClient.dio.patch('/vendor/offers/$id', data: data);
    return response.data;
  }

  Future<void> removeOffer(String id) async {
    await apiClient.dio.delete('/vendor/offers/$id');
  }

  Future<Map<String, dynamic>> toggleOfferStatus(String id) async {
    final response = await apiClient.dio.patch('/vendor/offers/$id/toggle');
    return response.data;
  }

  Future<Map<String, dynamic>> getOfferPerformance(String id) async {
    final response = await apiClient.dio.get('/vendor/offers/$id/performance');
    return response.data;
  }

  // ─── Ads / Sponsored Listings ────────────────────────────────────────

  Future<List<dynamic>> getAds() async {
    try {
      final response = await apiClient.dio.get('/vendor/ads');
      return response.data['ads'] ?? [];
    } catch (e) {
      return [];
    }
  }

  Future<Map<String, dynamic>> addAd(Map<String, dynamic> data) async {
    final response = await apiClient.dio.post('/vendor/ads', data: data);
    return response.data;
  }

  Future<Map<String, dynamic>> updateAd(String id, Map<String, dynamic> data) async {
    final response = await apiClient.dio.patch('/vendor/ads/$id', data: data);
    return response.data;
  }

  Future<void> removeAd(String id) async {
    await apiClient.dio.delete('/vendor/ads/$id');
  }

  Future<Map<String, dynamic>> toggleAd(String id) async {
    final response = await apiClient.dio.patch('/vendor/ads/$id/toggle');
    return response.data;
  }

  // ─── Review Reply ─────────────────────────────────────────────────────

  Future<Map<String, dynamic>> replyToReview(String reviewId, String reply, {dynamic refundAmount}) async {
    final data = <String, dynamic>{'vendor_reply': reply};
    if (refundAmount != null) {
      if (refundAmount is String && refundAmount == 'full') {
        data['refund_type'] = 'full';
      } else if (refundAmount is num) {
        data['refund_amount'] = refundAmount.toDouble();
      }
    }
    final response = await apiClient.dio.patch('/vendor/reviews/$reviewId/reply', data: data);
    return response.data;
  }

  // ─── Download Statement ──────────────────────────────────────────────

  Future<List<int>> downloadStatement(int month, int year) async {
    final response = await apiClient.dio.get(
      '/vendor/earnings/statement',
      queryParameters: {'month': month, 'year': year},
      options: Options(responseType: ResponseType.bytes),
    );
    return response.data;
  }

  // ─── File Upload ─────────────────────────────────────────────────────

  Future<String> uploadFile(File file, String type) async {
    final formData = FormData.fromMap({
      'file': await MultipartFile.fromFile(file.path, filename: file.path.split('/').last),
      'type': type,
    });
    final response = await apiClient.dio.post('/upload', data: formData);
    return response.data['url'] as String;
  }

  Future<Map<String, dynamic>> callCustomer(String orderId) async {
    final response = await apiClient.dio.post('/vendor/orders/$orderId/call-customer');
    return response.data;
  }

  // ─── Add-on Groups ──────────────────────────────────────────────────

  Future<List<dynamic>> getAddOnGroups() async {
    try {
      final response = await apiClient.dio.get('/vendor/add-on-groups');
      return response.data['add_on_groups'] ?? [];
    } catch (e) {
      return [];
    }
  }

  Future<Map<String, dynamic>> createAddOnGroup(Map<String, dynamic> data) async {
    final response = await apiClient.dio.post('/vendor/add-on-groups', data: data);
    return response.data;
  }

  Future<Map<String, dynamic>> updateAddOnGroup(String id, Map<String, dynamic> data) async {
    final response = await apiClient.dio.patch('/vendor/add-on-groups/$id', data: data);
    return response.data;
  }

  Future<void> deleteAddOnGroup(String id) async {
    await apiClient.dio.delete('/vendor/add-on-groups/$id');
  }

  Future<Map<String, dynamic>> addAddOnItem(String groupId, Map<String, dynamic> data) async {
    final response = await apiClient.dio.post('/vendor/add-on-groups/$groupId/items', data: data);
    return response.data;
  }

  Future<Map<String, dynamic>> updateAddOnItem(String id, Map<String, dynamic> data) async {
    final response = await apiClient.dio.patch('/vendor/add-on-items/$id', data: data);
    return response.data;
  }

  Future<void> deleteAddOnItem(String id) async {
    await apiClient.dio.delete('/vendor/add-on-items/$id');
  }

  // ─── Packaging Settings ──────────────────────────────────────────────

  Future<Map<String, dynamic>> getPackagingSettings() async {
    try {
      final response = await apiClient.dio.get('/vendor/packaging-settings');
      return response.data['packaging_settings'] ?? {};
    } catch (e) {
      return {};
    }
  }

  Future<Map<String, dynamic>> savePackagingSettings(Map<String, dynamic> data) async {
    final response = await apiClient.dio.put('/vendor/packaging-settings', data: data);
    return response.data;
  }

  // ─── BOGO Discounts ─────────────────────────────────────────────────

  Future<List<dynamic>> getBogoDiscounts() async {
    try {
      final response = await apiClient.dio.get('/vendor/bogo');
      return response.data['bogo_discounts'] ?? [];
    } catch (e) { return []; }
  }

  Future<Map<String, dynamic>> createBogo(Map<String, dynamic> data) async {
    final response = await apiClient.dio.post('/vendor/bogo', data: data);
    return response.data;
  }

  Future<Map<String, dynamic>> updateBogo(String id, Map<String, dynamic> data) async {
    final response = await apiClient.dio.patch('/vendor/bogo/$id', data: data);
    return response.data;
  }

  Future<void> deleteBogo(String id) async {
    await apiClient.dio.delete('/vendor/bogo/$id');
  }

  Future<void> toggleBogo(String id) async {
    await apiClient.dio.patch('/vendor/bogo/$id/toggle');
  }

  // ─── Opt-In Campaigns ───────────────────────────────────────────────

  Future<List<dynamic>> getAvailableCampaigns() async {
    try {
      final response = await apiClient.dio.get('/vendor/campaigns');
      return response.data['campaigns'] ?? [];
    } catch (e) { return []; }
  }

  Future<void> optInCampaign(String id) async {
    await apiClient.dio.post('/vendor/campaigns/$id/opt-in');
  }

  Future<void> optOutCampaign(String id) async {
    await apiClient.dio.post('/vendor/campaigns/$id/opt-out');
  }

  // ─── Approvals (Account Manager) ──────────────────────────────────────

  Future<Map<String, dynamic>> getPendingApprovals() async {
    final response = await apiClient.dio.get('/account-manager/pending');
    return response.data;
  }

  Future<Map<String, dynamic>> getApprovalHistory() async {
    final response = await apiClient.dio.get('/account-manager/history');
    return response.data;
  }

  Future<void> sendApprovalOtp(String entityType, String entityId) async {
    await apiClient.dio.post('/account-manager/send-otp', data: {
      'entity_type': entityType,
      'entity_id': entityId,
    });
  }

  Future<Map<String, dynamic>> verifyApprovalOtp(String entityType, String entityId, String otp) async {
    final response = await apiClient.dio.post('/account-manager/verify-otp', data: {
      'entity_type': entityType,
      'entity_id': entityId,
      'otp': otp,
      'action': 'approve',
    });
    return response.data;
  }

  Future<Map<String, dynamic>> rejectApproval(String entityType, String entityId, {String? reason}) async {
    final response = await apiClient.dio.post('/account-manager/reject', data: {
      'entity_type': entityType,
      'entity_id': entityId,
      'rejection_reason': reason ?? '',
    });
    return response.data;
  }

  // ─── Payout Cycles ─────────────────────────────────────────────────

  Future<Map<String, dynamic>> getPayoutCycles({int page = 1, String? status, String? fromDate, String? toDate}) async {
    final params = <String, dynamic>{'page': page};
    if (status != null) params['status'] = status;
    if (fromDate != null) params['from_date'] = fromDate;
    if (toDate != null) params['to_date'] = toDate;
    try {
      final response = await apiClient.dio.get('/vendor/payouts', queryParameters: params);
      return response.data;
    } catch (e) {
      return {'cycles': [], 'current_balance': 0};
    }
  }

  Future<Map<String, dynamic>> getPayoutCycleDetail(String cycleId) async {
    try {
      final response = await apiClient.dio.get('/vendor/payouts/$cycleId');
      return response.data;
    } catch (e) {
      return {};
    }
  }

  Future<Map<String, dynamic>> getVendorInvoices({String? type, int? year, int? month}) async {
    final params = <String, dynamic>{};
    if (type != null) params['type'] = type;
    if (year != null) params['year'] = year;
    if (month != null) params['month'] = month;
    try {
      final response = await apiClient.dio.get('/vendor/invoices', queryParameters: params);
      return response.data;
    } catch (e) {
      return {'invoices': []};
    }
  }

  Future<List<int>> downloadInvoicePdf(String invoiceId) async {
    final response = await apiClient.dio.get(
      '/vendor/invoices/$invoiceId/download',
      options: Options(responseType: ResponseType.bytes),
    );
    return response.data;
  }

  Future<List<int>> downloadInvoiceBundle(String type, int year, int month) async {
    final response = await apiClient.dio.post(
      '/vendor/invoices/download-bundle',
      data: {'type': type, 'year': year, 'month': month},
      options: Options(responseType: ResponseType.bytes),
    );
    return response.data;
  }

  Future<List<int>> downloadGstr1Report(int year, int month) async {
    final response = await apiClient.dio.get(
      '/vendor/gst/gstr1-report',
      queryParameters: {'year': year, 'month': month},
      options: Options(responseType: ResponseType.bytes),
    );
    return response.data;
  }

  // ─── Reporting Module ────────────────────────────────────────────────────

  Future<Map<String, dynamic>> getLiveTracking({String? date}) async {
    final params = <String, dynamic>{};
    if (date != null) params['date'] = date;
    final response = await apiClient.dio.get(
      '/vendor/reporting/live-tracking',
      queryParameters: params,
    );
    return response.data;
  }

  Future<Map<String, dynamic>> getTrendTable({
    required String granularity,
    required String start,
    required String end,
  }) async {
    final response = await apiClient.dio.get(
      '/vendor/reporting/trend-table',
      queryParameters: {
        'granularity': granularity,
        'start': start,
        'end': end,
      },
    );
    return response.data;
  }

  Future<List<int>> downloadReport({
    required String type,
    required String granularity,
    required String start,
    required String end,
  }) async {
    final response = await apiClient.dio.post(
      '/vendor/reporting/export',
      data: {
        'type': type,
        'granularity': granularity,
        'start': start,
        'end': end,
      },
      options: Options(responseType: ResponseType.bytes),
    );
    return response.data;
  }

  static String _extractAuthError(DioException e) {
    final data = e.response?.data;
    if (data is Map) {
      if (data['error'] is String) return data['error'] as String;
      if (data['message'] is String) return data['message'] as String;
    }
    if (e.type == DioExceptionType.connectionTimeout || e.type == DioExceptionType.receiveTimeout) {
      return 'Connection timed out. Please check your internet.';
    }
    if (e.type == DioExceptionType.connectionError) {
      return 'Unable to connect. Please try again.';
    }
    return 'Something went wrong. Please try again.';
  }
}
