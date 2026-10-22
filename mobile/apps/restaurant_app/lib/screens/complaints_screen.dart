import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:dio/dio.dart';
import 'package:core/core.dart';
import 'package:ui/ui.dart';

class ComplaintsScreen extends ConsumerStatefulWidget {
  const ComplaintsScreen({super.key});

  @override
  ConsumerState<ComplaintsScreen> createState() => _ComplaintsScreenState();
}

class _ComplaintsScreenState extends ConsumerState<ComplaintsScreen> {
  List<dynamic> _complaints = [];
  bool _isLoading = true;
  int _selectedTab = 0;
  int _page = 1;
  bool _hasMore = true;

  final _tabs = ['All', 'Pending', 'Responded', 'Closed'];

  @override
  void initState() {
    super.initState();
    _loadComplaints(reset: true);
  }

  String? _getFilterStatus() {
    switch (_selectedTab) {
      case 1:
        return 'pending';
      case 2:
        return 'responded';
      case 3:
        return 'closed';
      default:
        return null;
    }
  }

  Future<void> _loadComplaints({bool reset = false}) async {
    if (reset) {
      _page = 1;
      _hasMore = true;
    }
    setState(() => _isLoading = true);
    try {
      final apiClient = ref.read(apiClientProvider);
      final params = <String, dynamic>{'page': _page};
      final filter = _getFilterStatus();
      if (filter != null) params['status'] = filter;
      final res = await apiClient.dio.get('/vendor/complaints', queryParameters: params);
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

  void _loadMore() {
    if (!_hasMore || _isLoading) return;
    _page++;
    _loadComplaints();
  }

  int get _pendingCount => _complaints.where((c) => c['status'] == 'pending').length;

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: WeloAppBar(
        title: 'Complaints',
        actions: [
          if (_pendingCount > 0)
            Container(
              margin: const EdgeInsets.only(right: 12, top: 8),
              padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
              decoration: BoxDecoration(
                color: Colors.orange,
                borderRadius: BorderRadius.circular(12),
              ),
              child: Text('$_pendingCount pending', style: const TextStyle(color: Colors.white, fontSize: 11, fontWeight: FontWeight.bold)),
            ),
        ],
      ),
      body: Column(
        children: [
          Container(
            height: 44,
            margin: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
            child: ListView.builder(
              scrollDirection: Axis.horizontal,
              itemCount: _tabs.length,
              itemBuilder: (context, index) {
                return Padding(
                  padding: const EdgeInsets.only(right: 8),
                  child: WeloChip(
                    label: _tabs[index],
                    isSelected: index == _selectedTab,
                    onTap: () {
                      setState(() => _selectedTab = index);
                      _loadComplaints(reset: true);
                    },
                  ),
                );
              },
            ),
          ),
          Expanded(
            child: _isLoading && _complaints.isEmpty
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
                                icon: Icons.check_circle_outline,
                                title: 'No complaints',
                                subtitle: 'All clear! No complaints to show',
                              ),
                            ],
                          )
                        : ListView.builder(
                            physics: const AlwaysScrollableScrollPhysics(),
                            padding: const EdgeInsets.symmetric(horizontal: 16),
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
          ),
        ],
      ),
    );
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

  Widget _buildComplaintCard(dynamic complaint) {
    final status = complaint['status'] ?? 'pending';
    final reason = complaint['reason'] ?? '';
    final description = complaint['description'] ?? '';
    final orderId = complaint['order_id'] ?? '';
    final customerName = complaint['customer_name'] ?? complaint['customer']?['name'] ?? 'Customer';
    final createdAt = complaint['created_at'] ?? '';

    String dateStr = '';
    if (createdAt is String && createdAt.isNotEmpty) {
      try {
        final dt = DateTime.parse(createdAt).toLocal();
        final hour = dt.hour > 12 ? dt.hour - 12 : (dt.hour == 0 ? 12 : dt.hour);
        final ampm = dt.hour >= 12 ? 'PM' : 'AM';
        final minute = dt.minute.toString().padLeft(2, '0');
        dateStr = '$hour:$minute $ampm, ${dt.day}/${dt.month}/${dt.year}';
      } catch (_) {}
    }

    return WeloCard(
      margin: const EdgeInsets.only(bottom: 10),
      padding: const EdgeInsets.all(14),
      onTap: () => _showDetailSheet(complaint),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              Flexible(
                child: Text(
                  customerName,
                  style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 14),
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
          const SizedBox(height: 6),
          Row(
            children: [
              Flexible(
                child: Text(
                  'Order #${orderId.toString().substring(0, orderId.toString().length > 8 ? 8 : orderId.toString().length).toUpperCase()}',
                  style: const TextStyle(fontSize: 12, color: WeloColors.textBody),
                  overflow: TextOverflow.ellipsis,
                ),
              ),
              const SizedBox(width: 12),
              Icon(Icons.circle, size: 4, color: Colors.grey[400]),
              const SizedBox(width: 12),
              Flexible(
                child: Text(reason, style: const TextStyle(fontSize: 12, color: WeloColors.textBody), overflow: TextOverflow.ellipsis),
              ),
            ],
          ),
          const SizedBox(height: 8),
          Text(description, style: TextStyle(fontSize: 13, color: Colors.grey[700]), maxLines: 2, overflow: TextOverflow.ellipsis),
          if (dateStr.isNotEmpty) ...[
            const SizedBox(height: 8),
            Text(dateStr, style: TextStyle(fontSize: 11, color: Colors.grey[400])),
          ],
        ],
      ),
    );
  }

  void _showDetailSheet(dynamic complaint) {
    final status = complaint['status'] ?? 'pending';
    final reason = complaint['reason'] ?? '';
    final description = complaint['description'] ?? '';
    final orderId = complaint['order_id'] ?? '';
    final customerName = complaint['customer_name'] ?? complaint['customer']?['name'] ?? 'Customer';
    final complaintId = complaint['id'] ?? '';
    final totalAmount = complaint['order_total'] ?? complaint['total_amount'];

    final responseController = TextEditingController();
    final refundController = TextEditingController();

    showModalBottomSheet(
      context: context,
      isScrollControlled: true,
      shape: const RoundedRectangleBorder(
        borderRadius: BorderRadius.vertical(top: Radius.circular(24)),
      ),
      builder: (ctx) => Padding(
        padding: EdgeInsets.fromLTRB(24, 24, 24, MediaQuery.of(ctx).viewInsets.bottom + 24),
        child: SingleChildScrollView(
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Row(
                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                children: [
                  Flexible(
                    child: Text('Complaint Details', style: GoogleFonts.spaceGrotesk(fontSize: 18, fontWeight: FontWeight.w900)),
                  ),
                  Container(
                    padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
                    decoration: BoxDecoration(
                      color: _statusColor(status).withValues(alpha: 0.1),
                      borderRadius: BorderRadius.circular(12),
                    ),
                    child: Text(
                      status.toUpperCase(),
                      style: TextStyle(fontSize: 10, fontWeight: FontWeight.w900, color: _statusColor(status)),
                    ),
                  ),
                ],
              ),
              const SizedBox(height: 20),
              _detailRow('Customer', customerName),
              _detailRow('Order', '#${orderId.toString().substring(0, orderId.toString().length > 8 ? 8 : orderId.toString().length).toUpperCase()}'),
              _detailRow('Reason', reason),
              const SizedBox(height: 12),
              const Text('Description', style: TextStyle(fontSize: 12, fontWeight: FontWeight.bold, color: WeloColors.textBody)),
              const SizedBox(height: 4),
              Container(
                width: double.infinity,
                padding: const EdgeInsets.all(12),
                decoration: BoxDecoration(
                  color: Colors.grey.shade50,
                  borderRadius: BorderRadius.circular(10),
                ),
                child: Text(description, style: const TextStyle(fontSize: 13)),
              ),
              const SizedBox(height: 20),
              if (status == 'pending') ...[
                const Text('Your Response', style: TextStyle(fontSize: 12, fontWeight: FontWeight.bold, color: WeloColors.textBody)),
                const SizedBox(height: 8),
                TextField(
                  controller: responseController,
                  maxLines: 3,
                  decoration: InputDecoration(
                    hintText: 'Enter your response to the customer...',
                    border: OutlineInputBorder(borderRadius: BorderRadius.circular(12)),
                    contentPadding: const EdgeInsets.all(14),
                  ),
                ),
                const SizedBox(height: 16),
                Row(
                  children: [
                    Expanded(
                      child: TextField(
                        controller: refundController,
                        keyboardType: TextInputType.number,
                        decoration: InputDecoration(
                          hintText: totalAmount != null ? 'Max: ₹${(totalAmount as num).toStringAsFixed(0)}' : 'Refund amount (₹)',
                          prefixText: '₹ ',
                          border: OutlineInputBorder(borderRadius: BorderRadius.circular(12)),
                          contentPadding: const EdgeInsets.symmetric(horizontal: 14, vertical: 14),
                        ),
                      ),
                    ),
                  ],
                ),
                const SizedBox(height: 20),
                SizedBox(
                  width: double.infinity,
                  height: 48,
                  child: ElevatedButton(
                    onPressed: () => _submitResponse(ctx, complaintId, responseController.text, refundController.text),
                    style: ElevatedButton.styleFrom(
                      backgroundColor: WeloColors.sovereignOrange,
                      foregroundColor: Colors.white,
                      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
                    ),
                    child: Text('SUBMIT RESPONSE', style: GoogleFonts.spaceGrotesk(fontWeight: FontWeight.bold, letterSpacing: 0.5, fontSize: 13)),
                  ),
                ),
                const SizedBox(height: 10),
                SizedBox(
                  width: double.infinity,
                  height: 48,
                  child: OutlinedButton(
                    onPressed: () => _closeComplaint(ctx, complaintId),
                    style: OutlinedButton.styleFrom(
                      foregroundColor: WeloColors.error,
                      side: const BorderSide(color: WeloColors.error),
                      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
                    ),
                    child: const Text('CLOSE COMPLAINT', style: TextStyle(fontWeight: FontWeight.bold)),
                  ),
                ),
              ],
            ],
          ),
        ),
      ),
    );
  }

  Widget _detailRow(String label, String value) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 8),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text('$label: ', style: TextStyle(fontSize: 13, fontWeight: FontWeight.bold, color: Colors.grey[600])),
          Expanded(
            child: Text(value, style: const TextStyle(fontSize: 13), overflow: TextOverflow.ellipsis),
          ),
        ],
      ),
    );
  }

  Future<void> _submitResponse(BuildContext ctx, dynamic complaintId, String response, String refundStr) async {
    if (response.trim().isEmpty) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Please enter a response'), backgroundColor: WeloColors.error),
      );
      return;
    }
    try {
      final apiClient = ref.read(apiClientProvider);
      final data = <String, dynamic>{'response': response.trim()};
      if (refundStr.trim().isNotEmpty) {
        data['refund_amount'] = double.tryParse(refundStr.trim()) ?? 0;
      }
      await apiClient.dio.post('/vendor/complaints/$complaintId/respond', data: data);
      if (!mounted) return;
      Navigator.pop(ctx);
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Response submitted!'), backgroundColor: Colors.green),
      );
      _loadComplaints(reset: true);
    } on DioException catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text('Failed: ${e.message}'), backgroundColor: WeloColors.error),
        );
      }
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text('Failed: $e'), backgroundColor: WeloColors.error),
        );
      }
    }
  }

  Future<void> _closeComplaint(BuildContext ctx, dynamic complaintId) async {
    try {
      final apiClient = ref.read(apiClientProvider);
      await apiClient.dio.post('/vendor/complaints/$complaintId/close');
      if (!mounted) return;
      Navigator.pop(ctx);
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Complaint closed'), backgroundColor: Colors.green),
      );
      _loadComplaints(reset: true);
    } on DioException catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text('Failed: ${e.message}'), backgroundColor: WeloColors.error),
        );
      }
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text('Failed: $e'), backgroundColor: WeloColors.error),
        );
      }
    }
  }
}
