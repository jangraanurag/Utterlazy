import 'dart:async';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:geolocator/geolocator.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'package:api/api.dart';
import 'cart_state.dart';
import '../services/background_service_manager.dart';

enum RiderStatus {
  offline,
  online,
  receivedRequest,
  navigatingToRestaurant,
  atRestaurant,
  navigatingToCustomer,
  atCustomer,
  completed
}

class RiderState {
  final RiderStatus status;
  final Order? currentOrder;
  final double latitude;
  final double longitude;
  final double payout;
  final String? otpCode;
  final bool isLoading;
  final double? restaurantLatitude;
  final double? restaurantLongitude;
  final double? customerLatitude;
  final double? customerLongitude;

  RiderState({
    this.status = RiderStatus.offline,
    this.currentOrder,
    this.latitude = 0.0,
    this.longitude = 0.0,
    this.payout = 0.0,
    this.otpCode,
    this.isLoading = false,
    this.restaurantLatitude,
    this.restaurantLongitude,
    this.customerLatitude,
    this.customerLongitude,
  });

  bool get isOnline => status != RiderStatus.offline;

  RiderState copyWith({
    RiderStatus? status,
    Order? currentOrder,
    double? latitude,
    double? longitude,
    double? payout,
    String? otpCode,
    bool? isLoading,
    double? restaurantLatitude,
    double? restaurantLongitude,
    double? customerLatitude,
    double? customerLongitude,
  }) {
    return RiderState(
      status: status ?? this.status,
      currentOrder: currentOrder ?? this.currentOrder,
      latitude: latitude ?? this.latitude,
      longitude: longitude ?? this.longitude,
      payout: payout ?? this.payout,
      otpCode: otpCode ?? this.otpCode,
      isLoading: isLoading ?? this.isLoading,
      restaurantLatitude: restaurantLatitude ?? this.restaurantLatitude,
      restaurantLongitude: restaurantLongitude ?? this.restaurantLongitude,
      customerLatitude: customerLatitude ?? this.customerLatitude,
      customerLongitude: customerLongitude ?? this.customerLongitude,
    );
  }
}

class RiderNotifier extends StateNotifier<RiderState> {
  final DeliveryRepository _deliveryRepository;
  Timer? _locationTimer;
  Timer? _assignmentPollTimer;
  Timer? _activeOrderPollTimer;

  RiderNotifier(this._deliveryRepository) : super(RiderState()) {
    _restoreOnlineStatus();
  }

  Future<void> _restoreOnlineStatus() async {
    try {
      final prefs = await SharedPreferences.getInstance();
      final wasOnline = prefs.getBool('rider_is_online') ?? false;
      if (wasOnline) {
        // Fetch current assignment silently
        try {
          final order = await _deliveryRepository.getPendingOrder();
          if (order != null && mounted) {
            final backendPayout = order.deliveryFee > 0 ? order.deliveryFee : 45.0 + (order.totalAmount * 0.05);
            final RiderStatus restoreStatus;
            if (order.assignmentId != null && order.assignmentId!.isNotEmpty) {
              restoreStatus = RiderStatus.receivedRequest;
            } else if (order.status == 'out_for_delivery') {
              if (order.riderAtCustomerAt != null) {
                restoreStatus = RiderStatus.atCustomer;
              } else {
                restoreStatus = RiderStatus.navigatingToCustomer;
              }
            } else if (order.status == 'delivered') {
              restoreStatus = RiderStatus.completed;
            } else if (order.status == 'cancelled' || order.status == 'abandoned') {
              restoreStatus = RiderStatus.online;
            } else if (order.riderArrivedAt != null) {
              restoreStatus = RiderStatus.atRestaurant;
            } else {
              restoreStatus = RiderStatus.navigatingToRestaurant;
            }
            state = state.copyWith(
              status: restoreStatus,
              currentOrder: order,
              payout: backendPayout,
              otpCode: order.deliveryOtp,
              restaurantLatitude: order.restaurantLatitude,
              restaurantLongitude: order.restaurantLongitude,
              customerLatitude: order.customerLatitude,
              customerLongitude: order.customerLongitude,
            );
            _startActiveOrderPolling();
            return;
          }
        } catch (_) {}
        // No active order — restore online status
        await toggleOnline(true);
      }
    } catch (_) {}
  }

  Future<void> toggleOnline(bool online) async {
    state = state.copyWith(isLoading: true);
    try {
      await _deliveryRepository.setRiderStatus(online);
      if (online) {
        var permission = await Geolocator.checkPermission();
        if (permission == LocationPermission.denied) {
          permission = await Geolocator.requestPermission();
        }
        
        state = state.copyWith(
          status: RiderStatus.online,
          isLoading: false,
        );
        _startAssignmentPolling();
        _startLocationUpdates();
        BackgroundServiceManager.start('rider');
      } else {
        _stopAssignmentPolling();
        _stopLocationUpdates();
        _stopActiveOrderPolling();
        BackgroundServiceManager.stop();
        state = state.copyWith(
          status: RiderStatus.offline,
          currentOrder: null,
          isLoading: false,
        );
      }
      // Persist online status
      final prefs = await SharedPreferences.getInstance();
      await prefs.setBool('rider_is_online', online);
    } catch (e) {
      state = state.copyWith(isLoading: false);
    }
  }

  void _startLocationUpdates() {
    _locationTimer?.cancel();
    _locationTimer = Timer.periodic(const Duration(seconds: 8), (timer) async {
      if (state.status == RiderStatus.offline) {
        timer.cancel();
        return;
      }
      
      // Get real GPS position
      try {
        final permission = await Geolocator.checkPermission();
        if (permission == LocationPermission.always ||
            permission == LocationPermission.whileInUse) {
          Position? position;
          try {
            position = await Geolocator.getCurrentPosition(
              desiredAccuracy: LocationAccuracy.high,
              timeLimit: const Duration(seconds: 4),
            );
          } catch (_) {
            position = await Geolocator.getLastKnownPosition();
          }

          if (position != null && mounted) {
            state = state.copyWith(
              latitude: position.latitude,
              longitude: position.longitude,
            );
            _deliveryRepository.updateLocation(position.latitude, position.longitude);
          }
        }
      } catch (_) {}
    });
  }

  void _stopLocationUpdates() {
    _locationTimer?.cancel();
    _locationTimer = null;
  }

  void _startAssignmentPolling() {
    _assignmentPollTimer?.cancel();
    // Poll for orders every 8 seconds
    _assignmentPollTimer = Timer.periodic(const Duration(seconds: 8), (timer) async {
      if (state.status == RiderStatus.online) {
        final order = await _deliveryRepository.getPendingOrder();
        if (order != null && mounted) {
          final backendPayout = order.deliveryFee > 0 ? order.deliveryFee : 45.0 + (order.totalAmount * 0.05);
          // Determine correct status based on order state
          final RiderStatus newStatus;
          if (order.assignmentId != null && order.assignmentId!.isNotEmpty) {
            // Has a pending assignment — show accept screen
            newStatus = RiderStatus.receivedRequest;
          } else if (order.status == 'out_for_delivery') {
            if (order.riderAtCustomerAt != null) {
              // Rider has arrived at customer
              newStatus = RiderStatus.atCustomer;
            } else {
              // Navigating to customer
              newStatus = RiderStatus.navigatingToCustomer;
            }
          } else if (order.status == 'delivered') {
            newStatus = RiderStatus.completed;
          } else if (order.status == 'cancelled' || order.status == 'abandoned') {
            newStatus = RiderStatus.online;
          } else if (order.riderArrivedAt != null) {
            // Rider has arrived at restaurant — waiting to pick up
            newStatus = RiderStatus.atRestaurant;
          } else {
            // preparing or ready_for_pickup — heading to restaurant
            newStatus = RiderStatus.navigatingToRestaurant;
          }

          state = state.copyWith(
            status: newStatus,
            currentOrder: order,
            payout: backendPayout,
            otpCode: order.deliveryOtp,
            restaurantLatitude: order.restaurantLatitude,
            restaurantLongitude: order.restaurantLongitude,
            customerLatitude: order.customerLatitude,
            customerLongitude: order.customerLongitude,
          );
          _stopAssignmentPolling();
          _startActiveOrderPolling();
        }
      }
    });
  }

  void _stopAssignmentPolling() {
    _assignmentPollTimer?.cancel();
    _assignmentPollTimer = null;
  }

  Future<void> acceptOrder() async {
    if (state.currentOrder == null) return;
    final assignmentId = state.currentOrder!.assignmentId;
    if (assignmentId == null) return;
    state = state.copyWith(isLoading: true);
    try {
      await _deliveryRepository.acceptOrder(assignmentId);
      state = state.copyWith(
        status: RiderStatus.navigatingToRestaurant,
        isLoading: false,
      );
      _startLocationUpdates();
      _startActiveOrderPolling();
    } catch (e) {
      state = state.copyWith(isLoading: false);
    }
  }

  Future<void> rejectOrder() async {
    if (state.currentOrder == null) return;
    final assignmentId = state.currentOrder!.assignmentId;
    if (assignmentId == null) return;
    try {
      await _deliveryRepository.rejectOrder(assignmentId);
    } catch (_) {}
    state = state.copyWith(
      status: RiderStatus.online,
      currentOrder: null,
      isLoading: false,
      restaurantLatitude: null,
      restaurantLongitude: null,
      customerLatitude: null,
      customerLongitude: null,
    );
    _stopActiveOrderPolling();
    _startAssignmentPolling();
  }

  Future<void> arriveAtRestaurant() async {
    state = state.copyWith(isLoading: true);
    try {
      await _deliveryRepository.markRiderArrived(state.currentOrder!.id);
      state = state.copyWith(
        status: RiderStatus.atRestaurant,
        isLoading: false,
      );
    } catch (e) {
      state = state.copyWith(isLoading: false);
    }
  }

  Future<void> pickUpOrder() async {
    state = state.copyWith(isLoading: true);
    try {
      await _deliveryRepository.updateOrderStatus(state.currentOrder!.id, 'out_for_delivery');
      state = state.copyWith(
        status: RiderStatus.navigatingToCustomer,
        isLoading: false,
      );
    } catch (e) {
      state = state.copyWith(isLoading: false);
    }
  }

  Future<void> arriveAtCustomer() async {
    state = state.copyWith(isLoading: true);
    try {
      await _deliveryRepository.markRiderAtCustomer(state.currentOrder!.id);
      state = state.copyWith(
        status: RiderStatus.atCustomer,
        isLoading: false,
      );
    } catch (e) {
      state = state.copyWith(isLoading: false);
    }
  }

  Future<bool> deliverOrder(String otp) async {
    if (state.currentOrder == null) return false;
    state = state.copyWith(isLoading: true);
    try {
      await _deliveryRepository.updateOrderStatus(
        state.currentOrder!.id,
        'delivered',
        deliveryOtp: state.currentOrder!.paymentMode == 'prepaid' ? otp : null,
      );
      state = state.copyWith(
        status: RiderStatus.completed,
        isLoading: false,
      );
      _stopActiveOrderPolling();
      return true;
    } catch (e) {
      state = state.copyWith(isLoading: false);
      return false;
    }
  }

  void completeOrderCycle() {
    state = state.copyWith(
      status: RiderStatus.online,
      currentOrder: null,
      payout: 0.0,
      otpCode: null,
      restaurantLatitude: null,
      restaurantLongitude: null,
      customerLatitude: null,
      customerLongitude: null,
    );
    _stopActiveOrderPolling();
    _startAssignmentPolling();
  }

  void _startActiveOrderPolling() {
    _activeOrderPollTimer?.cancel();
    _activeOrderPollTimer = Timer.periodic(const Duration(seconds: 8), (timer) async {
      if (state.currentOrder == null) {
        timer.cancel();
        return;
      }
      try {
        final order = await _deliveryRepository.getPendingOrder();
        if (order == null && mounted) {
          state = state.copyWith(
            status: RiderStatus.online,
            currentOrder: null,
          );
          _startAssignmentPolling();
          _stopActiveOrderPolling();
        } else if (order != null && mounted) {
          if (order.status == 'delivered' || order.status == 'cancelled' || order.status == 'abandoned') {
            state = state.copyWith(
              status: order.status == 'delivered' ? RiderStatus.completed : RiderStatus.online,
              currentOrder: order.status == 'delivered' ? order : null,
              otpCode: order.deliveryOtp,
              restaurantLatitude: null,
              restaurantLongitude: null,
              customerLatitude: null,
              customerLongitude: null,
            );
            if (order.status != 'delivered') {
              _startAssignmentPolling();
            }
            _stopActiveOrderPolling();
          } else {
            state = state.copyWith(
              currentOrder: order,
              otpCode: order.deliveryOtp,
            );
          }
        }
      } catch (_) {
        // Ignore background polling errors
      }
    });
  }

  void _stopActiveOrderPolling() {
    _activeOrderPollTimer?.cancel();
    _activeOrderPollTimer = null;
  }

  Future<void> refreshState() async {
    if (state.status == RiderStatus.online) {
      final order = await _deliveryRepository.getPendingOrder();
        if (order != null && mounted) {
          final backendPayout = order.deliveryFee > 0 ? order.deliveryFee : 45.0 + (order.totalAmount * 0.05);
          state = state.copyWith(
            status: RiderStatus.receivedRequest,
            currentOrder: order,
            payout: backendPayout,
            otpCode: order.deliveryOtp,
            restaurantLatitude: order.restaurantLatitude,
            restaurantLongitude: order.restaurantLongitude,
            customerLatitude: order.customerLatitude,
            customerLongitude: order.customerLongitude,
          );
          _stopAssignmentPolling();
          _startActiveOrderPolling();
        }
    } else if (state.status != RiderStatus.offline) {
      final order = await _deliveryRepository.getPendingOrder();
      if (order == null && mounted) {
        state = state.copyWith(
          status: RiderStatus.online,
          currentOrder: null,
        );
        _startAssignmentPolling();
        _stopActiveOrderPolling();
      } else if (order != null && mounted) {
        RiderStatus newStatus = state.status;
        if (order.status == 'delivered') {
          newStatus = RiderStatus.completed;
          _stopActiveOrderPolling();
        } else if (order.status == 'cancelled' || order.status == 'abandoned') {
          newStatus = RiderStatus.online;
          _startAssignmentPolling();
          _stopActiveOrderPolling();
        }
        state = state.copyWith(
          currentOrder: order,
          status: newStatus,
          otpCode: order.deliveryOtp,
        );
      }
    }
  }

  @override
  void dispose() {
    _locationTimer?.cancel();
    _assignmentPollTimer?.cancel();
    _activeOrderPollTimer?.cancel();
    super.dispose();
  }
}

final deliveryRepositoryProvider = Provider<DeliveryRepository>((ref) {
  return DeliveryRepository(ref.read(apiClientProvider));
});

final riderStateProvider = StateNotifierProvider<RiderNotifier, RiderState>((ref) {
  final apiClient = ref.read(apiClientProvider);
  final deliveryRepository = DeliveryRepository(apiClient);
  return RiderNotifier(deliveryRepository);
});
