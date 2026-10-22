import 'dart:async';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:ui/ui.dart';
import 'package:core/core.dart';

class RiderEarningsScreen extends ConsumerStatefulWidget {
  const RiderEarningsScreen({super.key});

  @override
  ConsumerState<RiderEarningsScreen> createState() => _RiderEarningsScreenState();
}

class _RiderEarningsScreenState extends ConsumerState<RiderEarningsScreen> {
  bool _isLoading = true;
  String? _error;
  Map<String, dynamic>? _todayData;
  List<dynamic> _recentPayouts = [];
  int _totalDeliveries = 0;
  double _totalEarnings = 0;
  double _unsettled = 0;

  @override
  void initState() {
    super.initState();
    _fetchEarnings();
  }

  Future<void> _fetchEarnings() async {
    setState(() { _isLoading = true; _error = null; });
    try {
      final apiClient = ref.read(apiClientProvider);
      final todayResp = await apiClient.dio.get('/rider/earnings/today');
      final earningsResp = await apiClient.dio.get('/rider/earnings');

      if (mounted) {
        setState(() {
          _todayData = todayResp.data;
          _recentPayouts = (earningsResp.data['payouts']?['data'] as List?) ?? [];
          _totalDeliveries = earningsResp.data['total_deliveries'] ?? 0;
          _totalEarnings = double.tryParse('${earningsResp.data['total_earnings'] ?? 0}') ?? 0;
          _unsettled = double.tryParse('${earningsResp.data['unsettled'] ?? 0}') ?? 0;
          _isLoading = false;
        });
      }
    } catch (e) {
      if (mounted) setState(() { _error = e.toString(); _isLoading = false; });
    }
  }

  @override
  Widget build(BuildContext context) {
    final todayTotal = _todayData?['total'] ?? 0;
    final todayDeliveries = _todayData?['deliveries'] ?? 0;
    final breakdown = _todayData?['breakdown'] ?? {};

    return Scaffold(
      appBar: const WeloAppBar(title: 'Earnings'),
      body: RefreshIndicator(
        onRefresh: _fetchEarnings,
        color: WeloColors.sovereignOrange,
        child: _isLoading
            ? const Center(child: CircularProgressIndicator(color: WeloColors.sovereignOrange))
            : _error != null
                ? Center(child: Column(mainAxisAlignment: MainAxisAlignment.center, children: [
                    const Icon(Icons.error_outline, size: 48, color: WeloColors.error),
                    const SizedBox(height: 12),
                    Text(_error!, style: const TextStyle(color: WeloColors.textBody)),
                    const SizedBox(height: 16),
                    ElevatedButton(onPressed: _fetchEarnings, child: const Text('Retry')),
                  ]))
                : SingleChildScrollView(
                    physics: const AlwaysScrollableScrollPhysics(),
                    padding: const EdgeInsets.all(16),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        // Today's earnings card
                        WeloCard(
                          padding: const EdgeInsets.all(20),
                          child: Column(
                            children: [
                              const Text("Today's Earnings", style: TextStyle(color: WeloColors.textBody, fontSize: 14)),
                              const SizedBox(height: 8),
                              Text(
                                '₹${(todayTotal is num ? todayTotal.toDouble() : 0.0).toStringAsFixed(0)}',
                                style: const TextStyle(fontSize: 36, fontWeight: FontWeight.bold, color: WeloColors.sovereignOrange),
                              ),
                              const SizedBox(height: 16),
                              Row(
                                mainAxisAlignment: MainAxisAlignment.spaceEvenly,
                                children: [
                                  _buildStat('Deliveries', '$todayDeliveries'),
                                  Container(width: 1, height: 30, color: WeloColors.sovereignElevated),
                                  _buildStat('Tips', '₹${(breakdown['tips'] ?? 0)}'),
                                  Container(width: 1, height: 30, color: WeloColors.sovereignElevated),
                                  _buildStat('Surge', '₹${(breakdown['surge'] ?? 0)}'),
                                ],
                              ),
                            ],
                          ),
                        ),
                        const SizedBox(height: 16),

                        // Summary row
                        Row(
                          children: [
                            _buildMiniStat('Total Earned', '₹${_totalEarnings.toStringAsFixed(0)}'),
                            const SizedBox(width: 12),
                            _buildMiniStat('Deliveries', '$_totalDeliveries'),
                            const SizedBox(width: 12),
                            _buildMiniStat('Unsettled', '₹${_unsettled.toStringAsFixed(0)}'),
                          ],
                        ),
                        const SizedBox(height: 24),

                        const Text('Recent Earnings', style: TextStyle(fontSize: 18, fontWeight: FontWeight.bold)),
                        const SizedBox(height: 12),

                        if (_recentPayouts.isEmpty)
                          const Center(child: Padding(
                            padding: EdgeInsets.all(32),
                            child: Text('No earnings yet', style: TextStyle(color: WeloColors.textBody)),
                          ))
                        else
                          ..._recentPayouts.map((p) => _buildPayoutItem(p)),
                      ],
                    ),
                  ),
      ),
    );
  }

  Widget _buildStat(String label, String value) {
    return Column(children: [
      Text(value, style: const TextStyle(fontSize: 18, fontWeight: FontWeight.bold)),
      const SizedBox(height: 4),
      Text(label, style: const TextStyle(fontSize: 12, color: WeloColors.textBody)),
    ]);
  }

  Widget _buildMiniStat(String label, String value) {
    return Expanded(
      child: WeloCard(
        padding: const EdgeInsets.all(12),
        child: Column(children: [
          Text(value, style: const TextStyle(fontSize: 16, fontWeight: FontWeight.bold)),
          const SizedBox(height: 4),
          Text(label, style: const TextStyle(fontSize: 11, color: WeloColors.textBody)),
        ]),
      ),
    );
  }

  Widget _buildPayoutItem(dynamic payout) {
    final totalPayout = double.tryParse('${payout['total_payout'] ?? 0}') ?? 0;
    final createdAt = payout['created_at'] ?? '';
    final time = createdAt.length > 10 ? createdAt.substring(11, 16) : '';
    final orderId = payout['order_id']?.toString() ?? '';
    final orderIdShort = orderId.length > 8 ? orderId.substring(0, 8).toUpperCase() : orderId.toUpperCase();

    return WeloCard(
      margin: const EdgeInsets.only(bottom: 8),
      padding: const EdgeInsets.all(14),
      child: Row(
        children: [
          Container(
            width: 40,
            height: 40,
            decoration: BoxDecoration(
              color: WeloColors.success.withValues(alpha: 0.078),
              borderRadius: BorderRadius.circular(10),
            ),
            child: const Icon(Icons.delivery_dining, size: 20, color: WeloColors.success),
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text('Order #$orderIdShort', style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 14)),
                const SizedBox(height: 2),
                Text('₹${totalPayout.toStringAsFixed(0)}', style: const TextStyle(fontWeight: FontWeight.bold, color: WeloColors.success)),
              ],
            ),
          ),
          Text(time, style: const TextStyle(fontSize: 12, color: WeloColors.textBody)),
        ],
      ),
    );
  }
}
