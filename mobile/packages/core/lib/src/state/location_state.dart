import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'package:geolocator/geolocator.dart';
import '../services/location_service.dart';

class LocationState {
  final double latitude;
  final double longitude;
  final String name;
  final String address;
  final bool isLoading;

  LocationState({
    required this.latitude,
    required this.longitude,
    required this.name,
    required this.address,
    this.isLoading = false,
  });

  LocationState copyWith({
    double? latitude,
    double? longitude,
    String? name,
    String? address,
    bool? isLoading,
  }) {
    return LocationState(
      latitude: latitude ?? this.latitude,
      longitude: longitude ?? this.longitude,
      name: name ?? this.name,
      address: address ?? this.address,
      isLoading: isLoading ?? this.isLoading,
    );
  }
}

class LocationNotifier extends StateNotifier<LocationState> {
  final LocationService _locationService;

  LocationNotifier(this._locationService)
      : super(LocationState(
          latitude: 28.7942,
          longitude: 76.1384,
          name: 'BHIWANI CENTRE',
          address: 'Circular Road, Bhiwani, Haryana 127021',
        )) {
    _loadSavedLocation();
  }

  Future<void> _loadSavedLocation() async {
    try {
      final prefs = await SharedPreferences.getInstance();
      final lat = prefs.getDouble('selected_lat');
      final lng = prefs.getDouble('selected_lng');
      final name = prefs.getString('selected_name');
      final address = prefs.getString('selected_address');

      if (lat != null && lng != null && name != null && address != null) {
        state = LocationState(
          latitude: lat,
          longitude: lng,
          name: name,
          address: address,
        );
      }
    } catch (e) {
      // Ignore load error
    }
  }

  Future<void> setLocation({
    required double latitude,
    required double longitude,
    required String name,
    required String address,
  }) async {
    state = LocationState(
      latitude: latitude,
      longitude: longitude,
      name: name,
      address: address,
    );
    try {
      final prefs = await SharedPreferences.getInstance();
      await prefs.setDouble('selected_lat', latitude);
      await prefs.setDouble('selected_lng', longitude);
      await prefs.setString('selected_name', name);
      await prefs.setString('selected_address', address);
    } catch (e) {
      // Ignore save error
    }
  }

  Future<bool> detectCurrentLocation() async {
    state = state.copyWith(isLoading: true);
    try {
      bool serviceEnabled = await Geolocator.isLocationServiceEnabled();
      if (!serviceEnabled) {
        state = state.copyWith(isLoading: false);
        return false;
      }

      LocationPermission permission = await Geolocator.checkPermission();
      if (permission == LocationPermission.denied) {
        permission = await Geolocator.requestPermission();
        if (permission == LocationPermission.denied) {
          state = state.copyWith(isLoading: false);
          return false;
        }
      }
      
      if (permission == LocationPermission.deniedForever) {
        state = state.copyWith(isLoading: false);
        return false;
      }

      Position position = await Geolocator.getCurrentPosition();
      
      final reverseGeocodeData = await _locationService.reverseGeocode(
        position.latitude,
        position.longitude,
      );

      if (reverseGeocodeData != null && reverseGeocodeData['results'] != null && (reverseGeocodeData['results'] as List).isNotEmpty) {
        final result = (reverseGeocodeData['results'] as List).first;
        final address = result['formatted_address'] as String? ?? 'Current Location';
        
        String name = 'Current Location';
        final components = result['address_components'] as List?;
        if (components != null) {
          for (final c in components) {
            final types = c['types'] as List?;
            if (types != null && (types.contains('sublocality') || types.contains('sublocality_level_1'))) {
              name = c['long_name'] as String? ?? name;
              break;
            }
          }
        }

        await setLocation(
          latitude: position.latitude,
          longitude: position.longitude,
          name: name.toUpperCase(),
          address: address,
        );
        return true;
      } else {
        await setLocation(
          latitude: position.latitude,
          longitude: position.longitude,
          name: 'CURRENT LOCATION',
          address: '${position.latitude.toStringAsFixed(4)}, ${position.longitude.toStringAsFixed(4)}',
        );
        return true;
      }
    } catch (e) {
      state = state.copyWith(isLoading: false);
      return false;
    }
  }
}

final locationProvider = StateNotifierProvider<LocationNotifier, LocationState>((ref) {
  return LocationNotifier(LocationService());
});
