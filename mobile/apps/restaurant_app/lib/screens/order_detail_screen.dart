import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:ui/ui.dart';
import 'package:api/api.dart';
import 'package:core/core.dart';

class OrderDetailScreen extends ConsumerWidget {
  final Order order;

  const OrderDetailScreen({super.key, required this.order});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    return Scaffold(
      appBar: WeloAppBar(
        title: 'Order #${order.id.length > 8 ? order.id.substring(0, 8).toUpperCase() : order.id.toUpperCase()}',
        actions: [
          PopupMenuButton<String>(
            onSelected: (v) {
              if (v == 'timeline') { _showTimeline(context); }
              if (v == 'help') { _showHelpOptions(context, ref); }
            },
            itemBuilder: (_) => [
              const PopupMenuItem(value: 'timeline', child: Row(
                children: [Icon(Icons.timeline, size: 18), SizedBox(width: 8), Text('Timeline')],
              )),
              const PopupMenuItem(value: 'help', child: Row(
                children: [Icon(Icons.help_outline, size: 18), SizedBox(width: 8), Text('Need Help?')],
              )),
            ],
          ),
        ],
      ),
      body: SingleChildScrollView(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            _buildStatusHeader(),
            const SizedBox(height: 16),
            _buildCustomerInfo(context, ref),
            const SizedBox(height: 16),
            _buildItemsSection(),
            const SizedBox(height: 16),
            _buildBillDetails(),
            const SizedBox(height: 16),
            if (order.riderAssigned || order.status == 'ready_for_pickup' || order.status == 'out_for_delivery')
              _buildRiderSection(context, ref),
            const SizedBox(height: 16),
            _buildOrderInfo(),
            const SizedBox(height: 20),
            _buildActions(context, ref),
          ],
        ),
      ),
    );
  }

  Widget _buildStatusHeader() {
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
                    WeloOrderStatusBadge(status: order.status),
                    if (order.paymentMode == 'online')
                      Container(
                        margin: const EdgeInsets.only(left: 8),
                        padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 2),
                        decoration: BoxDecoration(
                          color: WeloColors.success.withValues(alpha: 0.078),
                          borderRadius: BorderRadius.circular(6),
                        ),
                        child: const Text('Prepaid', style: TextStyle(fontSize: 10, fontWeight: FontWeight.bold, color: WeloColors.success)),
                      ),
                  ],
                ),
                const SizedBox(height: 8),
                Text(
                  '₹${order.totalAmount.toInt()}',
                  style: const TextStyle(fontSize: 24, fontWeight: FontWeight.bold, color: WeloColors.sovereignOrange),
                ),
                if (order.createdAt != null) ...[
                  const SizedBox(height: 4),
                  Text('Ordered at ${_formatTime(order.createdAt!)}',
                    style: const TextStyle(color: WeloColors.textBody, fontSize: 13)),
                ],
              ],
            ),
          ),
          Column(
            children: [
              _buildTimelinePreview(),
            ],
          ),
        ],
      ),
    );
  }

  Widget _buildTimelinePreview() {
    final steps = [
      {'status': 'pending', 'icon': Icons.receipt, 'label': 'Placed'},
      {'status': 'confirmed', 'icon': Icons.check_circle_outline, 'label': 'Accepted'},
      {'status': 'preparing', 'icon': Icons.restaurant, 'label': 'Preparing'},
      {'status': 'ready_for_pickup', 'icon': Icons.checklist, 'label': 'Ready'},
      {'status': 'delivered', 'icon': Icons.done_all, 'label': 'Delivered'},
    ];

    final currentStatusIndex = steps.indexWhere((s) => s['status'] == order.status);

    return SizedBox(
      width: 40,
      child: Column(
        children: List.generate(steps.length, (i) {
          final isActive = i <= currentStatusIndex;
          return Padding(
            padding: const EdgeInsets.symmetric(vertical: 1),
            child: Icon(
              steps[i]['icon'] as IconData,
              size: 12,
              color: isActive ? WeloColors.success : WeloColors.textLight,
            ),
          );
        }),
      ),
    );
  }

  Widget _buildCustomerInfo(BuildContext context, WidgetRef ref) {
    return WeloCard(
      padding: const EdgeInsets.all(14),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const Text('Customer', style: TextStyle(fontWeight: FontWeight.bold, fontSize: 15)),
          const SizedBox(height: 10),
          Row(
            children: [
              const Icon(Icons.person_outline, size: 16, color: WeloColors.textBody),
              const SizedBox(width: 8),
              Expanded(child: Text(order.customerName ?? 'Customer',
                style: const TextStyle(fontWeight: FontWeight.w600))),
              if (order.customerPhone != null)
                GestureDetector(
                  onTap: () => _callCustomer(context, ref, order.id),
                  child: const Icon(Icons.phone_outlined, size: 18, color: WeloColors.sovereignOrange),
                ),
            ],
          ),
          if (order.customerAddress.isNotEmpty) ...[
            const SizedBox(height: 8),
            Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                const Icon(Icons.location_on_outlined, size: 16, color: WeloColors.textBody),
                const SizedBox(width: 8),
                Expanded(child: Text(order.customerAddress,
                  style: const TextStyle(color: WeloColors.textBody, fontSize: 13))),
              ],
            ),
          ],
          if (order.deliveryOtp != null) ...[
            const SizedBox(height: 8),
            Row(
              children: [
                const Icon(Icons.lock_outline, size: 16, color: WeloColors.textBody),
                const SizedBox(width: 8),
                Text('Delivery OTP: ', style: const TextStyle(color: WeloColors.textBody, fontSize: 13)),
                Text(order.deliveryOtp!,
                  style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 16, letterSpacing: 2)),
              ],
            ),
          ],
        ],
      ),
    );
  }

  Widget _buildItemsSection() {
    return WeloCard(
      padding: const EdgeInsets.all(14),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const Text('Items', style: TextStyle(fontWeight: FontWeight.bold, fontSize: 15)),
          const SizedBox(height: 10),
          ...List.generate(order.itemsCount.clamp(0, 20), (i) {
            // Items are not stored in the Order model directly, but itemSummary has text
            // In a real implementation, we'd parse items from the raw JSON
            final itemLines = order.itemSummary.split(', ');
            final line = i < itemLines.length ? itemLines[i] : 'Item ${i + 1}';
            return Padding(
              padding: const EdgeInsets.only(bottom: 6),
              child: Row(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  const Text('• ', style: TextStyle(fontWeight: FontWeight.bold)),
                  Expanded(child: Text(line, style: const TextStyle(fontSize: 13))),
                ],
              ),
            );
          }),
        ],
      ),
    );
  }

  Widget _buildBillDetails() {
    return WeloCard(
      padding: const EdgeInsets.all(14),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const Text('Bill Details', style: TextStyle(fontWeight: FontWeight.bold, fontSize: 15)),
          const SizedBox(height: 10),
          _buildBillRow('Subtotal', '₹${order.subtotal.toInt()}'),
          if (order.taxAmount > 0)
            _buildBillRow('Tax (GST)', '₹${order.taxAmount.toInt()}'),
          if (order.deliveryFee > 0)
            _buildBillRow('Delivery Fee', '₹${order.deliveryFee.toInt()}'),
          const Divider(height: 20),
          _buildBillRow('Total', '₹${order.totalAmount.toInt()}',
            total: true),
          if (order.paymentMode == 'online')
            Container(
              margin: const EdgeInsets.only(top: 8),
              padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
              decoration: BoxDecoration(
                color: WeloColors.success.withValues(alpha: 0.059),
                borderRadius: BorderRadius.circular(6),
              ),
              child: Row(
                mainAxisSize: MainAxisSize.min,
                children: [
                  const Icon(Icons.check_circle, size: 14, color: WeloColors.success),
                  const SizedBox(width: 4),
                  Text('${order.paymentStatus ?? "Paid"} via ${order.paymentMode}',
                    style: const TextStyle(fontSize: 12, color: WeloColors.success)),
                ],
              ),
            ),
        ],
      ),
    );
  }

  Widget _buildBillRow(String label, String value, {bool total = false}) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 3),
      child: Row(
        mainAxisAlignment: MainAxisAlignment.spaceBetween,
        children: [
          Text(label, style: TextStyle(
            color: total ? Colors.black : WeloColors.textBody,
            fontWeight: total ? FontWeight.bold : FontWeight.normal,
            fontSize: total ? 15 : 13,
          )),
          Text(value, style: TextStyle(
            fontWeight: total ? FontWeight.bold : FontWeight.w600,
            fontSize: total ? 15 : 13,
            color: total ? WeloColors.sovereignOrange : Colors.black87,
          )),
        ],
      ),
    );
  }

  Widget _buildRiderSection(BuildContext context, WidgetRef ref) {
    return WeloCard(
      padding: const EdgeInsets.all(14),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const Text('Delivery Partner', style: TextStyle(fontWeight: FontWeight.bold, fontSize: 15)),
          const SizedBox(height: 10),
          Row(
            children: [
              Icon(Icons.delivery_dining, size: 24,
                color: order.riderAssigned ? WeloColors.success : WeloColors.textLight),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      order.riderName ?? (order.riderAssigned ? 'Rider assigned' : 'Assigning rider...'),
                      style: const TextStyle(fontWeight: FontWeight.w600),
                    ),
                    if (order.riderDistanceToRestaurant != null)
                      Text('${order.riderDistanceToRestaurant!.toStringAsFixed(1)} km away',
                        style: const TextStyle(color: WeloColors.textBody, fontSize: 12)),
                  ],
                ),
              ),
              if (order.riderPhone != null)
                GestureDetector(
                  onTap: () => _callRider(context, ref, order.id),
                  child: Container(
                    padding: const EdgeInsets.all(8),
                    decoration: BoxDecoration(
                      color: WeloColors.success.withValues(alpha: 0.059),
                      borderRadius: BorderRadius.circular(8),
                    ),
                    child: const Icon(Icons.phone_outlined, size: 18, color: WeloColors.success),
                  ),
                ),
            ],
          ),
          if (order.riderArrived)
            Container(
              margin: const EdgeInsets.only(top: 8),
              padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
              decoration: BoxDecoration(
                color: WeloColors.sovereignTeal.withValues(alpha: 0.059),
                borderRadius: BorderRadius.circular(6),
              ),
              child: const Row(
                mainAxisSize: MainAxisSize.min,
                children: [
                  Icon(Icons.check_circle, size: 14, color: WeloColors.sovereignTeal),
                  SizedBox(width: 4),
                  Text('Rider has arrived', style: TextStyle(fontSize: 12, color: WeloColors.sovereignTeal)),
                ],
              ),
            ),
        ],
      ),
    );
  }

  Widget _buildOrderInfo() {
    return WeloCard(
      padding: const EdgeInsets.all(14),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const Text('Order Info', style: TextStyle(fontWeight: FontWeight.bold, fontSize: 15)),
          const SizedBox(height: 10),
          _buildInfoRow('Order ID', order.id),
          _buildInfoRow('Payment', '${order.paymentMode.toUpperCase()}${order.paymentStatus != null ? ' • ${order.paymentStatus}' : ''}'),
          _buildInfoRow('Items', '${order.itemsCount} items'),
          if (order.prepTimeMinutes > 0)
            _buildInfoRow('Prep Time', '${order.prepTimeMinutes} min'),
          if (order.createdAt != null)
            _buildInfoRow('Ordered at', _formatDateTime(order.createdAt!)),
          if (order.acceptedAt != null)
            _buildInfoRow('Accepted at', _formatDateTime(order.acceptedAt!)),
        ],
      ),
    );
  }

  Widget _buildInfoRow(String label, String value) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 2),
      child: Row(
        mainAxisAlignment: MainAxisAlignment.spaceBetween,
        children: [
          Text(label, style: const TextStyle(color: WeloColors.textBody, fontSize: 13)),
          Text(value, style: const TextStyle(fontWeight: FontWeight.w600, fontSize: 13)),
        ],
      ),
    );
  }

  Widget _buildActions(BuildContext context, WidgetRef ref) {
    return Column(
      children: [
        if (order.status == 'pending')
          Row(
            children: [
              Expanded(
                child: OutlinedButton(
                  onPressed: () => _handleReject(context, ref),
                  style: OutlinedButton.styleFrom(foregroundColor: WeloColors.error),
                  child: const Text('Reject'),
                ),
              ),
              const SizedBox(width: 12),
              Expanded(
                flex: 2,
                child: WeloPrimaryButton(
                  text: 'Accept Order',
                  onPressed: () => _handleAccept(context, ref),
                ),
              ),
            ],
          )
        else if (order.status == 'confirmed')
          SizedBox(
            width: double.infinity,
            child: WeloPrimaryButton(
              text: 'Start Preparing',
              onPressed: () => _handleUpdateStatus(context, ref, 'preparing'),
            ),
          )
        else if (order.status == 'preparing')
          SizedBox(
            width: double.infinity,
            child: WeloPrimaryButton(
              text: 'Mark Ready',
              onPressed: () => _handleUpdateStatus(context, ref, 'ready_for_pickup'),
            ),
          ),
      ],
    );
  }

  Future<void> _handleAccept(BuildContext context, WidgetRef ref) async {
    try {
      await ref.read(vendorRepositoryProvider).acceptOrder(order.id);
      if (context.mounted) { Navigator.pop(context, true); }
    } catch (e) {
      if (context.mounted) {
        ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('Failed to accept')));
      }
    }
  }

  Future<void> _handleReject(BuildContext context, WidgetRef ref) async {
    try {
      await ref.read(vendorRepositoryProvider).rejectOrder(order.id, 'Restaurant busy');
      if (context.mounted) { Navigator.pop(context, true); }
    } catch (e) {
      if (context.mounted) {
        ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('Failed to reject')));
      }
    }
  }

  Future<void> _handleUpdateStatus(BuildContext context, WidgetRef ref, String status) async {
    try {
      await ref.read(vendorRepositoryProvider).updateOrderStatus(order.id, status);
      if (context.mounted) { Navigator.pop(context, true); }
    } catch (e) {
      if (context.mounted) {
        ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text('Failed: $e')));
      }
    }
  }

  void _showTimeline(BuildContext context) {
    final steps = [
      {'label': 'Placed', 'time': order.createdAt, 'status': 'pending'},
      {'label': 'Accepted', 'time': order.acceptedAt, 'status': 'confirmed'},
      {'label': 'Preparing', 'time': null, 'status': 'preparing'},
      {'label': 'Ready', 'time': order.readyMarkedAt, 'status': 'ready_for_pickup'},
      {'label': 'Rider arrived', 'time': order.riderArrivedAt, 'status': 'ready_for_pickup'},
      {'label': 'Delivered', 'time': null, 'status': 'delivered'},
    ];

    showModalBottomSheet(
      context: context,
      shape: const RoundedRectangleBorder(
        borderRadius: BorderRadius.vertical(top: Radius.circular(24)),
      ),
      builder: (ctx) => Padding(
        padding: const EdgeInsets.all(24),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            const Text('Order Timeline', style: TextStyle(fontSize: 18, fontWeight: FontWeight.bold)),
            const SizedBox(height: 16),
            ...steps.asMap().entries.map((entry) {
              final i = entry.key;
              final step = entry.value;
              final isActive = _isStepActive(step['label'] as String);
              return Padding(
                padding: const EdgeInsets.symmetric(vertical: 4),
                child: Row(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Column(
                      children: [
                        Container(
                          width: 24, height: 24,
                          decoration: BoxDecoration(
                            color: isActive ? WeloColors.success : WeloColors.textLight.withValues(alpha: 0.157),
                            shape: BoxShape.circle,
                          ),
                          child: Icon(
                            isActive ? Icons.check : Icons.circle,
                            size: isActive ? 14 : 10,
                            color: isActive ? Colors.white : WeloColors.textLight,
                          ),
                        ),
                        if (i < steps.length - 1)
                          Container(
                            width: 2, height: 24,
                            color: isActive ? WeloColors.success.withValues(alpha: 0.235) : WeloColors.textLight.withValues(alpha: 0.078),
                          ),
                      ],
                    ),
                    const SizedBox(width: 12),
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(step['label'] as String,
                            style: TextStyle(
                              fontWeight: isActive ? FontWeight.bold : FontWeight.normal,
                              color: isActive ? Colors.black87 : WeloColors.textLight,
                            )),
                          if (step['time'] != null)
                            Text(_formatDateTime(step['time'] as String),
                              style: TextStyle(fontSize: 12, color: WeloColors.textBody)),
                        ],
                      ),
                    ),
                  ],
                ),
              );
            }),
          ],
        ),
      ),
    );
  }

  bool _isStepActive(String label) {
    final orderStatus = order.status;
    switch (label) {
      case 'Placed': return true;
      case 'Accepted': return orderStatus != 'pending';
      case 'Preparing': return ['preparing', 'ready_for_pickup', 'out_for_delivery', 'delivered'].contains(orderStatus);
      case 'Ready': return ['ready_for_pickup', 'out_for_delivery', 'delivered'].contains(orderStatus);
      case 'Rider arrived': return order.riderArrived;
      case 'Delivered': return orderStatus == 'delivered';
      default: return false;
    }
  }

  String _formatTime(String iso) {
    try {
      final dt = DateTime.parse(iso).toLocal();
      final h = dt.hour > 12 ? dt.hour - 12 : (dt.hour == 0 ? 12 : dt.hour);
      final ampm = dt.hour >= 12 ? 'PM' : 'AM';
      final m = dt.minute.toString().padLeft(2, '0');
      return '$h:$m $ampm';
    } catch (_) {
      return iso;
    }
  }

  String _formatDateTime(String iso) {
    try {
      final dt = DateTime.parse(iso).toLocal();
      final d = '${dt.day}/${dt.month}/${dt.year}';
      final h = dt.hour > 12 ? dt.hour - 12 : (dt.hour == 0 ? 12 : dt.hour);
      final ampm = dt.hour >= 12 ? 'PM' : 'AM';
      final m = dt.minute.toString().padLeft(2, '0');
      return '$d $h:$m $ampm';
    } catch (_) {
      return iso;
    }
  }

  void _showHelpOptions(BuildContext context, WidgetRef ref) {
    showModalBottomSheet(
      context: context,
      shape: const RoundedRectangleBorder(
        borderRadius: BorderRadius.vertical(top: Radius.circular(24)),
      ),
      builder: (ctx) => Padding(
        padding: const EdgeInsets.all(24),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            const Text('Need Help?', style: TextStyle(fontSize: 18, fontWeight: FontWeight.bold)),
            const SizedBox(height: 16),
            ListTile(
              leading: const Icon(Icons.timer_outlined, color: WeloColors.sovereignOrange),
              title: const Text('Kitchen is full / Need more time'),
              onTap: () {
                Navigator.pop(ctx);
                _showKitchenFullDialog(context, ref);
              },
            ),
            ListTile(
              leading: const Icon(Icons.remove_shopping_cart_outlined, color: WeloColors.error),
              title: const Text('Item out of stock'),
              onTap: () {
                Navigator.pop(ctx);
                _showOutOfStockDialog(context, ref);
              },
            ),
            ListTile(
              leading: const Icon(Icons.headset_mic_outlined, color: WeloColors.sovereignTeal),
              title: const Text('Contact Support'),
              onTap: () {
                Navigator.pop(ctx);
                // Call support logic
              },
            ),
          ],
        ),
      ),
    );
  }

  void _showKitchenFullDialog(BuildContext context, WidgetRef ref) {
    int selectedAction = 0; // 0 = Just Delay, 1 = Delay & Pause, 2 = Cancel & Pause
    int pauseDuration = 30; // 30, 60, 120

    showDialog(
      context: context,
      builder: (ctx) => StatefulBuilder(
        builder: (ctx, setState) => AlertDialog(
          title: const Text('Kitchen is Full', style: TextStyle(fontWeight: FontWeight.bold)),
          content: SingleChildScrollView(
            child: Column(
              mainAxisSize: MainAxisSize.min,
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                const Text('Choose an action for this order:'),
                const SizedBox(height: 12),
                // ignore: deprecated_member_use
                RadioListTile<int>(
                  value: 0,
                  // ignore: deprecated_member_use
                  groupValue: selectedAction,
                  // ignore: deprecated_member_use
                  onChanged: (v) => setState(() => selectedAction = v!),
                  title: const Text('Add 10 min delay to this order', style: TextStyle(fontSize: 14)),
                  contentPadding: EdgeInsets.zero,
                  activeColor: WeloColors.sovereignOrange,
                ),
                // ignore: deprecated_member_use
                RadioListTile<int>(
                  value: 1,
                  // ignore: deprecated_member_use
                  groupValue: selectedAction,
                  // ignore: deprecated_member_use
                  onChanged: (v) => setState(() => selectedAction = v!),
                  title: const Text('Add 10 min delay AND Pause new orders', style: TextStyle(fontSize: 14)),
                  contentPadding: EdgeInsets.zero,
                  activeColor: WeloColors.sovereignOrange,
                ),
                // ignore: deprecated_member_use
                RadioListTile<int>(
                  value: 2,
                  // ignore: deprecated_member_use
                  groupValue: selectedAction,
                  // ignore: deprecated_member_use
                  onChanged: (v) => setState(() => selectedAction = v!),
                  title: const Text('Cancel this order AND Pause new orders', style: TextStyle(fontSize: 14, color: WeloColors.error)),
                  contentPadding: EdgeInsets.zero,
                  activeColor: WeloColors.error,
                ),
                if (selectedAction == 1 || selectedAction == 2) ...[
                  const SizedBox(height: 16),
                  const Text('Pause new orders for:', style: TextStyle(fontWeight: FontWeight.bold)),
                  const SizedBox(height: 8),
                  Wrap(
                    spacing: 8,
                    children: [30, 60, 120].map((mins) {
                      final isSelected = pauseDuration == mins;
                      final label = mins == 30 ? '30 Min' : (mins == 60 ? '1 Hour' : '2 Hours');
                      return ChoiceChip(
                        label: Text(label),
                        selected: isSelected,
                        selectedColor: WeloColors.sovereignOrange,
                        labelStyle: TextStyle(color: isSelected ? Colors.white : Colors.black87),
                        onSelected: (selected) {
                          if (selected) setState(() => pauseDuration = mins);
                        },
                      );
                    }).toList(),
                  ),
                ],
              ],
            ),
          ),
          actions: [
            TextButton(onPressed: () => Navigator.pop(ctx), child: const Text('Close')),
            ElevatedButton(
              onPressed: () async {
                Navigator.pop(ctx);
                try {
                  // Pass action and pause_duration to backend
                  // 0 = just delay, 1 = delay+pause, 2 = cancel+pause
                  await ref.read(vendorRepositoryProvider).reportKitchenFull(
                    order.id,
                    10, // hardcoded 10 min delay
                    action: selectedAction,
                    pauseDuration: selectedAction > 0 ? pauseDuration : null,
                  );
                  if (context.mounted) {
                    ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('Action applied successfully')));
                  }
                } catch (e) {
                  if (context.mounted) {
                    ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('Failed to apply action')));
                  }
                }
              },
              style: ElevatedButton.styleFrom(backgroundColor: selectedAction == 2 ? WeloColors.error : WeloColors.sovereignOrange),
              child: const Text('Apply'),
            ),
          ],
        ),
      ),
    );
  }

  void _showOutOfStockDialog(BuildContext context, WidgetRef ref) {
    showDialog(
      context: context,
      builder: (ctx) => AlertDialog(
        title: const Text('Item Out of Stock', style: TextStyle(fontWeight: FontWeight.bold)),
        content: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            const Text('What would you like to do?'),
            const SizedBox(height: 16),
            ListTile(
              leading: const Icon(Icons.phone, color: WeloColors.sovereignOrange),
              title: const Text('Call Customer'),
              subtitle: const Text('Call to suggest a substitute'),
              onTap: () async {
                Navigator.pop(ctx);
                try {
                  String firstItemId = order.rawItems.isNotEmpty ? order.rawItems.first['id'].toString() : '0';
                  await ref.read(vendorRepositoryProvider).reportItemOutOfStock(order.id, firstItemId, 'substitute');
                  if (context.mounted) {
                    ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('Action applied successfully. Please call the customer.')));
                  }
                } catch (e) {
                  if (context.mounted) {
                    ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('Failed to apply action')));
                  }
                }
              },
              contentPadding: EdgeInsets.zero,
            ),
            const Divider(),
            ListTile(
              leading: const Icon(Icons.remove_circle_outline, color: WeloColors.error),
              title: const Text('Cancel Item'),
              subtitle: const Text('Refund item only. Commission applies.'),
              onTap: () async {
                Navigator.pop(ctx);
                try {
                  String firstItemId = order.rawItems.isNotEmpty ? order.rawItems.first['id'].toString() : '0';
                  await ref.read(vendorRepositoryProvider).reportItemOutOfStock(order.id, firstItemId, 'refund');
                  if (context.mounted) {
                    ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('Item cancelled successfully. Refund initiated.')));
                  }
                } catch (e) {
                  if (context.mounted) {
                    ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('Failed to cancel item')));
                  }
                }
              },
              contentPadding: EdgeInsets.zero,
            ),
            ListTile(
              leading: const Icon(Icons.cancel_outlined, color: WeloColors.error),
              title: const Text('Cancel Entire Order'),
              subtitle: const Text('Full refund. Commission applies.'),
              onTap: () async {
                Navigator.pop(ctx);
                try {
                  await ref.read(vendorRepositoryProvider).reportKitchenFull(
                    order.id,
                    0,
                    action: 2, // 2 = cancel order
                    pauseDuration: 0,
                  );
                  if (context.mounted) {
                    ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('Order cancelled successfully.')));
                  }
                } catch (e) {
                  if (context.mounted) {
                    ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('Failed to cancel order')));
                  }
                }
              },
              contentPadding: EdgeInsets.zero,
            ),
          ],
        ),
        actions: [
          TextButton(onPressed: () => Navigator.pop(ctx), child: const Text('Close')),
        ],
      ),
    );
  }

  void _callCustomer(BuildContext context, WidgetRef ref, String orderId) {
    final repo = ref.read(vendorRepositoryProvider);
    repo.callCustomer(orderId).then((data) {
      if (!context.mounted) return;
      final msg = (data['queued'] == true)
          ? (data['message'] as String? ?? 'You are in queue. You will receive a call shortly.')
          : 'Connecting you to the customer...';
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(msg), duration: Duration(seconds: 3)),
      );
    }).catchError((e) {
      if (!context.mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text('Failed: $e')),
      );
    });
  }

  void _callRider(BuildContext context, WidgetRef ref, String orderId) {
    ScaffoldMessenger.of(context).showSnackBar(
      const SnackBar(content: Text('Calling rider...'), duration: Duration(seconds: 2)),
    );
  }
}
