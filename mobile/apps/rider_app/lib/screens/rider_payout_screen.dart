import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:intl/intl.dart';
import 'package:api/api.dart';
import 'package:ui/ui.dart';
import 'package:core/core.dart';

class RiderPayoutScreen extends ConsumerStatefulWidget {
  const RiderPayoutScreen({super.key});

  @override
  ConsumerState<RiderPayoutScreen> createState() => _RiderPayoutScreenState();
}

class _RiderPayoutScreenState extends ConsumerState<RiderPayoutScreen> {
  List<dynamic> _cycles = [];
  bool _isLoading = true;
  bool _hasMore = true;
  int _page = 1;

  @override
  void initState() {
    super.initState();
    _loadCycles();
  }

  Future<void> _loadCycles({bool reset = false}) async {
    if (reset) {
      _page = 1;
      _hasMore = true;
    }
    try {
      final repo = DeliveryRepository(ref.read(apiClientProvider));
      final data = await repo.getPayoutCycles(page: _page);
      final items = data['data'] as List<dynamic>? ?? [];
      if (mounted) {
        setState(() {
          if (reset || _page == 1) {
            _cycles = items;
          } else {
            _cycles = [..._cycles, ...items];
          }
          _hasMore = items.length >= 20;
          _isLoading = false;
        });
      }
    } catch (e) {
      if (mounted) setState(() => _isLoading = false);
    }
  }

  String _statusColor(String status) {
    switch (status) {
      case 'paid': return 'green';
      case 'pending_payment': return 'orange';
      case 'generated': return 'blue';
      default: return 'grey';
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: Text('Daily Payouts', style: GoogleFonts.spaceGrotesk(fontWeight: FontWeight.bold)),
        backgroundColor: WeloColors.sovereignOrange,
        foregroundColor: Colors.white,
      ),
      body: _isLoading && _cycles.isEmpty
          ? const Center(child: CircularProgressIndicator(color: WeloColors.sovereignOrange))
          : RefreshIndicator(
              onRefresh: () => _loadCycles(reset: true),
              child: _cycles.isEmpty
                  ? ListView(children: const [
                      SizedBox(height: 100),
                      Center(child: Text('No payout cycles yet', style: TextStyle(color: Colors.grey))),
                    ])
                  : ListView.builder(
                      padding: const EdgeInsets.all(16),
                      itemCount: _cycles.length + (_hasMore ? 1 : 0),
                      itemBuilder: (context, index) {
                        if (index == _cycles.length) {
                          return Center(
                            child: TextButton(
                              onPressed: () {
                                _page++;
                                _loadCycles();
                              },
                              child: const Text('Load More'),
                            ),
                          );
                        }
                        return _buildCycleCard(_cycles[index]);
                      },
                    ),
            ),
    );
  }

  Widget _buildCycleCard(dynamic cycle) {
    final status = cycle['status'] as String? ?? 'generated';
    final date = cycle['cycle_date'] as String? ?? '';
    final netPayable = (cycle['net_payable'] as num?)?.toDouble() ?? 0;
    final totalOrders = (cycle['total_orders'] as num?)?.toInt() ?? 0;
    final distancePay = (cycle['distance_pay'] as num?)?.toDouble() ?? 0;
    final basePay = (cycle['base_pay'] as num?)?.toDouble() ?? 0;
    final surgePay = (cycle['surge_pay'] as num?)?.toDouble() ?? 0;
    final waitPay = (cycle['wait_pay'] as num?)?.toDouble() ?? 0;
    final tip = (cycle['tip_amount'] as num?)?.toDouble() ?? 0;
    final incentive = (cycle['incentive_bonus'] as num?)?.toDouble() ?? 0;
    final penalty = (cycle['penalty'] as num?)?.toDouble() ?? 0;
    final totalKm = (cycle['total_distance_km'] as num?)?.toDouble() ?? 0;

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
            Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  date.isNotEmpty ? DateFormat('dd MMM yyyy').format(DateTime.parse(date)) : '',
                  style: GoogleFonts.spaceGrotesk(fontWeight: FontWeight.bold, fontSize: 14),
                ),
                Text('$totalOrders orders • ${totalKm.toStringAsFixed(1)} km',
                  style: TextStyle(fontSize: 11, color: Colors.grey[500])),
              ],
            ),
            Column(
              crossAxisAlignment: CrossAxisAlignment.end,
              children: [
                Text('₹${netPayable.toStringAsFixed(0)}',
                  style: GoogleFonts.spaceGrotesk(fontWeight: FontWeight.bold, fontSize: 20, color: WeloColors.sovereignOrange)),
                Container(
                  padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
                  decoration: BoxDecoration(
                    color: _statusColor(status) == 'green'
                        ? Colors.green.withValues(alpha: 0.1)
                        : _statusColor(status) == 'blue'
                            ? Colors.blue.withValues(alpha: 0.1)
                            : Colors.orange.withValues(alpha: 0.1),
                    borderRadius: BorderRadius.circular(8),
                  ),
                  child: Text(
                    status.toUpperCase().replaceAll('_', ' '),
                    style: TextStyle(
                      fontSize: 8, fontWeight: FontWeight.w900,
                      color: _statusColor(status) == 'green'
                          ? Colors.green : _statusColor(status) == 'blue'
                              ? Colors.blue : Colors.orange,
                    ),
                  ),
                ),
              ],
            ),
          ],
        ),
        children: [
          Container(
            padding: const EdgeInsets.all(12),
            decoration: BoxDecoration(
              color: Colors.grey.shade50,
              borderRadius: BorderRadius.circular(12),
            ),
            child: Column(
              children: [
                _detailRow('Distance Pay', '₹${distancePay.toStringAsFixed(0)}'),
                _detailRow('Base Pay', '₹${basePay.toStringAsFixed(0)}'),
                if (surgePay > 0) _detailRow('Surge Pay', '₹${surgePay.toStringAsFixed(0)}'),
                if (waitPay > 0) _detailRow('Wait Pay', '₹${waitPay.toStringAsFixed(0)}'),
                if (tip > 0) _detailRow('Tips', '₹${tip.toStringAsFixed(0)}'),
                if (incentive > 0) _detailRow('Incentive', '₹${incentive.toStringAsFixed(0)}'),
                if (penalty > 0) _detailRow('Penalty', '-₹${penalty.toStringAsFixed(0)}'),
                const Divider(height: 16),
                _detailRow('Net Payable', '₹${netPayable.toStringAsFixed(0)}', bold: true),
              ],
            ),
          ),
          if (cycle['utr_number'] != null) ...[
            const SizedBox(height: 8),
            Row(
              children: [
                const Icon(Icons.receipt, size: 16, color: Colors.grey),
                const SizedBox(width: 6),
                Text('UTR: ${cycle['utr_number']}',
                  style: TextStyle(fontSize: 12, fontWeight: FontWeight.w500, color: Colors.grey[600])),
              ],
            ),
          ],
        ],
      ),
    );
  }

  Widget _detailRow(String label, String value, {bool bold = false}) {
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
