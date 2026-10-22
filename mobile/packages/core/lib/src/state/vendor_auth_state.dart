import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'package:api/api.dart';
import 'cart_state.dart'; // To get apiClientProvider
import '../services/background_service_manager.dart';
import '../services/fcm_service.dart';

class VendorAuthState {
  final bool isAuthenticated;
  final String? token;
  final String? restaurantId;
  final bool isLoading;

  VendorAuthState({
    this.isAuthenticated = false,
    this.token,
    this.restaurantId,
    this.isLoading = true,
  });

  VendorAuthState copyWith({
    bool? isAuthenticated,
    String? token,
    String? restaurantId,
    bool? isLoading,
  }) {
    return VendorAuthState(
      isAuthenticated: isAuthenticated ?? this.isAuthenticated,
      token: token ?? this.token,
      restaurantId: restaurantId ?? this.restaurantId,
      isLoading: isLoading ?? this.isLoading,
    );
  }
}

class VendorAuthNotifier extends StateNotifier<VendorAuthState> {
  final VendorRepository _vendorRepository;

  VendorAuthNotifier(this._vendorRepository) : super(VendorAuthState(isLoading: true)) {
    _loadToken();
  }

  Future<void> _loadToken() async {
    final prefs = await SharedPreferences.getInstance();
    final token = prefs.getString('vendor_auth_token');
    
    if (token != null && token.isNotEmpty) {
      _vendorRepository.apiClient.setToken(token);
      final restaurantId = prefs.getString('vendor_restaurant_id');
      state = state.copyWith(isAuthenticated: true, token: token, restaurantId: restaurantId, isLoading: false);
    } else {
      state = state.copyWith(isAuthenticated: false, isLoading: false);
    }
  }

  /// Throws [AuthException] on failure with the actual API error message
  Future<String> login(String email, String password) async {
    state = state.copyWith(isLoading: true);
    try {
      final response = await _vendorRepository.login(email, password);
      final token = response['token'] as String;
      final restaurantId = response['restaurant']?['id']?.toString();
      final prefs = await SharedPreferences.getInstance();
      await prefs.setString('vendor_auth_token', token);
      if (restaurantId != null) {
        await prefs.setString('vendor_restaurant_id', restaurantId);
      }
      _vendorRepository.apiClient.setToken(token);
      FcmService().registerToken();
      state = state.copyWith(isAuthenticated: true, token: token, restaurantId: restaurantId, isLoading: false);
      return token;
    } catch (e) {
      state = state.copyWith(isLoading: false);
      rethrow;
    }
  }

  Future<void> logout() async {
    final prefs = await SharedPreferences.getInstance();
    await prefs.remove('vendor_auth_token');
    _vendorRepository.apiClient.clearToken();
    await BackgroundServiceManager.stop();
    state = VendorAuthState(isAuthenticated: false, isLoading: false);
  }
}

final vendorRepositoryProvider = Provider<VendorRepository>((ref) {
  return VendorRepository(ref.read(apiClientProvider));
});

final vendorAuthProvider = StateNotifierProvider<VendorAuthNotifier, VendorAuthState>((ref) {
  final repository = ref.read(vendorRepositoryProvider);
  return VendorAuthNotifier(repository);
});
