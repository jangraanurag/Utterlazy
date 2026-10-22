import 'dart:async';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:ui/ui.dart';
import 'package:api/api.dart';
import 'package:core/core.dart';
import 'order_detail_screen.dart';

class OrdersScreen extends ConsumerStatefulWidget {
  const OrdersScreen({super.key});

  @override
  ConsumerState<OrdersScreen> createState() => _OrdersScreenState();
}

class _OrdersScreenState extends ConsumerState<OrdersScreen> {
  int _selectedTab = 0;
  final _tabs = ['New', 'Confirmed', 'Preparing', 'Ready', 'Past'];
  List<Order> _activeOrders = [];
  List<Order> _pastOrders = [];
  bool _isLoading = true;
  Timer? _pollingTimer;
  StreamSubscription? _newOrderSubscription;
  StreamSubscription? _orderStatusSubscription;

  @override
  void initState() {
    super.initState();
    _loadOrders();
    _pollingTimer = Timer.periodic(const Duration(seconds: 10), (timer) {
      _loadOrders(isSilent: true);
    });
    
    // Listen to real-time WebSocket events
    _newOrderSubscription = ref.read(webSocketServiceProvider).newOrderStream.listen((event) {
      if (mounted) {
        _loadOrders(isSilent: true);
      }
    });
    _orderStatusSubscription = ref.read(webSocketServiceProvider).orderStatusStream.listen((event) {
      if (mounted) {
        _loadOrders(isSilent: true);
      }
    });
  }

  @override
  void dispose() {
    _pollingTimer?.cancel();
    _newOrderSubscription?.cancel();
    _orderStatusSubscription?.cancel();
    super.dispose();
  }

  Future<void> _loadOrders({bool isSilent = false}) async {
    if (!isSilent) {
      setState(() => _isLoading = true);
    }
    try {
      final vendorRepo = ref.read(vendorRepositoryProvider);
      final active = await vendorRepo.getActiveOrders();
      final all = await vendorRepo.getAllOrders();

      if (mounted) {
        setState(() {
          _activeOrders = active;
          _pastOrders = all.where((o) => o.status == 'delivered' || o.status == 'cancelled').toList();
          _isLoading = false;
        });
      }
    } catch (e) {
      if (mounted && !isSilent) {
        setState(() => _isLoading = false);
      }
    }
  }

  Future<void> _handleAcceptOrder(String orderId) async {
    try {
      final vendorRepo = ref.read(vendorRepositoryProvider);
      await vendorRepo.acceptOrder(orderId);
      _loadOrders();
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('Order accepted!')),
        );
      }
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('Failed to accept order.')),
        );
      }
    }
  }

  Future<void> _handleRejectOrder(String orderId) async {
    try {
      final vendorRepo = ref.read(vendorRepositoryProvider);
      await vendorRepo.rejectOrder(orderId, 'Restaurant busy');
      _loadOrders();
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('Order rejected.')),
        );
      }
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('Failed to reject order.')),
        );
      }
    }
  }

  Future<void> _handleUpdateStatus(String orderId, String status) async {
    try {
      final vendorRepo = ref.read(vendorRepositoryProvider);
      await vendorRepo.updateOrderStatus(orderId, status);
      _loadOrders();
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text('Status updated to $status!')),
        );
      }
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('Failed to update status.')),
        );
      }
    }
  }

  List<Order> _getFilteredOrders() {
    switch (_selectedTab) {
      case 0: // New
        return _activeOrders.where((o) => o.status == 'pending').toList();
      case 1: // Confirmed
        return _activeOrders.where((o) => o.status == 'confirmed').toList();
      case 2: // Preparing
        return _activeOrders.where((o) => o.status == 'preparing').toList();
      case 3: // Ready
        return _activeOrders.where((o) => o.status == 'ready_for_pickup' || o.status == 'out_for_delivery').toList();
      case 4: // Past
        return _pastOrders;
      default:
        return [];
    }
  }

  @override
  Widget build(BuildContext context) {
    final filtered = _getFilteredOrders();

    return Scaffold(
      appBar: const WeloAppBar(title: 'Orders'),
      body: Column(
        children: [
          // Tab bar
          Container(
            height: 44,
            margin: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
            child: ListView.builder(
              scrollDirection: Axis.horizontal,
              itemCount: _tabs.length,
              itemBuilder: (context, index) {
                final isSelected = index == _selectedTab;
                return Padding(
                  padding: const EdgeInsets.only(right: 8),
                  child: WeloChip(
                    label: _tabs[index],
                    isSelected: isSelected,
                    onTap: () => setState(() => _selectedTab = index),
                  ),
                );
              },
            ),
          ),

          // Order list
          Expanded(
            child: _isLoading
                ? const Center(child: CircularProgressIndicator(color: WeloColors.sovereignOrange))
                : RefreshIndicator(
                    onRefresh: _loadOrders,
                    color: WeloColors.sovereignOrange,
                    child: filtered.isEmpty
                        ? ListView(
                            physics: const AlwaysScrollableScrollPhysics(),
                            children: const [
                              SizedBox(height: 100),
                              WeloEmptyState(
                                icon: Icons.receipt_long,
                                title: 'No orders here',
                                subtitle: 'Orders will appear here when available',
                              ),
                            ],
                          )
                        : ListView.builder(
                            physics: const AlwaysScrollableScrollPhysics(),
                            padding: const EdgeInsets.symmetric(horizontal: 16),
                            itemCount: filtered.length,
                            itemBuilder: (context, index) {
                              return _buildOrderCard(filtered[index]);
                            },
                          ),
                  ),
          ),
        ],
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
      margin: const EdgeInsets.only(bottom: 10),
      padding: const EdgeInsets.all(14),
      onTap: () {
        Navigator.of(context).push(
          MaterialPageRoute(builder: (_) => OrderDetailScreen(order: order)),
        ).then((_) => _loadOrders());
      },
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              Text(
                '#${order.id.length > 8 ? order.id.substring(0, 8).toUpperCase() : order.id.toUpperCase()}',
                style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 15),
              ),
              WeloOrderStatusBadge(status: order.status),
            ],
          ),
          const SizedBox(height: 8),
          Row(
            children: [
              const Icon(Icons.person_outline, size: 16, color: WeloColors.textBody),
              const SizedBox(width: 6),
              Text(order.customerName ?? 'Customer', style: const TextStyle(fontSize: 13, fontWeight: FontWeight.bold)),
              const SizedBox(width: 16),
              const Icon(Icons.access_time, size: 16, color: WeloColors.textBody),
              const SizedBox(width: 6),
              Text(
                order.createdAt != null
                    ? _formatTime(order.createdAt!)
                    : 'Just now',
                style: const TextStyle(fontSize: 13, color: WeloColors.textBody),
              ),
            ],
          ),
          if (order.customerAddress.isNotEmpty) ...[
            const SizedBox(height: 6),
            Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                const Icon(Icons.location_on_outlined, size: 16, color: WeloColors.textBody),
                const SizedBox(width: 6),
                Expanded(
                  child: Text(
                    order.customerAddress,
                    style: const TextStyle(fontSize: 12, color: WeloColors.textBody),
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                  ),
                ),
              ],
            ),
          ],
          const SizedBox(height: 8),
          Text(
            order.itemSummary.isNotEmpty ? order.itemSummary : '${order.itemsCount} items',
            style: const TextStyle(fontSize: 13, color: Colors.black54, fontWeight: FontWeight.w500),
          ),
          const SizedBox(height: 8),
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              Text(
                '₹${order.totalAmount.toInt()}',
                style: const TextStyle(fontWeight: FontWeight.bold, color: WeloColors.sovereignOrange, fontSize: 15),
              ),
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

          // Rider Status Info
          if (order.riderAssigned || order.status == 'ready_for_pickup' || order.status == 'out_for_delivery') ...[
            const SizedBox(height: 10),
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
          ],

          const SizedBox(height: 12),

          // Action Buttons
          if (order.status == 'pending')
            Row(
              children: [
                Expanded(
                  child: _buildActionBtn('Reject', WeloColors.error, () => _handleRejectOrder(order.id)),
                ),
                const SizedBox(width: 8),
                Expanded(
                  child: _buildActionBtn('Accept', WeloColors.success, () => _handleAcceptOrder(order.id)),
                ),
              ],
            )
          else if (order.status == 'confirmed')
            SizedBox(
              width: double.infinity,
              child: _buildActionBtn('Start Preparing', WeloColors.sovereignOrange, () => _handleUpdateStatus(order.id, 'preparing')),
            )
          else if (order.status == 'preparing')
            SizedBox(
              width: double.infinity,
              child: _buildActionBtn('Mark Ready', WeloColors.sovereignTeal, () => _handleUpdateStatus(order.id, 'ready_for_pickup')),
            ),
        ],
      ),
    );
  }

  Widget _buildActionBtn(String label, Color color, VoidCallback onTap) {
    return GestureDetector(
      onTap: onTap,
      child: Container(
        height: 36,
        alignment: Alignment.center,
        decoration: BoxDecoration(
          color: color.withValues(alpha: 0.078),
          borderRadius: BorderRadius.circular(10),
          border: Border.all(color: color.withValues(alpha: 0.196)),
        ),
        child: Text(
          label,
          style: TextStyle(
            fontSize: 13,
            fontWeight: FontWeight.bold,
            color: color,
          ),
        ),
      ),
    );
  }

  String _formatTime(String isoString) {
    try {
      final dt = DateTime.parse(isoString).toLocal();
      final hour = dt.hour > 12 ? dt.hour - 12 : (dt.hour == 0 ? 12 : dt.hour);
      final ampm = dt.hour >= 12 ? 'PM' : 'AM';
      final minute = dt.minute.toString().padLeft(2, '0');
      return '$hour:$minute $ampm';
    } catch (_) {
      return 'Just now';
    }
  }
}
