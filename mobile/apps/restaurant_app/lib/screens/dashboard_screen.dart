import 'dart:async';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:ui/ui.dart';
import 'package:api/api.dart';
import 'package:core/core.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'complaints_screen.dart';
import 'reviews_screen.dart';
import '../main.dart';

class DashboardScreen extends ConsumerStatefulWidget {
  const DashboardScreen({super.key});

  @override
  ConsumerState<DashboardScreen> createState() => _DashboardScreenState();
}

class _DashboardScreenState extends ConsumerState<DashboardScreen> {
  bool _isOnline = true;
  bool _isLoading = true;
  bool _autoAccept = false;
  String? _pausedUntilStr;
  DateTime? _pausedUntil;
  String _selectedOrderTab = 'preparing';
  List<Order> _activeOrders = [];
  double _revenue = 0.0;
  int _completedCount = 0;
  int _openComplaints = 0;
  int _totalReviews = 0;
  double _avgRating = 0;

  Timer? _pollingTimer;
  Timer? _orderCardTimer;
  String? _alertingOrderId;
  final Set<String> _notifiedRiders = {};
  final Set<String> _autoAcceptedOrders = {};
  final NotificationAlertService _alertService = NotificationAlertService();
  
  StreamSubscription? _newOrderSubscription;
  StreamSubscription? _orderStatusSubscription;

  @override
  void initState() {
    super.initState();
    _alertService.initialize();
    _loadAutoAcceptPreference();
    _loadDashboardData();
    _pollingTimer = Timer.periodic(const Duration(seconds: 10), (timer) {
      _loadDashboardData(isSilent: true);
    });
    _orderCardTimer = Timer.periodic(const Duration(seconds: 1), (timer) {
      if (mounted) { setState(() {}); }
    });
    
    _newOrderSubscription = ref.read(webSocketServiceProvider).newOrderStream.listen((event) {
      if (mounted) _loadDashboardData(isSilent: true);
    });
    _orderStatusSubscription = ref.read(webSocketServiceProvider).orderStatusStream.listen((event) {
      if (mounted) _loadDashboardData(isSilent: true);
    });
  }

  @override
  void dispose() {
    _pollingTimer?.cancel();
    _orderCardTimer?.cancel();
    _alertService.stopAll();
    _newOrderSubscription?.cancel();
    _orderStatusSubscription?.cancel();
    super.dispose();
  }

  Future<void> _loadAutoAcceptPreference() async {
    final prefs = await SharedPreferences.getInstance();
    setState(() {
      _autoAccept = prefs.getBool('vendor_auto_accept') ?? false;
    });
  }

  Future<void> _toggleAutoAccept(bool val) async {
    setState(() => _autoAccept = val);
    final prefs = await SharedPreferences.getInstance();
    await prefs.setBool('vendor_auto_accept', val);
    try {
      final vendorRepo = ref.read(vendorRepositoryProvider);
      await vendorRepo.updateProfile({'auto_accept_orders': val});
    } catch (_) {}
  }

  Future<void> _loadDashboardData({bool isSilent = false}) async {
    if (!isSilent) setState(() => _isLoading = true);
    try {
      final vendorRepo = ref.read(vendorRepositoryProvider);
      
      try {
        final profile = await vendorRepo.getProfile();
        final rest = profile['restaurant'];
        _isOnline = rest?['is_accepting_orders'] == 1 || rest?['is_accepting_orders'] == true;
        _autoAccept = rest?['auto_accept_orders'] == 1 || rest?['auto_accept_orders'] == true;
        _pausedUntilStr = rest?['paused_until'];
        if (_pausedUntilStr != null) {
          _pausedUntil = DateTime.tryParse(_pausedUntilStr!)?.toLocal();
        } else {
          _pausedUntil = null;
        }

        final restaurantId = rest?['id']?.toString();
        if (restaurantId != null) {
          final prefs = await SharedPreferences.getInstance();
          await prefs.setString('vendor_id', restaurantId);
          ref.read(webSocketServiceProvider).subscribeToVendor(restaurantId);
          ref.read(webSocketServiceProvider).subscribeToRestaurantApprovals(restaurantId);
        }
      } catch (_) {}

      final orders = await vendorRepo.getActiveOrders();
      
      try {
        final earnings = await vendorRepo.getEarnings('today');
        _revenue = double.tryParse((earnings['revenue'] ?? 0.0)?.toString() ?? '0') ?? 0.0;
        _completedCount = earnings['completed_orders'] ?? 0;
      } catch (_) {}

      try {
        final apiClient = ref.read(apiClientProvider);
        final compRes = await apiClient.dio.get('/vendor/complaints', queryParameters: {'status': 'pending'});
        final compData = compRes.data['data'] ?? compRes.data;
        final compItems = compData is List ? compData : (compData['data'] ?? compData['complaints'] ?? []);
        _openComplaints = compItems.length;
      } catch (_) {}

      try {
        final apiClient = ref.read(apiClientProvider);
        final revRes = await apiClient.dio.get('/vendor/reviews/stats');
        final revData = revRes.data['data'] ?? revRes.data;
        _totalReviews = revData['total_reviews'] ?? 0;
        _avgRating = double.tryParse((revData['average_rating'] ?? 0)?.toString() ?? '0') ?? 0.0;
      } catch (_) {}

      if (mounted) {
        setState(() {
          _activeOrders = orders;
          _isLoading = false;
        });

        if (_isOnline) {
          BackgroundServiceManager.start('vendor');
          _checkPendingOrders(orders);
          _checkRiderArrivals(orders);
        } else {
          BackgroundServiceManager.stop();
          _stopAlerts();
        }
      }
    } catch (e) {
      if (mounted && !isSilent) setState(() => _isLoading = false);
    }
  }

  void _checkPendingOrders(List<Order> orders) {
    final pendingOrders = orders.where((o) => o.status == 'pending').toList();
    if (pendingOrders.isNotEmpty) {
      final newPending = pendingOrders.first;

      // Auto-accept: inform once then accept
      if (_autoAccept && !_autoAcceptedOrders.contains(newPending.id)) {
        _autoAcceptedOrders.add(newPending.id);
        _informAndAutoAccept(newPending);
        return;
      }

      if (_alertingOrderId != newPending.id) {
        _alertingOrderId = newPending.id;
        _startAlerts(newPending);
      }
    } else {
      _stopAlerts();
    }
  }

  void _informAndAutoAccept(Order order) async {
    // Inform user with TTS + sound + vibration
    await _alertService.startOrderAlert(
      order.id,
      order.totalAmount,
      address: order.customerAddress,
    );

    // Show a brief snackbar to inform
    if (mounted) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text('Auto-accepting Order #${order.id.substring(0, 8).toUpperCase()} — ₹${order.totalAmount.toInt()}'),
          backgroundColor: WeloColors.success,
          duration: const Duration(seconds: 3),
        ),
      );
    }

    // Wait 2 seconds for user to hear/see the info, then auto-accept
    await Future.delayed(const Duration(seconds: 2));

    try {
      final vendorRepo = ref.read(vendorRepositoryProvider);
      await vendorRepo.acceptOrder(order.id);
      _alertService.stopAll();

      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text('Order #${order.id.substring(0, 8).toUpperCase()} accepted!'),
            backgroundColor: WeloColors.success,
          ),
        );
      }

      _loadDashboardData(isSilent: true);
    } catch (e) {
      _alertService.stopAll();
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('Auto-accept failed. Please accept manually.')),
        );
      }
    }
  }

  void _checkRiderArrivals(List<Order> orders) {
    for (final order in orders) {
      if (order.riderArrived && !_notifiedRiders.contains(order.id)) {
        _notifiedRiders.add(order.id);
        _alertService.startRiderArrivalAlert();
      }
    }
  }

  void _startAlerts(Order order) async {
    // Play alarm + vibrate + TTS
    _alertService.startOrderAlert(order.id, order.totalAmount, address: order.customerAddress);

    if (mounted) {
      showDialog(
        context: context,
        barrierDismissible: false,
        builder: (context) => _IncomingOrderAlert(
          order: order,
          onAccept: (extraMinutes) async {
            Navigator.pop(context);
            _alertService.stopAll();
            try {
              final vendorRepo = ref.read(vendorRepositoryProvider);
              await vendorRepo.acceptOrder(order.id, extraMinutes: extraMinutes);
              _loadDashboardData();
            } catch (e) {
              if (!mounted) return;
              ScaffoldMessenger.of(context).showSnackBar(
                const SnackBar(content: Text('Failed to accept order.')),
              );
            }
          },
          onReject: () async {
            Navigator.pop(context);
            _alertService.stopAll();
            try {
              final vendorRepo = ref.read(vendorRepositoryProvider);
              await vendorRepo.rejectOrder(order.id, 'Restaurant busy');
              _loadDashboardData();
            } catch (e) {
              if (!mounted) return;
              ScaffoldMessenger.of(context).showSnackBar(
                const SnackBar(content: Text('Failed to reject order.')),
              );
            }
          },
        ),
      ).then((_) => _alertService.stopAll());
    }
  }

  void _stopAlerts() {
    _alertingOrderId = null;
    _alertService.stopAll();
  }

  Future<void> _handleToggleOnline(bool val) async {
    if (!val) {
      _showOfflineOptionsDialog();
    } else {
      await _handleResumeOrders();
    }
  }

  void _showOfflineOptionsDialog() {
    showDialog(
      context: context,
      builder: (context) => Dialog(
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(24)),
        child: Padding(
          padding: const EdgeInsets.all(24),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Row(
                children: [
                  Container(
                    padding: const EdgeInsets.all(8),
                    decoration: BoxDecoration(
                      color: WeloColors.error.withValues(alpha: 0.078),
                      shape: BoxShape.circle,
                    ),
                    child: const Icon(
                      Icons.power_settings_new_rounded,
                      color: WeloColors.error,
                      size: 24,
                    ),
                  ),
                  const SizedBox(width: 12),
                  const Text(
                    'Go Offline Options',
                    style: TextStyle(
                      fontWeight: FontWeight.bold,
                      fontSize: 18,
                    ),
                  ),
                ],
              ),
              const SizedBox(height: 16),
              const Text(
                'Choose how long you want to remain offline:',
                style: TextStyle(color: Colors.black54, fontSize: 13),
              ),
              const SizedBox(height: 20),
              _buildOptionCard(
                title: 'Go Offline for 2 Hours',
                subtitle: 'Temporary break. Auto-opens after 2 hours.',
                icon: Icons.timer_outlined,
                color: WeloColors.sovereignOrange,
                onTap: () async {
                  Navigator.pop(context);
                  await _goOffline('2_hours');
                },
              ),
              const SizedBox(height: 12),
              _buildOptionCard(
                title: 'Till Day End',
                subtitle: 'Will auto-reset and open at 6:00 AM tomorrow.',
                icon: Icons.brightness_3_outlined,
                color: Colors.indigo,
                onTap: () async {
                  Navigator.pop(context);
                  await _goOffline('till_day_end');
                },
              ),
              const SizedBox(height: 12),
              _buildOptionCard(
                title: 'Till I Go Online Manually',
                subtitle: 'Stays offline indefinitely. Will NOT reset at 6:00 AM.',
                icon: Icons.do_not_disturb_on_total_silence_rounded,
                color: WeloColors.error,
                onTap: () async {
                  Navigator.pop(context);
                  await _goOffline('till_manual');
                },
              ),
              const SizedBox(height: 12),
              _buildOptionCard(
                title: 'Till Custom Date & Time',
                subtitle: 'Select a specific date and time to auto-resume.',
                icon: Icons.date_range_outlined,
                color: WeloColors.sovereignTeal,
                onTap: () async {
                  Navigator.pop(context);
                  await _selectCustomDateTime();
                },
              ),
              const SizedBox(height: 20),
              Row(
                mainAxisAlignment: MainAxisAlignment.end,
                children: [
                  TextButton(
                    onPressed: () => Navigator.pop(context),
                    child: const Text('Cancel', style: TextStyle(fontWeight: FontWeight.bold, color: Colors.grey)),
                  ),
                ],
              ),
            ],
          ),
        ),
      ),
    );
  }

  void _showBusyModeDialog() {
    showDialog(
      context: context,
      builder: (context) => Dialog(
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(24)),
        child: Padding(
          padding: const EdgeInsets.all(24),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Row(
                children: [
                  Container(
                    padding: const EdgeInsets.all(8),
                    decoration: BoxDecoration(
                      color: WeloColors.sovereignOrange.withValues(alpha: 0.078),
                      shape: BoxShape.circle,
                    ),
                    child: const Icon(
                      Icons.timer_outlined,
                      color: WeloColors.sovereignOrange,
                      size: 24,
                    ),
                  ),
                  const SizedBox(width: 12),
                  const Text(
                    'Pause Incoming Orders',
                    style: TextStyle(
                      fontWeight: FontWeight.bold,
                      fontSize: 18,
                    ),
                  ),
                ],
              ),
              const SizedBox(height: 16),
              const Text(
                'Select how long you want to pause incoming orders (Busy Mode). The restaurant will automatically resume receiving orders after the selected duration.',
                style: TextStyle(color: Colors.black54, fontSize: 13),
              ),
              const SizedBox(height: 20),
              _buildOptionCard(
                title: 'Pause for 30 Minutes',
                subtitle: 'Kitchen is slightly busy. Auto-resumes in 30 minutes.',
                icon: Icons.timer,
                color: WeloColors.sovereignOrange,
                onTap: () async {
                  Navigator.pop(context);
                  await _pauseOrders(30);
                },
              ),
              const SizedBox(height: 12),
              _buildOptionCard(
                title: 'Pause for 1 Hour',
                subtitle: 'Kitchen is very busy. Auto-resumes in 1 hour.',
                icon: Icons.timer,
                color: WeloColors.sovereignOrange,
                onTap: () async {
                  Navigator.pop(context);
                  await _pauseOrders(60);
                },
              ),
              const SizedBox(height: 12),
              _buildOptionCard(
                title: 'Pause for 2 Hours',
                subtitle: 'Severe backlog. Auto-resumes in 2 hours.',
                icon: Icons.timer,
                color: WeloColors.sovereignOrange,
                onTap: () async {
                  Navigator.pop(context);
                  await _pauseOrders(120);
                },
              ),
              const SizedBox(height: 20),
              Row(
                mainAxisAlignment: MainAxisAlignment.end,
                children: [
                  TextButton(
                    onPressed: () => Navigator.pop(context),
                    child: const Text('Cancel', style: TextStyle(fontWeight: FontWeight.bold, color: Colors.grey)),
                  ),
                ],
              ),
            ],
          ),
        ),
      ),
    );
  }

  Widget _buildOptionCard({
    required String title,
    required String subtitle,
    required IconData icon,
    required Color color,
    required VoidCallback onTap,
  }) {
    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(16),
      child: Container(
        padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
        decoration: BoxDecoration(
          border: Border.all(color: Colors.grey.shade200),
          borderRadius: BorderRadius.circular(16),
        ),
        child: Row(
          children: [
            Icon(icon, color: color, size: 24),
            const SizedBox(width: 12),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    title,
                    style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 13),
                  ),
                  const SizedBox(height: 2),
                  Text(
                    subtitle,
                    style: const TextStyle(fontSize: 10, color: Colors.black38),
                  ),
                ],
              ),
            ),
            const Icon(Icons.chevron_right, color: Colors.grey, size: 18),
          ],
        ),
      ),
    );
  }

  Future<void> _goOffline(String option, {String? customDatetime}) async {
    setState(() => _isLoading = true);
    try {
      final vendorRepo = ref.read(vendorRepositoryProvider);
      await vendorRepo.goOffline(option, customDatetime: customDatetime);
      await _loadDashboardData();
    } catch (_) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('Failed to update status.')),
        );
      }
    } finally {
      if (mounted) setState(() => _isLoading = false);
    }
  }

  Future<void> _selectCustomDateTime() async {
    final date = await showDatePicker(
      context: context,
      initialDate: DateTime.now().add(const Duration(days: 1)),
      firstDate: DateTime.now(),
      lastDate: DateTime.now().add(const Duration(days: 30)),
    );
    if (date == null) return;

    if (!mounted) return;
    final time = await showTimePicker(
      context: context,
      initialTime: const TimeOfDay(hour: 0, minute: 0),
    );
    if (time == null) return;

    final selectedDateTime = DateTime(
      date.year,
      date.month,
      date.day,
      time.hour,
      time.minute,
    );

    if (selectedDateTime.isBefore(DateTime.now())) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('Please select a future time.')),
        );
      }
      return;
    }

    await _goOffline('custom', customDatetime: selectedDateTime.toIso8601String());
  }

  Future<void> _pauseOrders(int minutes) async {
    setState(() => _isLoading = true);
    try {
      final vendorRepo = ref.read(vendorRepositoryProvider);
      await vendorRepo.pauseOrders(minutes);
      await _loadDashboardData();
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text('Orders paused for $minutes minutes!'),
            backgroundColor: WeloColors.sovereignOrange,
          ),
        );
      }
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('Failed to pause orders.')),
        );
      }
    } finally {
      if (mounted) setState(() => _isLoading = false);
    }
  }

  Future<void> _handleResumeOrders() async {
    setState(() => _isLoading = true);
    try {
      final vendorRepo = ref.read(vendorRepositoryProvider);
      await vendorRepo.resumeOrders();
      await _loadDashboardData();
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(
            content: Text('Orders resumed! You are online now.'),
            backgroundColor: WeloColors.success,
          ),
        );
      }
    } catch (_) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('Failed to resume orders.')),
        );
      }
    } finally {
      if (mounted) setState(() => _isLoading = false);
    }
  }

  Widget _buildPauseBanner() {
    if (_pausedUntil == null) return const SizedBox.shrink();
    final remaining = _pausedUntil!.difference(DateTime.now());
    if (remaining.isNegative) return const SizedBox.shrink();

    final minutes = remaining.inMinutes;
    final seconds = remaining.inSeconds % 60;
    final countdownStr = '$minutes:${seconds.toString().padLeft(2, '0')}';

    return Container(
      margin: const EdgeInsets.only(bottom: 16),
      padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
      decoration: BoxDecoration(
        color: WeloColors.sovereignOrange.withValues(alpha: 0.078),
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: WeloColors.sovereignOrange.withValues(alpha: 0.314), width: 1.5),
      ),
      child: Row(
        children: [
          const Icon(
            Icons.pause_circle_filled_rounded,
            color: WeloColors.sovereignOrange,
            size: 24,
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                const Text(
                  'Orders Paused (Busy Mode)',
                  style: TextStyle(
                    fontWeight: FontWeight.bold,
                    fontSize: 14,
                    color: WeloColors.sovereignOrange,
                  ),
                ),
                Text(
                  'Auto-resumes in $countdownStr',
                  style: const TextStyle(
                    fontSize: 12,
                    color: WeloColors.textBody,
                  ),
                ),
              ],
            ),
          ),
          ElevatedButton(
            onPressed: _handleResumeOrders,
            style: ElevatedButton.styleFrom(
              backgroundColor: WeloColors.success,
              foregroundColor: Colors.white,
              padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
              shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
            ),
            child: const Text('Resume', style: TextStyle(fontSize: 12, fontWeight: FontWeight.bold)),
          ),
        ],
      ),
    );
  }

  void _showRejectOrderDialog(Order order) {
    showDialog(
      context: context,
      builder: (context) => Dialog(
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(24)),
        child: Padding(
          padding: const EdgeInsets.all(24),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Row(
                children: [
                  Container(
                    padding: const EdgeInsets.all(8),
                    decoration: BoxDecoration(
                      color: WeloColors.error.withValues(alpha: 0.078),
                      shape: BoxShape.circle,
                    ),
                    child: const Icon(
                      Icons.warning_amber_rounded,
                      color: WeloColors.error,
                      size: 24,
                    ),
                  ),
                  const SizedBox(width: 12),
                  const Text(
                    'Cancel Order Warning',
                    style: TextStyle(
                      fontWeight: FontWeight.bold,
                      fontSize: 18,
                      color: WeloColors.error,
                    ),
                  ),
                ],
              ),
              const SizedBox(height: 16),
              const Text(
                'Warning: Cancelling this order requires a full refund. We strongly recommend contacting the customer first to resolve any issue.',
                style: TextStyle(color: Colors.black87, fontWeight: FontWeight.bold, fontSize: 13),
              ),
              const SizedBox(height: 8),
              if (order.customerName != null || order.customerPhone != null) ...[
                Container(
                  padding: const EdgeInsets.all(12),
                  decoration: BoxDecoration(
                    color: Colors.grey.shade50,
                    border: Border.all(color: Colors.grey.shade200),
                    borderRadius: BorderRadius.circular(12),
                  ),
                  child: Row(
                    children: [
                      const Icon(Icons.person, color: WeloColors.textLight, size: 20),
                      const SizedBox(width: 8),
                      Expanded(
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Text(order.customerName ?? 'Customer', style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 13)),
                            if (order.customerPhone != null)
                              Text(order.customerPhone!, style: const TextStyle(color: WeloColors.textLight, fontSize: 11)),
                          ],
                        ),
                      ),
                      if (order.customerPhone != null)
                        IconButton(
                          icon: const Icon(Icons.phone, color: WeloColors.success),
                          onPressed: () {
                            ScaffoldMessenger.of(context).showSnackBar(
                              SnackBar(
                                content: Text('Calling customer at ${order.customerPhone}...'),
                                backgroundColor: WeloColors.success,
                              ),
                            );
                          },
                        ),
                    ],
                  ),
                ),
                const SizedBox(height: 16),
              ],
              const Text(
                'Choose Cancellation Option:',
                style: TextStyle(fontWeight: FontWeight.bold, fontSize: 12, color: Colors.black54),
              ),
              const SizedBox(height: 12),
              _buildCancelOptionCard(
                title: 'Cancel Order Only',
                subtitle: 'Reject the order and initiate a full refund.',
                icon: Icons.cancel_outlined,
                color: WeloColors.error,
                onTap: () async {
                  Navigator.pop(context);
                  await _cancelOrder(order.id, 0);
                },
              ),
              const SizedBox(height: 10),
              _buildCancelOptionCard(
                title: 'Cancel & Pause for 30 Mins',
                subtitle: 'Cancel order and stop incoming orders for 30 mins.',
                icon: Icons.timer_outlined,
                color: WeloColors.sovereignOrange,
                onTap: () async {
                  Navigator.pop(context);
                  await _cancelOrder(order.id, 30);
                },
              ),
              const SizedBox(height: 10),
              _buildCancelOptionCard(
                title: 'Cancel & Pause for 1 Hour',
                subtitle: 'Cancel order and stop incoming orders for 1 hour.',
                icon: Icons.timer_outlined,
                color: WeloColors.sovereignOrange,
                onTap: () async {
                  Navigator.pop(context);
                  await _cancelOrder(order.id, 60);
                },
              ),
              const SizedBox(height: 20),
              Row(
                mainAxisAlignment: MainAxisAlignment.end,
                children: [
                  TextButton(
                    onPressed: () => Navigator.pop(context),
                    child: const Text('Go Back', style: TextStyle(fontWeight: FontWeight.bold, color: Colors.grey)),
                  ),
                ],
              ),
            ],
          ),
        ),
      ),
    );
  }

  Widget _buildCancelOptionCard({
    required String title,
    required String subtitle,
    required IconData icon,
    required Color color,
    required VoidCallback onTap,
  }) {
    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(12),
      child: Container(
        padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
        decoration: BoxDecoration(
          border: Border.all(color: Colors.grey.shade200),
          borderRadius: BorderRadius.circular(12),
        ),
        child: Row(
          children: [
            Icon(icon, color: color, size: 20),
            const SizedBox(width: 10),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(title, style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 12)),
                  const SizedBox(height: 1),
                  Text(subtitle, style: const TextStyle(fontSize: 9, color: Colors.black38)),
                ],
              ),
            ),
            const Icon(Icons.chevron_right, color: Colors.grey, size: 16),
          ],
        ),
      ),
    );
  }

  Future<void> _cancelOrder(String orderId, int pauseMinutes) async {
    setState(() => _isLoading = true);
    try {
      final vendorRepo = ref.read(vendorRepositoryProvider);
      await vendorRepo.reportKitchenFull(
        orderId,
        0,
        action: 2,
        pauseDuration: pauseMinutes > 0 ? pauseMinutes : null,
      );
      await _loadDashboardData();
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text(pauseMinutes > 0
                ? 'Order cancelled and shop paused for $pauseMinutes mins.'
                : 'Order cancelled successfully.'),
            backgroundColor: WeloColors.error,
          ),
        );
      }
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('Failed to cancel order.')),
        );
      }
    } finally {
      if (mounted) setState(() => _isLoading = false);
    }
  }

  Future<void> _handleAddDelay(String orderId) async {
    setState(() => _isLoading = true);
    try {
      final vendorRepo = ref.read(vendorRepositoryProvider);
      await vendorRepo.reportKitchenFull(orderId, 10, action: 0);
      await _loadDashboardData();
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(
            content: Text('Added 10 minutes prep delay to this order.'),
            backgroundColor: WeloColors.sovereignOrange,
          ),
        );
      }
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('Failed to add delay to order.')),
        );
      }
    } finally {
      if (mounted) setState(() => _isLoading = false);
    }
  }

  Future<void> _handleAcceptOrder(String orderId) async {
    try {
      final vendorRepo = ref.read(vendorRepositoryProvider);
      await vendorRepo.acceptOrder(orderId);
      _loadDashboardData();
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('Failed to accept order.')),
        );
      }
    }
  }

  Future<void> _handleUpdateStatus(String orderId, String status) async {
    try {
      final vendorRepo = ref.read(vendorRepositoryProvider);
      await vendorRepo.updateOrderStatus(orderId, status);
      _loadDashboardData();
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('Failed to update status.')),
        );
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: WeloAppBar(
        title: 'Dashboard',
        actions: [
          Row(
            children: [
              Text(
                _isOnline ? 'Online' : 'Offline',
                style: TextStyle(
                  fontWeight: FontWeight.bold,
                  fontSize: 13,
                  color: _isOnline ? WeloColors.success : WeloColors.textLight,
                ),
              ),
              const SizedBox(width: 4),
              Switch(
                value: _isOnline,
                activeThumbColor: WeloColors.success,
                onChanged: _handleToggleOnline,
              ),
            ],
          ),
          const SizedBox(width: 8),
        ],
      ),
      body: RefreshIndicator(
        onRefresh: _loadDashboardData,
        color: WeloColors.sovereignOrange,
        child: _isLoading
            ? const Center(child: CircularProgressIndicator(color: WeloColors.sovereignOrange))
            : SingleChildScrollView(
                physics: const AlwaysScrollableScrollPhysics(),
                padding: const EdgeInsets.all(16),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    if (_pausedUntil != null && _pausedUntil!.isAfter(DateTime.now()))
                      _buildPauseBanner(),
                    // Auto-Accept Toggle
                    WeloCard(
                      padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
                      child: Row(
                        mainAxisAlignment: MainAxisAlignment.spaceBetween,
                        children: [
                          Row(
                            children: [
                              Icon(
                                Icons.auto_awesome,
                                size: 20,
                                color: _autoAccept ? WeloColors.success : WeloColors.textLight,
                              ),
                              const SizedBox(width: 10),
                              Column(
                                crossAxisAlignment: CrossAxisAlignment.start,
                                children: [
                                  Text(
                                    'Auto-Accept Orders',
                                    style: TextStyle(
                                      fontWeight: FontWeight.bold,
                                      fontSize: 14,
                                      color: _autoAccept ? WeloColors.success : WeloColors.textBody,
                                    ),
                                  ),
                                  Text(
                                    _autoAccept ? 'New orders accepted automatically' : 'Manual accept/reject',
                                    style: TextStyle(fontSize: 11, color: WeloColors.textBody),
                                  ),
                                ],
                              ),
                            ],
                          ),
                          Switch(
                            value: _autoAccept,
                            activeThumbColor: WeloColors.success,
                            onChanged: _toggleAutoAccept,
                          ),
                        ],
                      ),
                    ),
                    const SizedBox(height: 16),

                    // Kitchen Busy? Pause Orders Card
                    WeloCard(
                      padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
                      child: Row(
                        mainAxisAlignment: MainAxisAlignment.spaceBetween,
                        children: [
                          Row(
                            children: [
                              Icon(
                                Icons.timer_outlined,
                                size: 20,
                                color: _pausedUntil != null && _pausedUntil!.isAfter(DateTime.now())
                                    ? WeloColors.sovereignOrange
                                    : WeloColors.textLight,
                              ),
                              const SizedBox(width: 10),
                              Column(
                                crossAxisAlignment: CrossAxisAlignment.start,
                                children: [
                                  Text(
                                    'Kitchen Busy? Pause Orders',
                                    style: TextStyle(
                                      fontWeight: FontWeight.bold,
                                      fontSize: 14,
                                      color: _pausedUntil != null && _pausedUntil!.isAfter(DateTime.now())
                                          ? WeloColors.sovereignOrange
                                          : WeloColors.textBody,
                                    ),
                                  ),
                                  Text(
                                    _pausedUntil != null && _pausedUntil!.isAfter(DateTime.now())
                                        ? 'Orders are currently paused'
                                        : 'Pause incoming orders temporarily',
                                    style: TextStyle(fontSize: 11, color: WeloColors.textBody),
                                  ),
                                ],
                              ),
                            ],
                          ),
                          ElevatedButton(
                            onPressed: _pausedUntil != null && _pausedUntil!.isAfter(DateTime.now())
                                ? _handleResumeOrders
                                : _showBusyModeDialog,
                            style: ElevatedButton.styleFrom(
                              backgroundColor: _pausedUntil != null && _pausedUntil!.isAfter(DateTime.now())
                                  ? WeloColors.success
                                  : WeloColors.sovereignOrange,
                              foregroundColor: Colors.white,
                              elevation: 0,
                              padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
                              shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                            ),
                            child: Text(
                              _pausedUntil != null && _pausedUntil!.isAfter(DateTime.now())
                                  ? 'Resume'
                                  : 'Pause',
                              style: const TextStyle(fontSize: 12, fontWeight: FontWeight.bold),
                            ),
                          ),
                        ],
                      ),
                    ),
                    const SizedBox(height: 16),

                    // Today so far summary
                    _buildTodaySummary(),
                    const SizedBox(height: 16),

                    // Quick Links
                    _buildQuickLinks(),
                    const SizedBox(height: 20),

                    // Stats cards
                    SizedBox(
                      height: 100,
                      child: ListView(
                        scrollDirection: Axis.horizontal,
                        children: [
                          _buildStatCard('Revenue', '₹${_revenue.toInt()}', Icons.attach_money, WeloColors.success),
                          _buildStatCard('Active', '${_activeOrders.length}', Icons.shopping_bag, WeloColors.sovereignOrange),
                          _buildStatCard('Completed', '$_completedCount', Icons.check_circle, WeloColors.sovereignTeal),
                          _buildComplaintsStatCard(),
                          _buildReviewsStatCard(),
                        ],
                      ),
                    ),
                    const SizedBox(height: 24),

                    Row(
                      mainAxisAlignment: MainAxisAlignment.spaceBetween,
                      children: [
                        const Text(
                          'Active Orders',
                          style: TextStyle(fontSize: 18, fontWeight: FontWeight.bold),
                        ),
                      ],
                    ),
                    const SizedBox(height: 12),
                    SingleChildScrollView(
                      scrollDirection: Axis.horizontal,
                      child: Row(
                        children: [
                          _buildTabItem('New (${_activeOrders.where((o) => o.status == 'pending').length})', 'pending'),
                          _buildTabItem('Preparing (${_activeOrders.where((o) => o.status == 'preparing' || o.status == 'confirmed').length})', 'preparing'),
                          _buildTabItem('Ready (${_activeOrders.where((o) => o.status == 'ready_for_pickup').length})', 'ready_for_pickup'),
                          _buildTabItem('Out for Delivery (${_activeOrders.where((o) => o.status == 'out_for_delivery').length})', 'out_for_delivery'),
                        ],
                      ),
                    ),
                    const SizedBox(height: 16),
                    ..._buildFilteredOrders(),
                  ],
                ),
              ),
      ),
    );
  }

  Widget _buildTabItem(String label, String value) {
    final isSelected = _selectedOrderTab == value;
    return GestureDetector(
      onTap: () => setState(() => _selectedOrderTab = value),
      child: Container(
        margin: const EdgeInsets.only(right: 8),
        padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
        decoration: BoxDecoration(
          color: isSelected ? WeloColors.sovereignOrange : Colors.grey.shade100,
          borderRadius: BorderRadius.circular(20),
          border: Border.all(color: isSelected ? WeloColors.sovereignOrange : Colors.grey.shade300),
        ),
        child: Text(
          label,
          style: TextStyle(
            fontWeight: FontWeight.bold,
            color: isSelected ? Colors.white : Colors.grey.shade700,
          ),
        ),
      ),
    );
  }

  List<Widget> _buildFilteredOrders() {
    final filtered = _activeOrders.where((o) {
      if (_selectedOrderTab == 'preparing') {
        return o.status == 'preparing' || o.status == 'confirmed';
      }
      return o.status == _selectedOrderTab;
    }).toList();

    if (filtered.isEmpty) {
      return [
        const Padding(
          padding: EdgeInsets.symmetric(vertical: 40.0),
          child: Center(
            child: Text(
              'No orders in this status right now.',
              style: TextStyle(color: Colors.black38, fontWeight: FontWeight.bold),
            ),
          ),
        )
      ];
    }

    return filtered.map((order) => Padding(
      padding: const EdgeInsets.only(bottom: 10.0),
      child: _buildOrderCard(order),
    )).toList();
  }

  Widget _buildTodaySummary() {
    return WeloCard(
      padding: const EdgeInsets.all(16),
      child: Row(
        children: [
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Row(
                  children: [
                    const Text('Today so far', style: TextStyle(color: WeloColors.textBody, fontSize: 13)),
                    const SizedBox(width: 8),
                    Container(
                      width: 8, height: 8,
                      decoration: BoxDecoration(
                        color: _isOnline ? WeloColors.success : WeloColors.textLight,
                        shape: BoxShape.circle,
                      ),
                    ),
                  ],
                ),
                const SizedBox(height: 6),
                Text('₹${_revenue.toInt()}',
                  style: const TextStyle(fontSize: 28, fontWeight: FontWeight.bold, color: WeloColors.sovereignOrange)),
                const SizedBox(height: 4),
                Row(
                  children: [
                    Text('$_completedCount orders delivered',
                      style: const TextStyle(color: WeloColors.textBody, fontSize: 12)),
                    const Spacer(),
                    Text('Active: ${_activeOrders.length}',
                      style: TextStyle(
                        fontSize: 12, fontWeight: FontWeight.bold,
                        color: _activeOrders.isNotEmpty ? WeloColors.sovereignOrange : WeloColors.textBody,
                      )),
                  ],
                ),
              ],
            ),
          ),
          Container(
            padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
            decoration: BoxDecoration(
              color: _isOnline ? WeloColors.success.withValues(alpha: 0.059) : WeloColors.textLight.withValues(alpha: 0.059),
              borderRadius: BorderRadius.circular(20),
            ),
            child: Row(
              mainAxisSize: MainAxisSize.min,
              children: [
                Container(
                  width: 8, height: 8,
                  decoration: BoxDecoration(
                    color: _isOnline ? WeloColors.success : WeloColors.textLight,
                    shape: BoxShape.circle,
                  ),
                ),
                const SizedBox(width: 4),
                Text(_isOnline ? 'Online' : 'Offline',
                  style: TextStyle(
                    fontSize: 12, fontWeight: FontWeight.bold,
                    color: _isOnline ? WeloColors.success : WeloColors.textLight,
                  )),
              ],
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildQuickLinks() {
    final links = [
      {'icon': Icons.receipt_long, 'label': 'Order History', 'color': WeloColors.sovereignOrange},
      {'icon': Icons.report_problem_outlined, 'label': 'Complaints ($_openComplaints)', 'color': WeloColors.error},
      {'icon': Icons.rate_review_outlined, 'label': 'Reviews ($_totalReviews)', 'color': WeloColors.sovereignGold},
      {'icon': Icons.analytics_rounded, 'label': 'Analytics', 'color': WeloColors.sovereignTeal},
      {'icon': Icons.menu_book_rounded, 'label': 'Menu', 'color': Colors.purple},
      {'icon': Icons.local_offer_rounded, 'label': 'Offers', 'color': WeloColors.sovereignOrange},
      {'icon': Icons.campaign_rounded, 'label': 'Ads', 'color': Colors.deepOrange},
      {'icon': Icons.inventory_2_rounded, 'label': 'Inventory', 'color': Colors.blue},
      {'icon': Icons.settings_rounded, 'label': 'Settings', 'color': WeloColors.textBody},
    ];

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Row(
          mainAxisAlignment: MainAxisAlignment.spaceBetween,
          children: [
            const Text('Quick Links', style: TextStyle(fontSize: 15, fontWeight: FontWeight.bold)),
            const SizedBox(),
          ],
        ),
        const SizedBox(height: 12),
        GridView.builder(
          shrinkWrap: true,
          physics: const NeverScrollableScrollPhysics(),
          gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(
            crossAxisCount: 4,
            crossAxisSpacing: 10,
            mainAxisSpacing: 10,
            childAspectRatio: 0.85,
          ),
          itemCount: links.length,
          itemBuilder: (context, index) {
            final link = links[index];
            return GestureDetector(
              onTap: () => _navigateToLink(link['label'] as String),
              child: Container(
                padding: const EdgeInsets.all(8),
                decoration: BoxDecoration(
                  color: (link['color'] as Color).withValues(alpha: 0.047),
                  borderRadius: BorderRadius.circular(14),
                ),
                child: Column(
                  mainAxisAlignment: MainAxisAlignment.center,
                  children: [
                    Icon(link['icon'] as IconData, size: 24, color: link['color'] as Color),
                    const SizedBox(height: 4),
                    Text(link['label'] as String,
                      textAlign: TextAlign.center,
                      maxLines: 2,
                      overflow: TextOverflow.ellipsis,
                      style: TextStyle(fontSize: 10, fontWeight: FontWeight.w600, color: link['color'] as Color)),
                  ],
                ),
              ),
            );
          },
        ),
      ],
    );
  }

  void _navigateToLink(String label) {
    switch (label) {
      case 'Order History':
        ref.read(vendorTabProvider.notifier).state = 1;
        break;
      case 'Complaints':
        Navigator.push(context, MaterialPageRoute(builder: (_) => const ComplaintsScreen()));
        break;
      case 'Reviews':
        Navigator.push(context, MaterialPageRoute(builder: (_) => ReviewsScreen(restaurantId: '')));
        break;
      case 'Ads':
        ref.read(vendorTabProvider.notifier).state = 5;
        break;
      case 'Analytics':
        ref.read(vendorTabProvider.notifier).state = 6;
        break;
      case 'Menu':
        ref.read(vendorTabProvider.notifier).state = 2;
        break;
      case 'Offers':
        ref.read(vendorTabProvider.notifier).state = 4;
        break;
      case 'Inventory':
        ref.read(vendorTabProvider.notifier).state = 3;
        break;
      case 'Settings':
        ref.read(vendorTabProvider.notifier).state = 10;
        break;
    }
  }

  Widget _buildStatCard(String label, String value, IconData icon, Color color) {
    return Container(
      width: 140,
      margin: const EdgeInsets.only(right: 12),
      child: WeloCard(
        padding: const EdgeInsets.all(14),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            Row(
              mainAxisAlignment: MainAxisAlignment.spaceBetween,
              children: [
                Text(label, style: const TextStyle(color: WeloColors.textBody, fontSize: 12)),
                Icon(icon, size: 16, color: color),
              ],
            ),
            const SizedBox(height: 8),
            Text(value, style: const TextStyle(fontSize: 22, fontWeight: FontWeight.bold)),
          ],
        ),
      ),
    );
  }

  Widget _buildComplaintsStatCard() {
    return GestureDetector(
      onTap: () => Navigator.push(context, MaterialPageRoute(builder: (_) => const ComplaintsScreen())),
      child: Container(
        width: 140,
        margin: const EdgeInsets.only(right: 12),
        child: WeloCard(
          padding: const EdgeInsets.all(14),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            mainAxisAlignment: MainAxisAlignment.center,
            children: [
              Row(
                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                children: [
                  const Text('Complaints', style: TextStyle(color: WeloColors.textBody, fontSize: 12)),
                  Stack(
                    clipBehavior: Clip.none,
                    children: [
                      const Icon(Icons.report_problem_outlined, size: 16, color: WeloColors.error),
                      if (_openComplaints > 0)
                        Positioned(
                          top: -6,
                          right: -6,
                          child: Container(
                            padding: const EdgeInsets.all(2),
                            decoration: const BoxDecoration(color: WeloColors.error, shape: BoxShape.circle),
                            constraints: const BoxConstraints(minWidth: 14, minHeight: 14),
                            child: Text('$_openComplaints', style: const TextStyle(color: Colors.white, fontSize: 8, fontWeight: FontWeight.bold), textAlign: TextAlign.center),
                          ),
                        ),
                    ],
                  ),
                ],
              ),
              const SizedBox(height: 8),
              Text('$_openComplaints open', style: TextStyle(fontSize: 22, fontWeight: FontWeight.bold, color: _openComplaints > 0 ? WeloColors.error : Colors.black)),
            ],
          ),
        ),
      ),
    );
  }

  Widget _buildReviewsStatCard() {
    return GestureDetector(
      onTap: () => Navigator.push(context, MaterialPageRoute(builder: (_) => ReviewsScreen(restaurantId: ''))),
      child: Container(
        width: 140,
        margin: const EdgeInsets.only(right: 12),
        child: WeloCard(
          padding: const EdgeInsets.all(14),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            mainAxisAlignment: MainAxisAlignment.center,
            children: [
              Row(
                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                children: [
                  const Text('Reviews', style: TextStyle(color: WeloColors.textBody, fontSize: 12)),
                  const Icon(Icons.rate_review_outlined, size: 16, color: WeloColors.sovereignGold),
                ],
              ),
              const SizedBox(height: 8),
              Row(
                children: [
                  Icon(Icons.star_rounded, size: 16, color: WeloColors.sovereignGold),
                  const SizedBox(width: 4),
                  Text('${_avgRating.toStringAsFixed(1)}', style: const TextStyle(fontSize: 22, fontWeight: FontWeight.bold)),
                  const SizedBox(width: 4),
                  Text('($_totalReviews)', style: TextStyle(fontSize: 12, color: Colors.grey[500])),
                ],
              ),
            ],
          ),
        ),
      ),
    );
  }

  String _getPrepTimerText(Order order) {
    if (order.prepDeadlineAt == null) return '';
    try {
      final deadline = DateTime.parse(order.prepDeadlineAt!).toLocal();
      final diff = deadline.difference(DateTime.now());
      if (diff.isNegative) {
        return 'Prep Overdue by ${diff.inMinutes.abs()}m';
      } else {
        return 'Prep Time: ${diff.inMinutes}m left';
      }
    } catch (_) {
      return '';
    }
  }

  Widget _buildOrderCard(Order order) {
    final prepTimerText = _getPrepTimerText(order);

    return WeloCard(
      padding: const EdgeInsets.all(14),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              Text('#${order.id.substring(0, 8).toUpperCase()}',
                  style: const TextStyle(fontFamily: 'SpaceGrotesk', fontWeight: FontWeight.bold, fontSize: 15)),
              WeloOrderStatusBadge(status: order.status),
            ],
          ),
          const SizedBox(height: 4),
          Text(order.customerAddress, style: const TextStyle(color: WeloColors.textBody, fontSize: 12), maxLines: 1, overflow: TextOverflow.ellipsis),
          const SizedBox(height: 8),
          Text(order.itemSummary.isNotEmpty ? order.itemSummary : '${order.itemsCount} items', style: const TextStyle(fontSize: 13, color: Colors.black54)),
          const SizedBox(height: 4),
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              Text('₹${order.totalAmount.toInt()}', style: const TextStyle(fontWeight: FontWeight.bold, color: WeloColors.sovereignOrange)),
              if (order.status == 'preparing' && prepTimerText.isNotEmpty)
                Container(
                  padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 2),
                  decoration: BoxDecoration(
                    color: WeloColors.sovereignOrange.withValues(alpha: 0.078),
                    borderRadius: BorderRadius.circular(4),
                  ),
                  child: Text(
                    prepTimerText,
                    style: TextStyle(
                      fontSize: 11,
                      fontWeight: FontWeight.bold,
                      color: prepTimerText.contains('Overdue') ? WeloColors.error : WeloColors.sovereignOrange,
                    ),
                  ),
                ),
            ],
          ),
          const SizedBox(height: 8),

          Container(
            padding: const EdgeInsets.all(10),
            decoration: BoxDecoration(
              color: Colors.grey.shade50,
              borderRadius: BorderRadius.circular(8),
              border: Border.all(color: Colors.grey.shade200),
            ),
            child: Row(
              children: [
                Icon(
                  Icons.delivery_dining,
                  size: 18,
                  color: order.riderAssigned ? WeloColors.success : WeloColors.textLight,
                ),
                const SizedBox(width: 8),
                Expanded(
                  child: Text(
                    !order.riderAssigned
                        ? 'Rider: Assigning Rider...'
                        : order.riderArrived
                            ? 'Rider: ${order.riderName ?? "Rider"} (Arrived / Waiting)'
                            : 'Rider: ${order.riderName ?? "Rider"} (${order.riderDistanceToRestaurant?.toStringAsFixed(1) ?? "0.0"} km away)',
                    style: TextStyle(
                      fontSize: 12,
                      fontWeight: FontWeight.bold,
                      color: order.riderAssigned ? Colors.black87 : WeloColors.textLight,
                    ),
                  ),
                ),
              ],
            ),
          ),
          
          if (order.prepDelayMinutes != null || order.handoverDelayMinutes != null) ...[
            const SizedBox(height: 8),
            Row(
              children: [
                if (order.prepDelayMinutes != null)
                  Padding(
                    padding: const EdgeInsets.only(right: 6.0),
                    child: Container(
                      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
                      decoration: BoxDecoration(
                        color: order.prepDelayMinutes! > 0
                            ? WeloColors.error.withValues(alpha: 0.059)
                            : WeloColors.success.withValues(alpha: 0.059),
                        borderRadius: BorderRadius.circular(6),
                      ),
                      child: Text(
                        order.prepDelayMinutes! > 0
                            ? 'Prep Delay: ${order.prepDelayMinutes}m'
                            : 'Prep Early: ${order.prepDelayMinutes!.abs()}m',
                        style: TextStyle(
                          fontSize: 10,
                          fontWeight: FontWeight.bold,
                          color: order.prepDelayMinutes! > 0 ? WeloColors.error : WeloColors.success,
                        ),
                      ),
                    ),
                  ),
                if (order.handoverDelayMinutes != null && order.handoverDelayMinutes! > 0)
                  Container(
                    padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
                    decoration: BoxDecoration(
                      color: WeloColors.error.withValues(alpha: 0.059),
                      borderRadius: BorderRadius.circular(6),
                    ),
                    child: Text(
                      'Handover Delay: ${order.handoverDelayMinutes}m',
                      style: const TextStyle(
                        fontSize: 10,
                        fontWeight: FontWeight.bold,
                        color: WeloColors.error,
                      ),
                    ),
                  ),
              ],
            ),
          ],

          const SizedBox(height: 12),

          if (order.status == 'pending')
            Row(
              children: [
                Expanded(
                  child: OutlinedButton(
                    onPressed: () => _showRejectOrderDialog(order),
                    style: OutlinedButton.styleFrom(
                      foregroundColor: WeloColors.error,
                      side: const BorderSide(color: WeloColors.error),
                      padding: const EdgeInsets.symmetric(vertical: 10),
                      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
                    ),
                    child: const Text('Reject', style: TextStyle(fontSize: 13)),
                  ),
                ),
                const SizedBox(width: 10),
                Expanded(
                  child: ElevatedButton(
                    onPressed: () => _handleAcceptOrder(order.id),
                    style: ElevatedButton.styleFrom(
                      backgroundColor: WeloColors.success,
                      foregroundColor: Colors.white,
                      padding: const EdgeInsets.symmetric(vertical: 10),
                      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
                    ),
                    child: const Text('Accept', style: TextStyle(fontSize: 13)),
                  ),
                ),
              ],
            )
          else if (order.status == 'accepted')
            SizedBox(
              width: double.infinity,
              child: ElevatedButton(
                onPressed: () => _handleUpdateStatus(order.id, 'preparing'),
                style: ElevatedButton.styleFrom(
                  backgroundColor: WeloColors.sovereignOrange,
                  foregroundColor: Colors.white,
                  padding: const EdgeInsets.symmetric(vertical: 10),
                  shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
                ),
                child: const Text('Start Preparing', style: TextStyle(fontSize: 13)),
              ),
            )
          else if (order.status == 'preparing')
            Row(
              children: [
                Expanded(
                  child: OutlinedButton.icon(
                    onPressed: () => _handleAddDelay(order.id),
                    icon: const Icon(Icons.add_alarm_rounded, size: 16),
                    label: const Text('Add 10m Delay', style: TextStyle(fontSize: 12)),
                    style: OutlinedButton.styleFrom(
                      foregroundColor: WeloColors.sovereignOrange,
                      side: const BorderSide(color: WeloColors.sovereignOrange),
                      padding: const EdgeInsets.symmetric(vertical: 10),
                      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
                    ),
                  ),
                ),
                const SizedBox(width: 10),
                Expanded(
                  child: ElevatedButton(
                    onPressed: () => _handleUpdateStatus(order.id, 'ready_for_pickup'),
                    style: ElevatedButton.styleFrom(
                      backgroundColor: WeloColors.sovereignTeal,
                      foregroundColor: Colors.white,
                      padding: const EdgeInsets.symmetric(vertical: 10),
                      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
                    ),
                    child: const Text('Mark Ready', style: TextStyle(fontSize: 13)),
                  ),
                ),
              ],
            ),
        ],
      ),
    );
  }
}

class _IncomingOrderAlert extends StatefulWidget {
  final Order order;
  final Function(int) onAccept;
  final VoidCallback onReject;

  const _IncomingOrderAlert({
    required this.order,
    required this.onAccept,
    required this.onReject,
  });

  @override
  State<_IncomingOrderAlert> createState() => _IncomingOrderAlertState();
}

class _IncomingOrderAlertState extends State<_IncomingOrderAlert> {
  int _remainingSeconds = 300;
  Timer? _timer;
  int _extraPrepMinutes = 0;

  @override
  void initState() {
    super.initState();
    _calculateRemainingTime();
    _timer = Timer.periodic(const Duration(seconds: 1), (timer) {
      if (_remainingSeconds > 0) {
        setState(() {
          _remainingSeconds--;
        });
      } else {
        _timer?.cancel();
        widget.onReject();
      }
    });
  }

  void _calculateRemainingTime() {
    try {
      if (widget.order.createdAt != null) {
        final createdTime = DateTime.parse(widget.order.createdAt!).toLocal();
        final elapsed = DateTime.now().difference(createdTime).inSeconds;
        setState(() {
          _remainingSeconds = (300 - elapsed).clamp(0, 300);
        });
      }
    } catch (_) {}
  }

  @override
  void dispose() {
    _timer?.cancel();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final minutes = _remainingSeconds ~/ 60;
    final seconds = _remainingSeconds % 60;
    final timeStr = '$minutes:${seconds.toString().padLeft(2, '0')}';

    return Dialog(
      insetPadding: const EdgeInsets.all(16),
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(24)),
      backgroundColor: Colors.white,
      child: Padding(
        padding: const EdgeInsets.symmetric(horizontal: 24.0, vertical: 32.0),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Container(
              padding: const EdgeInsets.all(20),
              decoration: BoxDecoration(
                color: WeloColors.sovereignOrange.withValues(alpha: 0.078),
                shape: BoxShape.circle,
              ),
              child: const Icon(
                Icons.notifications_active,
                color: WeloColors.sovereignOrange,
                size: 50,
              ),
            ),
            const SizedBox(height: 20),
            const Text(
              'NEW INCOMING ORDER!',
              style: TextStyle(
                fontWeight: FontWeight.bold,
                fontSize: 22,
                color: Colors.black,
              ),
            ),
            const SizedBox(height: 8),
            Text(
              'Order #${widget.order.id.substring(0, 8).toUpperCase()}',
              style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 15, color: Colors.black54),
            ),
            const SizedBox(height: 24),

            Container(
              padding: const EdgeInsets.symmetric(horizontal: 24, vertical: 12),
              decoration: BoxDecoration(
                color: WeloColors.error.withValues(alpha: 0.059),
                borderRadius: BorderRadius.circular(16),
                border: Border.all(color: WeloColors.error.withValues(alpha: 0.196), width: 1.5),
              ),
              child: Row(
                mainAxisSize: MainAxisSize.min,
                children: [
                  const Icon(Icons.timer, color: WeloColors.error, size: 20),
                  const SizedBox(width: 8),
                  Text(
                    'Auto-cancels in $timeStr',
                    style: const TextStyle(
                      fontWeight: FontWeight.w800,
                      fontSize: 16,
                      color: WeloColors.error,
                    ),
                  ),
                ],
              ),
            ),
            const SizedBox(height: 24),

            const Text(
              'Need extra prep time?',
              style: TextStyle(fontWeight: FontWeight.w700, fontSize: 14, color: Colors.black87),
            ),
            const SizedBox(height: 10),
            Row(
              mainAxisAlignment: MainAxisAlignment.center,
              children: [
                _buildStepperBtn(Icons.remove, () {
                  if (_extraPrepMinutes > 0) {
                    setState(() => _extraPrepMinutes--);
                  }
                }),
                Container(
                  width: 120,
                  alignment: Alignment.center,
                  child: Text(
                    '+$_extraPrepMinutes min',
                    style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 18),
                  ),
                ),
                _buildStepperBtn(Icons.add, () {
                  if (_extraPrepMinutes < 5) {
                    setState(() => _extraPrepMinutes++);
                  }
                }),
              ],
            ),
            const SizedBox(height: 32),

            Row(
              children: [
                Expanded(
                  child: OutlinedButton(
                    onPressed: widget.onReject,
                    style: OutlinedButton.styleFrom(
                      foregroundColor: WeloColors.error,
                      side: const BorderSide(color: WeloColors.error, width: 1.5),
                      padding: const EdgeInsets.symmetric(vertical: 14),
                      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
                    ),
                    child: const Text('Reject', style: TextStyle(fontSize: 15, fontWeight: FontWeight.bold)),
                  ),
                ),
                const SizedBox(width: 12),
                Expanded(
                  child: ElevatedButton(
                    onPressed: () => widget.onAccept(_extraPrepMinutes),
                    style: ElevatedButton.styleFrom(
                      backgroundColor: WeloColors.success,
                      foregroundColor: Colors.white,
                      elevation: 2,
                      padding: const EdgeInsets.symmetric(vertical: 14),
                      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
                    ),
                    child: const Text('Accept', style: TextStyle(fontSize: 15, fontWeight: FontWeight.bold)),
                  ),
                ),
              ],
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildStepperBtn(IconData icon, VoidCallback onTap) {
    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(12),
      child: Container(
        padding: const EdgeInsets.all(8),
        decoration: BoxDecoration(
          color: Colors.grey.shade100,
          border: Border.all(color: Colors.grey.shade300),
          borderRadius: BorderRadius.circular(12),
        ),
        child: Icon(icon, size: 20, color: Colors.black87),
      ),
    );
  }
}
