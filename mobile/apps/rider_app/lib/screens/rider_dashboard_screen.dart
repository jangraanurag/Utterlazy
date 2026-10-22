import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:api/api.dart';
import 'package:ui/ui.dart';
import 'package:core/core.dart';

class RiderDashboardScreen extends ConsumerStatefulWidget {
  const RiderDashboardScreen({super.key});

  @override
  ConsumerState<RiderDashboardScreen> createState() => _RiderDashboardScreenState();
}

class _RiderDashboardScreenState extends ConsumerState<RiderDashboardScreen> {
  Map<String, dynamic>? _data;
  bool _isLoading = true;
  String _period = 'week';

  @override
  void initState() {
    super.initState();
    _loadDashboard();
  }

  Future<void> _loadDashboard() async {
    setState(() => _isLoading = true);
    try {
      final repo = DeliveryRepository(ref.read(apiClientProvider));
      _data = await repo.getDashboard(period: _period);
    } catch (e) {
      // fallback
    } finally {
      if (mounted) setState(() => _isLoading = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: Text('Dashboard', style: GoogleFonts.spaceGrotesk(fontWeight: FontWeight.bold)),
        backgroundColor: WeloColors.sovereignOrange,
        foregroundColor: Colors.white,
        actions: [
          PopupMenuButton<String>(
            icon: const Icon(Icons.date_range, color: Colors.white),
            onSelected: (p) {
              _period = p;
              _loadDashboard();
            },
            itemBuilder: (_) => [
              const PopupMenuItem(value: 'week', child: Text('This Week')),
              const PopupMenuItem(value: 'month', child: Text('This Month')),
            ],
          ),
        ],
      ),
      body: _isLoading
          ? const Center(child: CircularProgressIndicator(color: WeloColors.sovereignOrange))
          : _data == null
              ? const Center(child: Text('Failed to load dashboard'))
              : RefreshIndicator(
                  onRefresh: _loadDashboard,
                  child: ListView(
                    padding: const EdgeInsets.all(16),
                    children: [
                      _buildPeriodInfo(),
                      const SizedBox(height: 16),
                      _buildMetricRow(),
                      const SizedBox(height: 24),
                      _buildTimeSlotChart(),
                      const SizedBox(height: 24),
                      _buildTopRestaurants(),
                    ],
                  ),
                ),
    );
  }

  Widget _buildPeriodInfo() {
    final current = _data!['current_period'] as Map<String, dynamic>;
    final comp = _data!['comparison'] as Map<String, dynamic>;
    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: WeloColors.sovereignOrange.withValues(alpha: 0.08),
        borderRadius: BorderRadius.circular(16),
      ),
      child: Column(
        children: [
          Text(
            '${current['start']} — ${current['end']}',
            style: GoogleFonts.spaceGrotesk(fontSize: 13, fontWeight: FontWeight.w600, color: Colors.grey[600]),
          ),
          const SizedBox(height: 4),
          Text(
            'vs ${comp['start']} — ${comp['end']}',
            style: TextStyle(fontSize: 11, color: Colors.grey[500]),
          ),
        ],
      ),
    );
  }

  Widget _buildMetricRow() {
    final current = _data!['current_period'] as Map<String, dynamic>;
    final comp = _data!['comparison'] as Map<String, dynamic>;
    return Row(
      children: [
        Expanded(child: _metricCard('Completed', '${current['completed_journeys']}', _deltaText(comp['completed_delta_pct']), Icons.check_circle_outline)),
        const SizedBox(width: 12),
        Expanded(child: _metricCard('Earnings', '₹${current['earnings']}', _deltaText(comp['earnings_delta_pct']), Icons.account_balance_wallet_outlined)),
        const SizedBox(width: 12),
        Expanded(child: _metricCard('Distance', '${current['total_distance_km']} km', _deltaText(comp['distance_delta_pct']), Icons.map_outlined)),
      ],
    );
  }

  Widget _metricCard(String label, String value, String delta, IconData icon) {
    final isPositive = delta.startsWith('+');
    return Container(
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: Colors.grey.shade200),
      ),
      child: Column(
        children: [
          Icon(icon, color: WeloColors.sovereignOrange, size: 24),
          const SizedBox(height: 8),
          Text(value, style: GoogleFonts.spaceGrotesk(fontSize: 18, fontWeight: FontWeight.w900)),
          const SizedBox(height: 4),
          Text(label, style: TextStyle(fontSize: 11, color: Colors.grey[600])),
          if (delta.isNotEmpty) ...[
            const SizedBox(height: 4),
            Text(delta, style: TextStyle(fontSize: 12, fontWeight: FontWeight.bold, color: isPositive ? Colors.green : Colors.red)),
          ],
        ],
      ),
    );
  }

  Widget _buildTimeSlotChart() {
    final slots = _data!['time_slot_analysis'] as Map<String, dynamic>;
    final maxVal = slots.values.map((e) => (e as int).toDouble()).reduce((a, b) => a > b ? a : b);
    final labels = slots.keys.toList();

    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: Colors.grey.shade200),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text('Time Slot Analysis', style: GoogleFonts.spaceGrotesk(fontSize: 15, fontWeight: FontWeight.bold)),
          const SizedBox(height: 16),
          SizedBox(
            height: 160,
            child: CustomPaint(
              size: Size(double.infinity, 160),
              painter: _BarChartPainter(labels, slots.values.map((e) => (e as int).toDouble()).toList(), maxVal),
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildTopRestaurants() {
    final restaurants = _data!['top_restaurants'] as List<dynamic>? ?? [];
    if (restaurants.isEmpty) return const SizedBox.shrink();

    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: Colors.grey.shade200),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text('Top Restaurants', style: GoogleFonts.spaceGrotesk(fontSize: 15, fontWeight: FontWeight.bold)),
          const SizedBox(height: 12),
          ...restaurants.take(5).map((r) => Padding(
            padding: const EdgeInsets.symmetric(vertical: 6),
            child: Row(
              children: [
                Icon(Icons.restaurant, color: WeloColors.sovereignOrange, size: 18),
                const SizedBox(width: 12),
                Expanded(child: Text(r['name'] ?? '', style: const TextStyle(fontWeight: FontWeight.w500))),
                Text('${r['order_count']} orders', style: TextStyle(color: Colors.grey[600], fontSize: 13)),
              ],
            ),
          )),
        ],
      ),
    );
  }

  String _deltaText(dynamic val) {
    if (val == null) return '';
    final v = (val is num) ? val.toDouble() : double.tryParse(val.toString()) ?? 0.0;
    if (v == 0) return '—';
    return '${v > 0 ? '+' : ''}${v.toStringAsFixed(1)}%';
  }
}

class _BarChartPainter extends CustomPainter {
  final List<String> labels;
  final List<double> values;
  final double maxVal;

  _BarChartPainter(this.labels, this.values, this.maxVal);

  @override
  void paint(Canvas canvas, Size size) {
    if (maxVal == 0) return;
    final barWidth = (size.width - 40) / labels.length;
    final paint = Paint()..color = WeloColors.sovereignOrange.withValues(alpha: 0.7);

    for (int i = 0; i < labels.length; i++) {
      final barH = (values[i] / maxVal) * (size.height - 30);
      final x = 20.0 + i * barWidth + 4;
      final y = size.height - 20 - barH;
      canvas.drawRRect(
        RRect.fromRectAndCorners(Rect.fromLTWH(x, y, barWidth - 8, barH), topLeft: Radius.circular(4), topRight: Radius.circular(4)),
        paint,
      );

      // Label
      final tp = TextPainter(
        text: TextSpan(text: labels[i], style: TextStyle(color: Colors.grey[600], fontSize: 9)),
        textDirection: TextDirection.ltr,
      )..layout(maxWidth: barWidth);
      tp.paint(canvas, Offset(x + (barWidth - 8 - tp.width) / 2, size.height - 16));
    }
  }

  @override
  bool shouldRepaint(covariant _BarChartPainter old) => old.values != values;
}
