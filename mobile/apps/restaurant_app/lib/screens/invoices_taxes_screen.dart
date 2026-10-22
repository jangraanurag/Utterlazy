import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:ui/ui.dart';
import 'package:core/core.dart';
import 'dart:io';
import 'package:path_provider/path_provider.dart';
import 'package:open_filex/open_filex.dart';

class InvoicesTaxesScreen extends ConsumerStatefulWidget {
  const InvoicesTaxesScreen({super.key});

  @override
  ConsumerState<InvoicesTaxesScreen> createState() => _InvoicesTaxesScreenState();
}

class _InvoicesTaxesScreenState extends ConsumerState<InvoicesTaxesScreen> {
  String _activeTab = 'commission';
  int _selectedYear = DateTime.now().year;
  int _selectedMonth = DateTime.now().month;
  bool _isLoading = true;
  bool _isDownloading = false;
  List<dynamic> _invoices = [];

  final _tabLabels = {
    'commission': 'Online Ordering',
    'ads': 'Ads',
    'recovery': 'Recovery',
    'gstr1': 'GSTR1',
  };

  @override
  void initState() {
    super.initState();
    _loadData();
  }

  Future<void> _loadData() async {
    setState(() => _isLoading = true);
    try {
      final repo = ref.read(vendorRepositoryProvider);
      final data = await repo.getVendorInvoices(
        type: _activeTab == 'gstr1' ? null : _activeTab,
        year: _selectedYear,
        month: _selectedMonth,
      );
      if (mounted) {
        setState(() {
          _invoices = (data['invoices'] as List?) ?? [];
          _isLoading = false;
        });
      }
    } catch (e) {
      if (mounted) setState(() { _invoices = []; _isLoading = false; });
    }
  }

  Future<void> _downloadPdf(String invoiceId) async {
    setState(() => _isDownloading = true);
    try {
      final repo = ref.read(vendorRepositoryProvider);
      final bytes = await repo.downloadInvoicePdf(invoiceId);
      await _saveAndOpen(bytes, 'Invoice_$invoiceId.pdf');
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text('Download failed: $e')));
      }
    } finally {
      if (mounted) setState(() => _isDownloading = false);
    }
  }

  Future<void> _downloadBundle() async {
    setState(() => _isDownloading = true);
    try {
      final repo = ref.read(vendorRepositoryProvider);
      final bytes = await repo.downloadInvoiceBundle(_activeTab, _selectedYear, _selectedMonth);
      await _saveAndOpen(bytes, 'Invoices_${_activeTab}_$_selectedYear-$_selectedMonth.zip');
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text('Download failed: $e')));
      }
    } finally {
      if (mounted) setState(() => _isDownloading = false);
    }
  }

  Future<void> _downloadGstr1() async {
    setState(() => _isDownloading = true);
    try {
      final repo = ref.read(vendorRepositoryProvider);
      final bytes = await repo.downloadGstr1Report(_selectedYear, _selectedMonth);
      await _saveAndOpen(bytes, 'GSTR1_${_selectedYear}_$_selectedMonth.xlsx');
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text('Download failed: $e')));
      }
    } finally {
      if (mounted) setState(() => _isDownloading = false);
    }
  }

  Future<void> _saveAndOpen(List<int> bytes, String filename) async {
    final dir = await getExternalStorageDirectory();
    final downloadDir = Directory('${dir?.path ?? '/storage/emulated/0/Download'}/UtterLazy');
    if (!await downloadDir.exists()) {
      await downloadDir.create(recursive: true);
    }
    final file = File('${downloadDir.path}/$filename');
    await file.writeAsBytes(bytes);
    if (mounted) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text('Saved: $filename'),
          action: SnackBarAction(label: 'Open', onPressed: () => OpenFilex.open(file.path)),
          duration: const Duration(seconds: 8),
        ),
      );
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: const WeloAppBar(title: 'Invoices & Taxes'),
      body: Column(
        children: [
          // Year/Month selectors
          Padding(
            padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
            child: Row(
              children: [
                Expanded(
                  child: DropdownButtonFormField<int>(
                    initialValue: _selectedYear,
                    decoration: const InputDecoration(
                      labelText: 'Year', border: OutlineInputBorder(),
                      contentPadding: EdgeInsets.symmetric(horizontal: 12, vertical: 8),
                    ),
                    items: List.generate(5, (i) => 2024 + i).map((y) =>
                      DropdownMenuItem(value: y, child: Text('$y', style: const TextStyle(fontSize: 13)))).toList(),
                    onChanged: (v) { _selectedYear = v!; _loadData(); },
                  ),
                ),
                const SizedBox(width: 8),
                Expanded(
                  child: DropdownButtonFormField<int>(
                    initialValue: _selectedMonth,
                    decoration: const InputDecoration(
                      labelText: 'Month', border: OutlineInputBorder(),
                      contentPadding: EdgeInsets.symmetric(horizontal: 12, vertical: 8),
                    ),
                    items: const [
                      DropdownMenuItem(value: 1, child: Text('January', style: TextStyle(fontSize: 13))),
                      DropdownMenuItem(value: 2, child: Text('February', style: TextStyle(fontSize: 13))),
                      DropdownMenuItem(value: 3, child: Text('March', style: TextStyle(fontSize: 13))),
                      DropdownMenuItem(value: 4, child: Text('April', style: TextStyle(fontSize: 13))),
                      DropdownMenuItem(value: 5, child: Text('May', style: TextStyle(fontSize: 13))),
                      DropdownMenuItem(value: 6, child: Text('June', style: TextStyle(fontSize: 13))),
                      DropdownMenuItem(value: 7, child: Text('July', style: TextStyle(fontSize: 13))),
                      DropdownMenuItem(value: 8, child: Text('August', style: TextStyle(fontSize: 13))),
                      DropdownMenuItem(value: 9, child: Text('September', style: TextStyle(fontSize: 13))),
                      DropdownMenuItem(value: 10, child: Text('October', style: TextStyle(fontSize: 13))),
                      DropdownMenuItem(value: 11, child: Text('November', style: TextStyle(fontSize: 13))),
                      DropdownMenuItem(value: 12, child: Text('December', style: TextStyle(fontSize: 13))),
                    ],
                    onChanged: (v) { _selectedMonth = v!; _loadData(); },
                  ),
                ),
              ],
            ),
          ),

          // Tabs
          SizedBox(
            height: 44,
            child: ListView(
              scrollDirection: Axis.horizontal,
              padding: const EdgeInsets.symmetric(horizontal: 16),
              children: _tabLabels.entries.map((e) {
                final isActive = _activeTab == e.key;
                return Padding(
                  padding: const EdgeInsets.only(right: 4),
                  child: GestureDetector(
                    onTap: () => setState(() { _activeTab = e.key; _loadData(); }),
                    child: Container(
                      padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
                      decoration: BoxDecoration(
                        color: isActive ? WeloColors.sovereignOrange.withValues(alpha: 0.1) : Colors.transparent,
                        borderRadius: BorderRadius.circular(8),
                        border: Border.all(color: isActive ? WeloColors.sovereignOrange : Colors.grey.shade300),
                      ),
                      child: Text(e.value, style: TextStyle(
                        fontSize: 12, fontWeight: FontWeight.bold,
                        color: isActive ? WeloColors.sovereignOrange : Colors.black54,
                      )),
                    ),
                  ),
                );
              }).toList(),
            ),
          ),
          const SizedBox(height: 8),

          // Action buttons
          if (_activeTab != 'gstr1')
            Padding(
              padding: const EdgeInsets.symmetric(horizontal: 16),
              child: Row(
                children: [
                  Expanded(
                    child: OutlinedButton.icon(
                      onPressed: _isDownloading ? null : _downloadBundle,
                      icon: const Icon(Icons.folder_zip, size: 16),
                      label: const Text('Download All ZIP'),
                    ),
                  ),
                ],
              ),
            )
          else
            Padding(
              padding: const EdgeInsets.symmetric(horizontal: 16),
              child: SizedBox(
                width: double.infinity,
                child: OutlinedButton.icon(
                  onPressed: _isDownloading ? null : _downloadGstr1,
                  icon: const Icon(Icons.description, size: 16),
                  label: const Text('Download GSTR1 XLSX'),
                ),
              ),
            ),
          const SizedBox(height: 8),

          // Loading or list
          Expanded(
            child: _isLoading
                ? const Center(child: CircularProgressIndicator(color: WeloColors.sovereignOrange))
                : _activeTab == 'gstr1'
                    ? const Padding(
                        padding: EdgeInsets.all(24),
                        child: Column(
                          mainAxisAlignment: MainAxisAlignment.center,
                          children: [
                            Icon(Icons.description_outlined, size: 64, color: WeloColors.textLight),
                            SizedBox(height: 16),
                            Text('GSTR1 report includes: B2CS, HSN Summary,\nTax Liability, At-a-glance and more.',
                              textAlign: TextAlign.center, style: TextStyle(color: WeloColors.textBody)),
                            SizedBox(height: 8),
                            Text('Click "Download GSTR1 XLSX" above.',
                              style: TextStyle(color: WeloColors.sovereignOrange, fontWeight: FontWeight.bold)),
                          ],
                        ),
                      )
                    : _invoices.isEmpty
                        ? const Center(child: Text('No invoices for this period', style: TextStyle(color: WeloColors.textBody)))
                        : RefreshIndicator(
                            onRefresh: _loadData,
                            color: WeloColors.sovereignOrange,
                            child: ListView.builder(
                              padding: const EdgeInsets.all(16),
                              itemCount: _invoices.length,
                              itemBuilder: (ctx, i) => _buildInvoiceCard(_invoices[i]),
                            ),
                          ),
          ),
        ],
      ),
    );
  }

  Widget _buildInvoiceCard(dynamic inv) {
    final number = inv['invoice_number'] ?? '';
    final start = (inv['period_start']?.toString() ?? '').substring(0, 10);
    final end = (inv['period_end']?.toString() ?? '').substring(0, 10);
    final amount = double.tryParse(inv['amount']?.toString() ?? '0') ?? 0;
    final cgst = double.tryParse(inv['cgst_amount']?.toString() ?? '0') ?? 0;
    final sgst = double.tryParse(inv['sgst_amount']?.toString() ?? '0') ?? 0;
    final total = double.tryParse(inv['total_amount']?.toString() ?? '0') ?? 0;
    final status = inv['status']?.toString() ?? 'pending';
    final id = inv['id']?.toString() ?? '';

    return WeloCard(
      margin: const EdgeInsets.only(bottom: 12),
      padding: const EdgeInsets.all(14),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              Expanded(
                child: Text(number, style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 13)),
              ),
              Container(
                padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
                decoration: BoxDecoration(
                  color: status == 'paid'
                      ? Colors.green.shade100
                      : status == 'approved'
                          ? Colors.blue.shade100
                          : Colors.amber.shade100,
                  borderRadius: BorderRadius.circular(10),
                ),
                child: Text(status.toUpperCase(),
                  style: TextStyle(fontSize: 9, fontWeight: FontWeight.bold,
                    color: status == 'paid' ? Colors.green.shade800 : status == 'approved' ? Colors.blue.shade800 : Colors.amber.shade800)),
              ),
            ],
          ),
          const SizedBox(height: 4),
          Text('$start → $end', style: const TextStyle(color: WeloColors.textLight, fontSize: 12)),
          const SizedBox(height: 10),
          Row(
            children: [
              _amountChip('Amount', amount, WeloColors.textBody),
              _amountChip('CGST', cgst, Colors.blue),
              _amountChip('SGST', sgst, Colors.green),
              _amountChip('Total', total, WeloColors.sovereignOrange),
            ],
          ),
          const SizedBox(height: 10),
          OutlinedButton.icon(
            onPressed: _isDownloading ? null : () => _downloadPdf(id),
            icon: const Icon(Icons.picture_as_pdf, size: 14),
            label: const Text('Download PDF', style: TextStyle(fontSize: 12)),
            style: OutlinedButton.styleFrom(padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 6)),
          ),
        ],
      ),
    );
  }

  Widget _amountChip(String label, double value, Color color) {
    return Expanded(
      child: Column(
        children: [
          Text('₹${value.toStringAsFixed(0)}', style: TextStyle(fontWeight: FontWeight.bold, fontSize: 13, color: color)),
          Text(label, style: const TextStyle(color: WeloColors.textBody, fontSize: 9)),
        ],
      ),
    );
  }
}
