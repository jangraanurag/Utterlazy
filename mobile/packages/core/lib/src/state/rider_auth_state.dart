import 'dart:convert';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'package:api/api.dart';
import 'cart_state.dart';
import '../services/fcm_service.dart';

class RiderAuthState {
  final bool isAuthenticated;
  final String? token;
  final bool isLoading;
  final Map<String, dynamic>? riderData;

  RiderAuthState({
    this.isAuthenticated = false,
    this.token,
    this.isLoading = true,
    this.riderData,
  });

  RiderAuthState copyWith({
    bool? isAuthenticated,
    String? token,
    bool? isLoading,
    Map<String, dynamic>? riderData,
  }) {
    return RiderAuthState(
      isAuthenticated: isAuthenticated ?? this.isAuthenticated,
      token: token ?? this.token,
      isLoading: isLoading ?? this.isLoading,
      riderData: riderData ?? this.riderData,
    );
  }

  String get name => riderData?['name'] ?? 'Rider';
  String get phone => riderData?['phone'] ?? '';
  String get email => riderData?['email'] ?? '';
  String get vehicleType => riderData?['vehicle_type'] ?? '—';
  String get vehicleNumber => riderData?['vehicle_number'] ?? '—';
  bool get isVerified => riderData?['is_verified'] == true || riderData?['is_verified'] == 1;
  double get avgRating => (riderData?['avg_rating'] as num?)?.toDouble() ?? 0.0;
  int get totalDeliveries => (riderData?['total_deliveries'] as num?)?.toInt() ?? 0;
}

class RiderAuthNotifier extends StateNotifier<RiderAuthState> {
  final ApiClient _apiClient;

  RiderAuthNotifier(this._apiClient) : super(RiderAuthState(isLoading: true)) {
    _loadToken();
  }

  Future<void> _loadToken() async {
    try {
      final prefs = await SharedPreferences.getInstance();
      final token = prefs.getString('rider_auth_token');
      
      if (token != null && token.isNotEmpty) {
        _apiClient.setToken(token);
        // Restore rider data from cache
        final riderJson = prefs.getString('rider_data');
        final riderData = riderJson != null ? jsonDecode(riderJson) as Map<String, dynamic> : null;
        state = state.copyWith(isAuthenticated: true, token: token, riderData: riderData, isLoading: false);
      } else {
        state = state.copyWith(isAuthenticated: false, isLoading: false);
      }
    } catch (e) {
      state = state.copyWith(isAuthenticated: false, isLoading: false);
    }
  }

  /// Throws [AuthException] on failure with the actual API error message
  Future<String> login(String email, String password) async {
    state = state.copyWith(isLoading: true);
    try {
      final response = await _apiClient.dio.post('/rider/login', data: {
        'email': email,
        'password': password,
      });
      final data = response.data;
      final token = data['token'] as String;
      final riderData = data['rider'] as Map<String, dynamic>?;
      final riderId = riderData?['id']?.toString();
      final prefs = await SharedPreferences.getInstance();
      await prefs.setString('rider_auth_token', token);
      if (riderId != null) await prefs.setString('rider_id', riderId);
      if (riderData != null) await prefs.setString('rider_data', jsonEncode(riderData));
      _apiClient.setToken(token);
      FcmService().registerToken();
      state = state.copyWith(isAuthenticated: true, token: token, riderData: riderData, isLoading: false);
      return token;
    } catch (e) {
      state = state.copyWith(isLoading: false);
      rethrow;
    }
  }

  Future<void> logout() async {
    final prefs = await SharedPreferences.getInstance();
    await prefs.remove('rider_auth_token');
    _apiClient.clearToken();
    state = RiderAuthState(isAuthenticated: false, isLoading: false);
  }
}

final riderAuthProvider = StateNotifierProvider<RiderAuthNotifier, RiderAuthState>((ref) {
  final apiClient = ref.read(apiClientProvider);
  return RiderAuthNotifier(apiClient);
});
