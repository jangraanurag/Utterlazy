import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:ui/ui.dart';
import 'package:api/api.dart';
import 'package:core/core.dart';

class LocationSelectionScreen extends ConsumerStatefulWidget {
  const LocationSelectionScreen({super.key});

  @override
  ConsumerState<LocationSelectionScreen> createState() => _LocationSelectionScreenState();
}

class _LocationSelectionScreenState extends ConsumerState<LocationSelectionScreen> {
  final _searchController = TextEditingController();
  final _locationService = LocationService();
  List<Map<String, dynamic>> _predictions = [];
  List<Map<String, dynamic>> _savedLocations = [];
  bool _isSearching = false;

  @override
  void initState() {
    super.initState();
    _loadSavedLocations();
  }

  Future<void> _loadSavedLocations() async {
    try {
      final addressRepo = AddressRepository(ref.read(apiClientProvider));
      final locations = await addressRepo.getAddresses();
      if (mounted) {
        setState(() {
          _savedLocations = locations.map<Map<String, dynamic>>((a) {
            return {
              'id': a.id,
              'name': a.label,
              'address': [a.flatBuilding, a.street, a.city].where((s) => s != null && s.isNotEmpty).join(', '),
              'latitude': a.latitude,
              'longitude': a.longitude,
            };
          }).toList();
        });
      }
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('Failed to load saved locations'), backgroundColor: Colors.red),
        );
      }
    }
  }

  void _onSearchChanged(String value) async {
    if (value.length < 3) {
      setState(() => _predictions = []);
      return;
    }

    setState(() => _isSearching = true);
    final results = await _locationService.autocomplete(value);
    setState(() {
      _predictions = results;
      _isSearching = false;
    });
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: Colors.white,
      appBar: AppBar(
        backgroundColor: Colors.white,
        elevation: 0,
        leading: IconButton(
          icon: const Icon(Icons.arrow_back, color: Colors.black),
          onPressed: () => Navigator.pop(context),
        ),
        title: const Text(
          'Location Selection',
          style: TextStyle(color: Colors.black, fontWeight: FontWeight.w900),
        ),
      ),
      body: SingleChildScrollView(
        padding: const EdgeInsets.all(24.0),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            const Text(
              'Where should we deliver\nyour culinary mission?',
              style: TextStyle(
                fontSize: 28,
                fontWeight: FontWeight.w900,
                color: Colors.black,
                height: 1.2,
                letterSpacing: -0.5,
              ),
            ),
            const SizedBox(height: 32),
            _buildSearchBar(),
            const SizedBox(height: 40),
            if (_predictions.isEmpty) ...[
              _buildDetectLocationButton(),
              const SizedBox(height: 48),
              const Text(
                'SAVED LOCATIONS',
                style: TextStyle(color: Colors.black38, letterSpacing: 2, fontWeight: FontWeight.w900, fontSize: 10),
              ),
              const SizedBox(height: 24),
              if (_savedLocations.isEmpty)
                const Padding(
                  padding: EdgeInsets.symmetric(vertical: 20),
                  child: Text('No saved locations yet. Search and save a location.', style: TextStyle(color: Colors.black38)),
                )
              else
                ..._savedLocations.map((loc) {
                  final icon = loc['name']?.toString().toUpperCase() == 'HOME'
                      ? Icons.home_outlined
                      : loc['name']?.toString().toUpperCase() == 'OFFICE'
                          ? Icons.business_center_outlined
                          : Icons.location_on_outlined;
                  final lat = (loc['latitude'] as num?)?.toDouble() ?? 0.0;
                  final lng = (loc['longitude'] as num?)?.toDouble() ?? 0.0;
                  return _buildSavedLocationItem(icon, loc['name'] ?? 'Saved', loc['address'] ?? '', lat, lng);
                }),
            ] else ...[
              const Text(
                'SEARCH RESULTS',
                style: TextStyle(color: WeloColors.sovereignOrange, letterSpacing: 2, fontWeight: FontWeight.w900, fontSize: 10),
              ),
              const SizedBox(height: 24),
              ..._predictions.map((p) => _buildSearchResultItem(p)),
            ],
          ],
        ),
      ),
    );
  }

  Widget _buildSearchBar() {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 20, vertical: 4),
      decoration: BoxDecoration(
        color: const Color(0xFFF8F8F8),
        borderRadius: BorderRadius.circular(20),
        border: Border.all(color: const Color(0xFFF1F1F1)),
      ),
      child: TextField(
        controller: _searchController,
        onChanged: _onSearchChanged,
        style: const TextStyle(color: Colors.black, fontWeight: FontWeight.w700),
        decoration: InputDecoration(
          hintText: 'Search for area, street...',
          hintStyle: const TextStyle(color: Colors.black26, fontWeight: FontWeight.w500),
          prefixIcon: const Icon(Icons.search, color: WeloColors.sovereignOrange),
          suffixIcon: _isSearching 
            ? const SizedBox(width: 20, height: 20, child: Padding(padding: EdgeInsets.all(12), child: CircularProgressIndicator(strokeWidth: 2, color: WeloColors.sovereignOrange)))
            : null,
          border: InputBorder.none,
        ),
      ),
    );
  }

  Widget _buildDetectLocationButton() {
    final locationState = ref.watch(locationProvider);
    return InkWell(
      onTap: locationState.isLoading
          ? null
          : () async {
              final success = await ref.read(locationProvider.notifier).detectCurrentLocation();
              if (mounted) {
                if (success) {
                  Navigator.pop(context);
                } else {
                  ScaffoldMessenger.of(context).showSnackBar(
                    const SnackBar(content: Text('Failed to detect current location. Please verify GPS permissions.')),
                  );
                }
              }
            },
      child: Container(
        padding: const EdgeInsets.all(24),
        decoration: BoxDecoration(
          color: WeloColors.sovereignOrange.withValues(alpha: 0.05),
          borderRadius: BorderRadius.circular(24),
          border: Border.all(color: WeloColors.sovereignOrange.withValues(alpha: 0.1)),
        ),
        child: Row(
          children: [
            const Icon(Icons.my_location, color: WeloColors.sovereignOrange),
            const SizedBox(width: 16),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    locationState.isLoading ? 'Detecting Location...' : 'Detect My Current Location',
                    style: const TextStyle(color: WeloColors.sovereignOrange, fontWeight: FontWeight.w900, fontSize: 15),
                  ),
                  const Text(
                    'Using GPS Intelligence',
                    style: TextStyle(color: Colors.black26, fontSize: 10, fontWeight: FontWeight.w700),
                  ),
                ],
              ),
            ),
            if (locationState.isLoading)
              const SizedBox(
                width: 20,
                height: 20,
                child: CircularProgressIndicator(strokeWidth: 2, color: WeloColors.sovereignOrange),
              )
            else
              Icon(Icons.chevron_right, color: WeloColors.sovereignOrange.withValues(alpha: 0.3)),
          ],
        ),
      ),
    );
  }

  Widget _buildSavedLocationItem(IconData icon, String title, String subtitle, double lat, double lng) {
    return InkWell(
      onTap: () async {
        await ref.read(locationProvider.notifier).setLocation(
              latitude: lat,
              longitude: lng,
              name: title.toUpperCase(),
              address: subtitle,
            );
        if (mounted) Navigator.pop(context);
      },
      child: Padding(
        padding: const EdgeInsets.only(bottom: 24.0),
        child: Row(
          children: [
            Container(
              width: 48,
              height: 48,
              decoration: BoxDecoration(
                color: const Color(0xFFF8F8F8),
                borderRadius: BorderRadius.circular(12),
                border: Border.all(color: const Color(0xFFF1F1F1)),
              ),
              child: Icon(icon, color: Colors.black45, size: 24),
            ),
            const SizedBox(width: 16),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(title, style: const TextStyle(color: Colors.black, fontWeight: FontWeight.w900, fontSize: 15)),
                  const SizedBox(height: 4),
                  Text(subtitle, style: const TextStyle(color: Colors.black38, fontSize: 13, fontWeight: FontWeight.w500), overflow: TextOverflow.ellipsis, maxLines: 2),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildSearchResultItem(Map<String, dynamic> prediction) {
    return InkWell(
      onTap: () async {
        setState(() => _isSearching = true);
        final details = await _locationService.getPlaceDetails(prediction['place_id'] ?? '');
        setState(() => _isSearching = false);
        if (details != null) {
          final lat = details['geometry']?['location']?['lat'];
          final lng = details['geometry']?['location']?['lng'];
          final formattedAddress = details['formatted_address'] ?? prediction['description'];
          final name = details['name'] ?? prediction['description']?.split(',').first ?? 'Search Result';
          
          if (lat != null && lng != null) {
            await ref.read(locationProvider.notifier).setLocation(
                  latitude: double.parse(lat.toString()),
                  longitude: double.parse(lng.toString()),
                  name: name.toString().toUpperCase(),
                  address: formattedAddress.toString(),
                );
            if (mounted) Navigator.pop(context);
          }
        }
      },
      child: Padding(
        padding: const EdgeInsets.only(bottom: 24.0),
        child: Row(
          children: [
            const Icon(Icons.location_on_outlined, color: WeloColors.sovereignOrange, size: 24),
            const SizedBox(width: 16),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    prediction['description'] ?? '',
                    style: const TextStyle(color: Colors.black, fontWeight: FontWeight.w900, fontSize: 15),
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                  ),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }
}
