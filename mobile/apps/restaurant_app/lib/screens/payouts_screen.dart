import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:ui/ui.dart';
import 'package:core/core.dart';

class PayoutsScreen extends ConsumerStatefulWidget {
  const PayoutsScreen({super.key});

  @override
  ConsumerState<PayoutsScreen> createState() => _PayoutsScreenState();
}

class _PayoutsScreenState extends ConsumerState<PayoutsScreen> {
  bool _isLoading = true;
  List<dynamic> _cycles = [];
  double _currentBalance = 0;
  int _page = 1;
  bool _hasMore = true;
  String? _statusFilter;

  @override
  void initState() {
    super.initState();
    _loadCycles();
  }

  Future<void> _loadCycles() async {
    setState(() => _isLoading = _page == 1);
    try {
      final repo = ref.read(vendorRepositoryProvider);
      final data = await repo.getPayoutCycles(page: _page, status: _statusFilter);
      final cycles = data['cycles']?['data'] ?? data['cycles'] ?? [];
      final balance = double.tryParse(data['current_balance']?.toString() ?? '0') ?? 0;
      if (mounted) {
        setState(() {
          if (_page == 1) {
            _cycles = cycles is List ? cycles : [];
          } else {
            _cycles.addAll(cycles is List ? cycles : []);
          }
          _currentBalance = balance;
          _hasMore = (cycles is List ? cycles.length : 0) >= 20;
          _isLoading = false;
        });
      }
    } catch (e) {
      if (mounted) setState(() => _isLoading = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: const WeloAppBar(title: 'Payouts'),
      body: _isLoading
          ? const Center(child: CircularProgressIndicator(color: WeloColors.sovereignOrange))
          : RefreshIndicator(
              onRefresh: () async { _page = 1; await _loadCycles(); },
              color: WeloColors.sovereignOrange,
              child: CustomScrollView(
                slivers: [
                  // Balance card
                  SliverToBoxAdapter(
                    child: Padding(
                      padding: const EdgeInsets.fromLTRB(16, 16, 16, 8),
                      child: WeloCard(
                        padding: const EdgeInsets.all(20),
                        child: Column(
                          children: [
                            const Text('Running Balance', style: TextStyle(color: WeloColors.textBody, fontSize: 13)),
                            const SizedBox(height: 8),
                            Text(
                              '₹${_currentBalance.toStringAsFixed(2)}',
                              style: const TextStyle(
                                fontSize: 32, fontWeight: FontWeight.bold, color: WeloColors.sovereignOrange,
                              ),
                            ),
                          ],
                        ),
                      ),
                    ),
                  ),
                  // Filter chips
                  SliverToBoxAdapter(
                    child: Padding(
                      padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
                      child: Row(
                        children: [
                          _buildFilterChip('All', null),
                          const SizedBox(width: 8),
                          _buildFilterChip('Generated', 'generated'),
                          const SizedBox(width: 8),
                          _buildFilterChip('Paid', 'paid'),
                        ],
                      ),
                    ),
                  ),
                  // Cycles list
                  if (_cycles.isEmpty)
                    const SliverFillRemaining(
                      child: Center(
                        child: Column(
                          mainAxisSize: MainAxisSize.min,
                          children: [
                            Icon(Icons.account_balance_wallet_outlined, size: 64, color: WeloColors.textLight),
                            SizedBox(height: 12),
                            Text('No payout cycles yet', style: TextStyle(color: WeloColors.textBody)),
                          ],
                        ),
                      ),
                    )
                  else
                    SliverList(
                      delegate: SliverChildBuilderDelegate(
                        (context, index) {
                          if (index >= _cycles.length) {
                            return null;
                          }
                          return _buildCycleCard(_cycles[index]);
                        },
                        childCount: _cycles.length,
                      ),
                    ),
                  if (_hasMore)
                    SliverToBoxAdapter(
                      child: Padding(
                        padding: const EdgeInsets.all(16),
                        child: Center(
                          child: TextButton(
                            onPressed: () { _page++; _loadCycles(); },
                            child: const Text('Load More'),
                          ),
                        ),
                      ),
                    ),
                ],
              ),
            ),
    );
  }

  Widget _buildFilterChip(String label, String? value) {
    final isSelected = _statusFilter == value;
    return GestureDetector(
      onTap: () {
        setState(() => _statusFilter = value);
        _page = 1;
        _loadCycles();
      },
      child: Container(
        padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 6),
        decoration: BoxDecoration(
          color: isSelected ? WeloColors.sovereignOrange : Colors.grey.shade200,
          borderRadius: BorderRadius.circular(20),
        ),
        child: Text(
          label,
          style: TextStyle(
            fontSize: 12, fontWeight: FontWeight.bold,
            color: isSelected ? Colors.white : Colors.black54,
          ),
        ),
      ),
    );
  }

  Widget _buildCycleCard(dynamic c) {
    final start = (c['cycle_start']?.toString() ?? '').substring(0, 10);
    final end = (c['cycle_end']?.toString() ?? '').substring(0, 10);
    final gross = double.tryParse(c['gross_order_value']?.toString() ?? '0') ?? 0;
    final deductions = double.tryParse(c['total_deductions']?.toString() ?? '0') ?? 0;
    final additions = double.tryParse(c['total_additions']?.toString() ?? '0') ?? 0;
    final net = double.tryParse(c['net_payable']?.toString() ?? '0') ?? 0;
    final status = c['status']?.toString() ?? '';
    final utr = c['utr_number']?.toString() ?? '';

    return Padding(
      padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 4),
      child: WeloCard(
        padding: const EdgeInsets.all(14),
        child: InkWell(
          borderRadius: BorderRadius.circular(16),
          onTap: () => _showCycleDetail(c),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Row(
                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                children: [
                  Text('$start → $end', style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 13)),
                  Container(
                    padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
                    decoration: BoxDecoration(
                      color: status == 'paid' ? Colors.green.shade100 : Colors.amber.shade100,
                      borderRadius: BorderRadius.circular(10),
                    ),
                    child: Text(
                      status.toUpperCase(),
                      style: TextStyle(
                        fontSize: 9, fontWeight: FontWeight.bold,
                        color: status == 'paid' ? Colors.green.shade800 : Colors.amber.shade800,
                      ),
                    ),
                  ),
                ],
              ),
              const SizedBox(height: 10),
              Row(
                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                children: [
                  _buildAmountCol('Gross', '₹${gross.toStringAsFixed(0)}', WeloColors.textBody),
                  _buildAmountCol('Deductions', '-₹${deductions.toStringAsFixed(0)}', WeloColors.error),
                  _buildAmountCol('Additions', '+₹${additions.toStringAsFixed(0)}', WeloColors.success),
                  _buildAmountCol('Net', '₹${net.toStringAsFixed(0)}', WeloColors.sovereignOrange),
                ],
              ),
              if (utr.isNotEmpty) ...[
                const SizedBox(height: 8),
                Text('UTR: $utr', style: const TextStyle(color: WeloColors.textLight, fontSize: 11)),
              ],
            ],
          ),
        ),
      ),
    );
  }

  Widget _buildAmountCol(String label, String value, Color color) {
    return Column(
      children: [
        Text(value, style: TextStyle(fontWeight: FontWeight.bold, fontSize: 14, color: color)),
        const SizedBox(height: 2),
        Text(label, style: const TextStyle(color: WeloColors.textBody, fontSize: 10)),
      ],
    );
  }

  void _showCycleDetail(dynamic c) {
    showModalBottomSheet(
      context: context,
      isScrollControlled: true,
      shape: const RoundedRectangleBorder(
        borderRadius: BorderRadius.vertical(top: Radius.circular(24)),
      ),
      builder: (ctx) => _CycleDetailSheet(cycle: c),
    );
  }
}

class _CycleDetailSheet extends ConsumerStatefulWidget {
  final dynamic cycle;
  const _CycleDetailSheet({required this.cycle});

  @override
  ConsumerState<_CycleDetailSheet> createState() => _CycleDetailSheetState();
}

class _CycleDetailSheetState extends ConsumerState<_CycleDetailSheet> {
  bool _isLoading = true;
  Map<String, dynamic>? _detail;

  @override
  void initState() {
    super.initState();
    _loadDetail();
  }

  Future<void> _loadDetail() async {
    try {
      final repo = ref.read(vendorRepositoryProvider);
      final data = await repo.getPayoutCycleDetail(widget.cycle['id'].toString());
      if (mounted) {
        setState(() {
          _detail = data['cycle'] is Map ? Map<String, dynamic>.from(data['cycle']) : null;
          _isLoading = false;
        });
      }
    } catch (e) {
      if (mounted) setState(() { _detail = null; _isLoading = false; });
    }
  }

  @override
  Widget build(BuildContext context) {
    final c = _detail ?? widget.cycle;
    final start = (c['cycle_start'] as String?)?.substring(0, 10) ?? '';
    final end = (c['cycle_end'] as String?)?.substring(0, 10) ?? '';
    final gross = double.tryParse(c['gross_order_value']?.toString() ?? '0') ?? 0;
    final deductions = double.tryParse(c['total_deductions']?.toString() ?? '0') ?? 0;
    final additions = double.tryParse(c['total_additions']?.toString() ?? '0') ?? 0;
    final net = double.tryParse(c['net_payable']?.toString() ?? '0') ?? 0;
    final status = c['status']?.toString() ?? '';
    final utr = c['utr_number']?.toString() ?? '';
    final orderLines = (c['order_lines'] as List?) ?? [];
    final dedList = (c['deductions'] as List?) ?? [];
    final addList = (c['additions'] as List?) ?? [];

    return DraggableScrollableSheet(
      initialChildSize: 0.85,
      minChildSize: 0.5,
      maxChildSize: 0.95,
      expand: false,
      builder: (ctx, scrollCtrl) => Container(
        decoration: const BoxDecoration(
          color: Colors.white,
          borderRadius: BorderRadius.vertical(top: Radius.circular(24)),
        ),
        child: _isLoading
            ? const Center(child: CircularProgressIndicator(color: WeloColors.sovereignOrange))
            : ListView(
                controller: scrollCtrl,
                padding: const EdgeInsets.all(24),
                children: [
                  Row(
                    mainAxisAlignment: MainAxisAlignment.spaceBetween,
                    children: [
                      Text('$start → $end', style: const TextStyle(fontSize: 16, fontWeight: FontWeight.bold)),
                      IconButton(
                        icon: const Icon(Icons.close),
                        onPressed: () => Navigator.pop(ctx),
                      ),
                    ],
                  ),
                  const SizedBox(height: 4),
                  Container(
                    padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
                    decoration: BoxDecoration(
                      color: status == 'paid' ? Colors.green.shade100 : Colors.amber.shade100,
                      borderRadius: BorderRadius.circular(10),
                    ),
                    child: Text(status.toUpperCase(), style: TextStyle(fontSize: 10, fontWeight: FontWeight.bold, color: status == 'paid' ? Colors.green.shade800 : Colors.amber.shade800)),
                  ),
                  const SizedBox(height: 16),
                  // Summary grid
                  Row(
                    children: [
                      _detailCard('Gross', '₹${gross.toStringAsFixed(0)}', WeloColors.textBody),
                      _detailCard('Deductions', '-₹${deductions.toStringAsFixed(0)}', WeloColors.error),
                      _detailCard('Additions', '+₹${additions.toStringAsFixed(0)}', WeloColors.success),
                      _detailCard('Net', '₹${net.toStringAsFixed(0)}', WeloColors.sovereignOrange),
                    ],
                  ),
                  if (utr.isNotEmpty) ...[
                    const SizedBox(height: 12),
                    Card(
                      color: Colors.green.shade50,
                      child: Padding(
                        padding: const EdgeInsets.all(12),
                        child: Row(
                          children: [
                            const Icon(Icons.check_circle, color: Colors.green, size: 20),
                            const SizedBox(width: 8),
                            Text('UTR: $utr', style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 13)),
                          ],
                        ),
                      ),
                    ),
                  ],
                  const SizedBox(height: 20),
                  if (orderLines.isNotEmpty) ...[
                    const Text('Order Lines', style: TextStyle(fontWeight: FontWeight.bold, fontSize: 14)),
                    const SizedBox(height: 8),
                    ...orderLines.map((ol) => Card(
                      margin: const EdgeInsets.only(bottom: 6),
                      child: Padding(
                        padding: const EdgeInsets.all(10),
                        child: Row(
                          children: [
                            Expanded(
                              child: Column(
                                crossAxisAlignment: CrossAxisAlignment.start,
                                children: [
                                  Text('Order #${(ol['order_id'] as String?)?.substring(0, 8)}',
                                    style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 12)),
                                  Text('₹${ol['order_value']} → ₹${ol['net_settlement']}',
                                    style: const TextStyle(color: WeloColors.textBody, fontSize: 11)),
                                ],
                              ),
                            ),
                            Text(ol['order_status'] ?? '',
                              style: const TextStyle(fontSize: 10, color: WeloColors.textLight)),
                          ],
                        ),
                      ),
                    )),
                  ],
                  if (dedList.isNotEmpty) ...[
                    const SizedBox(height: 16),
                    const Text('Deductions', style: TextStyle(fontWeight: FontWeight.bold, fontSize: 14, color: WeloColors.error)),
                    const SizedBox(height: 8),
                    ...dedList.map((d) => Padding(
                      padding: const EdgeInsets.symmetric(vertical: 4),
                      child: Row(
                        mainAxisAlignment: MainAxisAlignment.spaceBetween,
                        children: [
                          Expanded(child: Text(d['type']?.toString() ?? '', style: const TextStyle(fontSize: 13))),
                          Text('-₹${d['amount']}', style: const TextStyle(fontWeight: FontWeight.bold, color: WeloColors.error)),
                        ],
                      ),
                    )),
                  ],
                  if (addList.isNotEmpty) ...[
                    const SizedBox(height: 16),
                    const Text('Additions', style: TextStyle(fontWeight: FontWeight.bold, fontSize: 14, color: WeloColors.success)),
                    const SizedBox(height: 8),
                    ...addList.map((a) => Padding(
                      padding: const EdgeInsets.symmetric(vertical: 4),
                      child: Row(
                        mainAxisAlignment: MainAxisAlignment.spaceBetween,
                        children: [
                          Expanded(child: Text(a['type']?.toString() ?? '', style: const TextStyle(fontSize: 13))),
                          Text('+₹${a['amount']}', style: const TextStyle(fontWeight: FontWeight.bold, color: WeloColors.success)),
                        ],
                      ),
                    )),
                  ],
                ],
              ),
      ),
    );
  }

  Widget _detailCard(String label, String value, Color color) {
    return Expanded(
      child: Card(
        child: Padding(
          padding: const EdgeInsets.all(8),
          child: Column(
            children: [
              Text(value, style: TextStyle(fontWeight: FontWeight.bold, fontSize: 13, color: color)),
              const SizedBox(height: 2),
              Text(label, style: const TextStyle(color: WeloColors.textBody, fontSize: 9)),
            ],
          ),
        ),
      ),
    );
  }
}
