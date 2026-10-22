import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:api/api.dart';
import 'package:core/core.dart';
import 'package:ui/ui.dart';
import 'order_tracking_screen.dart';

class OrderTrackingFromNotification extends ConsumerWidget {
  final String orderId;
  const OrderTrackingFromNotification({super.key, required this.orderId});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    return Scaffold(
      appBar: WeloAppBar(title: 'Order Tracking'),
      body: FutureBuilder<Order>(
        future: _loadOrder(ref, orderId),
        builder: (context, snapshot) {
          if (snapshot.connectionState == ConnectionState.waiting) {
            return const Center(child: CircularProgressIndicator(color: WeloColors.sovereignOrange));
          }
          if (snapshot.hasError || !snapshot.hasData) {
            return Center(
              child: Column(
                mainAxisSize: MainAxisSize.min,
                children: [
                  const Text('Could not load order'),
                  const SizedBox(height: 12),
                  ElevatedButton(
                    onPressed: () => Navigator.pop(context),
                    child: const Text('Go Back'),
                  ),
                ],
              ),
            );
          }
          return OrderTrackingScreen(order: snapshot.data!);
        },
      ),
    );
  }

  Future<Order> _loadOrder(WidgetRef ref, String id) async {
    final apiClient = ref.read(apiClientProvider);
    final response = await apiClient.dio.get('/orders/$id');
    return Order.fromJson(response.data['order'] ?? response.data);
  }
}
