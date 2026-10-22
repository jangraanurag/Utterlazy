import 'package:dio/dio.dart';
import 'package:flutter/foundation.dart';

class LocationService {
  final Dio _dio = Dio();
  final String _apiKey = 'WoKIcPjKhh1a09ugBYDPrUW9QHjLNDrW6izkx25d'; // Will move to secure config later

  Future<List<Map<String, dynamic>>> autocomplete(String query) async {
    try {
      final response = await _dio.get(
        'https://api.olamaps.io/places/v1/autocomplete',
        queryParameters: {
          'input': query,
          'api_key': _apiKey,
        },
      );

      if (response.statusCode == 200) {
        return List<Map<String, dynamic>>.from(response.data['predictions']);
      }
      return [];
    } catch (e) {
      debugPrint('Ola Autocomplete Error: $e');
      return [];
    }
  }

  Future<Map<String, dynamic>?> getPlaceDetails(String placeId) async {
    try {
      final response = await _dio.get(
        'https://api.olamaps.io/places/v1/details',
        queryParameters: {
          'place_id': placeId,
          'api_key': _apiKey,
        },
      );

      if (response.statusCode == 200) {
        return response.data['result'];
      }
      return null;
    } catch (e) {
      debugPrint('Ola Details Error: $e');
      return null;
    }
  }

  Future<Map<String, dynamic>?> reverseGeocode(double lat, double lng) async {
    try {
      final response = await _dio.get(
        'https://api.olamaps.io/places/v1/reverse-geocode',
        queryParameters: {
          'latlng': '$lat,$lng',
          'api_key': _apiKey,
        },
      );

      if (response.statusCode == 200) {
        return response.data;
      }
      return null;
    } catch (e) {
      debugPrint('Ola Reverse Geocode Error: $e');
      return null;
    }
  }
}
