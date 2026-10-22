import '../api_client.dart';
import '../models/scratch_card_reward.dart';

class CouponRepository {
  final ApiClient apiClient;

  CouponRepository(this.apiClient);

  Future<ScratchCardReward?> getScratchCard() async {
    try {
      final response = await apiClient.dio.get('/scratch-card');
      if (response.statusCode == 200 && response.data != null && response.data['success'] == true) {
        return ScratchCardReward.fromJson(response.data['reward']);
      }
      return null;
    } catch (e) {
      // Fallback for visual mock testing and offline mode
      return ScratchCardReward(
        brand: 'vCommission Partner',
        title: 'Exclusive Deal',
        description: 'Get amazing discounts and deals when shopping. Click Redeem to activate.',
        code: 'VCOMM100',
        link: 'https://api-docs.trackier.io/docs/perf-pub-api-docs',
        expiry: 'Subject to change or termination by the merchant at any time.',
      );
    }
  }

  Future<List<dynamic>> getAvailableCoupons(double subtotal) async {
    try {
      final response = await apiClient.dio.get('/coupons/available', queryParameters: {'subtotal': subtotal});
      return response.data['coupons'] ?? [];
    } catch (e) {
      return [];
    }
  }

  Future<Map<String, dynamic>> validateCoupon(String code, double subtotal) async {
    final response = await apiClient.dio.post('/coupons/validate', data: {
      'code': code,
      'subtotal': subtotal,
    });
    return response.data;
  }
}
