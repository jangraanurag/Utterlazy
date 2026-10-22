import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:ui/ui.dart';
import 'package:core/core.dart';

class AnalyticsScreen extends ConsumerStatefulWidget {
  const AnalyticsScreen({super.key});

  @override
  ConsumerState<AnalyticsScreen> createState() => _AnalyticsScreenState();
}

class _AnalyticsScreenState extends ConsumerState<AnalyticsScreen> {
  bool _isLoading = true;
  Map<String, dynamic> _data = {};
  final String _selectedTab = 'Today';

  @override
  void initState() {
    super.initState();
    _loadAnalytics();
  }

  Future<void> _loadAnalytics() async {
    setState(() => _isLoading = true);
    try {
      final repo = ref.read(vendorRepositoryProvider);
      final data = await repo.getAnalytics();
      if (mounted) { setState(() { _data = data; _isLoading = false; }); }
    } catch (e) {
      if (mounted) setState(() => _isLoading = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final today = _data['today'] as Map<String, dynamic>? ?? {};
    final topItems = _data['top_items'] as List<dynamic>? ?? [];
    final hourly = _data['hourly_breakdown'] as List<dynamic>? ?? [];
    final dailyTrend = _data['daily_trend'] as List<dynamic>? ?? [];

    return Scaffold(
      appBar: const WeloAppBar(title: 'Analytics'),
      body: _isLoading
          ? const Center(child: CircularProgressIndicator(color: WeloColors.sovereignOrange))
          : RefreshIndicator(
              onRefresh: _loadAnalytics,
              color: WeloColors.sovereignOrange,
              child: SingleChildScrollView(
                physics: const AlwaysScrollableScrollPhysics(),
                padding: const EdgeInsets.all(16),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    _buildPeriodSelector(),
                    const SizedBox(height: 20),
                    _buildTodaySummary(today),
                    const SizedBox(height: 20),
                    _buildComparisonRow(),
                    const SizedBox(height: 24),
                    _buildRetentionAndCancellation(),
                    const SizedBox(height: 24),
                    _buildHourlyChart(hourly),
                    const SizedBox(height: 24),
                    if (topItems.isNotEmpty) ...[
                      _buildTopItems(topItems),
                      const SizedBox(height: 24),
                    ],
                    if (dailyTrend.isNotEmpty) ...[
                      _buildDailyTrend(dailyTrend),
                      const SizedBox(height: 24),
                    ],
                  ],
                ),
              ),
            ),
    );
  }

  Widget _buildPeriodSelector() {
    return Row(
      children: ['Today', 'This Week', 'This Month'].map((p) {
        final isSelected = p == _selectedTab;
        return Padding(
          padding: const EdgeInsets.only(right: 8),
          child: WeloChip(
            label: p,
            isSelected: isSelected,
            onTap: () {},
          ),
        );
      }).toList(),
    );
  }

  Widget _buildTodaySummary(Map<String, dynamic> today) {
    final revenue = double.tryParse((today['revenue'] ?? 0)?.toString() ?? '0') ?? 0.0;
    final orders = int.tryParse((today['orders'] ?? 0)?.toString() ?? '0') ?? 0;
    final avgOrder = double.tryParse((today['avg_order_value'] ?? 0)?.toString() ?? '0') ?? 0.0;
    final rating = double.tryParse((today['avg_rating'] ?? 4.5)?.toString() ?? '0') ?? 0.0;

    return WeloCard(
      padding: const EdgeInsets.all(20),
      child: Column(
        children: [
          Row(
            children: [
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    const Text('Today\'s Revenue', style: TextStyle(color: WeloColors.textBody, fontSize: 13)),
                    const SizedBox(height: 4),
                    Text('₹${revenue.toInt()}',
                      style: const TextStyle(fontSize: 28, fontWeight: FontWeight.bold, color: WeloColors.sovereignOrange)),
                  ],
                ),
              ),
              Container(
                padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
                decoration: BoxDecoration(
                  color: orders > 0 ? WeloColors.success.withValues(alpha: 0.078) : WeloColors.textLight.withValues(alpha: 0.078),
                  borderRadius: BorderRadius.circular(20),
                ),
                child: Row(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    Container(
                      width: 8, height: 8,
                      decoration: BoxDecoration(
                        color: orders > 0 ? WeloColors.success : WeloColors.textLight,
                        shape: BoxShape.circle,
                      ),
                    ),
                    const SizedBox(width: 4),
                    Text('$orders orders', style: TextStyle(
                      fontSize: 12, fontWeight: FontWeight.bold,
                      color: orders > 0 ? WeloColors.success : WeloColors.textLight,
                    )),
                  ],
                ),
              ),
            ],
          ),
          const SizedBox(height: 16),
          Row(
            children: [
              _buildMiniStat('Avg Order', '₹${avgOrder.toInt()}'),
              const SizedBox(width: 16),
              _buildMiniStat('Rating', '$rating ⭐'),
              const SizedBox(width: 16),
              _buildMiniStat('Cancelled', '${today['cancelled'] ?? 0}'),
            ],
          ),
        ],
      ),
    );
  }

  Widget _buildMiniStat(String label, String value) {
    return Expanded(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(label, style: const TextStyle(color: WeloColors.textBody, fontSize: 11)),
          const SizedBox(height: 2),
          Text(value, style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 16)),
        ],
      ),
    );
  }

  Widget _buildComparisonRow() {
    final weekChange = double.tryParse((_data['week_change'] ?? 0)?.toString() ?? '0') ?? 0.0;
    final monthChange = double.tryParse((_data['month_change'] ?? 0)?.toString() ?? '0') ?? 0.0;

    return Row(
      children: [
        _buildChangeCard('vs Last Week', weekChange),
        const SizedBox(width: 12),
        _buildChangeCard('vs Last Month', monthChange),
      ],
    );
  }

  Widget _buildRetentionAndCancellation() {
    final retention = _data['retention'] as Map<String, dynamic>? ?? {};
    final cancellations = _data['cancellations_30d'] as Map<String, dynamic>? ?? {};

    final retentionRate = double.tryParse((retention['rate'] ?? 0)?.toString() ?? '0') ?? 0.0;
    final cancellationRate = double.tryParse((cancellations['rate'] ?? 0)?.toString() ?? '0') ?? 0.0;

    return Row(
      children: [
        Expanded(
          child: WeloCard(
            padding: const EdgeInsets.all(12),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                const Text('Customer Retention', style: TextStyle(color: WeloColors.textBody, fontSize: 11)),
                const SizedBox(height: 4),
                Text('$retentionRate%',
                  style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 16, color: WeloColors.success)),
                const SizedBox(height: 2),
                Text('${retention['returning_customers'] ?? 0} returning users', style: const TextStyle(fontSize: 10, color: WeloColors.textLight)),
              ],
            ),
          ),
        ),
        const SizedBox(width: 12),
        Expanded(
          child: WeloCard(
            padding: const EdgeInsets.all(12),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                const Text('Cancellations (30d)', style: TextStyle(color: WeloColors.textBody, fontSize: 11)),
                const SizedBox(height: 4),
                Text('$cancellationRate%',
                  style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 16, color: WeloColors.error)),
                const SizedBox(height: 2),
                Text('${cancellations['cancelled'] ?? 0} orders cancelled', style: const TextStyle(fontSize: 10, color: WeloColors.textLight)),
              ],
            ),
          ),
        ),
      ],
    );
  }

  Widget _buildChangeCard(String label, double change) {
    final isUp = change >= 0;
    return Expanded(
      child: WeloCard(
        padding: const EdgeInsets.all(12),
        child: Row(
          children: [
            Icon(isUp ? Icons.trending_up : Icons.trending_down,
              color: isUp ? WeloColors.success : WeloColors.error, size: 20),
            const SizedBox(width: 8),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(label, style: const TextStyle(color: WeloColors.textBody, fontSize: 11)),
                  Text('${isUp ? '+' : ''}$change%',
                    style: TextStyle(
                      fontWeight: FontWeight.bold, fontSize: 15,
                      color: isUp ? WeloColors.success : WeloColors.error,
                    )),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildHourlyChart(List<dynamic> hourly) {
    if (hourly.isEmpty) {
      return WeloCard(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            const Text('Hourly Breakdown', style: TextStyle(fontSize: 16, fontWeight: FontWeight.bold)),
            const SizedBox(height: 16),
            Center(
              child: Text('No orders today yet',
                style: TextStyle(color: WeloColors.textBody)),
            ),
          ],
        ),
      );
    }

                double maxRevenue = 0;
    for (final h in hourly) {
      final rev = double.tryParse((h['revenue'] ?? 0)?.toString() ?? '0') ?? 0.0;
      if (rev > maxRevenue) { maxRevenue = rev; }
    }

    return WeloCard(
      padding: const EdgeInsets.all(16),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const Text('Hourly Breakdown', style: TextStyle(fontSize: 16, fontWeight: FontWeight.bold)),
          const SizedBox(height: 16),
          SizedBox(
            height: 100,
            child: Row(
              crossAxisAlignment: CrossAxisAlignment.end,
              children: hourly.map((h) {
                final rev = double.tryParse((h['revenue'] ?? 0)?.toString() ?? '0') ?? 0.0;
                final heightRatio = maxRevenue > 0 ? (rev / maxRevenue) : 0.0;
                final hour = h['hour']?.toString() ?? '';
                return Expanded(
                  child: Padding(
                    padding: const EdgeInsets.symmetric(horizontal: 1),
                    child: Column(
                      mainAxisAlignment: MainAxisAlignment.end,
                      children: [
                        Container(
                          height: (80 * heightRatio.clamp(0.05, 1.0)).toDouble(),
                          decoration: BoxDecoration(
                            color: heightRatio > 0.5
                                ? WeloColors.sovereignOrange
                                : WeloColors.sovereignOrange.withValues(alpha: 0.392),
                            borderRadius: BorderRadius.circular(4),
                          ),
                        ),
                        const SizedBox(height: 4),
                        Text(hour.length >= 2 ? hour.substring(0, 2) : hour,
                          style: const TextStyle(fontSize: 8)),
                      ],
                    ),
                  ),
                );
              }).toList(),
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildTopItems(List<dynamic> topItems) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        const Text('Top Items Today', style: TextStyle(fontSize: 16, fontWeight: FontWeight.bold)),
        const SizedBox(height: 12),
        ...topItems.map((item) {
          final name = item['name']?.toString() ?? 'Item';
          final qty = int.tryParse((item['quantity'] ?? 0)?.toString() ?? '0') ?? 0;
          final rev = double.tryParse((item['revenue'] ?? 0)?.toString() ?? '0') ?? 0.0;
          return WeloCard(
            margin: const EdgeInsets.only(bottom: 8),
            padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
            child: Row(
              children: [
                Expanded(child: Text(name, style: const TextStyle(fontWeight: FontWeight.w600))),
                Text('$qty sold', style: const TextStyle(color: WeloColors.textBody, fontSize: 13)),
                const SizedBox(width: 16),
                Text('₹${rev.toInt()}', style: const TextStyle(
                  fontWeight: FontWeight.bold, color: WeloColors.sovereignOrange)),
              ],
            ),
          );
        }),
      ],
    );
  }

  Widget _buildDailyTrend(List<dynamic> dailyTrend) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        const Text('Daily Trend (7 days)', style: TextStyle(fontSize: 16, fontWeight: FontWeight.bold)),
        const SizedBox(height: 12),
        ...dailyTrend.take(7).map((d) {
          final date = d['date']?.toString() ?? '';
          final orders = int.tryParse((d['orders'] ?? 0)?.toString() ?? '0') ?? 0;
          final rev = double.tryParse((d['revenue'] ?? 0)?.toString() ?? '0') ?? 0.0;
          return Padding(
            padding: const EdgeInsets.symmetric(vertical: 4),
            child: Row(
              children: [
                SizedBox(
                  width: 80,
                  child: Text(date, style: const TextStyle(color: WeloColors.textBody, fontSize: 13)),
                ),
                Expanded(
                  child: Text('$orders orders', style: const TextStyle(fontWeight: FontWeight.w600)),
                ),
                Text('₹${rev.toInt()}', style: const TextStyle(
                  fontWeight: FontWeight.bold, color: WeloColors.sovereignOrange)),
              ],
            ),
          );
        }),
      ],
    );
  }
}
