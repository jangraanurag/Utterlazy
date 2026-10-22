import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:ui/ui.dart';
import 'package:core/core.dart';
import 'dart:io';
import 'package:path_provider/path_provider.dart';
import 'package:open_filex/open_filex.dart';

class EarningsScreen extends ConsumerStatefulWidget {
  const EarningsScreen({super.key});

  @override
  ConsumerState<EarningsScreen> createState() => _EarningsScreenState();
}

class _EarningsScreenState extends ConsumerState<EarningsScreen> {
  String _period = 'Today';
  bool _isLoading = true;
  bool _isDownloading = false;
  double _grossRevenue = 0.0;
  double _platformFee = 0.0;
  double _taxGst = 0.0;
  double _netSettlement = 0.0;

  @override
  void initState() {
    super.initState();
    _loadEarnings();
  }

  Future<void> _loadEarnings() async {
    setState(() => _isLoading = true);
    try {
      final vendorRepo = ref.read(vendorRepositoryProvider);
      final periodParam = _period == 'Today'
          ? 'today'
          : _period == 'This Week'
              ? 'week'
              : 'month';

      final data = await vendorRepo.getEarnings(periodParam);

      if (mounted) {
        setState(() {
          final double rev = double.tryParse((data['revenue'] ?? 0.0)?.toString() ?? '0') ?? 0.0;
          _grossRevenue = rev;
          _platformFee = rev * 0.05;
          _taxGst = rev * 0.02;
          _netSettlement = rev - _platformFee - _taxGst;
          _isLoading = false;
        });
      }
    } catch (e) {
      if (mounted) {
        setState(() => _isLoading = false);
      }
    }
  }

  Future<void> _exportStatement() async {
    setState(() => _isDownloading = true);
    try {
      final repo = ref.read(vendorRepositoryProvider);
      final now = DateTime.now();
      final bytes = await repo.downloadStatement(now.month, now.year);

      final dir = await getExternalStorageDirectory();
      final downloadDir = Directory('${dir?.path ?? '/storage/emulated/0/Download'}/UtterLazy');
      if (!await downloadDir.exists()) {
        await downloadDir.create(recursive: true);
      }

      final fileName = 'Statement_${now.year}_${now.month.toString().padLeft(2, '0')}.xlsx';
      final file = File('${downloadDir.path}/$fileName');
      await file.writeAsBytes(bytes);

      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text('Saved: $fileName'),
            action: SnackBarAction(
              label: 'Open',
              onPressed: () => OpenFilex.open(file.path),
            ),
            duration: const Duration(seconds: 8),
          ),
        );
      }
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text('Download failed: $e')),
        );
      }
    } finally {
      if (mounted) setState(() => _isDownloading = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: const WeloAppBar(title: 'Earnings'),
      body: _isLoading
          ? const Center(child: CircularProgressIndicator(color: WeloColors.sovereignOrange))
          : RefreshIndicator(
              onRefresh: _loadEarnings,
              color: WeloColors.sovereignOrange,
              child: SingleChildScrollView(
                physics: const AlwaysScrollableScrollPhysics(),
                padding: const EdgeInsets.all(16),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    // Period selector
                    Row(
                      children: ['Today', 'This Week', 'This Month'].map((p) {
                        final isSelected = p == _period;
                        return Padding(
                          padding: const EdgeInsets.only(right: 8),
                          child: WeloChip(
                            label: p,
                            isSelected: isSelected,
                            onTap: () {
                              setState(() => _period = p);
                              _loadEarnings();
                            },
                          ),
                        );
                      }).toList(),
                    ),
                    const SizedBox(height: 20),

                    // Revenue card
                    WeloCard(
                      padding: const EdgeInsets.all(20),
                      child: Column(
                        children: [
                          const Text('Gross Revenue', style: TextStyle(color: WeloColors.textBody)),
                          const SizedBox(height: 8),
                          Text(
                            '₹${_grossRevenue.toInt()}',
                            style: const TextStyle(
                              fontSize: 32,
                              fontWeight: FontWeight.bold,
                              color: WeloColors.sovereignOrange,
                            ),
                          ),
                          const SizedBox(height: 16),
                          Row(
                            mainAxisAlignment: MainAxisAlignment.spaceEvenly,
                            children: [
                              _buildRevenueItem('Platform Fee', '₹${_platformFee.toInt()}'),
                              _buildRevenueItem('Tax (GST)', '₹${_taxGst.toInt()}'),
                              _buildRevenueItem('Net Settlement', '₹${_netSettlement.toInt()}'),
                            ],
                          ),
                        ],
                      ),
                    ),
                    const SizedBox(height: 20),

                    // Tax summary
                    const Text('Tax Reconciliation', style: TextStyle(fontSize: 16, fontWeight: FontWeight.bold)),
                    const SizedBox(height: 10),
                    _buildTaxRow('Output GST', '₹${_taxGst.toStringAsFixed(2)}'),
                    _buildTaxRow('TCS U/S 52 (1%)', '₹${(_grossRevenue * 0.01).toStringAsFixed(2)}'),
                    _buildTaxRow('TDS U/S 194O (1%)', '₹${(_grossRevenue * 0.01).toStringAsFixed(2)}'),
                    _buildTaxRow('Total Taxable Value', '₹${_grossRevenue.toStringAsFixed(2)}'),
                    const SizedBox(height: 20),

                    // Settlement
                    const Text('Settlement', style: TextStyle(fontSize: 16, fontWeight: FontWeight.bold)),
                    const SizedBox(height: 10),
                    WeloCard(
                      padding: const EdgeInsets.all(14),
                      child: Column(
                        children: [
                          _buildSettlementRow('Bank Account', '****1234'),
                          _buildSettlementRow('Bank Name', 'HDFC Bank'),
                          _buildSettlementRow('IFSC', 'HDFC0001234'),
                          const SizedBox(height: 12),
                          SizedBox(
                            width: double.infinity,
                            child: OutlinedButton(
                              onPressed: _isDownloading ? null : _exportStatement,
                              child: _isDownloading
                                  ? const SizedBox(
                                      width: 18,
                                      height: 18,
                                      child: CircularProgressIndicator(strokeWidth: 2, color: WeloColors.sovereignOrange),
                                    )
                                  : const Text('Export Statement'),
                            ),
                          ),
                        ],
                      ),
                    ),
                  ],
                ),
              ),
            ),
    );
  }

  Widget _buildRevenueItem(String label, String value) {
    return Column(
      children: [
        Text(value, style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 15)),
        const SizedBox(height: 4),
        Text(label, style: const TextStyle(fontSize: 11, color: WeloColors.textBody)),
      ],
    );
  }

  Widget _buildTaxRow(String label, String value) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 6),
      child: Row(
        mainAxisAlignment: MainAxisAlignment.spaceBetween,
        children: [
          Text(label, style: const TextStyle(color: WeloColors.textBody, fontSize: 14)),
          Text(value, style: const TextStyle(fontWeight: FontWeight.w600, fontSize: 14)),
        ],
      ),
    );
  }

  Widget _buildSettlementRow(String label, String value) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 6),
      child: Row(
        mainAxisAlignment: MainAxisAlignment.spaceBetween,
        children: [
          Text(label, style: const TextStyle(color: WeloColors.textBody)),
          Text(value, style: const TextStyle(fontWeight: FontWeight.w600)),
        ],
      ),
    );
  }
}
