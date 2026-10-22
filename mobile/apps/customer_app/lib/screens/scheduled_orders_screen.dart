import 'dart:async';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:ui/ui.dart';
import 'package:api/api.dart';
import 'package:core/core.dart';

class ScheduledOrdersScreen extends ConsumerStatefulWidget {
  const ScheduledOrdersScreen({super.key});

  @override
  ConsumerState<ScheduledOrdersScreen> createState() => _ScheduledOrdersScreenState();
}

class _ScheduledOrdersScreenState extends ConsumerState<ScheduledOrdersScreen> {
  List<Order> _scheduledOrders = [];
  bool _isLoading = true;

  @override
  void initState() {
    super.initState();
    _loadScheduledOrders();
  }

  Future<void> _loadScheduledOrders() async {
    try {
      final orderRepo = OrderRepository(ref.read(apiClientProvider));
      final res = await orderRepo.getOrders(status: 'scheduled');
      final data = res['data'] ?? res['recent_orders'] ?? res['orders'] ?? [];
      final list = (data is List) ? data.map((json) => Order.fromJson(json as Map<String, dynamic>)).toList() : <Order>[];
      if (mounted) {
        setState(() {
          _scheduledOrders = list;
          _isLoading = false;
        });
      }
    } catch (_) {
      if (mounted) setState(() => _isLoading = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: const WeloAppBar(title: 'Scheduled Orders'),
      body: _isLoading
          ? const Center(child: CircularProgressIndicator(color: WeloColors.sovereignOrange))
          : _scheduledOrders.isEmpty
              ? ListView(
                  padding: const EdgeInsets.all(16),
                  children: [
                    const SizedBox(height: 100),
                    WeloCard(
                      padding: const EdgeInsets.all(20),
                      child: Column(
                        children: [
                          const Icon(Icons.schedule_send, size: 48, color: WeloColors.textLight),
                          const SizedBox(height: 12),
                          const Text('No scheduled orders', style: TextStyle(color: WeloColors.textBody)),
                          const SizedBox(height: 8),
                          const Text('Schedule your next meal in advance!', style: TextStyle(fontSize: 13)),
                        ],
                      ),
                    ),
                  ],
                )
              : RefreshIndicator(
                  onRefresh: _loadScheduledOrders,
                  child: ListView.builder(
                    padding: const EdgeInsets.all(16),
                    itemCount: _scheduledOrders.length,
                    itemBuilder: (context, index) {
                      final order = _scheduledOrders[index];
                      return Padding(
                        padding: const EdgeInsets.only(bottom: 12),
                        child: _buildScheduledOrder(order: order),
                      );
                    },
                  ),
                ),
    );
  }

  Widget _buildScheduledOrder({required Order order}) {
    return WeloCard(
      padding: const EdgeInsets.all(16),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              Flexible(child: Text(order.restaurantName, style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 16), overflow: TextOverflow.ellipsis)),
              Container(
                padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
                decoration: BoxDecoration(
                  color: WeloColors.sovereignTeal.withValues(alpha: 0.078),
                  borderRadius: BorderRadius.circular(8),
                ),
                child: Text(order.status.toUpperCase(), style: const TextStyle(fontSize: 10, fontWeight: FontWeight.bold, color: WeloColors.sovereignTeal)),
              ),
            ],
          ),
          const SizedBox(height: 8),
          Text(order.itemSummary.isNotEmpty ? order.itemSummary : '${order.itemsCount} items', style: const TextStyle(color: WeloColors.textBody, fontSize: 13)),
          const SizedBox(height: 8),
          Text('₹${order.totalAmount.toInt()}', style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 18)),
        ],
      ),
    );
  }
}
