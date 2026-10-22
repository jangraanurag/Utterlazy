import '../api_client.dart';
import '../exceptions/auth_exception.dart';
import 'package:dio/dio.dart';

class AuthRepository {
  final ApiClient apiClient;

  AuthRepository(this.apiClient);

  /// Returns the token string on success.
  /// Throws `AuthException` with a user-friendly message on failure.
  Future<String> login(String email, String password) async {
    try {
      final response = await apiClient.dio.post('/auth/login', data: {
        'email': email,
        'password': password,
      });
      return response.data['token'] as String;
    } on DioException catch (e) {
      final msg = _extractError(e);
      throw AuthException(msg);
    }
  }

  Future<String> register(String name, String email, String password) async {
    try {
      final response = await apiClient.dio.post('/auth/register', data: {
        'name': name,
        'email': email,
        'password': password,
        'password_confirmation': password,
      });
      return response.data['token'] as String;
    } on DioException catch (e) {
      throw AuthException(_extractError(e));
    }
  }

  Future<void> requestOtp(String phone) async {
    try {
      await apiClient.dio.post('/auth/otp/request', data: {'phone': phone});
    } on DioException catch (e) {
      throw AuthException(_extractError(e));
    }
  }

  Future<Map<String, dynamic>> verifyOtp(String phone, String otp) async {
    try {
      final response = await apiClient.dio.post('/auth/otp/verify', data: {
        'phone': phone,
        'otp': otp,
      });
      return response.data;
    } on DioException catch (e) {
      throw AuthException(_extractError(e));
    }
  }

  Future<Map<String, dynamic>> getProfile() async {
    final response = await apiClient.dio.get('/auth/me');
    return response.data;
  }

  Future<Map<String, dynamic>> updateProfile(Map<String, dynamic> data) async {
    final response = await apiClient.dio.patch('/auth/profile', data: data);
    return response.data;
  }

  Future<void> changePassword(Map<String, dynamic> data) async {
    try {
      await apiClient.dio.post('/auth/change-password', data: data);
    } on DioException catch (e) {
      throw AuthException(_extractError(e));
    }
  }

  /// Request a password reset OTP for the given email
  Future<void> requestPasswordResetOtp(String email) async {
    try {
      await apiClient.dio.post('/auth/password-reset/request', data: {
        'email': email,
      });
    } on DioException catch (e) {
      throw AuthException(_extractError(e));
    }
  }

  /// Verify password reset OTP and return a reset token
  Future<String> verifyPasswordResetOtp(String email, String otp) async {
    try {
      final response = await apiClient.dio.post('/auth/password-reset/verify', data: {
        'email': email,
        'otp': otp,
      });
      return response.data['reset_token'] as String;
    } on DioException catch (e) {
      throw AuthException(_extractError(e));
    }
  }

  /// Reset password using verified reset token
  Future<void> resetPassword(String email, String resetToken, String newPassword) async {
    try {
      await apiClient.dio.post('/auth/password-reset/reset', data: {
        'email': email,
        'reset_token': resetToken,
        'password': newPassword,
        'password_confirmation': newPassword,
      });
    } on DioException catch (e) {
      throw AuthException(_extractError(e));
    }
  }

  Future<void> logout() async {
    try {
      await apiClient.dio.post('/auth/logout');
    } catch (e) {
      // Ignore logout errors
    }
  }

  String _extractError(DioException e) {
    final data = e.response?.data;
    if (data is Map) {
      // Laravel validation errors: { message: "...", errors: { field: ["..."] } }
      if (data['errors'] is Map) {
        final errors = data['errors'] as Map;
        if (errors['email'] is List) return (errors['email'] as List).first.toString();
        if (errors['phone'] is List) return (errors['phone'] as List).first.toString();
        if (errors['otp'] is List) return (errors['otp'] as List).first.toString();
        // Flatten first error from any field
        for (final v in errors.values) {
          if (v is List && v.isNotEmpty) return v.first.toString();
        }
      }
      if (data['message'] is String) return data['message'] as String;
      if (data['error'] is String) return data['error'] as String;
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
