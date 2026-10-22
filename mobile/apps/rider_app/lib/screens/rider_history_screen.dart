import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:intl/intl.dart';
import 'package:api/api.dart';
import 'package:ui/ui.dart';
import 'package:core/core.dart';

class RiderHistoryScreen extends ConsumerStatefulWidget {
  const RiderHistoryScreen({super.key});

  @override
  ConsumerState<RiderHistoryScreen> createState() => _RiderHistoryScreenState();
}

class _RiderHistoryScreenState extends ConsumerState<RiderHistoryScreen> {
  List<dynamic> _orders = [];
  bool _isLoading = true;
  bool _hasMore = true;
  int _page = 1;

  @override
  void initState() {
    super.initState();
    _loadHistory();
  }

  Future<void> _loadHistory({bool reset = false}) async {
    if (reset) {
      _page = 1;
      _hasMore = true;
    }
    try {
      final repo = DeliveryRepository(ref.read(apiClientProvider));
      final data = await repo.getHistory(page: _page);
      final items = data['data'] as List<dynamic>? ?? [];
      if (mounted) {
        setState(() {
          if (reset || _page == 1) {
            _orders = items;
          } else {
            _orders = [..._orders, ...items];
          }
          _hasMore = items.length >= 20;
          _isLoading = false;
        });
      }
    } catch (e) {
      if (mounted) setState(() => _isLoading = false);
    }
  }

  String _formatIst(String? iso) {
    if (iso == null) return '';
    final dt = DateTime.parse(iso);
    return '${DateFormat('dd MMM').format(dt)} ${DateFormat('HH:mm').format(dt)} IST';
  }

  String _statusLabel(String status) {
    final map = {
      'delivered': 'Delivered',
      'completed': 'Completed',
      'cancelled': 'Cancelled',
      'pending': 'Pending',
      'confirmed': 'Confirmed',
      'preparing': 'Preparing',
      'ready_for_pickup': 'Ready for Pickup',
      'out_for_delivery': 'Out for Delivery',
    };
    return map[status] ?? status;
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: Text('Order History', style: GoogleFonts.spaceGrotesk(fontWeight: FontWeight.bold)),
        backgroundColor: WeloColors.sovereignOrange,
        foregroundColor: Colors.white,
      ),
      body: _isLoading && _orders.isEmpty
          ? const Center(child: CircularProgressIndicator(color: WeloColors.sovereignOrange))
          : RefreshIndicator(
              onRefresh: () => _loadHistory(reset: true),
              child: _orders.isEmpty
                  ? ListView(children: const [
                      SizedBox(height: 100),
                      Center(child: Text('No orders yet', style: TextStyle(color: Colors.grey))),
                    ])
                  : ListView.builder(
                      padding: const EdgeInsets.all(16),
                      itemCount: _orders.length + (_hasMore ? 1 : 0),
                      itemBuilder: (context, index) {
                        if (index == _orders.length) {
                          return Center(
                            child: TextButton(
                              onPressed: () {
                                _page++;
                                _loadHistory();
                              },
                              child: const Text('Load More'),
                            ),
                          );
                        }
                        return _buildOrderCard(_orders[index]);
                      },
                    ),
            ),
    );
  }

  Widget _buildOrderCard(dynamic order) {
    final pay = order['pay'] as Map<String, dynamic>?;
    final status = order['status'] as String? ?? '';
    final isPaid = order['is_paid'] == true;
    final events = (order['status_events'] as List<dynamic>?) ?? [];
    final showPay = pay != null;

    return Container(
      margin: const EdgeInsets.only(bottom: 12),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: Colors.grey.shade200),
      ),
      child: ExpansionTile(
        tilePadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
        childrenPadding: const EdgeInsets.fromLTRB(16, 0, 16, 16),
        shape: const RoundedRectangleBorder(),
        title: Row(
          mainAxisAlignment: MainAxisAlignment.spaceBetween,
          children: [
            Flexible(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    order['restaurant_name'] ?? 'Restaurant',
                    style: GoogleFonts.spaceGrotesk(fontWeight: FontWeight.bold, fontSize: 14),
                    overflow: TextOverflow.ellipsis,
                  ),
                  const SizedBox(height: 2),
                  Text(
                    'Order #${(order['order_id'] as String?)?.substring(0, 8).toUpperCase() ?? ''}',
                    style: TextStyle(fontSize: 11, color: Colors.grey[500]),
                  ),
                ],
              ),
            ),
            Column(
              crossAxisAlignment: CrossAxisAlignment.end,
              children: [
                Container(
                  padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
                  decoration: BoxDecoration(
                    color: status == 'delivered' || status == 'completed'
                        ? Colors.green.withValues(alpha: 0.1)
                        : status == 'cancelled'
                            ? Colors.red.withValues(alpha: 0.1)
                            : Colors.orange.withValues(alpha: 0.1),
                    borderRadius: BorderRadius.circular(8),
                  ),
                  child: Text(
                    _statusLabel(status).toUpperCase(),
                    style: TextStyle(
                      fontSize: 9, fontWeight: FontWeight.w900,
                      color: status == 'delivered' || status == 'completed'
                          ? Colors.green : status == 'cancelled'
                              ? Colors.red : Colors.orange,
                    ),
                  ),
                ),
                if (showPay) ...[
                  const SizedBox(height: 4),
                  Text('₹${pay['total']?.toStringAsFixed(0) ?? '0'}',
                    style: GoogleFonts.spaceGrotesk(fontWeight: FontWeight.bold, fontSize: 16, color: WeloColors.sovereignOrange)),
                ],
              ],
            ),
          ],
        ),
        children: [
          // Pay breakdown
          if (showPay) ...[
            Container(
              padding: const EdgeInsets.all(12),
              decoration: BoxDecoration(
                color: Colors.grey.shade50,
                borderRadius: BorderRadius.circular(12),
              ),
              child: Column(
                children: [
                  _payRow('Distance Pay', '₹${pay['distance_pay']?.toStringAsFixed(0) ?? '0'}'),
                  _payRow('Base Pay', '₹${pay['base_pay']?.toStringAsFixed(0) ?? '0'}'),
                  _payRow('Surge Pay', '₹${pay['surge_pay']?.toStringAsFixed(0) ?? '0'}'),
                  _payRow('Tips', '₹${pay['tip']?.toStringAsFixed(0) ?? '0'}'),
                  if ((pay['incentive'] ?? 0) > 0) _payRow('Incentive', '₹${pay['incentive']?.toStringAsFixed(0) ?? '0'}'),
                  if ((pay['penalty'] ?? 0) > 0) _payRow('Penalty', '-₹${pay['penalty']?.toStringAsFixed(0) ?? '0'}'),
                  const Divider(height: 16),
                  _payRow('Total', '₹${pay['total']?.toStringAsFixed(0) ?? '0'}', bold: true),
                ],
              ),
            ),
            const SizedBox(height: 8),
          ],

          // Paid status
          Row(
            children: [
              Icon(
                isPaid ? Icons.check_circle : Icons.access_time,
                size: 16,
                color: isPaid ? Colors.green : Colors.orange,
              ),
              const SizedBox(width: 6),
              Text(
                isPaid ? 'Paid' : 'Unpaid',
                style: TextStyle(fontWeight: FontWeight.bold, fontSize: 12, color: isPaid ? Colors.green : Colors.orange),
              ),
            ],
          ),
          const SizedBox(height: 8),

          // Status history
          Text('Status Timeline', style: TextStyle(fontSize: 11, fontWeight: FontWeight.bold, color: Colors.grey[600])),
          const SizedBox(height: 6),
          ...events.map((e) => Padding(
            padding: const EdgeInsets.symmetric(vertical: 3),
            child: Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Container(
                  width: 8, height: 8,
                  margin: const EdgeInsets.only(top: 4, right: 10),
                  decoration: BoxDecoration(
                    color: WeloColors.sovereignOrange,
                    shape: BoxShape.circle,
                  ),
                ),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(e['label'] ?? e['status'] ?? '',
                        style: const TextStyle(fontWeight: FontWeight.w500, fontSize: 13)),
                      Text(_formatIst(e['timestamp']),
                        style: TextStyle(fontSize: 10, color: Colors.grey[500])),
                    ],
                  ),
                ),
              ],
            ),
          )),

          // Distance
          Text('Distance: ${(order['distance_km'] as num?)?.toStringAsFixed(2) ?? '0'} km',
            style: TextStyle(fontSize: 11, color: Colors.grey[500])),
        ],
      ),
    );
  }

  Widget _payRow(String label, String value, {bool bold = false}) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 2),
      child: Row(
        mainAxisAlignment: MainAxisAlignment.spaceBetween,
        children: [
          Text(label, style: TextStyle(fontSize: 12, color: Colors.grey[600], fontWeight: bold ? FontWeight.bold : FontWeight.normal)),
          Text(value, style: TextStyle(fontWeight: bold ? FontWeight.bold : FontWeight.w600, fontSize: 12)),
        ],
      ),
    );
  }
}
