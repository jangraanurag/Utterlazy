import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:core/core.dart';
import 'package:ui/ui.dart';
import 'dart:io';
import 'package:path_provider/path_provider.dart';
import 'package:open_filex/open_filex.dart';

class GstScreen extends ConsumerStatefulWidget {
  const GstScreen({super.key});

  @override
  ConsumerState<GstScreen> createState() => _GstScreenState();
}

class _GstScreenState extends ConsumerState<GstScreen> {
  int _selectedYear = DateTime.now().year;
  int _selectedMonth = DateTime.now().month;
  bool _downloading = false;

  static const _months = [
    'January', 'February', 'March', 'April', 'May', 'June',
    'July', 'August', 'September', 'October', 'November', 'December'
  ];

  Future<void> _downloadReport() async {
    setState(() => _downloading = true);
    try {
      final repo = ref.read(vendorRepositoryProvider);
      final bytes = await repo.downloadGstReport(_selectedYear, _selectedMonth);

      final dir = await getExternalStorageDirectory();
      final utterlazyDir = Directory('${dir?.path ?? '/storage/emulated/0/Download'}/UtterLazy');
      if (!await utterlazyDir.exists()) {
        await utterlazyDir.create(recursive: true);
      }

      final fileName = 'GST_Report_${_selectedYear}_${_selectedMonth.toString().padLeft(2, '0')}.xlsx';
      final file = File('${utterlazyDir.path}/$fileName');
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
          SnackBar(content: Text('Failed: $e')),
        );
      }
    } finally {
      if (mounted) setState(() => _downloading = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: const WeloAppBar(title: 'GST Reports'),
      body: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            const Text('Monthly GST Report', style: TextStyle(fontSize: 18, fontWeight: FontWeight.bold)),
            const SizedBox(height: 8),
            const Text(
              'Download detailed 7-sheet Excel report with orders, GSTR-1, GSTR-3B, invoices, and payments.',
              style: TextStyle(color: WeloColors.textBody),
            ),
            const SizedBox(height: 24),

            Row(
              children: [
                Expanded(
                  child: DropdownButtonFormField<int>(
                    initialValue: _selectedYear,
                    decoration: const InputDecoration(labelText: 'Year', border: OutlineInputBorder()),
                    items: List.generate(5, (i) => DateTime.now().year - 2 + i)
                        .map((y) => DropdownMenuItem(value: y, child: Text('$y')))
                        .toList(),
                    onChanged: (v) => setState(() => _selectedYear = v!),
                  ),
                ),
                const SizedBox(width: 12),
                Expanded(
                  child: DropdownButtonFormField<int>(
                    initialValue: _selectedMonth,
                    decoration: const InputDecoration(labelText: 'Month', border: OutlineInputBorder()),
                    items: _months.asMap().entries.map((e) =>
                        DropdownMenuItem(value: e.key + 1, child: Text(e.value)))
                        .toList(),
                    onChanged: (v) => setState(() => _selectedMonth = v!),
                  ),
                ),
              ],
            ),

            const SizedBox(height: 24),

            SizedBox(
              width: double.infinity,
              child: WeloPrimaryButton(
                text: 'Download Excel Report',
                isLoading: _downloading,
                onPressed: _downloadReport,
              ),
            ),

            const SizedBox(height: 32),
            const Divider(),
            const SizedBox(height: 16),

            const Text('Report Contents', style: TextStyle(fontSize: 16, fontWeight: FontWeight.bold)),
            const SizedBox(height: 8),
            _sheetItem('Sheet 1', 'Summary'),
            _sheetItem('Sheet 2', 'Orders with HSN & GST'),
            _sheetItem('Sheet 3', 'GSTR-1 Report'),
            _sheetItem('Sheet 4', 'GSTR-3B Return'),
            _sheetItem('Sheet 5', 'Sales Report (Reverse Charge)'),
            _sheetItem('Sheet 6', 'Invoices (Commission + Sponsorship)'),
            _sheetItem('Sheet 7', 'Payments'),
          ],
        ),
      ),
    );
  }

  Widget _sheetItem(String number, String title) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 4),
      child: Row(
        children: [
          Container(
            width: 24, height: 24,
            decoration: BoxDecoration(
              color: WeloColors.sovereignOrange.withValues(alpha: 0.1),
              borderRadius: BorderRadius.circular(4),
            ),
            child: Center(child: Text(number.split(' ')[1], style: const TextStyle(fontSize: 11, fontWeight: FontWeight.bold, color: WeloColors.sovereignOrange))),
          ),
          const SizedBox(width: 12),
          Text(title, style: const TextStyle(fontSize: 14)),
        ],
      ),
    );
  }
}
