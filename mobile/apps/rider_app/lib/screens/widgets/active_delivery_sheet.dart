import 'dart:async';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:url_launcher/url_launcher.dart';
import 'package:ui/ui.dart';
import 'package:core/core.dart';
import 'package:api/api.dart';
import 'otp_dialog.dart';

enum SheetMode { minimized, normal, maximized }

class ActiveDeliverySheet extends ConsumerStatefulWidget {
  const ActiveDeliverySheet({super.key});

  @override
  ConsumerState<ActiveDeliverySheet> createState() => _ActiveDeliverySheetState();
}

class _ActiveDeliverySheetState extends ConsumerState<ActiveDeliverySheet> {
  SheetMode _mode = SheetMode.normal;

  void _cycleMode() {
    setState(() {
      _mode = switch (_mode) {
        SheetMode.minimized => SheetMode.normal,
        SheetMode.normal => SheetMode.maximized,
        SheetMode.maximized => SheetMode.minimized,
      };
    });
  }

  @override
  Widget build(BuildContext context) {
    final riderState = ref.watch(riderStateProvider);
    final order = riderState.currentOrder;
    final isPickup = riderState.status == RiderStatus.navigatingToRestaurant ||
        riderState.status == RiderStatus.atRestaurant;

    return Container(
      decoration: const BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.vertical(top: Radius.circular(20)),
        boxShadow: [
          BoxShadow(color: Colors.black12, blurRadius: 8, offset: Offset(0, -3)),
        ],
      ),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          // Drag handle (tap to cycle mode)
          GestureDetector(
            onTap: _cycleMode,
            child: Container(
              padding: const EdgeInsets.symmetric(vertical: 10),
              child: Container(
                width: 36,
                height: 4,
                decoration: BoxDecoration(
                  color: Colors.grey.shade300,
                  borderRadius: BorderRadius.circular(2),
                ),
              ),
            ),
          ),

          switch (_mode) {
            SheetMode.minimized => _buildMinimized(riderState, order, isPickup),
            SheetMode.normal => _buildNormal(riderState, order, isPickup),
            SheetMode.maximized => _buildMaximized(riderState, order, isPickup),
          },
        ],
      ),
    );
  }

  Widget _buildMinimized(RiderState state, Order? order, bool isPickup) {
    final distKm = isPickup ? order?.riderToRestaurantDistanceKm : order?.restaurantToCustomerDistanceKm;
    final etaMin = isPickup ? order?.riderToRestaurantEtaMin : order?.restaurantToCustomerEtaMin;
    final distStr = distKm != null ? '${distKm.toStringAsFixed(1)} km' : '';
    final etaStr = etaMin != null ? '~$etaMin min' : '';
    final collectAmount = _getCollectAmount(order);

    return Padding(
      padding: const EdgeInsets.fromLTRB(16, 0, 16, 14),
      child: Row(
        children: [
          Icon(
            isPickup ? Icons.store : Icons.location_on,
            color: WeloColors.sovereignOrange,
            size: 22,
          ),
          const SizedBox(width: 10),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              mainAxisSize: MainAxisSize.min,
              children: [
                Text(
                  isPickup ? (order?.restaurantName ?? '') : (order?.customerAddress ?? ''),
                  style: const TextStyle(fontSize: 13, fontWeight: FontWeight.bold),
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                ),
                if (distStr.isNotEmpty || etaStr.isNotEmpty)
                  Text('$distStr • $etaStr', style: const TextStyle(fontSize: 11, color: WeloColors.textBody)),
              ],
            ),
          ),
          // Navigate button + payout + collect
          _buildNavButton(order, isPickup),
          const SizedBox(width: 8),
          Container(
            padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 6),
            decoration: BoxDecoration(
              color: WeloColors.success.withValues(alpha: 0.059),
              borderRadius: BorderRadius.circular(8),
            ),
            child: Text('Payout ₹${state.payout.toStringAsFixed(0)}',
                style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 11, color: WeloColors.success)),
          ),
          if (collectAmount != null && collectAmount > 0) ...[
            const SizedBox(width: 6),
            Container(
              padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 6),
              decoration: BoxDecoration(
                color: WeloColors.error.withValues(alpha: 0.059),
                borderRadius: BorderRadius.circular(8),
              ),
              child: Text('Collect ₹${collectAmount.toStringAsFixed(0)}',
                  style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 11, color: WeloColors.error)),
            ),
          ],
          const SizedBox(width: 8),
          // Action button
          SizedBox(
            height: 40,
            child: ElevatedButton(
              onPressed: state.isLoading ? null : () => _handleAction(state),
              style: ElevatedButton.styleFrom(
                backgroundColor: _getButtonColor(state.status),
                foregroundColor: Colors.white,
                shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
                padding: const EdgeInsets.symmetric(horizontal: 14),
              ),
              child: Text(_getButtonText(state.status, order?.paymentMode),
                  style: const TextStyle(fontSize: 13, fontWeight: FontWeight.bold)),
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildNormal(RiderState state, Order? order, bool isPickup) {
    final distKm = isPickup ? order?.riderToRestaurantDistanceKm : order?.restaurantToCustomerDistanceKm;
    final distM = isPickup ? order?.riderToRestaurantDistanceM : order?.restaurantToCustomerDistanceM;
    final etaMin = isPickup ? order?.riderToRestaurantEtaMin : order?.restaurantToCustomerEtaMin;
    final distStr = distM != null && distM < 1000 ? '${distM}m' : (distKm != null ? '${distKm.toStringAsFixed(1)} km' : '');
    final etaStr = etaMin != null ? '~$etaMin min' : '';
    final collectAmount = _getCollectAmount(order);

    return Padding(
      padding: const EdgeInsets.fromLTRB(16, 0, 16, 14),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          // Step indicator
          _buildCompactStep(state.status),
          const SizedBox(height: 10),

          // Destination + distance
          Row(
            children: [
              Icon(isPickup ? Icons.store : Icons.location_on,
                  color: isPickup ? WeloColors.sovereignOrange : WeloColors.success, size: 20),
              const SizedBox(width: 10),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    Text(isPickup ? 'Pickup from' : 'Deliver to',
                        style: const TextStyle(fontSize: 11, color: WeloColors.textBody)),
                    Text(isPickup ? (order?.restaurantName ?? '') : (order?.customerAddress ?? ''),
                        style: const TextStyle(fontSize: 14, fontWeight: FontWeight.bold),
                        maxLines: 1, overflow: TextOverflow.ellipsis),
                  ],
                ),
              ),
              if (distStr.isNotEmpty || etaStr.isNotEmpty)
                Container(
                  padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 6),
                  decoration: BoxDecoration(
                    color: WeloColors.sovereignOrange.withValues(alpha: 0.059),
                    borderRadius: BorderRadius.circular(10),
                  ),
                  child: Column(children: [
                    if (distStr.isNotEmpty)
                      Text(distStr, style: const TextStyle(fontSize: 13, fontWeight: FontWeight.bold, color: WeloColors.sovereignOrange)),
                    if (etaStr.isNotEmpty)
                      Text(etaStr, style: const TextStyle(fontSize: 11, color: WeloColors.textBody)),
                  ]),
                ),
            ],
          ),
          const SizedBox(height: 10),

          // Buttons row
          Row(
            children: [
              _buildNavButton(order, isPickup),
              const SizedBox(width: 8),
              if (isPickup || state.status == RiderStatus.navigatingToCustomer)
                _buildCircleButton(Icons.phone, WeloColors.success,
                    () => _call(context, order?.id, isPickup ? 'restaurant' : 'customer')),
              const SizedBox(width: 8),
              Expanded(
                child: Container(
                  padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
                  decoration: BoxDecoration(
                    color: WeloColors.success.withValues(alpha: 0.059),
                    borderRadius: BorderRadius.circular(10),
                  ),
                  child: Row(
                    mainAxisAlignment: MainAxisAlignment.center,
                    children: [
                      const Icon(Icons.account_balance_wallet, size: 16, color: WeloColors.success),
                      const SizedBox(width: 6),
                      Text('₹${state.payout.toStringAsFixed(0)}',
                          style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 14, color: WeloColors.success)),
                    ],
                  ),
                ),
              ),
              if (collectAmount != null && collectAmount > 0) ...[
                const SizedBox(width: 8),
                Container(
                  padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
                  decoration: BoxDecoration(
                    color: WeloColors.error.withValues(alpha: 0.059),
                    borderRadius: BorderRadius.circular(10),
                  ),
                  child: Row(
                    mainAxisAlignment: MainAxisAlignment.center,
                    children: [
                      const Icon(Icons.payments_outlined, size: 16, color: WeloColors.error),
                      const SizedBox(width: 6),
                      Text('₹${collectAmount.toStringAsFixed(0)}',
                          style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 14, color: WeloColors.error)),
                    ],
                  ),
                ),
              ],
              const SizedBox(width: 8),
              SizedBox(
                height: 44,
                child: ElevatedButton(
                  onPressed: state.isLoading ? null : () => _handleAction(state),
                  style: ElevatedButton.styleFrom(
                    backgroundColor: _getButtonColor(state.status),
                    foregroundColor: Colors.white,
                    shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                    padding: const EdgeInsets.symmetric(horizontal: 16),
                  ),
                  child: state.isLoading
                      ? const SizedBox(width: 20, height: 20, child: CircularProgressIndicator(strokeWidth: 2, valueColor: AlwaysStoppedAnimation(Colors.white)))
                      : Row(mainAxisSize: MainAxisSize.min, children: [
                          Icon(_getButtonIcon(state.status), size: 18),
                          const SizedBox(width: 6),
                          Text(_getButtonText(state.status, order?.paymentMode),
                              style: const TextStyle(fontSize: 13, fontWeight: FontWeight.bold)),
                        ]),
                ),
              ),
            ],
          ),
        ],
      ),
    );
  }

  Widget _buildMaximized(RiderState state, Order? order, bool isPickup) {

    return SingleChildScrollView(
      padding: const EdgeInsets.fromLTRB(16, 0, 16, 14),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          _buildCompactStep(state.status),
          const SizedBox(height: 12),

          // Restaurant card
          _buildLocationCard(
            icon: Icons.store,
            color: WeloColors.sovereignOrange,
            label: 'Pickup',
            name: order?.restaurantName ?? 'Restaurant',
            address: order?.restaurantAddress ?? '',
            distKm: order?.riderToRestaurantDistanceKm,
            distM: order?.riderToRestaurantDistanceM,
            etaMin: order?.riderToRestaurantEtaMin,
            onCall: () => _call(context, order?.id, 'restaurant'),
            onNav: () => _openMaps(order?.restaurantLatitude, order?.restaurantLongitude, order?.restaurantName),
          ),
          const SizedBox(height: 8),

          // Customer card
          _buildLocationCard(
            icon: Icons.location_on,
            color: WeloColors.success,
            label: 'Drop',
            name: order?.customerAddress ?? 'Customer',
            address: '',
            distKm: order?.restaurantToCustomerDistanceKm,
            distM: order?.restaurantToCustomerDistanceM,
            etaMin: order?.restaurantToCustomerEtaMin,
            onCall: () => _call(context, order?.id, 'customer'),
            onNav: () => _openMaps(order?.customerLatitude, order?.customerLongitude, 'Customer'),
          ),
          const SizedBox(height: 12),

          // Order info
          Row(
            children: [
              _buildInfoChip(Icons.receipt_long, '₹${order?.totalAmount.toStringAsFixed(0) ?? "0"}'),
              const SizedBox(width: 8),
              _buildInfoChip(Icons.payment, order?.paymentMode.toUpperCase() ?? 'COD'),
              const SizedBox(width: 8),
              _buildInfoChip(Icons.receipt, '#${order?.id.substring(0, 8) ?? "—"}'),
            ],
          ),
          const SizedBox(height: 12),

          // Order items
          if (order != null && order.rawItems.isNotEmpty) ...[
            _buildOrderItems(order),
            const SizedBox(height: 12),
          ],

          // Action
          SizedBox(
            width: double.infinity,
            height: 48,
            child: ElevatedButton(
              onPressed: state.isLoading ? null : () => _handleAction(state),
              style: ElevatedButton.styleFrom(
                backgroundColor: _getButtonColor(state.status),
                foregroundColor: Colors.white,
                shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
              ),
              child: state.isLoading
                  ? const SizedBox(width: 20, height: 20, child: CircularProgressIndicator(strokeWidth: 2, valueColor: AlwaysStoppedAnimation(Colors.white)))
                  : Row(mainAxisAlignment: MainAxisAlignment.center, children: [
                      Icon(_getButtonIcon(state.status), size: 20),
                      const SizedBox(width: 8),
                      Text(_getButtonText(state.status, order?.paymentMode),
                          style: const TextStyle(fontSize: 15, fontWeight: FontWeight.bold)),
                    ]),
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildLocationCard({
    required IconData icon,
    required Color color,
    required String label,
    required String name,
    required String address,
    double? distKm,
    int? distM,
    int? etaMin,
    required VoidCallback onCall,
    required VoidCallback onNav,
  }) {
    final distStr = distM != null && distM < 1000 ? '${distM}m' : (distKm != null ? '${distKm.toStringAsFixed(1)} km' : '');
    final etaStr = etaMin != null ? '~$etaMin min' : '';

    return Container(
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        color: Colors.grey.shade50,
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: Colors.grey.shade200),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(children: [
            Icon(icon, size: 16, color: color),
            const SizedBox(width: 6),
            Text(label, style: TextStyle(fontSize: 11, color: color, fontWeight: FontWeight.w600)),
            const Spacer(),
            if (distStr.isNotEmpty || etaStr.isNotEmpty)
              Text('$distStr • $etaStr', style: const TextStyle(fontSize: 11, fontWeight: FontWeight.bold)),
          ]),
          const SizedBox(height: 4),
          Text(name, style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 13), maxLines: 1, overflow: TextOverflow.ellipsis),
          if (address.isNotEmpty)
            Text(address, style: const TextStyle(fontSize: 11, color: WeloColors.textBody), maxLines: 1, overflow: TextOverflow.ellipsis),
          const SizedBox(height: 8),
          Row(children: [
            _buildCircleButton(Icons.phone, color, onCall),
            const SizedBox(width: 8),
            _buildCircleButton(Icons.navigation, color, onNav),
          ]),
        ],
      ),
    );
  }

  Widget _buildNavButton(Order? order, bool isPickup) {
    final lat = isPickup ? order?.restaurantLatitude : order?.customerLatitude;
    final lng = isPickup ? order?.restaurantLongitude : order?.customerLongitude;
    final name = isPickup ? order?.restaurantName : 'Customer';

    return _buildCircleButton(
      Icons.navigation,
      WeloColors.sovereignOrange,
      () => _openMaps(lat, lng, name),
    );
  }

  Future<void> _openMaps(double? lat, double? lng, String? name) async {
    if (lat == null || lng == null) return;
    final url = Uri.parse('https://www.google.com/maps/dir/?api=1&destination=$lat,$lng&travelmode=driving');
    if (await canLaunchUrl(url)) {
      await launchUrl(url, mode: LaunchMode.externalApplication);
    }
  }

  Widget _buildCircleButton(IconData icon, Color color, VoidCallback onTap) {
    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(20),
      child: Container(
        width: 36,
        height: 36,
        decoration: BoxDecoration(
          color: color.withValues(alpha: 0.059),
          shape: BoxShape.circle,
          border: Border.all(color: color.withValues(alpha: 0.196)),
        ),
        child: Icon(icon, size: 18, color: color),
      ),
    );
  }

  Widget _buildInfoChip(IconData icon, String text) {
    return Expanded(
      child: Container(
        padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 6),
        decoration: BoxDecoration(
          color: Colors.grey.shade50,
          borderRadius: BorderRadius.circular(8),
          border: Border.all(color: Colors.grey.shade200),
        ),
        child: Row(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            Icon(icon, size: 14, color: WeloColors.textBody),
            const SizedBox(width: 4),
            Text(text, style: const TextStyle(fontSize: 12, fontWeight: FontWeight.w600)),
          ],
        ),
      ),
    );
  }

  Widget _buildCompactStep(RiderStatus status) {
    final steps = [
      (RiderStatus.navigatingToRestaurant, 'Pickup'),
      (RiderStatus.atRestaurant, 'At'),
      (RiderStatus.navigatingToCustomer, 'Deliver'),
      (RiderStatus.atCustomer, 'Done'),
    ];
    int currentIndex = steps.indexWhere((s) => s.$1 == status);
    if (currentIndex == -1) currentIndex = 0;

    return Row(
      children: List.generate(steps.length * 2 - 1, (index) {
        if (index.isOdd) {
          return Expanded(child: Container(height: 2, color: index ~/ 2 < currentIndex ? WeloColors.success : Colors.grey.shade200));
        }
        final stepIndex = index ~/ 2;
        final isActive = stepIndex <= currentIndex;
        final isCurrent = stepIndex == currentIndex;
        return Container(
          width: 22,
          height: 22,
          decoration: BoxDecoration(
            color: isCurrent ? WeloColors.sovereignOrange : (isActive ? WeloColors.success : Colors.grey.shade200),
            shape: BoxShape.circle,
          ),
          child: Center(
            child: isActive
                ? const Icon(Icons.check, size: 11, color: Colors.white)
                : Text('${stepIndex + 1}', style: TextStyle(fontSize: 9, color: Colors.grey.shade500)),
          ),
        );
      }),
    );
  }

  Future<void> _call(BuildContext context, String? orderId, String who) async {
    if (orderId == null) return;
    final repo = ref.read(deliveryRepositoryProvider);
    try {
      final data = who == 'restaurant' ? await repo.callRestaurant(orderId) : await repo.callCustomer(orderId);
      if (context.mounted) {
        final msg = (data['queued'] == true) ? (data['message'] ?? 'In queue.') : 'Calling $who...';
        ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(msg), duration: const Duration(seconds: 3)));
      }
    } catch (e) {
      if (context.mounted) ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text('Failed: $e')));
    }
  }

  void _handleAction(RiderState state) {
    final notifier = ref.read(riderStateProvider.notifier);
    switch (state.status) {
      case RiderStatus.navigatingToRestaurant:
        notifier.arriveAtRestaurant();
        break;
      case RiderStatus.atRestaurant:
        notifier.pickUpOrder();
        break;
      case RiderStatus.navigatingToCustomer:
        notifier.arriveAtCustomer();
        break;
      case RiderStatus.atCustomer:
        if (state.currentOrder?.paymentMode == 'prepaid') {
          showDialog(context: context, builder: (_) => const OtpDialog());
        } else {
          notifier.deliverOrder('');
        }
        break;
      default:
        break;
    }
  }

  Color _getButtonColor(RiderStatus status) => switch (status) {
    RiderStatus.atRestaurant => WeloColors.sovereignTeal,
    RiderStatus.atCustomer => WeloColors.sovereignOrange,
    _ => WeloColors.success,
  };

  IconData _getButtonIcon(RiderStatus status) => switch (status) {
    RiderStatus.navigatingToRestaurant => Icons.navigation_rounded,
    RiderStatus.atRestaurant => Icons.inventory_2_outlined,
    RiderStatus.navigatingToCustomer => Icons.navigation_rounded,
    RiderStatus.atCustomer => Icons.lock_outline,
    _ => Icons.check,
  };

  String _getButtonText(RiderStatus status, String? paymentMode) => switch (status) {
    RiderStatus.navigatingToRestaurant => 'Arrived',
    RiderStatus.atRestaurant => 'Pick Up',
    RiderStatus.navigatingToCustomer => 'Arrived',
    RiderStatus.atCustomer => paymentMode == 'prepaid' ? 'Verify' : 'Deliver',
    _ => 'Continue',
  };

  double? _getCollectAmount(Order? order) {
    if (order == null) return null;
    // COD: collect total amount. Prepaid: collect 0 (already paid online)
    if (order.paymentMode == 'cod') {
      return order.totalAmount;
    }
    return null; // Prepaid — nothing to collect
  }

  Widget _buildOrderItems(Order order) {
    final items = order.rawItems;
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        color: Colors.grey.shade50,
        borderRadius: BorderRadius.circular(12),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const Text('ITEMS', style: TextStyle(fontSize: 10, fontWeight: FontWeight.w900, color: WeloColors.textLight)),
          const SizedBox(height: 8),
          ...items.map((item) {
            final name = item['menu_item']?['name'] ?? item['menu_item_name'] ?? 'Item';
            final qty = item['quantity'] ?? 1;
            final variant = item['variant_snapshot'] is Map ? item['variant_snapshot']['label'] : null;
            return Padding(
              padding: const EdgeInsets.only(bottom: 4),
              child: Row(
                children: [
                  Text('$name × $qty', style: const TextStyle(fontWeight: FontWeight.w600, fontSize: 12, color: Colors.black87)),
                  if (variant != null) ...[
                    const SizedBox(width: 4),
                    Text('($variant)', style: const TextStyle(color: Colors.black45, fontSize: 11)),
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
