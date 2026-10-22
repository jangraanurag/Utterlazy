import 'dart:async';
import 'dart:math' as math;

import 'package:flutter/material.dart';
import 'package:flutter_map/flutter_map.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:intl/intl.dart';
import 'package:latlong2/latlong.dart';

import 'package:api/api.dart';
import 'package:ui/ui.dart';
import 'package:core/core.dart';
import 'package:dio/dio.dart';
import 'download_invoice_screen.dart';
import 'games_screen.dart';
import 'complaint_screen.dart';
import 'write_review_screen.dart';

class OrderTrackingScreen extends ConsumerStatefulWidget {
  final Order order;
  const OrderTrackingScreen({super.key, required this.order});

  @override
  ConsumerState<OrderTrackingScreen> createState() => _OrderTrackingScreenState();
}

class _OrderTrackingScreenState extends ConsumerState<OrderTrackingScreen> with TickerProviderStateMixin {
  late final OrderRepository _orderRepository;
  late AnimationController _progressController;
  late AnimationController _pulseController;
  Timer? _statusTimer;

  String _orderStatus = 'pending';
  String? _riderName;
  String? _riderPhone;
  String? _deliveryOtp;
  bool _riderAssigned = false;
  bool _riderArrived = false;
  double? _riderDistance;

  double _riderProgress = 0.0;
  double _targetProgress = 0.0;
  double _previousTargetProgress = -1.0;

  bool _isLoading = true;
  int _consecutiveErrors = 0;
  static const int _maxConsecutiveErrors = 5;

  double? restLat;
  double? restLng;
  double? custLat;
  double? custLng;
  String? _riderLat;
  String? _riderLng;
  String? _estimatedTime;
  String? _trafficCondition;
  String? _weatherCondition;

  @override
  void initState() {
    super.initState();
    _orderRepository = OrderRepository(ref.read(apiClientProvider));
    _orderStatus = widget.order.status;
    _riderName = widget.order.riderName;
    _riderPhone = widget.order.riderPhone;

    _progressController = AnimationController(
      vsync: this,
      duration: const Duration(seconds: 8),
    );
    _progressController.addListener(() {
      setState(() {
        _riderProgress = _riderProgress + (_targetProgress - _riderProgress) * _progressController.value;
      });
    });

    _pulseController = AnimationController(
      vsync: this,
      duration: const Duration(milliseconds: 1500),
    )..repeat(reverse: true);

    final bool isFinal = ['delivered', 'cancelled', 'abandoned'].contains(_orderStatus);
    if (!isFinal) {
      _startStatusPolling();
    } else {
      _isLoading = false;
    }
  }

  void _callRider(String orderId) {
    final repo = OrderRepository(ref.read(apiClientProvider));
    repo.callRider(orderId).then((data) {
      if (!mounted) return;
      final msg = (data['queued'] == true)
          ? (data['message'] as String? ?? 'You are in queue. You will receive a call shortly.')
          : 'Connecting you to the rider...';
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(msg), duration: Duration(seconds: 3)),
      );
    }).catchError((e) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text('Failed: $e')),
      );
    });
  }

  void _callRestaurant(String orderId) {
    final repo = OrderRepository(ref.read(apiClientProvider));
    repo.callRestaurant(orderId).then((data) {
      if (!mounted) return;
      final msg = (data['queued'] == true)
          ? (data['message'] as String? ?? 'You are in queue. You will receive a call shortly.')
          : 'Connecting you to the restaurant...';
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(msg), duration: Duration(seconds: 3)),
      );
    }).catchError((e) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text('Failed: $e')),
      );
    });
  }

  void _startStatusPolling() {
    _statusTimer = Timer.periodic(const Duration(seconds: 8), (timer) async {
      try {
        final orderData = (await _orderRepository.getOrder(widget.order.id))['order'];

        if (orderData != null && mounted) {
          final newStatus = orderData['status'] as String;
          final riderData = orderData['rider'];
          final restaurantData = orderData['restaurant'];
          final addressData = orderData['address'];

          double newTargetProgress = 0.0;
          if (newStatus == 'confirmed') newTargetProgress = 0.1;
          else if (newStatus == 'preparing') newTargetProgress = 0.3;
          else if (newStatus == 'ready_for_pickup') newTargetProgress = 0.5;
          else if (newStatus == 'out_for_delivery') newTargetProgress = 0.8;
          else if (newStatus == 'delivered') newTargetProgress = 1.0;

          setState(() {
            _isLoading = false;
            _consecutiveErrors = 0;
            _orderStatus = newStatus;
            if (riderData != null) {
              _riderName = riderData['name'] as String?;
              _riderPhone = riderData['phone'] as String?;
              _riderLat = riderData['latitude']?.toString();
              _riderLng = riderData['longitude']?.toString();
              _calculateETA();
            }
            _deliveryOtp = orderData['delivery_otp'] as String?;
            _riderAssigned = orderData['rider_assigned'] == 1 || orderData['rider_assigned'] == true;
            _riderArrived = orderData['rider_arrived'] == 1 || orderData['rider_arrived'] == true;
            _riderDistance = orderData['rider_distance_to_restaurant'] != null
                ? double.tryParse(orderData['rider_distance_to_restaurant'].toString())
                : null;

            if (restaurantData != null) {
              restLat = double.tryParse(restaurantData['latitude']?.toString() ?? '');
              restLng = double.tryParse(restaurantData['longitude']?.toString() ?? '');
            }
            if (addressData != null) {
              custLat = double.tryParse(addressData['latitude']?.toString() ?? '');
              custLng = double.tryParse(addressData['longitude']?.toString() ?? '');
            }

            _targetProgress = newTargetProgress;
          });

          if (_targetProgress != _previousTargetProgress) {
            _previousTargetProgress = _targetProgress;
            _progressController.reset();
            _progressController.forward();
          }

          if (['delivered', 'cancelled', 'abandoned'].contains(newStatus)) {
            _statusTimer?.cancel();
          }
        }
      } catch (e) {
        if (mounted) {
          setState(() {
            _consecutiveErrors++;
            if (_consecutiveErrors >= _maxConsecutiveErrors) {
              _isLoading = false;
            }
          });
        }
      }
    });
  }

  final TextEditingController _recipientController = TextEditingController();
  bool _isSharing = false;

  void _calculateETA() {
    if (_riderLat == null || _riderLng == null) return;
    final rLat = restLat ?? 0.0;
    final rLng = restLng ?? 0.0;
    final cLat = custLat ?? 0.0;
    final cLng = custLng ?? 0.0;
    final riderLat = double.tryParse(_riderLat!) ?? 0.0;
    final riderLng = double.tryParse(_riderLng!) ?? 0.0;

    if (rLat == 0 || rLng == 0 || cLat == 0 || cLng == 0) return;

    final distToRestaurant = _haversineKm(riderLat, riderLng, rLat, rLng);
    final distToCustomer = _haversineKm(rLat, rLng, cLat, cLng);

    // Traffic multiplier
    final hour = DateTime.now().hour;
    final dayOfWeek = DateTime.now().weekday; // 1=Mon, 7=Sun
    double trafficMult = 1.0;
    String trafficDesc = 'Normal traffic';
    if (dayOfWeek >= 1 && dayOfWeek <= 5) {
      if ((hour >= 8 && hour < 10) || (hour >= 17 && hour < 20)) {
        trafficMult = 1.35; trafficDesc = 'Heavy traffic';
      } else if (hour >= 12 && hour < 14) {
        trafficMult = 1.20; trafficDesc = 'Moderate traffic';
      } else if ((hour >= 7 && hour < 8) || (hour >= 20 && hour < 22)) {
        trafficMult = 1.15; trafficDesc = 'Light traffic';
      }
    } else {
      if (hour >= 10 && hour < 20) {
        trafficMult = 1.10; trafficDesc = 'Light traffic';
      }
    }

    // Weather multiplier
    final month = DateTime.now().month;
    double weatherMult = 1.0;
    String weatherDesc = 'Clear skies';
    if (month >= 6 && month <= 9) {
      weatherMult = 1.25; weatherDesc = 'Monsoon season';
    } else if (month >= 4 && month <= 5) {
      weatherMult = 1.10; weatherDesc = 'Hot weather';
    }

    final timeToRestaurant = (distToRestaurant * 3 * trafficMult * weatherMult + 1).round();
    final timeToCustomer = (distToCustomer * 3 * trafficMult * weatherMult + 1).round();

    int totalMin;
    if (_orderStatus == 'out_for_delivery') {
      totalMin = timeToCustomer;
    } else {
      totalMin = timeToRestaurant + 5 + timeToCustomer;
    }

    if (mounted) {
      setState(() {
        _estimatedTime = totalMin > 0 ? '~$totalMin min' : null;
        _trafficCondition = trafficDesc;
        _weatherCondition = weatherDesc;
      });
    }
  }

  double _haversineKm(double lat1, double lng1, double lat2, double lng2) {
    const earthRadius = 6371.0;
    final dLat = (lat2 - lat1) * math.pi / 180;
    final dLng = (lng2 - lng1) * math.pi / 180;
    final a = math.sin(dLat / 2) * math.sin(dLat / 2) +
        math.cos(lat1 * math.pi / 180) * math.cos(lat2 * math.pi / 180) *
        math.sin(dLng / 2) * math.sin(dLng / 2);
    return earthRadius * 2 * math.asin(math.sqrt(a));
  }

  Future<void> _shareTracking() async {
    showDialog(
      context: context,
      builder: (context) {
        return StatefulBuilder(
          builder: (context, setDialogState) {
            return AlertDialog(
              shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(24)),
              title: Row(
                children: [
                  const Icon(Icons.share_arrival_time_rounded, color: WeloColors.sovereignOrange),
                  const SizedBox(width: 10),
                  Text('Share Live Tracking', style: GoogleFonts.spaceGrotesk(fontWeight: FontWeight.bold, fontSize: 16)),
                ],
              ),
              content: Column(
                mainAxisSize: MainAxisSize.min,
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  const Text(
                    'Enter your friend\'s email or phone number registered on UtterLazy to share this order\'s live tracking via app notification.',
                    style: TextStyle(fontSize: 12, color: Colors.black54),
                  ),
                  const SizedBox(height: 16),
                  TextField(
                    controller: _recipientController,
                    decoration: InputDecoration(
                      hintText: 'Email or Phone Number',
                      prefixIcon: const Icon(Icons.person_outline),
                      border: OutlineInputBorder(borderRadius: BorderRadius.circular(16)),
                      focusedBorder: OutlineInputBorder(
                        borderRadius: BorderRadius.circular(16),
                        borderSide: const BorderSide(color: WeloColors.sovereignOrange, width: 2),
                      ),
                    ),
                  ),
                ],
              ),
              actions: [
                TextButton(
                  onPressed: _isSharing ? null : () => Navigator.pop(context),
                  child: const Text('Cancel', style: TextStyle(color: Colors.grey, fontWeight: FontWeight.bold)),
                ),
                ElevatedButton(
                  onPressed: _isSharing
                      ? null
                      : () async {
                          final recipient = _recipientController.text.trim();
                          if (recipient.isEmpty) return;

                          setDialogState(() => _isSharing = true);
                          try {
                            final apiClient = ref.read(apiClientProvider);
                            await apiClient.dio.post(
                              '/orders/${widget.order.id}/share',
                              data: {'recipient': recipient},
                            );
                            if (!mounted) return;
                            Navigator.pop(context);
                            ScaffoldMessenger.of(context).showSnackBar(
                              const SnackBar(
                                content: Text('Live tracking link shared successfully!'),
                                backgroundColor: WeloColors.success,
                              ),
                            );
                          } catch (e) {
                            String errorMsg = 'Failed to share tracking.';
                            if (e is DioException) {
                              errorMsg = e.response?.data?['error'] ?? errorMsg;
                            }
                            if (!mounted) return;
                            ScaffoldMessenger.of(context).showSnackBar(
                              SnackBar(
                                content: Text(errorMsg),
                                backgroundColor: WeloColors.error,
                              ),
                            );
                          } finally {
                            setDialogState(() => _isSharing = false);
                          }
                        },
                  style: ElevatedButton.styleFrom(
                    backgroundColor: WeloColors.sovereignOrange,
                    foregroundColor: Colors.white,
                    shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
                  ),
                  child: _isSharing
                      ? const SizedBox(
                          width: 20,
                          height: 20,
                          child: CircularProgressIndicator(color: Colors.white, strokeWidth: 2),
                        )
                      : const Text('Share', style: TextStyle(fontWeight: FontWeight.bold)),
                ),
              ],
            );
          },
        );
      },
    );
  }

  @override
  void dispose() {
    _statusTimer?.cancel();
    _progressController.dispose();
    _pulseController.dispose();
    _recipientController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    if (_isLoading) {
      return Scaffold(
        backgroundColor: WeloColors.sovereignBg,
        appBar: AppBar(
          title: const Text('TRACK MISSION LIVE', style: TextStyle(fontSize: 13, fontWeight: FontWeight.w900, letterSpacing: 1)),
          centerTitle: true,
        ),
        body: Center(
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              const CircularProgressIndicator(color: WeloColors.sovereignOrange),
              const SizedBox(height: 16),
              Text('Loading order status...', style: TextStyle(color: Colors.grey[600], fontSize: 13)),
            ],
          ),
        ),
      );
    }

    if (_consecutiveErrors >= _maxConsecutiveErrors) {
      return Scaffold(
        backgroundColor: WeloColors.sovereignBg,
        appBar: AppBar(
          title: const Text('TRACK MISSION LIVE', style: TextStyle(fontSize: 13, fontWeight: FontWeight.w900, letterSpacing: 1)),
          centerTitle: true,
        ),
        body: Center(
          child: Padding(
            padding: const EdgeInsets.all(32),
            child: Column(
              mainAxisSize: MainAxisSize.min,
              children: [
                Icon(Icons.wifi_off_rounded, size: 48, color: Colors.grey[400]),
                const SizedBox(height: 16),
                Text('Connection Lost', style: TextStyle(fontSize: 18, fontWeight: FontWeight.bold, color: Colors.grey[800])),
                const SizedBox(height: 8),
                Text('Unable to fetch order updates. Please check your connection.',
                    textAlign: TextAlign.center, style: TextStyle(color: Colors.grey[600], fontSize: 13)),
                const SizedBox(height: 24),
                ElevatedButton.icon(
                  onPressed: () {
                    setState(() {
                      _consecutiveErrors = 0;
                      _isLoading = true;
                    });
                    _startStatusPolling();
                  },
                  icon: const Icon(Icons.refresh, size: 18),
                  label: const Text('RETRY'),
                  style: ElevatedButton.styleFrom(
                    backgroundColor: WeloColors.sovereignOrange,
                    foregroundColor: Colors.white,
                    padding: const EdgeInsets.symmetric(horizontal: 32, vertical: 14),
                    shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                  ),
                ),
              ],
            ),
          ),
        ),
      );
    }

    final bool isFinal = ['delivered', 'cancelled', 'abandoned'].contains(_orderStatus);
    if (isFinal) return _buildStaticHistoryView();

    int stepIndex = 0;
    if (_orderStatus == 'confirmed') stepIndex = 1;
    if (_orderStatus == 'preparing' || _orderStatus == 'ready_for_pickup') stepIndex = 2;
    if (_orderStatus == 'out_for_delivery') stepIndex = 3;
    if (_orderStatus == 'delivered') stepIndex = 4;

    final rLat = restLat ?? 12.9716;
    final rLng = restLng ?? 77.5946;
    final cLat = custLat ?? 12.9900;
    final cLng = custLng ?? 77.6200;
    final centerLat = (rLat + cLat) / 2;
    final centerLng = (rLng + cLng) / 2;

    return Scaffold(
      backgroundColor: WeloColors.sovereignBg,
      appBar: AppBar(
        title: const Text('TRACK MISSION LIVE', style: TextStyle(fontSize: 13, fontWeight: FontWeight.w900, letterSpacing: 1)),
        centerTitle: true,
        actions: [
          IconButton(
            icon: const Icon(Icons.share, size: 20),
            onPressed: _shareTracking,
            tooltip: 'Share Live Tracking',
          ),
        ],
      ),
      body: RefreshIndicator(
        onRefresh: () async {
          try {
            final orderData = (await _orderRepository.getOrder(widget.order.id))['order'];
            if (orderData != null && mounted) {
              final newStatus = orderData['status'] as String;
              final riderData = orderData['rider'];
              final restaurantData = orderData['restaurant'];
              final addressData = orderData['address'];

              double newTargetProgress = 0.0;
              if (newStatus == 'confirmed') newTargetProgress = 0.1;
              else if (newStatus == 'preparing') newTargetProgress = 0.3;
              else if (newStatus == 'ready_for_pickup') newTargetProgress = 0.5;
              else if (newStatus == 'out_for_delivery') newTargetProgress = 0.8;
              else if (newStatus == 'delivered') newTargetProgress = 1.0;

              setState(() {
                _consecutiveErrors = 0;
                _isLoading = false;
                _orderStatus = newStatus;
                if (riderData != null) {
                  _riderName = riderData['name'] as String?;
                  _riderPhone = riderData['phone'] as String?;
                  _riderLat = riderData['latitude']?.toString();
                  _riderLng = riderData['longitude']?.toString();
                }
                _deliveryOtp = orderData['delivery_otp'] as String?;
                _riderAssigned = orderData['rider_assigned'] == 1 || orderData['rider_assigned'] == true;
                _riderArrived = orderData['rider_arrived'] == 1 || orderData['rider_arrived'] == true;
                _riderDistance = orderData['rider_distance_to_restaurant'] != null
                    ? double.tryParse(orderData['rider_distance_to_restaurant'].toString())
                    : null;
                if (restaurantData != null) {
                  restLat = double.tryParse(restaurantData['latitude']?.toString() ?? '');
                  restLng = double.tryParse(restaurantData['longitude']?.toString() ?? '');
                }
                if (addressData != null) {
                  custLat = double.tryParse(addressData['latitude']?.toString() ?? '');
                  custLng = double.tryParse(addressData['longitude']?.toString() ?? '');
                }
                _targetProgress = newTargetProgress;
              });

              if (_targetProgress != _previousTargetProgress) {
                _previousTargetProgress = _targetProgress;
                _progressController.reset();
                _progressController.forward();
              }
            }
          } catch (e) {
            if (mounted) setState(() => _consecutiveErrors++);
          }
        },
        color: WeloColors.sovereignOrange,
        child: Column(
          children: [
            Expanded(
              child: LayoutBuilder(
                builder: (context, constraints) {

                  return SizedBox.expand(
                    child: FlutterMap(
                      options: MapOptions(
                        initialCenter: LatLng(centerLat, centerLng),
                        initialZoom: 14,
                        maxZoom: 18,
                        minZoom: 5,
                      ),
                      children: [
                        TileLayer(
                          urlTemplate: 'https://tile.openstreetmap.org/{z}/{x}/{y}.png',
                          userAgentPackageName: 'com.utterlazy.customer',
                        ),
                        // Route line: restaurant → customer
                        PolylineLayer(
                          polylines: [
                            Polyline(
                              points: [LatLng(rLat, rLng), LatLng(cLat, cLng)],
                              color: Colors.grey.shade400,
                              strokeWidth: 3,
                            ),
                          ],
                        ),
                        // Restaurant marker
                        MarkerLayer(
                          markers: [
                            Marker(
                              point: LatLng(rLat, rLng),
                              width: 40,
                              height: 40,
                              child: Container(
                                decoration: BoxDecoration(
                                  color: WeloColors.sovereignOrange,
                                  shape: BoxShape.circle,
                                  border: Border.all(color: Colors.white, width: 2),
                                  boxShadow: [BoxShadow(color: Colors.black26, blurRadius: 6)],
                                ),
                                child: const Icon(Icons.store, color: Colors.white, size: 20),
                              ),
                            ),
                          ],
                        ),
                        // Customer marker
                        MarkerLayer(
                          markers: [
                            Marker(
                              point: LatLng(cLat, cLng),
                              width: 40,
                              height: 40,
                              child: Container(
                                decoration: BoxDecoration(
                                  color: WeloColors.sovereignTeal,
                                  shape: BoxShape.circle,
                                  border: Border.all(color: Colors.white, width: 2),
                                  boxShadow: [BoxShadow(color: Colors.black26, blurRadius: 6)],
                                ),
                                child: const Icon(Icons.home, color: Colors.white, size: 20),
                              ),
                            ),
                          ],
                        ),
                        // Rider marker (live location)
                        if (_riderLat != null && _riderLng != null)
                          MarkerLayer(
                            markers: [
                              Marker(
                                point: LatLng(
                                  double.tryParse(_riderLat!) ?? rLat,
                                  double.tryParse(_riderLng!) ?? rLng,
                                ),
                                width: 44,
                                height: 44,
                                child: Container(
                                  decoration: BoxDecoration(
                                    color: WeloColors.sovereignOrange,
                                    shape: BoxShape.circle,
                                    border: Border.all(color: Colors.white, width: 3),
                                    boxShadow: [
                                      BoxShadow(color: WeloColors.sovereignOrange.withValues(alpha: 0.392), blurRadius: 12, spreadRadius: 4),
                                    ],
                                  ),
                                  child: const Icon(Icons.delivery_dining, color: Colors.white, size: 22),
                                ),
                              ),
                            ],
                          ),
                      ],
                    ),
                  );
                },
              ),
            ),
            _buildInfoDrawer(stepIndex),
          ],
        ),
      ),
    );
  }

  Widget _buildInfoDrawer(int stepIndex) {
    final bool isFinal = ['delivered', 'cancelled', 'abandoned'].contains(_orderStatus);

    return Container(
      padding: const EdgeInsets.fromLTRB(24, 24, 24, 40),
      decoration: const BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.vertical(top: Radius.circular(32)),
        boxShadow: [BoxShadow(color: Colors.black12, blurRadius: 20, offset: Offset(0, -5))],
      ),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      _getStatusLabel(),
                      style: const TextStyle(fontWeight: FontWeight.w900, fontSize: 18, letterSpacing: -0.5),
                      overflow: TextOverflow.ellipsis,
                    ),
                    const SizedBox(height: 4),
                    Text(
                      _getRiderStatusText(),
                      style: const TextStyle(color: Colors.black45, fontSize: 13, fontWeight: FontWeight.w500),
                      overflow: TextOverflow.ellipsis,
                      maxLines: 2,
                    ),
                    if (_estimatedTime != null && !isFinal) ...[
                      const SizedBox(height: 8),
                      Container(
                        padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 6),
                        decoration: BoxDecoration(
                          color: WeloColors.sovereignOrange.withValues(alpha: 0.059),
                          borderRadius: BorderRadius.circular(20),
                        ),
                        child: Row(
                          mainAxisSize: MainAxisSize.min,
                          children: [
                            const Icon(Icons.access_time, size: 14, color: WeloColors.sovereignOrange),
                            const SizedBox(width: 4),
                            Text(
                              'Arriving in $_estimatedTime',
                              style: const TextStyle(fontSize: 13, fontWeight: FontWeight.bold, color: WeloColors.sovereignOrange),
                            ),
                          ],
                        ),
                      ),
                      if (_trafficCondition != null || _weatherCondition != null)
                        Padding(
                          padding: const EdgeInsets.only(top: 6),
                          child: Row(
                            children: [
                              if (_trafficCondition != null) ...[
                                Icon(
                                  _trafficCondition!.contains('Heavy') ? Icons.traffic : Icons.traffic,
                                  size: 12,
                                  color: _trafficCondition!.contains('Heavy') ? Colors.red : Colors.orange,
                                ),
                                const SizedBox(width: 3),
                                Text(_trafficCondition!, style: const TextStyle(fontSize: 11, color: Colors.black54)),
                              ],
                              if (_trafficCondition != null && _weatherCondition != null)
                                const Padding(
                                  padding: EdgeInsets.symmetric(horizontal: 8),
                                  child: Text('•', style: TextStyle(color: Colors.black26)),
                                ),
                              if (_weatherCondition != null) ...[
                                Icon(
                                  _weatherCondition!.contains('Monsoon') || _weatherCondition!.contains('Rain')
                                      ? Icons.cloud
                                      : _weatherCondition!.contains('Hot')
                                          ? Icons.wb_sunny
                                          : Icons.wb_sunny_outlined,
                                  size: 12,
                                  color: _weatherCondition!.contains('Clear') ? Colors.orange : Colors.blue,
                                ),
                                const SizedBox(width: 3),
                                Text(_weatherCondition!, style: const TextStyle(fontSize: 11, color: Colors.black54)),
                              ],
                            ],
                          ),
                        ),
                    ],
                  ],
                ),
              ),
              if (_deliveryOtp != null && !isFinal)
                Container(
                  padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
                  decoration: BoxDecoration(color: WeloColors.sovereignOrange.withValues(alpha: 0.08), borderRadius: BorderRadius.circular(16)),
                  child: Column(
                    children: [
                      const Text('DELIVERY OTP', style: TextStyle(fontSize: 8, fontWeight: FontWeight.w900, color: WeloColors.sovereignOrange, letterSpacing: 1)),
                      const SizedBox(height: 4),
                      Text(_deliveryOtp!, style: GoogleFonts.spaceGrotesk(fontSize: 16, fontWeight: FontWeight.bold, letterSpacing: 2)),
                    ],
                  ),
                ),
            ],
          ),

          if (_riderName != null && _riderAssigned && !isFinal) ...[
            const SizedBox(height: 12),
            Container(
              padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
              decoration: BoxDecoration(
                color: Colors.grey.shade50,
                borderRadius: BorderRadius.circular(12),
              ),
              child: Row(
                children: [
                  CircleAvatar(
                    radius: 16,
                    backgroundColor: Colors.green.shade50,
                    child: Icon(Icons.person, size: 18, color: Colors.green.shade700),
                  ),
                  const SizedBox(width: 10),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(_riderName!, style: const TextStyle(fontSize: 13, fontWeight: FontWeight.bold), overflow: TextOverflow.ellipsis),
                        if (_riderPhone != null)
                          Text(_riderPhone!, style: TextStyle(fontSize: 11, color: Colors.grey[600]), overflow: TextOverflow.ellipsis),
                      ],
                    ),
                  ),
                  if (_riderPhone != null)
                    IconButton(
                      onPressed: () => _callRider(widget.order.id),
                      icon: const Icon(Icons.phone_rounded, color: Colors.green, size: 20),
                    ),
                ],
              ),
            ),
          ],

          if (widget.order.prepDelayMinutes != null && widget.order.prepDelayMinutes! > 0 && !isFinal)
            Container(
              margin: const EdgeInsets.only(bottom: 16),
              padding: const EdgeInsets.all(12),
              decoration: BoxDecoration(
                color: WeloColors.sovereignOrange.withValues(alpha: 0.078),
                borderRadius: BorderRadius.circular(8),
                border: Border.all(color: WeloColors.sovereignOrange.withValues(alpha: 0.196)),
              ),
              child: Row(
                children: [
                  const Icon(Icons.info_outline, color: WeloColors.sovereignOrange, size: 20),
                  const SizedBox(width: 8),
                  Expanded(
                    child: Text(
                      'High demand at restaurant. Your order is delayed by ${widget.order.prepDelayMinutes} mins.',
                      style: const TextStyle(fontSize: 12, color: WeloColors.sovereignOrange, fontWeight: FontWeight.bold),
                    ),
                  ),
                ],
              ),
            ),

          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              _buildStepIndicator('Placed', stepIndex >= 1, _getStepTime('placed')),
              _buildStepLine(stepIndex >= 2),
              _buildStepIndicator('Prep.', stepIndex >= 2, _getStepTime('prep')),
              _buildStepLine(stepIndex >= 3),
              _buildStepIndicator('On Way', stepIndex >= 3, _getStepTime('delivery')),
              _buildStepLine(stepIndex >= 4),
              _buildStepIndicator('Arrived', stepIndex >= 4, ''),
            ],
          ),

          const SizedBox(height: 16),
          Container(
            padding: const EdgeInsets.all(12),
            decoration: BoxDecoration(
              color: Colors.grey.shade50,
              borderRadius: BorderRadius.circular(12),
            ),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Row(
                  mainAxisAlignment: MainAxisAlignment.spaceBetween,
                  children: [
                    const Text('ORDER SUMMARY', style: TextStyle(fontSize: 10, fontWeight: FontWeight.w900, letterSpacing: 1, color: Colors.black45)),
                    Text('${widget.order.itemsCount} item${widget.order.itemsCount != 1 ? 's' : ''}', style: const TextStyle(fontSize: 10, color: Colors.black45)),
                  ],
                ),
                const SizedBox(height: 8),
                Text(
                  widget.order.itemSummary.isNotEmpty ? widget.order.itemSummary : 'Items',
                  style: const TextStyle(fontSize: 12, fontWeight: FontWeight.w500),
                  maxLines: 2,
                  overflow: TextOverflow.ellipsis,
                ),
                const Divider(height: 12),
                Row(
                  mainAxisAlignment: MainAxisAlignment.spaceBetween,
                  children: [
                    const Text('Total', style: TextStyle(fontSize: 12, fontWeight: FontWeight.bold)),
                    Text('₹${widget.order.totalAmount.toStringAsFixed(0)}', style: const TextStyle(fontSize: 12, fontWeight: FontWeight.bold)),
                  ],
                ),
              ],
            ),
          ),

          if (!isFinal) ...[
            const SizedBox(height: 16),
            SizedBox(
              width: double.infinity,
              child: OutlinedButton.icon(
                onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => const GamesScreen())),
                icon: const Icon(Icons.sports_esports, size: 16),
                label: const Text('PLAY WHILE YOU WAIT', style: TextStyle(fontSize: 11, fontWeight: FontWeight.w900, letterSpacing: 0.5)),
                style: OutlinedButton.styleFrom(
                  foregroundColor: WeloColors.sovereignOrange,
                  side: const BorderSide(color: WeloColors.sovereignOrange),
                  padding: const EdgeInsets.symmetric(vertical: 12),
                  shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                ),
              ),
            ),
          ],
        ],
      ),
    );
  }

  Widget _buildStepIndicator(String label, bool active, [String time = '']) {
    return Column(
      children: [
        CircleAvatar(
          radius: 12,
          backgroundColor: active ? WeloColors.sovereignOrange : Colors.black12,
          child: Icon(active ? Icons.check : Icons.circle, size: 12, color: Colors.white),
        ),
        const SizedBox(height: 6),
        Text(label, style: TextStyle(fontSize: 10, fontWeight: FontWeight.bold, color: active ? Colors.black : Colors.black26)),
        if (time.isNotEmpty)
          Text(time, style: TextStyle(fontSize: 8, color: Colors.grey[400]), overflow: TextOverflow.ellipsis),
      ],
    );
  }

  Widget _buildStepLine(bool active) {
    return Expanded(
      child: Container(
        height: 2,
        margin: const EdgeInsets.symmetric(horizontal: 8),
        color: active ? WeloColors.sovereignOrange : Colors.black12,
      ),
    );
  }

  String _getStatusLabel() {
    switch (_orderStatus) {
      case 'delivered': return 'DELIVERED SUCCESSFULLY';
      case 'out_for_delivery': return 'OUT FOR DELIVERY';
      case 'ready_for_pickup': return 'FOOD IS READY';
      case 'preparing': return 'PREPARING YOUR ORDER';
      default: return 'ORDER PLACED';
    }
  }

  String _getRiderStatusText() {
    if (_orderStatus == 'delivered') return 'Hope you enjoyed your meal!';
    if (_orderStatus == 'out_for_delivery') {
      if (_riderName != null) {
        final distText = _riderDistance != null ? ' (${_riderDistance!.toStringAsFixed(1)} km away)' : '';
        return '$_riderName is on the way$distText';
      }
      return 'Rider is on the way to your address';
    }
    if (_orderStatus == 'ready_for_pickup') {
      if (_riderAssigned) {
        if (_riderArrived) return '${_riderName ?? "Rider"} has arrived at the restaurant';
        final distText = _riderDistance != null ? ' (${_riderDistance!.toStringAsFixed(1)} km away)' : '';
        return '${_riderName ?? "Rider"} is heading to restaurant$distText';
      }
      return 'Waiting for rider assignment';
    }
    if (_orderStatus == 'preparing') {
      if (_riderAssigned) {
        if (_riderArrived) return '${_riderName ?? "Rider"} has arrived and is waiting';
        final distText = _riderDistance != null ? ' (${_riderDistance!.toStringAsFixed(1)} km away)' : '';
        return 'Preparing. ${_riderName ?? "Rider"} is heading to restaurant$distText';
      }
      return 'Chef is preparing your delicious meal';
    }
    return 'Waiting for restaurant confirmation';
  }

  String _getStepTime(String step) {
    switch (step) {
      case 'placed': return widget.order.createdAt != null ? _formatTimeShort(widget.order.createdAt!) : '';
      case 'prep': return widget.order.acceptedAt != null ? _formatTimeShort(widget.order.acceptedAt!) : '';
      case 'delivery': return widget.order.readyMarkedAt != null ? _formatTimeShort(widget.order.readyMarkedAt!) : '';
      default: return '';
    }
  }

  String _formatTimeShort(String timeStr) {
    try {
      final parsed = DateTime.parse(timeStr);
      return DateFormat('hh:mm a').format(parsed.toLocal());
    } catch (_) {
      return '';
    }
  }

  // ═══════════════════════════════════════
  //  STATIC HISTORY VIEW
  // ═══════════════════════════════════════

  Widget _buildStaticHistoryView() {
    final steps = _getStaticHistorySteps();

    return Scaffold(
      backgroundColor: const Color(0xFFF6F7F9),
      appBar: AppBar(
        title: const Text('ORDER DETAIL', style: TextStyle(fontSize: 13, fontWeight: FontWeight.w900, letterSpacing: 1)),
        centerTitle: true,
      ),
      body: SingleChildScrollView(
        padding: const EdgeInsets.all(20),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Container(
              width: double.infinity,
              padding: const EdgeInsets.all(24),
              decoration: BoxDecoration(
                color: Colors.white,
                borderRadius: BorderRadius.circular(24),
                border: Border.all(color: Colors.black.withValues(alpha: 0.05)),
              ),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Row(
                    mainAxisAlignment: MainAxisAlignment.spaceBetween,
                    children: [
                      Flexible(
                        child: Text(
                          'ORDER #${widget.order.id.substring(0, math.min(widget.order.id.length, 8)).toUpperCase()}',
                          style: GoogleFonts.spaceGrotesk(fontSize: 16, fontWeight: FontWeight.w900),
                          overflow: TextOverflow.ellipsis,
                        ),
                      ),
                      Container(
                        padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 6),
                        decoration: BoxDecoration(
                          color: _orderStatus == 'delivered' ? Colors.green.shade50 : Colors.red.shade50,
                          borderRadius: BorderRadius.circular(12),
                        ),
                        child: Text(
                          _orderStatus.toUpperCase(),
                          style: TextStyle(
                            color: _orderStatus == 'delivered' ? Colors.green.shade700 : Colors.red.shade700,
                            fontSize: 10,
                            fontWeight: FontWeight.w900,
                            letterSpacing: 0.5,
                          ),
                        ),
                      ),
                    ],
                  ),
                  const SizedBox(height: 16),
                  const Divider(height: 1, color: Color(0xFFF1F1F1)),
                  const SizedBox(height: 16),
                  Text('FROM (RESTAURANT)', style: TextStyle(fontSize: 10, fontWeight: FontWeight.w900, color: Colors.grey[400], letterSpacing: 1)),
                  const SizedBox(height: 4),
                  Row(
                    children: [
                      Expanded(
                        child: Text(widget.order.restaurantName, style: const TextStyle(fontSize: 14, fontWeight: FontWeight.bold), overflow: TextOverflow.ellipsis, maxLines: 1),
                      ),
                      IconButton(
                        onPressed: () => _callRestaurant(widget.order.id),
                        icon: const Icon(Icons.phone_rounded, color: WeloColors.sovereignOrange, size: 20),
                        tooltip: 'Call Restaurant',
                      ),
                    ],
                  ),
                  const SizedBox(height: 16),
                  Text('DELIVERED TO', style: TextStyle(fontSize: 10, fontWeight: FontWeight.w900, color: Colors.grey[400], letterSpacing: 1)),
                  const SizedBox(height: 4),
                  Text(widget.order.customerAddress, style: const TextStyle(fontSize: 14, fontWeight: FontWeight.bold), overflow: TextOverflow.ellipsis, maxLines: 2),
                ],
              ),
            ),
            const SizedBox(height: 24),

            // Timeline
            Container(
              width: double.infinity,
              padding: const EdgeInsets.all(24),
              decoration: BoxDecoration(
                color: Colors.white,
                borderRadius: BorderRadius.circular(24),
                border: Border.all(color: Colors.black.withValues(alpha: 0.05)),
              ),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text('ORDER TIMELINE', style: GoogleFonts.spaceGrotesk(fontSize: 13, fontWeight: FontWeight.w900)),
                  const SizedBox(height: 24),
                  ...steps.map((step) {
                    final isLast = steps.last == step;
                    return Row(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Column(
                          children: [
                            CircleAvatar(
                              radius: 14,
                              backgroundColor: WeloColors.sovereignOrange.withValues(alpha: 0.1),
                              child: Icon(step.icon, color: WeloColors.sovereignOrange, size: 14),
                            ),
                            if (!isLast)
                              Container(width: 2, height: 32, color: Colors.grey[200]),
                          ],
                        ),
                        const SizedBox(width: 16),
                        Expanded(
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Text(step.label, style: const TextStyle(fontSize: 13, fontWeight: FontWeight.bold)),
                              if (step.time.isNotEmpty) ...[
                                const SizedBox(height: 2),
                                Text(step.time, style: TextStyle(fontSize: 11, color: Colors.grey[500])),
                              ],
                            ],
                          ),
                        ),
                      ],
                    );
                  }).toList(),
                ],
              ),
            ),

            // Order Items
            if (widget.order.rawItems.isNotEmpty || widget.order.itemSummary.isNotEmpty) ...[
              const SizedBox(height: 24),
              Container(
                width: double.infinity,
                padding: const EdgeInsets.all(24),
                decoration: BoxDecoration(
                  color: Colors.white,
                  borderRadius: BorderRadius.circular(24),
                  border: Border.all(color: Colors.black.withValues(alpha: 0.05)),
                ),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text('ORDER ITEMS', style: GoogleFonts.spaceGrotesk(fontSize: 13, fontWeight: FontWeight.w900)),
                    const SizedBox(height: 12),
                    if (widget.order.rawItems.isNotEmpty)
                      ...widget.order.rawItems.map((item) {
                        final name = item['menu_item']?['name'] ?? item['menu_item_name'] ?? 'Item';
                        final qty = item['quantity'] ?? 1;
                        final price = double.tryParse((item['item_price'] ?? 0).toString()) ?? 0;
                        final variant = item['variant_snapshot'] is Map
                            ? item['variant_snapshot']['label'] : null;
                        final addons = item['addons_snapshot'] is List
                            ? (item['addons_snapshot'] as List).map((a) {
                                final aName = a is Map ? (a['name'] ?? '') : '';
                                final aPrice = a is Map ? (a['price'] ?? 0) : 0;
                                return aName.isNotEmpty ? '$aName (+₹${aPrice})' : '';
                              }).where((s) => s.isNotEmpty).join(', ')
                            : null;
                        return Padding(
                          padding: const EdgeInsets.only(bottom: 10),
                          child: Row(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Expanded(
                                child: Column(
                                  crossAxisAlignment: CrossAxisAlignment.start,
                                  children: [
                                    Text('$name × $qty', style: const TextStyle(fontWeight: FontWeight.w600, fontSize: 14)),
                                    if (variant != null)
                                      Text(variant, style: const TextStyle(color: Colors.black45, fontSize: 12)),
                                    if (addons != null && addons.isNotEmpty)
                                      Text(addons, style: const TextStyle(color: Colors.black38, fontSize: 12)),
                                  ],
                                ),
                              ),
                              Text('₹${(price * qty).toStringAsFixed(0)}', style: const TextStyle(fontWeight: FontWeight.w700, fontSize: 14)),
                            ],
                          ),
                        );
                      })
                    else
                      Text(widget.order.itemSummary, style: const TextStyle(fontSize: 13, fontWeight: FontWeight.w500), maxLines: 5, overflow: TextOverflow.ellipsis),
                  ],
                ),
              ),
            ],

            // Payment
            const SizedBox(height: 24),
            Container(
              width: double.infinity,
              padding: const EdgeInsets.all(24),
              decoration: BoxDecoration(
                color: Colors.white,
                borderRadius: BorderRadius.circular(24),
                border: Border.all(color: Colors.black.withValues(alpha: 0.05)),
              ),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text('PAYMENT DETAILS', style: GoogleFonts.spaceGrotesk(fontSize: 13, fontWeight: FontWeight.w900)),
                  const SizedBox(height: 16),
                  _buildPaymentRow('Subtotal', '₹${widget.order.subtotal.toStringAsFixed(0)}'),
                  if (widget.order.deliveryFee > 0)
                    _buildPaymentRow('Delivery Fee', '₹${widget.order.deliveryFee.toStringAsFixed(0)}'),
                  if (widget.order.taxAmount > 0)
                    _buildPaymentRow('Tax', '₹${widget.order.taxAmount.toStringAsFixed(0)}'),
                  const Divider(height: 20),
                  _buildPaymentRow('Total Paid', '₹${widget.order.totalAmount.toStringAsFixed(0)}', isBold: true),
                  const SizedBox(height: 8),
                  Text('Paid via ${widget.order.paymentMode.toUpperCase()}', style: TextStyle(fontSize: 11, color: Colors.grey[500])),
                ],
              ),
            ),

            if (_orderStatus == 'delivered') ...[
              const SizedBox(height: 24),
              SizedBox(
                width: double.infinity,
                child: ElevatedButton.icon(
                  onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => DownloadInvoiceScreen(orderId: widget.order.id))),
                  icon: const Icon(Icons.receipt_long, size: 18),
                  label: const Text('VIEW TAX INVOICE', style: TextStyle(fontWeight: FontWeight.bold, fontSize: 12, letterSpacing: 0.5)),
                  style: ElevatedButton.styleFrom(
                    backgroundColor: WeloColors.sovereignOrange,
                    foregroundColor: Colors.white,
                    padding: const EdgeInsets.symmetric(vertical: 16),
                    shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
                    elevation: 0,
                  ),
                ),
              ),
              const SizedBox(height: 10),
              SizedBox(
                width: double.infinity,
                child: OutlinedButton.icon(
                  onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => WriteReviewScreen(
                    restaurantName: widget.order.restaurantName,
                    restaurantId: widget.order.restaurantId,
                    orderId: widget.order.id,
                  ))),
                  icon: const Icon(Icons.rate_review_outlined, size: 18),
                  label: const Text('RATE & REVIEW', style: TextStyle(fontWeight: FontWeight.bold, fontSize: 12, letterSpacing: 0.5)),
                  style: OutlinedButton.styleFrom(
                    foregroundColor: WeloColors.sovereignGold,
                    side: const BorderSide(color: WeloColors.sovereignGold),
                    padding: const EdgeInsets.symmetric(vertical: 16),
                    shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
                  ),
                ),
              ),
              const SizedBox(height: 10),
              SizedBox(
                width: double.infinity,
                child: OutlinedButton.icon(
                  onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => ComplaintScreen(orderId: widget.order.id))),
                  icon: const Icon(Icons.report_problem_outlined, size: 18),
                  label: const Text('REPORT ISSUE', style: TextStyle(fontWeight: FontWeight.bold, fontSize: 12, letterSpacing: 0.5)),
                  style: OutlinedButton.styleFrom(
                    foregroundColor: WeloColors.error,
                    side: const BorderSide(color: WeloColors.error),
                    padding: const EdgeInsets.symmetric(vertical: 16),
                    shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
                  ),
                ),
              ),
            ],
          ],
        ),
      ),
    );
  }

  Widget _buildPaymentRow(String label, String value, {bool isBold = false}) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 8),
      child: Row(
        mainAxisAlignment: MainAxisAlignment.spaceBetween,
        children: [
          Flexible(
            child: Text(label, style: TextStyle(fontSize: 13, fontWeight: isBold ? FontWeight.bold : FontWeight.w400, color: isBold ? Colors.black : Colors.grey[600])),
          ),
          Text(value, style: TextStyle(fontSize: 13, fontWeight: isBold ? FontWeight.bold : FontWeight.w500)),
        ],
      ),
    );
  }

  List<_StaticStep> _getStaticHistorySteps() {
    final List<_StaticStep> steps = [];
    final DateFormat formatter = DateFormat('hh:mm a, dd MMM');

    String formatTime(String? timeStr) {
      if (timeStr == null) return '';
      try {
        return formatter.format(DateTime.parse(timeStr).toLocal());
      } catch (_) {
        return timeStr;
      }
    }

    if (widget.order.createdAt != null) {
      steps.add(_StaticStep(label: 'Order Placed', time: formatTime(widget.order.createdAt), icon: Icons.shopping_bag_outlined));
    }
    if (widget.order.acceptedAt != null) {
      steps.add(_StaticStep(label: 'Restaurant Confirmed', time: formatTime(widget.order.acceptedAt), icon: Icons.check_circle_outline));
    }
    if (widget.order.readyMarkedAt != null) {
      steps.add(_StaticStep(label: 'Food Prepared & Ready', time: formatTime(widget.order.readyMarkedAt), icon: Icons.takeout_dining_outlined));
    }
    if (widget.order.riderArrivedAt != null) {
      steps.add(_StaticStep(label: 'Rider Picked Up', time: formatTime(widget.order.riderArrivedAt), icon: Icons.directions_bike));
    }
    if (_orderStatus == 'delivered') {
      steps.add(_StaticStep(label: 'Delivered', time: formatTime(widget.order.readyMarkedAt), icon: Icons.home_outlined));
    } else if (_orderStatus == 'cancelled') {
      steps.add(_StaticStep(label: 'Order Cancelled', time: '', icon: Icons.cancel_outlined));
    } else if (_orderStatus == 'abandoned') {
      steps.add(_StaticStep(label: 'Order Abandoned', time: '', icon: Icons.warning_amber_outlined));
    }

    return steps;
  }
}

class _StaticStep {
  final String label;
  final String time;
  final IconData icon;
  _StaticStep({required this.label, required this.time, required this.icon});
}
