import 'dart:async';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:ui/ui.dart';
import 'package:api/api.dart';
import 'package:core/core.dart';
import '../main.dart';

class IncomingDeliveryScreen extends ConsumerStatefulWidget {
  final String orderId;

  const IncomingDeliveryScreen({super.key, required this.orderId});

  @override
  ConsumerState<IncomingDeliveryScreen> createState() => _IncomingDeliveryScreenState();
}

class _IncomingDeliveryScreenState extends ConsumerState<IncomingDeliveryScreen> {
  static const int _totalSeconds = 60;

  bool _isLoading = true;
  Order? _order;
  String? _error;
  int _remainingSeconds = _totalSeconds;
  Timer? _timer;

  @override
  void initState() {
    super.initState();
    _fetchAssignment();
  }

  @override
  void dispose() {
    _timer?.cancel();
    super.dispose();
  }

  Future<void> _fetchAssignment() async {
    try {
      await ref.read(riderStateProvider.notifier).refreshState();
      final riderState = ref.read(riderStateProvider);
      final order = riderState.currentOrder;

      if (order != null && mounted) {
        setState(() {
          _order = order;
          _isLoading = false;
        });
        if (riderState.status == RiderStatus.receivedRequest) {
          _startCountdown();
        } else {
          // Already accepted/handled — go home
          _navigateToHome();
        }
      } else {
        // No pending assignment — order may have been reassigned/timed out
        if (mounted) {
          setState(() {
            _error = 'No active delivery request';
            _isLoading = false;
          });
        }
      }
    } catch (e) {
      if (mounted) {
        setState(() {
          _error = 'Failed to load delivery request';
          _isLoading = false;
        });
      }
    }
  }

  void _startCountdown() {
    try {
      final createdStr = _order?.createdAt;
      if (createdStr != null) {
        final createdTime = DateTime.parse(createdStr).toLocal();
        final elapsed = DateTime.now().difference(createdTime).inSeconds;
        _remainingSeconds = (_totalSeconds - elapsed).clamp(0, _totalSeconds);
      }
    } catch (_) {}

    if (_remainingSeconds <= 0) {
      _handleReject();
      return;
    }

    _timer = Timer.periodic(const Duration(seconds: 1), (timer) {
      if (_remainingSeconds > 0) {
        if (mounted) setState(() => _remainingSeconds--);
      } else {
        _timer?.cancel();
        _handleReject();
      }
    });
  }

  void _navigateToHome() {
    Navigator.of(context, rootNavigator: true).pushAndRemoveUntil(
      MaterialPageRoute(builder: (_) => const RiderShell(initialIndex: 0)),
      (route) => false,
    );
  }

  Future<void> _handleAccept() async {
    _timer?.cancel();
    try {
      await ref.read(riderStateProvider.notifier).acceptOrder();
      _navigateToHome();
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('Failed to accept delivery.')),
        );
      }
    }
  }

  Future<void> _handleReject() async {
    _timer?.cancel();
    try {
      await ref.read(riderStateProvider.notifier).rejectOrder();
    } catch (_) {}
    _navigateToHome();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: Colors.white,
      body: SafeArea(
        child: _isLoading
            ? const Center(child: CircularProgressIndicator(color: WeloColors.sovereignOrange))
            : _error != null
                ? _buildErrorView()
                : _buildIncomingView(),
      ),
    );
  }

  Widget _buildErrorView() {
    return Center(
      child: Padding(
        padding: const EdgeInsets.all(32),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            const Icon(Icons.error_outline, size: 64, color: WeloColors.error),
            const SizedBox(height: 16),
            Text(_error!, style: const TextStyle(fontSize: 16, color: WeloColors.textBody)),
            const SizedBox(height: 24),
            ElevatedButton(
              onPressed: _navigateToHome,
              style: ElevatedButton.styleFrom(
                backgroundColor: WeloColors.sovereignOrange,
                foregroundColor: Colors.white,
              ),
              child: const Text('Go Home'),
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildIncomingView() {
    final order = _order!;
    final minutes = _remainingSeconds ~/ 60;
    final seconds = _remainingSeconds % 60;
    final timeStr = '$minutes:${seconds.toString().padLeft(2, '0')}';

    return SingleChildScrollView(
      padding: const EdgeInsets.symmetric(horizontal: 24, vertical: 32),
      child: Column(
        children: [
          const SizedBox(height: 24),
          Container(
            padding: const EdgeInsets.all(20),
            decoration: BoxDecoration(
              color: WeloColors.sovereignOrange.withValues(alpha: 0.078),
              shape: BoxShape.circle,
            ),
            child: const Icon(
              Icons.delivery_dining,
              color: WeloColors.sovereignOrange,
              size: 60,
            ),
          ),
          const SizedBox(height: 20),
          const Text(
            'NEW DELIVERY REQUEST!',
            style: TextStyle(fontWeight: FontWeight.bold, fontSize: 24, color: Colors.black),
          ),
          const SizedBox(height: 8),
          Text(
            'Order #${order.id.substring(0, 8).toUpperCase()}',
            style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 16, color: Colors.black54),
          ),
          const SizedBox(height: 20),
          // Timer
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
                  'Auto-rejects in $timeStr',
                  style: const TextStyle(fontWeight: FontWeight.w800, fontSize: 16, color: WeloColors.error),
                ),
              ],
            ),
          ),
          const SizedBox(height: 24),
          // Distance + ETA info card
          _buildDistanceCard(order),
          const SizedBox(height: 16),
          // Order items
          if (order.rawItems.isNotEmpty) ...[
            _buildOrderItems(order),
            const SizedBox(height: 16),
          ],
          // Pickup address
          _buildInfoCard(
            icon: Icons.store,
            title: 'Pickup',
            address: order.restaurantAddress ?? order.restaurantName,
          ),
          const SizedBox(height: 12),
          // Drop address
          _buildInfoCard(
            icon: Icons.location_on,
            title: 'Drop',
            address: order.customerAddress,
          ),
          const SizedBox(height: 16),
          // Payout
          Container(
            width: double.infinity,
            padding: const EdgeInsets.all(14),
            decoration: BoxDecoration(
              color: WeloColors.success.withValues(alpha: 0.059),
              borderRadius: BorderRadius.circular(16),
            ),
            child: Row(
              mainAxisAlignment: MainAxisAlignment.center,
              children: [
                const Icon(Icons.account_balance_wallet, size: 20, color: WeloColors.success),
                const SizedBox(width: 8),
                Text(
                  'Your Payout: ₹${ref.watch(riderStateProvider).payout.toStringAsFixed(0)}',
                  style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 18, color: WeloColors.success),
                ),
              ],
            ),
          ),
          const SizedBox(height: 28),
          // Accept / Reject buttons
          Row(
            children: [
              Expanded(
                child: OutlinedButton(
                  onPressed: _handleReject,
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
                flex: 2,
                child: ElevatedButton(
                  onPressed: ref.watch(riderStateProvider).isLoading ? null : _handleAccept,
                  style: ElevatedButton.styleFrom(
                    backgroundColor: WeloColors.success,
                    foregroundColor: Colors.white,
                    elevation: 2,
                    padding: const EdgeInsets.symmetric(vertical: 14),
                    shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
                  ),
                  child: ref.watch(riderStateProvider).isLoading
                      ? const SizedBox(
                          width: 20,
                          height: 20,
                          child: CircularProgressIndicator(
                            strokeWidth: 2,
                            valueColor: AlwaysStoppedAnimation(Colors.white),
                          ),
                        )
                      : const Text('Accept Delivery', style: TextStyle(fontSize: 15, fontWeight: FontWeight.bold)),
                ),
              ),
            ],
          ),
          const SizedBox(height: 40),
        ],
      ),
    );
  }

  Widget _buildDistanceCard(Order order) {
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: Colors.grey.shade50,
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: Colors.grey.shade200),
      ),
      child: Column(
        children: [
          _buildDistanceRow(
            icon: Icons.two_wheeler,
            label: 'You → Restaurant',
            km: order.riderToRestaurantDistanceKm,
            m: order.riderToRestaurantDistanceM,
            eta: order.riderToRestaurantEtaMin,
          ),
          const Padding(
            padding: EdgeInsets.symmetric(vertical: 10),
            child: Divider(height: 1, color: Colors.black12),
          ),
          _buildDistanceRow(
            icon: Icons.delivery_dining,
            label: 'Restaurant → Customer',
            km: order.restaurantToCustomerDistanceKm,
            m: order.restaurantToCustomerDistanceM,
            eta: order.restaurantToCustomerEtaMin,
          ),
        ],
      ),
    );
  }

  Widget _buildDistanceRow({
    required IconData icon,
    required String label,
    required double? km,
    required int? m,
    required int? eta,
  }) {
    final distStr = _formatDistance(km, m);
    final etaStr = eta != null ? '~$eta min' : '';

    return Row(
      children: [
        Icon(icon, size: 20, color: WeloColors.sovereignOrange),
        const SizedBox(width: 10),
        Expanded(
          child: Text(label, style: const TextStyle(fontSize: 13, color: Colors.black87)),
        ),
        Text(
          etaStr.isNotEmpty ? '$distStr  •  $etaStr' : distStr,
          style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 13, color: Colors.black87),
        ),
      ],
    );
  }

  String _formatDistance(double? km, int? meters) {
    if (meters != null && meters > 0) {
      if (meters < 1000) return '${meters}m';
      return '${(meters / 1000).toStringAsFixed(1)}km';
    }
    if (km != null && km > 0) {
      if (km < 1) return '${(km * 1000).round()}m';
      return '${km.toStringAsFixed(1)}km';
    }
    return '—';
  }

  Widget _buildInfoCard({required IconData icon, required String title, required String address}) {
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: Colors.grey.shade50,
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: Colors.grey.shade200),
      ),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Icon(icon, size: 20, color: WeloColors.textBody),
          const SizedBox(width: 10),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(title, style: const TextStyle(fontSize: 12, color: WeloColors.textLight, fontWeight: FontWeight.w600)),
                const SizedBox(height: 2),
                Text(address, style: const TextStyle(fontSize: 13, color: Colors.black87)),
              ],
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildOrderItems(Order order) {
    final items = order.rawItems;
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: Colors.grey.shade50,
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: Colors.grey.shade200),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const Text('Items', style: TextStyle(fontSize: 12, color: WeloColors.textLight, fontWeight: FontWeight.w600)),
          const SizedBox(height: 8),
          ...items.map((item) {
            final name = item['menu_item']?['name'] ?? item['menu_item_name'] ?? 'Item';
            final qty = item['quantity'] ?? 1;
            final variant = item['variant_snapshot'] is Map ? item['variant_snapshot']['label'] : null;
            return Padding(
              padding: const EdgeInsets.only(bottom: 6),
              child: Row(
                children: [
                  Text('$name × $qty', style: const TextStyle(fontWeight: FontWeight.w600, fontSize: 13)),
                  if (variant != null) ...[
                    const SizedBox(width: 4),
                    Text('($variant)', style: const TextStyle(color: Colors.black45, fontSize: 12)),
                  ],
                ],
              ),
            );
          }),
        ],
      ),
    );
  }
}
