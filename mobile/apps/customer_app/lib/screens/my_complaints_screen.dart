import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:core/core.dart';
import 'package:ui/ui.dart';

class MyComplaintsScreen extends ConsumerStatefulWidget {
  const MyComplaintsScreen({super.key});

  @override
  ConsumerState<MyComplaintsScreen> createState() => _MyComplaintsScreenState();
}

class _MyComplaintsScreenState extends ConsumerState<MyComplaintsScreen> {
  List<dynamic> _complaints = [];
  bool _isLoading = true;
  bool _hasMore = true;
  int _page = 1;

  @override
  void initState() {
    super.initState();
    _loadComplaints();
  }

  Future<void> _loadComplaints({bool reset = false}) async {
    if (reset) {
      _page = 1;
      _hasMore = true;
    }
    try {
      final apiClient = ref.read(apiClientProvider);
      final res = await apiClient.dio.get('/complaints/my', queryParameters: {'page': _page});
      final data = res.data['data'] ?? res.data;
      final items = data is List ? data : (data['data'] ?? data['complaints'] ?? []);
      if (mounted) {
        setState(() {
          if (reset || _page == 1) {
            _complaints = items;
          } else {
            _complaints = [..._complaints, ...items];
          }
          _hasMore = items.length >= 20;
          _isLoading = false;
        });
      }
    } catch (e) {
      if (mounted) setState(() => _isLoading = false);
    }
  }

  Future<void> _loadMore() async {
    if (!_hasMore || _isLoading) return;
    _page++;
    await _loadComplaints();
  }

  Color _statusColor(String? status) {
    switch (status) {
      case 'pending':
        return Colors.orange;
      case 'responded':
        return Colors.green;
      case 'closed':
        return Colors.grey;
      default:
        return Colors.orange;
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: const WeloAppBar(title: 'My Complaints'),
      body: _isLoading && _complaints.isEmpty
          ? const Center(child: CircularProgressIndicator(color: WeloColors.sovereignOrange))
          : RefreshIndicator(
              onRefresh: () => _loadComplaints(reset: true),
              color: WeloColors.sovereignOrange,
              child: _complaints.isEmpty
                  ? ListView(
                      physics: const AlwaysScrollableScrollPhysics(),
                      children: const [
                        SizedBox(height: 100),
                        WeloEmptyState(
                          icon: Icons.report_problem_outlined,
                          title: 'No Complaints',
                          subtitle: 'You haven\'t filed any complaints yet',
                        ),
                      ],
                    )
                  : ListView.builder(
                      physics: const AlwaysScrollableScrollPhysics(),
                      padding: const EdgeInsets.all(16),
                      itemCount: _complaints.length + (_hasMore ? 1 : 0),
                      itemBuilder: (context, index) {
                        if (index == _complaints.length) {
                          return Center(
                            child: Padding(
                              padding: const EdgeInsets.all(16),
                              child: GestureDetector(
                                onTap: _loadMore,
                                child: Text('Load More', style: TextStyle(color: WeloColors.sovereignOrange, fontWeight: FontWeight.bold)),
                              ),
                            ),
                          );
                        }
                        return _buildComplaintCard(_complaints[index]);
                      },
                    ),
            ),
    );
  }

  Widget _buildComplaintCard(dynamic complaint) {
    final status = complaint['status'] ?? 'pending';
    final reason = complaint['reason'] ?? '';
    final description = complaint['description'] ?? '';
    final orderId = complaint['order_id'] ?? '';
    final vendorResponse = complaint['vendor_response'];
    final refundAmount = complaint['refund_amount'];

    return WeloCard(
      margin: const EdgeInsets.only(bottom: 12),
      padding: const EdgeInsets.all(16),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              Flexible(
                child: Text(
                  'Order #${orderId.toString().substring(0, orderId.toString().length > 8 ? 8 : orderId.toString().length).toUpperCase()}',
                  style: GoogleFonts.spaceGrotesk(fontWeight: FontWeight.bold, fontSize: 14),
                  overflow: TextOverflow.ellipsis,
                ),
              ),
              Container(
                padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
                decoration: BoxDecoration(
                  color: _statusColor(status).withValues(alpha: 0.1),
                  borderRadius: BorderRadius.circular(12),
                ),
                child: Text(
                  status.toUpperCase(),
                  style: TextStyle(fontSize: 10, fontWeight: FontWeight.w900, color: _statusColor(status), letterSpacing: 0.5),
                ),
              ),
            ],
          ),
          const SizedBox(height: 8),
          Row(
            children: [
              Icon(Icons.category_outlined, size: 14, color: Colors.grey[500]),
              const SizedBox(width: 6),
              Flexible(
                child: Text(reason, style: const TextStyle(fontSize: 13, fontWeight: FontWeight.w600), overflow: TextOverflow.ellipsis),
              ),
            ],
          ),
          const SizedBox(height: 8),
          Text(description, style: TextStyle(fontSize: 13, color: Colors.grey[700]), maxLines: 3, overflow: TextOverflow.ellipsis),
          if (vendorResponse != null && (vendorResponse as String).isNotEmpty) ...[
            const SizedBox(height: 12),
            Container(
              width: double.infinity,
              padding: const EdgeInsets.all(12),
              decoration: BoxDecoration(
                color: Colors.blue.shade50,
                borderRadius: BorderRadius.circular(10),
              ),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text('Restaurant Response', style: TextStyle(fontSize: 11, fontWeight: FontWeight.bold, color: Colors.blue.shade700)),
                  const SizedBox(height: 4),
                  Text(vendorResponse, style: const TextStyle(fontSize: 13), maxLines: 4, overflow: TextOverflow.ellipsis),
                ],
              ),
            ),
          ],
          if (refundAmount != null && (refundAmount is num && refundAmount > 0)) ...[
            const SizedBox(height: 8),
            Row(
              children: [
                Icon(Icons.account_balance_wallet, size: 14, color: Colors.green[700]),
                const SizedBox(width: 6),
                Text(
                  'Refund: ₹${refundAmount.toStringAsFixed(0)}',
                  style: TextStyle(fontSize: 13, fontWeight: FontWeight.bold, color: Colors.green[700]),
                ),
              ],
            ),
          ],
        ],
      ),
    );
  }
}
