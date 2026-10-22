import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:ui/ui.dart';
import 'package:core/core.dart';
import 'dart:io';
import 'package:path_provider/path_provider.dart';
import 'package:open_filex/open_filex.dart';

class ReportingScreen extends ConsumerStatefulWidget {
  const ReportingScreen({super.key});

  @override
  ConsumerState<ReportingScreen> createState() => _ReportingScreenState();
}

class _ReportingScreenState extends ConsumerState<ReportingScreen> {
  String _activeTab = 'live';
  bool _isLoading = true;
  bool _isExporting = false;
  String? _error;

  // Live Tracking
  List<dynamic> _liveData = [];

  // Business Reports
  String _granularity = 'daily';
  String _reportStart = '';
  String _reportEnd = '';
  List<dynamic> _trendRows = [];
  List<dynamic> _trendPeriods = [];

  @override
  void initState() {
    super.initState();
    final now = DateTime.now();
    final start = now.subtract(const Duration(days: 30));
    _reportStart = '${start.year}-${start.month.toString().padLeft(2, '0')}-${start.day.toString().padLeft(2, '0')}';
    _reportEnd = '${now.year}-${now.month.toString().padLeft(2, '0')}-${now.day.toString().padLeft(2, '0')}';
    _loadLiveTracking();
  }

  Future<void> _loadLiveTracking() async {
    setState(() { _isLoading = true; _error = null; });
    try {
      final repo = ref.read(vendorRepositoryProvider);
      final data = await repo.getLiveTracking();
      if (mounted) {
        setState(() {
          _liveData = (data['data'] as List?) ?? [];
          _isLoading = false;
        });
      }
    } catch (e) {
      if (mounted) setState(() { _error = e.toString(); _isLoading = false; });
    }
  }

  Future<void> _loadTrendTable() async {
    setState(() { _isLoading = true; _error = null; });
    try {
      final repo = ref.read(vendorRepositoryProvider);
      final data = await repo.getTrendTable(
        granularity: _granularity,
        start: _reportStart,
        end: _reportEnd,
      );
      if (mounted) {
        setState(() {
          _trendPeriods = (data['periods'] as List?) ?? [];
          _trendRows = (data['rows'] as List?) ?? [];
          _isLoading = false;
        });
      }
    } catch (e) {
      if (mounted) setState(() { _error = e.toString(); _isLoading = false; });
    }
  }

  Future<void> _exportReport(String type) async {
    setState(() => _isExporting = true);
    try {
      final repo = ref.read(vendorRepositoryProvider);
      final bytes = await repo.downloadReport(
        type: type,
        granularity: _granularity,
        start: _reportStart,
        end: _reportEnd,
      );
      if (!mounted) return;
      final dir = await getExternalStorageDirectory();
      final folder = Directory('${dir!.path}/UtterLazy');
      if (!await folder.exists()) await folder.create(recursive: true);
      final file = File('${folder.path}/${type}_report.csv');
      await file.writeAsBytes(bytes);
      if (!mounted) return;
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text('Report saved: ${file.path.split('/').last}'),
            action: SnackBarAction(label: 'Open', onPressed: () => OpenFilex.open(file.path)),
          ),
        );
      }
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text('Export failed: $e')));
      }
    } finally {
      if (mounted) setState(() => _isExporting = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: WeloAppBar(title: 'Reporting'),
      body: Column(
        children: [
          // Tabs
          Row(
            children: [
              _buildTab('live', 'Live Tracking'),
              _buildTab('reports', 'Business Reports'),
            ],
          ),
          const Divider(height: 1),

          Expanded(
            child: _isLoading
                ? const Center(child: CircularProgressIndicator())
                : _error != null
                    ? Center(child: Text(_error!, style: TextStyle(color: WeloColors.error)))
                    : _activeTab == 'live'
                        ? _buildLiveTracking()
                        : _buildBusinessReports(),
          ),
        ],
      ),
    );
  }

  Widget _buildTab(String key, String label) {
    final isActive = _activeTab == key;
    return Expanded(
      child: GestureDetector(
        onTap: () {
          setState(() => _activeTab = key);
          if (key == 'live') _loadLiveTracking();
          else _loadTrendTable();
        },
        child: Container(
          padding: const EdgeInsets.symmetric(vertical: 14),
          decoration: BoxDecoration(
            border: Border(
              bottom: BorderSide(
                color: isActive ? WeloColors.sovereignOrange : Colors.transparent,
                width: 2,
              ),
            ),
          ),
          child: Text(
            label,
            textAlign: TextAlign.center,
            style: TextStyle(
              color: isActive ? WeloColors.sovereignOrange : WeloColors.textBody,
              fontWeight: FontWeight.w700,
              fontSize: 13,
              letterSpacing: 0.5,
            ),
          ),
        ),
      ),
    );
  }

  // ════════ LIVE TRACKING ════════

  Widget _buildLiveTracking() {
    if (_liveData.isEmpty) {
      return const Center(child: Text('No data yet. Run aggregation first.', style: TextStyle(color: WeloColors.textBody)));
    }
    return RefreshIndicator(
      onRefresh: _loadLiveTracking,
      child: ListView.builder(
        padding: const EdgeInsets.all(12),
        itemCount: _liveData.length,
        itemBuilder: (ctx, i) => _buildSection(_liveData[i]),
      ),
    );
  }

  Widget _buildSection(dynamic section) {
    final category = section['category'] as String;
    final metrics = section['metrics'] as List;

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Padding(
          padding: const EdgeInsets.symmetric(vertical: 12, horizontal: 4),
          child: Text(category, style: const TextStyle(color: WeloColors.textDark, fontWeight: FontWeight.w700, fontSize: 16)),
        ),
        if (category == 'Dish trends')
          ...metrics.map((m) => _buildDishCard(m))
        else
          ...metrics.map((m) => _buildMetricCard(m)),
        const SizedBox(height: 8),
      ],
    );
  }

  Widget _buildMetricCard(dynamic metric) {
    final name = metric['name'] as String;
    final dataPoints = (metric['dataPoints'] as List?) ?? [];
    final trend = metric['trend'] as String? ?? 'flat';
    final lastValue = dataPoints.isNotEmpty ? (dataPoints.last['value'] as num?)?.toDouble() ?? 0.0 : 0.0;
    final values = dataPoints.map((dp) => (dp['value'] as num?)?.toDouble() ?? 0.0).toList();

    return Container(
      margin: const EdgeInsets.only(bottom: 8),
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        color: WeloColors.surfaceLight,
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: WeloColors.cardBorder),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              Text(name, style: TextStyle(color: WeloColors.textBody, fontSize: 11, fontWeight: FontWeight.w600)),
              _trendIcon(trend),
            ],
          ),
          const SizedBox(height: 4),
          Text(_formatValue(name, lastValue), style: const TextStyle(color: WeloColors.textDark, fontSize: 22, fontWeight: FontWeight.w900)),
          const SizedBox(height: 8),
          SizedBox(
            height: 40,
            child: _buildSparkline(values, trend),
          ),
          if (dataPoints.length >= 2)
            Padding(
              padding: const EdgeInsets.only(top: 4),
              child: Row(
                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                children: [
                  Text(_shortDate(dataPoints.first['date'] as String), style: TextStyle(color: WeloColors.textBody.withValues(alpha: 0.5), fontSize: 9)),
                  Text(_shortDate(dataPoints.last['date'] as String), style: TextStyle(color: WeloColors.textBody.withValues(alpha: 0.5), fontSize: 9)),
                ],
              ),
            ),
        ],
      ),
    );
  }

  Widget _buildDishCard(dynamic dish) {
    final name = dish['name'] as String;
    final dataPoints = (dish['dataPoints'] as List?) ?? [];
    final trend = dish['trend'] as String? ?? 'flat';
    final lastOrders = dataPoints.isNotEmpty ? (dataPoints.last['orders'] as num?)?.toInt() ?? 0 : 0;
    final lastRevenue = dataPoints.isNotEmpty ? (dataPoints.last['revenue'] as num?)?.toDouble() ?? 0 : 0;
    final values = dataPoints.map((dp) => (dp['orders'] as num?)?.toDouble() ?? 0).toList();

    return Container(
      margin: const EdgeInsets.only(bottom: 8),
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        color: WeloColors.surfaceLight,
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: WeloColors.cardBorder),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              Text(name, style: const TextStyle(color: WeloColors.textDark, fontWeight: FontWeight.w700, fontSize: 14)),
              _trendIcon(trend),
            ],
          ),
          const SizedBox(height: 4),
          Row(
            children: [
              Text('Orders: $lastOrders', style: const TextStyle(color: WeloColors.textBody, fontSize: 12)),
              const SizedBox(width: 16),
              Text('Revenue: ₹$lastRevenue', style: const TextStyle(color: WeloColors.textBody, fontSize: 12)),
            ],
          ),
          const SizedBox(height: 8),
          SizedBox(height: 32, child: _buildSparkline(values, trend)),
        ],
      ),
    );
  }

  Widget _trendIcon(String trend) {
    return Icon(
      trend == 'up' ? Icons.trending_up : trend == 'down' ? Icons.trending_down : Icons.trending_flat,
      color: trend == 'up' ? Colors.green : trend == 'down' ? Colors.red : Colors.grey,
      size: 18,
    );
  }

  Widget _buildSparkline(List<double> values, String trend) {
    if (values.isEmpty) return const SizedBox();
    final max = values.reduce((a, b) => a > b ? a : b);
    if (max == 0) return const SizedBox();
    return CustomPaint(
      size: const Size(double.infinity, 40),
      painter: _SparklinePainter(values, trend == 'up' ? Colors.green : trend == 'down' ? Colors.red : Colors.grey),
    );
  }

  String _formatValue(String name, double value) {
    if (name.contains('Rs') || name.contains('spend') || name.contains('Sales')) return '₹${value.toStringAsFixed(2)}';
    if (name.contains('%') || name.contains('rating')) return value.toStringAsFixed(1);
    return value.toInt().toString();
  }

  String _shortDate(String date) {
    if (date.length < 10) return date;
    final parts = date.split('-');
    if (parts.length < 3) return date;
    const months = ['', 'Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
    final m = int.tryParse(parts[1]) ?? 0;
    return '${parts[2]} ${months[m]}';
  }

  // ════════ BUSINESS REPORTS ════════

  Widget _buildBusinessReports() {
    return Column(
      children: [
        // Filters
        Padding(
          padding: const EdgeInsets.all(12),
          child: Row(
            children: [
              Expanded(
                child: _buildDropdown(_granularity, ['daily', 'weekly', 'monthly'], (v) {
                  setState(() => _granularity = v!);
                  _loadTrendTable();
                }),
              ),
              const SizedBox(width: 8),
              Expanded(child: _buildDateField(_reportStart, (d) => setState(() => _reportStart = d))),
              const SizedBox(width: 4),
              const Text('→', style: TextStyle(color: WeloColors.textBody)),
              const SizedBox(width: 4),
              Expanded(child: _buildDateField(_reportEnd, (d) => setState(() => _reportEnd = d))),
            ],
          ),
        ),

        // Export buttons
        Padding(
          padding: const EdgeInsets.symmetric(horizontal: 12),
          child: Row(
            children: [
              Expanded(
                child: _buildExportBtn('detailed', 'Detailed Report'),
              ),
              const SizedBox(width: 8),
              Expanded(
                child: _buildExportBtn('item_sales', 'Item Sales'),
              ),
            ],
          ),
        ),

        const SizedBox(height: 12),

        // Trend table
        Expanded(
          child: _trendPeriods.isEmpty
              ? const Center(child: Text('No data', style: TextStyle(color: WeloColors.textBody)))
              : RefreshIndicator(
                  onRefresh: _loadTrendTable,
                  child: SingleChildScrollView(
                    scrollDirection: Axis.horizontal,
                    child: DataTable(
                      columnSpacing: 16,
                      headingRowColor: WidgetStateProperty.all(WeloColors.surfaceLight),
                      columns: [
                        const DataColumn(label: Text('Metric', style: TextStyle(fontWeight: FontWeight.w700, fontSize: 12))),
                        ..._trendPeriods.map((p) => DataColumn(
                          label: Text(p['label'] as String? ?? '', style: const TextStyle(fontWeight: FontWeight.w700, fontSize: 11)),
                          numeric: true,
                        )),
                      ],
                      rows: _trendRows.map((row) {
                        final values = (row['values'] as List?) ?? [];
                        return DataRow(cells: [
                          DataCell(Text('${row['metric']}', style: const TextStyle(fontSize: 12))),
                          ...values.asMap().entries.map((e) => DataCell(
                            Text(_formatCellVal(e.value), style: const TextStyle(fontSize: 11), textAlign: TextAlign.right),
                          )),
                        ]);
                      }).toList(),
                    ),
                  ),
                ),
        ),
      ],
    );
  }

  String _formatCellVal(dynamic val) {
    if (val == null || val == 0) return '0';
    final n = double.tryParse(val.toString()) ?? 0;
    if (n >= 1000) return '${(n / 1000).toStringAsFixed(1)}K';
    return n.toStringAsFixed(1);
  }

  Widget _buildDropdown(String value, List<String> items, ValueChanged<String?> onChanged) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 8),
      decoration: BoxDecoration(
        color: WeloColors.surfaceLight,
        borderRadius: BorderRadius.circular(8),
        border: Border.all(color: WeloColors.cardBorder),
      ),
      child: DropdownButtonHideUnderline(
        child: DropdownButton<String>(
          value: value,
          isExpanded: true,
          dropdownColor: WeloColors.surfaceLight,
          items: items.map((i) => DropdownMenuItem(value: i, child: Text(i[0].toUpperCase() + i.substring(1), style: const TextStyle(fontSize: 13)))).toList(),
          onChanged: onChanged,
        ),
      ),
    );
  }

  Widget _buildDateField(String value, ValueChanged<String> onChanged) {
    return GestureDetector(
      onTap: () async {
        final picked = await showDatePicker(
          context: context,
          initialDate: DateTime.tryParse(value) ?? DateTime.now(),
          firstDate: DateTime(2024),
          lastDate: DateTime.now(),
        );
        if (picked != null) {
          onChanged('${picked.year}-${picked.month.toString().padLeft(2, '0')}-${picked.day.toString().padLeft(2, '0')}');
          _loadTrendTable();
        }
      },
      child: Container(
        padding: const EdgeInsets.symmetric(vertical: 10, horizontal: 8),
        decoration: BoxDecoration(
          color: WeloColors.surfaceLight,
          borderRadius: BorderRadius.circular(8),
          border: Border.all(color: WeloColors.cardBorder),
        ),
        child: Text(value, style: const TextStyle(fontSize: 11, color: WeloColors.textDark)),
      ),
    );
  }

  Widget _buildExportBtn(String type, String label) {
    return SizedBox(
      height: 38,
      child: TextButton(
        style: TextButton.styleFrom(
          backgroundColor: WeloColors.sovereignOrange,
          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(8)),
        ),
        onPressed: _isExporting ? null : () => _exportReport(type),
        child: _isExporting
            ? const SizedBox(width: 16, height: 16, child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white))
            : Text(label, style: const TextStyle(color: Colors.white, fontWeight: FontWeight.w700, fontSize: 11)),
      ),
    );
  }
}

class _SparklinePainter extends CustomPainter {
  final List<double> values;
  final Color color;

  _SparklinePainter(this.values, this.color);

  @override
  void paint(Canvas canvas, Size size) {
    if (values.isEmpty) return;
    final max = values.reduce((a, b) => a > b ? a : b);
    if (max == 0) return;
    final paint = Paint()
      ..color = color
      ..strokeWidth = 1.5
      ..style = PaintingStyle.stroke
      ..strokeCap = StrokeCap.round;

    final path = Path();
    final h = size.height;
    final w = size.width;

    for (var i = 0; i < values.length; i++) {
      final x = (i / (values.length - 1)) * w;
      final y = h - (values[i] / max) * h;
      if (i == 0) path.moveTo(x, y);
      else path.lineTo(x, y);
    }
    canvas.drawPath(path, paint);
  }

  @override
  bool shouldRepaint(covariant _SparklinePainter old) => old.values != values;
}
