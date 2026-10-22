import 'dart:async';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_map/flutter_map.dart';
import 'package:latlong2/latlong.dart';
import 'package:ui/ui.dart';
import 'package:core/core.dart';
import 'permission_status_page.dart';
import 'widgets/order_request_sheet.dart';
import 'widgets/active_delivery_sheet.dart';
import 'widgets/completed_sheet.dart';

class RiderHomeScreen extends ConsumerStatefulWidget {
  const RiderHomeScreen({super.key});

  @override
  ConsumerState<RiderHomeScreen> createState() => _RiderHomeScreenState();
}

class _RiderHomeScreenState extends ConsumerState<RiderHomeScreen> {
  final MapController _mapController = MapController();
  final NotificationAlertService _alertService = NotificationAlertService();
  RiderStatus? _previousStatus;
  PermissionCheckResult? _permResult;
  bool _permissionsLoading = true;

  @override
  void initState() {
    super.initState();
    _alertService.initialize();
    _checkPermissions();
  }

  Future<void> _checkPermissions() async {
    setState(() => _permissionsLoading = true);
    final result = await PermissionHelper.checkAll(isRider: true);
    if (mounted) {
      setState(() {
        _permResult = result;
        _permissionsLoading = false;
      });
    }
  }

  @override
  void dispose() {
    _alertService.stopAll();
    super.dispose();
  }

  void _checkForAlerts(RiderState state) {
    if (_previousStatus == RiderStatus.online &&
        state.status == RiderStatus.receivedRequest) {
      final order = state.currentOrder;
      if (order != null) {
        _alertService.startDeliveryAlert(
          order.id,
          state.payout,
          address: order.customerAddress,
        );
      }
    }
    // Stop alarm when status changes away from receivedRequest
    if (_previousStatus == RiderStatus.receivedRequest &&
        state.status != RiderStatus.receivedRequest) {
      _alertService.stopAll();
    }
    if (state.status == RiderStatus.navigatingToRestaurant ||
        state.status == RiderStatus.atRestaurant) {
      _alertService.stopAll();
    }
    _previousStatus = state.status;
  }

  @override
  Widget build(BuildContext context) {
    final riderState = ref.watch(riderStateProvider);

    _checkForAlerts(riderState);

    final riderLatLng = riderState.latitude != 0.0
        ? LatLng(riderState.latitude, riderState.longitude)
        : null;
    
    final restaurantLatLng = riderState.restaurantLatitude != null
        ? LatLng(riderState.restaurantLatitude!, riderState.restaurantLongitude!)
        : null;
    
    final customerLatLng = riderState.customerLatitude != null
        ? LatLng(riderState.customerLatitude!, riderState.customerLongitude!)
        : null;

    if (riderLatLng != null) {
      WidgetsBinding.instance.addPostFrameCallback((_) {
        try {
          _mapController.move(riderLatLng, _mapController.camera.zoom);
        } catch (_) {}
      });
    }

    return Scaffold(
      body: Stack(
        children: [
          Positioned.fill(
            child: FlutterMap(
              mapController: _mapController,
              options: MapOptions(
                initialCenter: riderLatLng ?? const LatLng(12.9716, 77.5946),
                initialZoom: 14,
                maxZoom: 18,
                minZoom: 5,
              ),
              children: [
                TileLayer(
                  urlTemplate: 'https://tile.openstreetmap.org/{z}/{x}/{y}.png',
                  userAgentPackageName: 'com.utterlazy.rider',
                ),
                if ((riderState.status == RiderStatus.navigatingToRestaurant && restaurantLatLng != null) && riderLatLng != null)
                  PolylineLayer(
                    polylines: [
                      Polyline(
                        points: [riderLatLng, restaurantLatLng],
                        color: WeloColors.sovereignOrange.withValues(alpha: 0.706),
                        strokeWidth: 4,
                      ),
                    ],
                  ),
                if ((riderState.status == RiderStatus.navigatingToCustomer && customerLatLng != null) && riderLatLng != null)
                  PolylineLayer(
                    polylines: [
                      Polyline(
                        points: [riderLatLng, customerLatLng],
                        color: WeloColors.sovereignTeal.withValues(alpha: 0.706),
                        strokeWidth: 4,
                      ),
                    ],
                  ),
                if ((riderState.status == RiderStatus.navigatingToRestaurant ||
                    riderState.status == RiderStatus.atRestaurant) && restaurantLatLng != null)
                  MarkerLayer(
                    markers: [
                      Marker(
                        point: restaurantLatLng,
                        width: 40,
                        height: 40,
                        child: Container(
                          decoration: BoxDecoration(
                            color: WeloColors.sovereignOrange,
                            shape: BoxShape.circle,
                            border: Border.all(color: Colors.white, width: 2),
                            boxShadow: [
                              BoxShadow(
                                color: Colors.black26,
                                blurRadius: 6,
                                offset: const Offset(0, 2),
                              ),
                            ],
                          ),
                          child: const Icon(Icons.store, color: Colors.white, size: 20),
                        ),
                      ),
                    ],
                  ),
                if ((riderState.status == RiderStatus.navigatingToCustomer ||
                    riderState.status == RiderStatus.atCustomer) && customerLatLng != null)
                  MarkerLayer(
                    markers: [
                      Marker(
                        point: customerLatLng,
                        width: 40,
                        height: 40,
                        child: Container(
                          decoration: BoxDecoration(
                            color: WeloColors.sovereignTeal,
                            shape: BoxShape.circle,
                            border: Border.all(color: Colors.white, width: 2),
                            boxShadow: [
                              BoxShadow(
                                color: Colors.black26,
                                blurRadius: 6,
                                offset: const Offset(0, 2),
                              ),
                            ],
                          ),
                          child: const Icon(Icons.person, color: Colors.white, size: 20),
                        ),
                      ),
                    ],
                  ),
                if (riderState.isOnline && riderLatLng != null)
                  MarkerLayer(
                    markers: [
                      Marker(
                        point: riderLatLng,
                        width: 44,
                        height: 44,
                        child: Container(
                          decoration: BoxDecoration(
                            color: WeloColors.sovereignOrange,
                            shape: BoxShape.circle,
                            border: Border.all(color: Colors.white, width: 3),
                            boxShadow: [
                              BoxShadow(
                                color: WeloColors.sovereignOrange.withValues(alpha: 0.392),
                                blurRadius: 12,
                                spreadRadius: 4,
                              ),
                            ],
                          ),
                          child: const Icon(Icons.delivery_dining, color: Colors.white, size: 22),
                        ),
                      ),
                    ],
                  ),
              ],
            ),
          ),

          Positioned(
            top: MediaQuery.of(context).padding.top + 8,
            left: 16,
            right: 16,
            child: Column(
              children: [
                if (!_permissionsLoading && _permResult != null && _permResult!.hasMissing)
                  _buildPermissionBanner(),
                WeloCard(
                  padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 10),
                  child: Row(
                    mainAxisAlignment: MainAxisAlignment.spaceBetween,
                    children: [
                      Row(
                        children: [
                          Container(
                            width: 10,
                            height: 10,
                            decoration: BoxDecoration(
                              color: riderState.isOnline ? WeloColors.success : WeloColors.textLight,
                              shape: BoxShape.circle,
                            ),
                          ),
                          const SizedBox(width: 10),
                          Text(
                            riderState.isOnline ? 'You are Online' : 'Offline',
                            style: TextStyle(
                              fontWeight: FontWeight.bold,
                              fontSize: 15,
                              color: riderState.isOnline ? WeloColors.success : WeloColors.textBody,
                            ),
                          ),
                        ],
                      ),
                      Switch(
                        value: riderState.isOnline,
                        activeThumbColor: WeloColors.success,
                        onChanged: (val) async {
                          if (val) {
                            await PermissionHelper.checkAndRequestPermissions(
                              isRider: true,
                              context: context,
                            );
                          }
                          ref.read(riderStateProvider.notifier).toggleOnline(val);
                          _checkPermissions();
                        },
                      ),
                    ],
                  ),
                ),
              ],
            ),
          ),

          if (riderState.isOnline && riderLatLng != null)
            Positioned(
              right: 16,
              bottom: 280,
              child: FloatingActionButton(
                backgroundColor: Colors.white,
                foregroundColor: WeloColors.primary,
                elevation: 4,
                onPressed: () {
                  _mapController.move(riderLatLng, 16);
                },
                child: const Icon(Icons.my_location, size: 22),
              ),
            ),

          Positioned(
            bottom: 0,
            left: 0,
            right: 0,
            child: _buildBottomSheet(riderState),
          ),
        ],
      ),
    );
  }

  Widget _buildPermissionBanner() {
    final count = _permResult!.denied.length;
    return Padding(
      padding: const EdgeInsets.only(bottom: 8),
      child: Material(
        color: WeloColors.warning.withValues(alpha: 0.078),
        borderRadius: BorderRadius.circular(12),
        child: InkWell(
          borderRadius: BorderRadius.circular(12),
          onTap: () {
            Navigator.of(context).push(
              MaterialPageRoute(builder: (_) => const PermissionStatusPage()),
            ).then((_) => _checkPermissions());
          },
          child: Padding(
            padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 10),
            child: Row(
              children: [
                Icon(Icons.warning_amber_rounded, color: WeloColors.warning, size: 20),
                const SizedBox(width: 10),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        '$count permission${count > 1 ? 's' : ''} not granted',
                        style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 12),
                      ),
                      Text(
                        'Notifications may not work when app is closed.',
                        style: TextStyle(fontSize: 11, color: WeloColors.textBody),
                      ),
                    ],
                  ),
                ),
                Icon(Icons.chevron_right, color: WeloColors.textLight, size: 20),
              ],
            ),
          ),
        ),
      ),
    );
  }

  Widget _buildBottomSheet(RiderState state) {
    return switch (state.status) {
      RiderStatus.offline => _buildOfflineSheet(),
      RiderStatus.online => _buildWaitingSheet(),
      RiderStatus.receivedRequest => const OrderRequestSheet(),
      RiderStatus.navigatingToRestaurant ||
      RiderStatus.atRestaurant ||
      RiderStatus.navigatingToCustomer ||
      RiderStatus.atCustomer => const ActiveDeliverySheet(),
      RiderStatus.completed => const CompletedSheet(),
    };
  }

  Widget _buildOfflineSheet() {
    return Container(
      padding: const EdgeInsets.all(24),
      decoration: const BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.vertical(top: Radius.circular(24)),
        boxShadow: [
          BoxShadow(color: Colors.black12, blurRadius: 10, offset: Offset(0, -5)),
        ],
      ),
      child: RefreshIndicator(
        onRefresh: () => ref.read(riderStateProvider.notifier).refreshState(),
        color: WeloColors.sovereignOrange,
        child: SingleChildScrollView(
          physics: const AlwaysScrollableScrollPhysics(),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              Container(
                width: 40,
                height: 4,
                decoration: BoxDecoration(
                  color: WeloColors.sovereignElevated,
                  borderRadius: BorderRadius.circular(2),
                ),
              ),
              const SizedBox(height: 20),
              const Icon(Icons.power_settings_new, size: 40, color: WeloColors.textLight),
              const SizedBox(height: 12),
              const Text(
                'You\'re Offline',
                style: TextStyle(fontSize: 18, fontWeight: FontWeight.bold),
              ),
              const SizedBox(height: 6),
              const Text(
                'Go online to start receiving orders',
                style: TextStyle(color: WeloColors.textBody),
              ),
              const SizedBox(height: 20),
            ],
          ),
        ),
      ),
    );
  }

  Widget _buildWaitingSheet() {
    return Container(
      padding: const EdgeInsets.all(24),
      decoration: const BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.vertical(top: Radius.circular(24)),
        boxShadow: [
          BoxShadow(color: Colors.black12, blurRadius: 10, offset: Offset(0, -5)),
        ],
      ),
      child: RefreshIndicator(
        onRefresh: () => ref.read(riderStateProvider.notifier).refreshState(),
        color: WeloColors.sovereignOrange,
        child: SingleChildScrollView(
          physics: const AlwaysScrollableScrollPhysics(),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              Container(
                width: 40,
                height: 4,
                decoration: BoxDecoration(
                  color: WeloColors.sovereignElevated,
                  borderRadius: BorderRadius.circular(2),
                ),
              ),
              const SizedBox(height: 20),
              const SizedBox(
                width: 24,
                height: 24,
                child: CircularProgressIndicator(
                  strokeWidth: 2.5,
                  color: WeloColors.sovereignOrange,
                ),
              ),
              const SizedBox(height: 12),
              const Text(
                'Looking for orders...',
                style: TextStyle(fontSize: 16, fontWeight: FontWeight.w600),
              ),
              const SizedBox(height: 6),
              const Text(
                'Stay in a busy area for faster orders',
                style: TextStyle(color: WeloColors.textBody, fontSize: 13),
              ),
              const SizedBox(height: 20),
            ],
          ),
        ),
      ),
    );
  }
}
