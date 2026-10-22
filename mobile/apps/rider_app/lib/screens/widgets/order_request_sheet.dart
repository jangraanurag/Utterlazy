import 'dart:async';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:ui/ui.dart';
import 'package:api/api.dart';
import 'package:core/core.dart';

class OrderRequestSheet extends ConsumerStatefulWidget {
  const OrderRequestSheet({super.key});

  @override
  ConsumerState<OrderRequestSheet> createState() => _OrderRequestSheetState();
}

class _OrderRequestSheetState extends ConsumerState<OrderRequestSheet>
    with SingleTickerProviderStateMixin {
  late AnimationController _timerController;
  int _secondsLeft = 60;

  @override
  void initState() {
    super.initState();
    _timerController = AnimationController(
      vsync: this,
      duration: const Duration(seconds: 60),
    )..forward();

    Timer.periodic(const Duration(seconds: 1), (timer) {
      if (!mounted) {
        timer.cancel();
        return;
      }
      setState(() {
        _secondsLeft--;
        if (_secondsLeft <= 0) {
          timer.cancel();
          ref.read(riderStateProvider.notifier).rejectOrder();
        }
      });
    });
  }

  @override
  void dispose() {
    _timerController.dispose();
    super.dispose();
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

  Widget _buildDistanceRow({
    required IconData icon,
    required String label,
    required Order order,
    required bool isPickup,
  }) {
    final km = isPickup ? order.riderToRestaurantDistanceKm : order.restaurantToCustomerDistanceKm;
    final m = isPickup ? order.riderToRestaurantDistanceM : order.restaurantToCustomerDistanceM;
    final eta = isPickup ? order.riderToRestaurantEtaMin : order.restaurantToCustomerEtaMin;
    final distStr = _formatDistance(km, m);
    final etaStr = eta != null ? '~$eta min' : '';

    return Row(
      children: [
        Icon(icon, size: 16, color: WeloColors.textBody),
        const SizedBox(width: 8),
        Text(label, style: const TextStyle(color: WeloColors.textBody, fontSize: 12)),
        const Spacer(),
        Text(
          etaStr.isNotEmpty ? '$distStr  •  $etaStr' : distStr,
          style: const TextStyle(fontWeight: FontWeight.w600, fontSize: 12, color: Colors.black87),
        ),
      ],
    );
  }

  @override
  Widget build(BuildContext context) {
    final riderState = ref.watch(riderStateProvider);
    final order = riderState.currentOrder;

    return Container(
      padding: const EdgeInsets.all(24),
      decoration: const BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.vertical(top: Radius.circular(24)),
        boxShadow: [
          BoxShadow(color: Colors.black12, blurRadius: 10, offset: Offset(0, -5)),
        ],
      ),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          // Timer bar
          Container(
            width: double.infinity,
            height: 4,
            decoration: BoxDecoration(
              color: WeloColors.sovereignElevated,
              borderRadius: BorderRadius.circular(2),
            ),
            child: AnimatedBuilder(
              animation: _timerController,
              builder: (context, child) {
                return FractionallySizedBox(
                  alignment: Alignment.centerLeft,
                  widthFactor: 1.0 - _timerController.value,
                  child: Container(
                    decoration: BoxDecoration(
                      color: _secondsLeft > 20 ? WeloColors.sovereignOrange : WeloColors.error,
                      borderRadius: BorderRadius.circular(2),
                    ),
                  ),
                );
              },
            ),
          ),
          const SizedBox(height: 16),

          // Header
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              const Text(
                'New Order Request',
                style: TextStyle(fontSize: 18, fontWeight: FontWeight.bold),
              ),
              Container(
                padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
                decoration: BoxDecoration(
                  color: _secondsLeft > 20
                      ? WeloColors.sovereignOrange.withValues(alpha: 0.078)
                      : WeloColors.error.withValues(alpha: 0.078),
                  borderRadius: BorderRadius.circular(12),
                ),
                child: Text(
                  '${_secondsLeft}s',
                  style: TextStyle(
                    fontWeight: FontWeight.bold,
                    color: _secondsLeft > 20 ? WeloColors.sovereignOrange : WeloColors.error,
                  ),
                ),
              ),
            ],
          ),
          const SizedBox(height: 16),

          // Order details
          WeloCard(
            padding: const EdgeInsets.all(14),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Row(
                  children: [
                    const Icon(Icons.store, size: 18, color: WeloColors.sovereignOrange),
                    const SizedBox(width: 8),
                    Expanded(
                      child: Text(
                        order?.restaurantName ?? 'Restaurant',
                        style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 15),
                      ),
                    ),
                  ],
                ),
                const SizedBox(height: 8),
                Row(
                  children: [
                    const Icon(Icons.location_on_outlined, size: 18, color: WeloColors.textBody),
                    const SizedBox(width: 8),
                    Expanded(
                      child: Text(
                        order?.customerAddress ?? 'Customer Address',
                        style: const TextStyle(color: WeloColors.textBody, fontSize: 13),
                      ),
                    ),
                  ],
                ),
                const SizedBox(height: 8),
                Row(
                  children: [
                    const Icon(Icons.receipt_long, size: 18, color: WeloColors.textBody),
                    const SizedBox(width: 8),
                    Text(
                      'Order #${order?.id ?? "—"}',
                      style: const TextStyle(color: WeloColors.textBody, fontSize: 13),
                    ),
                  ],
                ),
                if (order != null) ...[
                  const SizedBox(height: 12),
                  _buildDistanceRow(
                    icon: Icons.two_wheeler,
                    label: 'You → Restaurant',
                    order: order,
                    isPickup: true,
                  ),
                  const SizedBox(height: 8),
                  _buildDistanceRow(
                    icon: Icons.delivery_dining,
                    label: 'Restaurant → Customer',
                    order: order,
                    isPickup: false,
                  ),
                ],
              ],
            ),
          ),
          const SizedBox(height: 12),

          // Payout info
          Container(
            padding: const EdgeInsets.all(12),
            decoration: BoxDecoration(
              color: WeloColors.success.withValues(alpha: 0.059),
              borderRadius: BorderRadius.circular(12),
            ),
            child: Row(
              mainAxisAlignment: MainAxisAlignment.center,
              children: [
                const Icon(Icons.account_balance_wallet, size: 18, color: WeloColors.success),
                const SizedBox(width: 8),
                Text(
                  'Payout: ₹${riderState.payout.toStringAsFixed(0)}',
                  style: const TextStyle(
                    fontWeight: FontWeight.bold,
                    fontSize: 16,
                    color: WeloColors.success,
                  ),
                ),
              ],
            ),
          ),
          const SizedBox(height: 16),

          // Action buttons
          Row(
            children: [
              Expanded(
                child: OutlinedButton(
                  onPressed: () {
                    ref.read(riderStateProvider.notifier).rejectOrder();
                  },
                  style: OutlinedButton.styleFrom(
                    foregroundColor: WeloColors.error,
                    side: const BorderSide(color: WeloColors.error),
                    padding: const EdgeInsets.symmetric(vertical: 16),
                    shape: RoundedRectangleBorder(
                      borderRadius: BorderRadius.circular(14),
                    ),
                  ),
                  child: const Text('Reject', style: TextStyle(fontWeight: FontWeight.w600)),
                ),
              ),
              const SizedBox(width: 12),
              Expanded(
                flex: 2,
                child: ElevatedButton(
                  onPressed: riderState.isLoading
                      ? null
                      : () {
                          ref.read(riderStateProvider.notifier).acceptOrder();
                        },
                  style: ElevatedButton.styleFrom(
                    backgroundColor: WeloColors.success,
                    foregroundColor: Colors.white,
                    padding: const EdgeInsets.symmetric(vertical: 16),
                    shape: RoundedRectangleBorder(
                      borderRadius: BorderRadius.circular(14),
                    ),
                  ),
                  child: riderState.isLoading
                      ? const SizedBox(
                          width: 20,
                          height: 20,
                          child: CircularProgressIndicator(
                            strokeWidth: 2,
                            valueColor: AlwaysStoppedAnimation(Colors.white),
                          ),
                        )
                      : const Text('Accept Order', style: TextStyle(fontWeight: FontWeight.w600)),
                ),
              ),
            ],
          ),
          const SizedBox(height: 8),
        ],
      ),
    );
  }
}
