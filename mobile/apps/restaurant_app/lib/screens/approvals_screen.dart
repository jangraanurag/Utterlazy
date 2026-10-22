import 'dart:async';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:ui/ui.dart';
import 'package:api/api.dart';
import 'package:core/core.dart';
import 'package:intl/intl.dart';
import 'widgets/approval_otp_dialog.dart';
import '../providers.dart';

class ApprovalsScreen extends ConsumerStatefulWidget {
  const ApprovalsScreen({super.key});

  @override
  ConsumerState<ApprovalsScreen> createState() => _ApprovalsScreenState();
}

class _ApprovalsScreenState extends ConsumerState<ApprovalsScreen> {
  int _tabIndex = 0;
  bool _isLoading = true;
  List<ApprovalItem> _pendingItems = [];
  List<ApprovalItem> _historyItems = [];
  StreamSubscription? _approvalSubscription;

  @override
  void initState() {
    super.initState();
    _loadData();
    _approvalSubscription = ref.read(webSocketServiceProvider).approvalStream.listen((event) {
      if (mounted) _loadData();
    });
  }

  @override
  void dispose() {
    _approvalSubscription?.cancel();
    super.dispose();
  }

  Future<void> _loadData() async {
    setState(() => _isLoading = true);
    try {
      final vendorRepo = ref.read(vendorRepositoryProvider);
      final pending = await vendorRepo.getPendingApprovals();
      final history = await vendorRepo.getApprovalHistory();
      if (mounted) {
        setState(() {
          _pendingItems = _parseItems(pending['data'] ?? pending['pending'] ?? []);
          _historyItems = _parseItems(history['data'] ?? history['history'] ?? []);
          _isLoading = false;
        });
        final count = _pendingItems.length;
        ref.read(approvalBadgeProvider.notifier).state = count;
      }
    } catch (e) {
      if (mounted) setState(() => _isLoading = false);
    }
  }

  List<ApprovalItem> _parseItems(dynamic data) {
    if (data is! List) return [];
    return data.map((json) => ApprovalItem.fromJson(json is Map<String, dynamic> ? json : {})).toList();
  }

  Future<void> _handleApprove(ApprovalItem item) async {
    try {
      final vendorRepo = ref.read(vendorRepositoryProvider);
      await vendorRepo.sendApprovalOtp(item.type, item.id);
      if (!mounted) return;
      final result = await showDialog<bool>(
        context: context,
        barrierDismissible: false,
        builder: (_) => ApprovalOtpDialog(entityType: item.type, entityId: item.id),
      );
      if (result == true && mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('Approved successfully!'), backgroundColor: WeloColors.success),
        );
        _loadData();
      }
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('Failed to send OTP. Please try again.')),
        );
      }
    }
  }

  Future<void> _handleReject(ApprovalItem item) async {
    final reasonCtrl = TextEditingController();
    final reason = await showDialog<String>(
      context: context,
      builder: (ctx) => Dialog(
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(24)),
        child: Padding(
          padding: const EdgeInsets.all(24),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Row(
                children: [
                  Container(
                    padding: const EdgeInsets.all(8),
                    decoration: BoxDecoration(
                      color: WeloColors.error.withValues(alpha: 0.078),
                      shape: BoxShape.circle,
                    ),
                    child: const Icon(Icons.warning_amber_rounded, color: WeloColors.error, size: 24),
                  ),
                  const SizedBox(width: 12),
                  const Text('Reject Approval', style: TextStyle(fontWeight: FontWeight.bold, fontSize: 18)),
                ],
              ),
              const SizedBox(height: 16),
              const Text('Are you sure you want to reject this item?', style: TextStyle(color: Colors.black54, fontSize: 13)),
              const SizedBox(height: 12),
              TextField(
                controller: reasonCtrl,
                maxLines: 3,
                decoration: InputDecoration(
                  hintText: 'Reason for rejection (optional)',
                  border: OutlineInputBorder(borderRadius: BorderRadius.circular(12)),
                  filled: true,
                  fillColor: WeloColors.sovereignElevated.withValues(alpha: 0.392),
                ),
              ),
              const SizedBox(height: 20),
              Row(
                mainAxisAlignment: MainAxisAlignment.end,
                children: [
                  TextButton(
                    onPressed: () => Navigator.pop(ctx),
                    child: const Text('Cancel', style: TextStyle(color: Colors.grey)),
                  ),
                  const SizedBox(width: 8),
                  ElevatedButton(
                    onPressed: () => Navigator.pop(ctx, reasonCtrl.text),
                    style: ElevatedButton.styleFrom(
                      backgroundColor: WeloColors.error,
                      foregroundColor: Colors.white,
                      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                    ),
                    child: const Text('Reject'),
                  ),
                ],
              ),
            ],
          ),
        ),
      ),
    );

    if (reason == null || !mounted) return;
    try {
      final vendorRepo = ref.read(vendorRepositoryProvider);
      await vendorRepo.rejectApproval(item.type, item.id, reason: reason.isEmpty ? null : reason);
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('Rejected successfully.'), backgroundColor: WeloColors.error),
        );
        _loadData();
      }
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('Failed to reject. Please try again.')),
        );
      }
    }
  }

  Widget _buildDetailsSummary(ApprovalItem item) {
    final details = item.details;
    final chips = <Widget>[];
    if (details['budget'] != null) {
      chips.add(_buildDetailChip('Budget', '₹${details['budget']}'));
    }
    if (details['start_date'] != null) {
      chips.add(_buildDetailChip('Start', details['start_date'].toString().substring(0, 10)));
    }
    if (details['end_date'] != null) {
      chips.add(_buildDetailChip('End', details['end_date'].toString().substring(0, 10)));
    }
    if (chips.isEmpty) {
      final keys = details.keys.take(3).toList();
      for (final k in keys) {
        chips.add(_buildDetailChip(k, details[k].toString()));
      }
    }
    return Wrap(
      spacing: 6,
      runSpacing: 6,
      children: chips,
    );
  }

  Widget _buildDetailChip(String label, String value) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
      decoration: BoxDecoration(
        color: WeloColors.sovereignElevated.withValues(alpha: 0.392),
        borderRadius: BorderRadius.circular(8),
      ),
      child: Text('$label: $value', style: const TextStyle(fontSize: 10, color: WeloColors.textBody)),
    );
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: WeloAppBar(
        title: 'Approvals',
      ),
      body: Column(
        children: [
          Container(
            padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
            child: Row(
              children: [
                _buildTabItem('Pending', 0),
                const SizedBox(width: 8),
                _buildTabItem('History', 1),
              ],
            ),
          ),
          Expanded(
            child: _isLoading
                ? const Center(child: CircularProgressIndicator(color: WeloColors.sovereignOrange))
                : _tabIndex == 0 ? _buildPendingTab() : _buildHistoryTab(),
          ),
        ],
      ),
    );
  }

  Widget _buildTabItem(String label, int index) {
    final isActive = _tabIndex == index;
    return GestureDetector(
      onTap: () => setState(() => _tabIndex = index),
      child: Container(
        padding: const EdgeInsets.symmetric(horizontal: 20, vertical: 10),
        decoration: BoxDecoration(
          color: isActive ? WeloColors.sovereignOrange : Colors.grey.shade100,
          borderRadius: BorderRadius.circular(20),
          border: Border.all(color: isActive ? WeloColors.sovereignOrange : Colors.grey.shade300),
        ),
        child: Text(
          index == 0 ? 'Pending (${_pendingItems.length})' : 'History',
          style: TextStyle(
            fontWeight: FontWeight.bold,
            fontSize: 13,
            color: isActive ? Colors.white : Colors.grey.shade700,
          ),
        ),
      ),
    );
  }

  Widget _buildPendingTab() {
    if (_pendingItems.isEmpty) {
      return Center(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Icon(Icons.check_circle_outline, size: 64, color: WeloColors.textLight),
            const SizedBox(height: 12),
            const Text('No pending approvals', style: TextStyle(color: WeloColors.textBody)),
          ],
        ),
      );
    }
    return RefreshIndicator(
      onRefresh: _loadData,
      color: WeloColors.sovereignOrange,
      child: ListView.builder(
        padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
        itemCount: _pendingItems.length,
        itemBuilder: (context, index) => _buildPendingCard(_pendingItems[index]),
      ),
    );
  }

  Widget _buildPendingCard(ApprovalItem item) {
    return WeloCard(
      margin: const EdgeInsets.only(bottom: 12),
      padding: const EdgeInsets.all(16),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Expanded(
                child: Text(item.title, style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 14)),
              ),
              _buildTypeBadge(item.type),
            ],
          ),
          const SizedBox(height: 8),
          Row(
            children: [
              Icon(Icons.person_outline, size: 14, color: WeloColors.textLight),
              const SizedBox(width: 4),
              Text(item.submittedBy, style: const TextStyle(fontSize: 12, color: WeloColors.textBody)),
              const SizedBox(width: 16),
              Icon(Icons.access_time, size: 14, color: WeloColors.textLight),
              const SizedBox(width: 4),
              Text(
                item.submittedAt != null ? _formatDate(item.submittedAt!) : '',
                style: const TextStyle(fontSize: 12, color: WeloColors.textBody),
              ),
            ],
          ),
          const SizedBox(height: 10),
          _buildDetailsSummary(item),
          const SizedBox(height: 12),
          Row(
            children: [
              Expanded(
                child: ElevatedButton(
                  onPressed: () => _handleApprove(item),
                  style: ElevatedButton.styleFrom(
                    backgroundColor: WeloColors.sovereignOrange,
                    foregroundColor: Colors.white,
                    padding: const EdgeInsets.symmetric(vertical: 12),
                    shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                  ),
                  child: const Text('Approve', style: TextStyle(fontWeight: FontWeight.bold, fontSize: 13)),
                ),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: OutlinedButton(
                  onPressed: () => _handleReject(item),
                  style: OutlinedButton.styleFrom(
                    foregroundColor: WeloColors.error,
                    side: const BorderSide(color: WeloColors.error),
                    padding: const EdgeInsets.symmetric(vertical: 12),
                    shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                  ),
                  child: const Text('Reject', style: TextStyle(fontWeight: FontWeight.bold, fontSize: 13)),
                ),
              ),
            ],
          ),
        ],
      ),
    );
  }

  Widget _buildHistoryTab() {
    if (_historyItems.isEmpty) {
      return Center(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Icon(Icons.history, size: 64, color: WeloColors.textLight),
            const SizedBox(height: 12),
            const Text('No approval history', style: TextStyle(color: WeloColors.textBody)),
          ],
        ),
      );
    }
    return RefreshIndicator(
      onRefresh: _loadData,
      color: WeloColors.sovereignOrange,
      child: ListView.builder(
        padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
        itemCount: _historyItems.length,
        itemBuilder: (context, index) => _buildHistoryCard(_historyItems[index]),
      ),
    );
  }

  Widget _buildHistoryCard(ApprovalItem item) {
    return WeloCard(
      margin: const EdgeInsets.only(bottom: 12),
      padding: const EdgeInsets.all(16),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Expanded(
                child: Text(item.title, style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 14)),
              ),
              _buildStatusChip(item.approvalStatus),
            ],
          ),
          const SizedBox(height: 8),
          Row(
            children: [
              Icon(Icons.person_outline, size: 14, color: WeloColors.textLight),
              const SizedBox(width: 4),
              Text(item.submittedBy, style: const TextStyle(fontSize: 12, color: WeloColors.textBody)),
            ],
          ),
          const SizedBox(height: 6),
          _buildDetailsSummary(item),
        ],
      ),
    );
  }

  Widget _buildTypeBadge(String type) {
    final isAd = type == 'ad';
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
      decoration: BoxDecoration(
        color: (isAd ? WeloColors.sovereignOrange : WeloColors.sovereignTeal).withValues(alpha: 0.118),
        borderRadius: BorderRadius.circular(12),
      ),
      child: Text(
        isAd ? 'Ad' : 'Offer',
        style: TextStyle(
          fontSize: 10,
          fontWeight: FontWeight.bold,
          color: isAd ? WeloColors.sovereignOrange : WeloColors.sovereignTeal,
        ),
      ),
    );
  }

  Widget _buildStatusChip(String status) {
    final color = status == 'approved'
        ? WeloColors.success
        : (status == 'rejected' ? WeloColors.error : WeloColors.textLight);
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
      decoration: BoxDecoration(
        color: color.withValues(alpha: 0.118),
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: color),
      ),
      child: Text(
        status[0].toUpperCase() + status.substring(1),
        style: TextStyle(fontSize: 10, fontWeight: FontWeight.bold, color: color),
      ),
    );
  }

  String _formatDate(String dateStr) {
    try {
      final dt = DateTime.parse(dateStr);
      return DateFormat('dd MMM yyyy').format(dt);
    } catch (_) {
      return dateStr.substring(0, 10);
    }
  }
}
