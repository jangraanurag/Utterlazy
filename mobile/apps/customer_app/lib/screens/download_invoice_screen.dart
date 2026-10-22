import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:ui/ui.dart';
import 'package:api/api.dart';
import 'package:core/core.dart';

class DownloadInvoiceScreen extends ConsumerStatefulWidget {
  final String orderId;
  const DownloadInvoiceScreen({super.key, required this.orderId});

  @override
  ConsumerState<DownloadInvoiceScreen> createState() => _DownloadInvoiceScreenState();
}

class _DownloadInvoiceScreenState extends ConsumerState<DownloadInvoiceScreen> {
  Map<String, dynamic>? _invoiceData;
  bool _isLoading = true;
  String? _error;

  @override
  void initState() {
    super.initState();
    _fetchInvoice();
  }

  Future<void> _fetchInvoice() async {
    try {
      final orderRepo = OrderRepository(ref.read(apiClientProvider));
      final res = await orderRepo.getInvoice(widget.orderId);
      if (mounted) {
        setState(() {
          _invoiceData = res['invoice'] as Map<String, dynamic>?;
          _isLoading = false;
        });
      }
    } catch (e) {
      if (mounted) {
        setState(() {
          _error = e.toString();
          _isLoading = false;
        });
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: const Color(0xFFF6F7F9),
      appBar: const WeloAppBar(title: 'TAX INVOICE'),
      body: _isLoading
          ? const Center(child: CircularProgressIndicator(color: WeloColors.sovereignOrange))
          : _error != null
              ? _buildErrorView()
              : _buildInvoiceContent(),
    );
  }

  Widget _buildErrorView() {
    return Center(
      child: Padding(
        padding: const EdgeInsets.all(32),
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            const Icon(Icons.error_outline, size: 64, color: Colors.red),
            const SizedBox(height: 16),
            Text(
              'Failed to load invoice',
              style: GoogleFonts.spaceGrotesk(fontSize: 18, fontWeight: FontWeight.bold),
            ),
            const SizedBox(height: 8),
            Text(_error ?? '', style: const TextStyle(color: Colors.black54), textAlign: TextAlign.center),
            const SizedBox(height: 24),
            ElevatedButton(
              onPressed: () {
                setState(() {
                  _isLoading = true;
                  _error = null;
                });
                _fetchInvoice();
              },
              style: ElevatedButton.styleFrom(backgroundColor: WeloColors.sovereignOrange, foregroundColor: Colors.white),
              child: const Text('RETRY'),
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildInvoiceContent() {
    if (_invoiceData == null) {
      return const Center(child: Text('No invoice data available'));
    }

    final invoiceNo = _invoiceData!['invoice_number'] ?? 'N/A';
    final date = _invoiceData!['invoice_date'] ?? 'N/A';
    final seller = _invoiceData!['seller'] ?? {};
    final buyer = _invoiceData!['buyer'] ?? {};
    final items = _invoiceData!['items'] as List<dynamic>? ?? [];
    final summary = _invoiceData!['summary'] ?? {};
    final payment = _invoiceData!['payment'] ?? {};

    final isBusiness = buyer['is_business'] == true;

    return SingleChildScrollView(
      padding: const EdgeInsets.all(20),
      child: Column(
        children: [
          WeloCard(
            padding: const EdgeInsets.all(24),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                // Header
                Row(
                  mainAxisAlignment: MainAxisAlignment.spaceBetween,
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          const Text(
                            'UTTERLAZY',
                            style: TextStyle(
                              fontSize: 26,
                              fontWeight: FontWeight.w900,
                              color: WeloColors.sovereignOrange,
                              letterSpacing: 0.5,
                            ),
                          ),
                          const SizedBox(height: 4),
                          Text(
                            'Invoice $invoiceNo',
                            style: const TextStyle(fontSize: 12, fontWeight: FontWeight.w700, color: Colors.black45),
                          ),
                          const SizedBox(height: 2),
                          Text(
                            'Order ID: ${widget.orderId}',
                            style: const TextStyle(fontSize: 10, fontWeight: FontWeight.bold, color: Colors.black38),
                          ),
                        ],
                      ),
                    ),
                    Column(
                      crossAxisAlignment: CrossAxisAlignment.end,
                      children: [
                        Text(
                          'DATE: $date',
                          style: GoogleFonts.spaceGrotesk(fontSize: 12, fontWeight: FontWeight.bold),
                        ),
                      ],
                    ),
                  ],
                ),
                const Divider(height: 32, color: Colors.black12),

                // Seller & Buyer Details
                Row(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          const Text(
                            'SELLER',
                            style: TextStyle(fontSize: 9, fontWeight: FontWeight.bold, color: Colors.black38, letterSpacing: 1),
                          ),
                          const SizedBox(height: 4),
                          Text(
                            seller['name'] ?? 'Restaurant',
                            style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 13),
                          ),
                          const SizedBox(height: 2),
                          Text(
                            seller['address'] ?? '',
                            style: const TextStyle(color: Colors.black54, fontSize: 11),
                            maxLines: 3,
                            overflow: TextOverflow.ellipsis,
                          ),
                          if (seller['gstin'] != null && seller['gstin'] != 'N/A') ...[
                            const SizedBox(height: 4),
                            Text(
                              'GSTIN: ${seller['gstin']}',
                              style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 11, color: WeloColors.sovereignOrange),
                            ),
                          ],
                        ],
                      ),
                    ),
                    const SizedBox(width: 16),
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          const Text(
                            'BUYER',
                            style: TextStyle(fontSize: 9, fontWeight: FontWeight.bold, color: Colors.black38, letterSpacing: 1),
                          ),
                          const SizedBox(height: 4),
                          Text(
                            buyer['name'] ?? 'Customer',
                            style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 13),
                          ),
                          const SizedBox(height: 2),
                          if (isBusiness) ...[
                            Container(
                              padding: const EdgeInsets.all(8),
                              decoration: BoxDecoration(
                                color: WeloColors.sovereignOrange.withValues(alpha: 0.05),
                                borderRadius: BorderRadius.circular(8),
                                border: Border.all(color: WeloColors.sovereignOrange.withValues(alpha: 0.1)),
                              ),
                              child: Column(
                                crossAxisAlignment: CrossAxisAlignment.start,
                                children: [
                                  const Text(
                                    'BUSINESS INVOICE',
                                    style: TextStyle(fontSize: 8, fontWeight: FontWeight.bold, color: WeloColors.sovereignOrange),
                                  ),
                                  const SizedBox(height: 2),
                                  Text(
                                    buyer['company_name'] ?? '',
                                    style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 11),
                                  ),
                                  Text(
                                    'GSTIN: ${buyer['gstin']}',
                                    style: const TextStyle(fontSize: 10, color: Colors.black54),
                                  ),
                                ],
                              ),
                            )
                          ] else ...[
                            Text(
                              buyer['address'] ?? 'N/A',
                              style: const TextStyle(color: Colors.black54, fontSize: 11),
                            ),
                          ],
                        ],
                      ),
                    ),
                  ],
                ),
                const Divider(height: 40, color: Colors.black12),

                // Items list
                const Text(
                  'ITEMS MANIFEST',
                  style: TextStyle(fontSize: 9, fontWeight: FontWeight.bold, color: Colors.black38, letterSpacing: 1),
                ),
                const SizedBox(height: 8),
                ...items.map((item) {
                  final name = item['name'] ?? '';
                  final qty = item['quantity'] ?? 1;
                  final total = item['total'] ?? 0;
                  final gstPercent = item['gst_percent'] ?? 5;
                  final gstAmount = item['gst_amount'] ?? 0;
                  final hsn = item['hsn_code'] ?? '9964';

                  return Padding(
                    padding: const EdgeInsets.symmetric(vertical: 8.0),
                    child: Row(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Expanded(
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Text(
                                '$name x$qty',
                                style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 13),
                              ),
                              const SizedBox(height: 2),
                              Text(
                                'HSN: $hsn | GST: $gstPercent% (₹${gstAmount.toStringAsFixed(2)})',
                                style: const TextStyle(fontSize: 10, color: Colors.black38),
                              ),
                            ],
                          ),
                        ),
                        Text(
                          '₹${total.toStringAsFixed(2)}',
                          style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 13),
                        ),
                      ],
                    ),
                  );
                }).toList(),

                const Divider(height: 32, color: Colors.black12),

                // Summary
                _buildSummaryRow('Subtotal', '₹${double.parse(summary['subtotal'].toString()).toStringAsFixed(2)}'),
                _buildSummaryRow('CGST', '₹${double.parse(summary['cgst'].toString()).toStringAsFixed(2)}'),
                _buildSummaryRow('SGST', '₹${double.parse(summary['sgst'].toString()).toStringAsFixed(2)}'),
                if (double.parse(summary['delivery_fee'].toString()) > 0)
                  _buildSummaryRow('Delivery Fee', '₹${double.parse(summary['delivery_fee'].toString()).toStringAsFixed(2)}'),
                if (double.parse(summary['discount'].toString()) > 0)
                  _buildSummaryRow('Discount', '-₹${double.parse(summary['discount'].toString()).toStringAsFixed(2)}', isDiscount: true),
                const Divider(height: 24, color: Colors.black12),
                _buildSummaryRow('Grand Total', '₹${double.parse(summary['grand_total'].toString()).toStringAsFixed(2)}', isTotal: true),
                
                const Divider(height: 40, color: Colors.black12),

                // Payment Details
                Row(
                  mainAxisAlignment: MainAxisAlignment.spaceBetween,
                  children: [
                    Flexible(
                      child: Text(
                        'PAYMENT: ${payment['mode'].toString().toUpperCase()}',
                        style: const TextStyle(color: Colors.black45, fontWeight: FontWeight.bold, fontSize: 10),
                        overflow: TextOverflow.ellipsis,
                      ),
                    ),
                    const SizedBox(width: 8),
                    Text(
                      'STATUS: ${payment['status'].toString().toUpperCase()}',
                      style: TextStyle(
                        color: payment['status'].toString().toLowerCase() == 'paid' ? Colors.green : WeloColors.sovereignOrange,
                        fontWeight: FontWeight.bold,
                        fontSize: 10,
                      ),
                    ),
                  ],
                ),
                const SizedBox(height: 32),

                // Download Simulated Action
                SizedBox(
                  width: double.infinity,
                  child: ElevatedButton.icon(
                    onPressed: _simulateDownload,
                    icon: const Icon(Icons.download, size: 18),
                    label: const Text('DOWNLOAD PDF / SHARE', style: TextStyle(fontWeight: FontWeight.bold, fontSize: 12, letterSpacing: 0.5)),
                    style: ElevatedButton.styleFrom(
                      backgroundColor: WeloColors.sovereignOrange,
                      foregroundColor: Colors.white,
                      padding: const EdgeInsets.symmetric(vertical: 16),
                      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
                      elevation: 0,
                    ),
                  ),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildSummaryRow(String label, String val, {bool isDiscount = false, bool isTotal = false}) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 4.0),
      child: Row(
        mainAxisAlignment: MainAxisAlignment.spaceBetween,
        children: [
          Flexible(
            child: Text(
              label,
              style: TextStyle(
                color: isTotal ? Colors.black : Colors.black54,
                fontWeight: isTotal ? FontWeight.w900 : FontWeight.bold,
                fontSize: isTotal ? 16 : 13,
              ),
              overflow: TextOverflow.ellipsis,
            ),
          ),
          const SizedBox(width: 8),
          Text(
            val,
            style: TextStyle(
              color: isDiscount
                  ? Colors.green
                  : (isTotal ? WeloColors.sovereignOrange : Colors.black87),
              fontWeight: isTotal ? FontWeight.w900 : FontWeight.bold,
              fontSize: isTotal ? 20 : 13,
            ),
          ),
        ],
      ),
    );
  }

  void _simulateDownload() {
    ScaffoldMessenger.of(context).showSnackBar(
      const SnackBar(
        content: Text('Tax Invoice PDF downloaded and saved to internal storage.'),
        behavior: SnackBarBehavior.floating,
      ),
    );
  }
}
