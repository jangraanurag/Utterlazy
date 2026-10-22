import 'dart:developer';
import 'dart:io';
import 'package:flutter/foundation.dart';
import 'package:dio/dio.dart';

class ApiConfig {
  static String get baseUrl {
    // Priority: 1. Environment variable, 2. Hardcoded, 3. Default
    try {
      // Check for .env file in assets
      final env = Platform.environment;
      if (env['UTTERLAZY_API_URL'] != null) return env['UTTERLAZY_API_URL']!;
    } catch (_) {}

    // For different build flavors
    if (kReleaseMode) {
      return 'https://api.utterlazy.com/api';
    }
    // Debug/emulator
    return 'http://10.0.2.2:8001/api';
  }

  static String get wsUrl => baseUrl.replaceFirst('/api', '').replaceFirst('http', 'ws');
}

class ApiClient {
  late final Dio dio;

  ApiClient({String? baseUrl}) {
    dio = Dio(BaseOptions(
      baseUrl: baseUrl ?? ApiConfig.baseUrl,
      connectTimeout: const Duration(seconds: 30),
      receiveTimeout: const Duration(seconds: 30),
      headers: {
        'Accept': 'application/json',
        'Content-Type': 'application/json',
      },
    ));

    dio.interceptors.add(InterceptorsWrapper(
      onRequest: (options, handler) {
        log('REQUEST[${options.method}] => PATH: ${options.path}');
        return handler.next(options);
      },
      onResponse: (response, handler) {
        log('RESPONSE[${response.statusCode}] => PATH: ${response.requestOptions.path}');
        return handler.next(response);
      },
      onError: (DioException e, handler) {
        log('ERROR[${e.response?.statusCode}] => PATH: ${e.requestOptions.path}');
        return handler.next(e);
      },
    ));
  }

  void setToken(String token) {
    dio.options.headers['Authorization'] = 'Bearer $token';
  }

  void clearToken() {
    dio.options.headers.remove('Authorization');
  }
}
