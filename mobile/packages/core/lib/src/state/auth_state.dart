import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'package:api/api.dart';
import 'cart_state.dart';

class AuthState {
  final bool isAuthenticated;
  final String? token;
  final bool isLoading;

  AuthState({
    this.isAuthenticated = false,
    this.token,
    this.isLoading = true,
  });

  AuthState copyWith({
    bool? isAuthenticated,
    String? token,
    bool? isLoading,
  }) {
    return AuthState(
      isAuthenticated: isAuthenticated ?? this.isAuthenticated,
      token: token ?? this.token,
      isLoading: isLoading ?? this.isLoading,
    );
  }
}

class AuthNotifier extends StateNotifier<AuthState> {
  final AuthRepository _authRepository;

  AuthNotifier(this._authRepository) : super(AuthState(isLoading: true)) {
    _loadToken();
  }

  Future<void> _loadToken() async {
    final prefs = await SharedPreferences.getInstance();
    final token = prefs.getString('auth_token');

    if (token != null && token.isNotEmpty) {
      _authRepository.apiClient.setToken(token);
      try {
        await _authRepository.getProfile();
        state = state.copyWith(isAuthenticated: true, token: token, isLoading: false);
      } on DioException catch (e) {
        if (e.response?.statusCode == 401) {
          await prefs.remove('auth_token');
          _authRepository.apiClient.clearToken();
          state = state.copyWith(isAuthenticated: false, token: null, isLoading: false);
        } else {
          state = state.copyWith(isAuthenticated: true, token: token, isLoading: false);
        }
      } catch (e) {
        state = state.copyWith(isAuthenticated: true, token: token, isLoading: false);
      }
    } else {
      state = state.copyWith(isAuthenticated: false, isLoading: false);
    }
  }

  /// Throws [AuthException] on failure with the actual API error message
  Future<String> login(String email, String password) async {
    state = state.copyWith(isLoading: true);
    try {
      final token = await _authRepository.login(email, password);
      final prefs = await SharedPreferences.getInstance();
      await prefs.setString('auth_token', token);
      _authRepository.apiClient.setToken(token);
      state = state.copyWith(isAuthenticated: true, token: token, isLoading: false);
      return token;
    } catch (e) {
      state = state.copyWith(isLoading: false);
      rethrow;
    }
  }

  Future<bool> requestOtp(String phone) async {
    try {
      state = state.copyWith(isLoading: true);
      await _authRepository.requestOtp(phone);
      state = state.copyWith(isLoading: false);
      return true;
    } catch (e) {
      state = state.copyWith(isLoading: false);
      rethrow;
    }
  }

  Future<Map<String, dynamic>> verifyOtp(String phone, String otp) async {
    try {
      state = state.copyWith(isLoading: true);
      final response = await _authRepository.verifyOtp(phone, otp);
      final token = response['token'];
      final prefs = await SharedPreferences.getInstance();
      await prefs.setString('auth_token', token);
      _authRepository.apiClient.setToken(token);
      state = state.copyWith(isAuthenticated: true, token: token, isLoading: false);
      return response;
    } catch (e) {
      state = state.copyWith(isLoading: false);
      rethrow;
    }
  }

  Future<Map<String, dynamic>> getProfile() async {
    try {
      return await _authRepository.getProfile();
    } catch (e) {
      return {'greeting': 'Welcome back.'};
    }
  }

  Future<void> logout() async {
    final prefs = await SharedPreferences.getInstance();
    await prefs.remove('auth_token');
    _authRepository.apiClient.clearToken();
    state = AuthState(isAuthenticated: false, isLoading: false);
  }

  /// Throws [AuthException] on failure with the actual API error message
  Future<String> register(String name, String email, String phone, String password) async {
    state = state.copyWith(isLoading: true);
    try {
      final token = await _authRepository.register(name, email, password);
      final prefs = await SharedPreferences.getInstance();
      await prefs.setString('auth_token', token);
      _authRepository.apiClient.setToken(token);
      state = state.copyWith(isAuthenticated: true, token: token, isLoading: false);
      return token;
    } catch (e) {
      state = state.copyWith(isLoading: false);
      rethrow;
    }
  }

  Future<void> requestPasswordResetOtp(String email) async {
    await _authRepository.requestPasswordResetOtp(email);
  }

  Future<String> verifyPasswordResetOtp(String email, String otp) async {
    return await _authRepository.verifyPasswordResetOtp(email, otp);
  }

  Future<void> resetPassword(String email, String resetToken, String newPassword) async {
    await _authRepository.resetPassword(email, resetToken, newPassword);
  }
}

final authProvider = StateNotifierProvider<AuthNotifier, AuthState>((ref) {
  final apiClient = ref.read(apiClientProvider);
  final authRepository = AuthRepository(apiClient);
  return AuthNotifier(authRepository);
});
