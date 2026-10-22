import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:core/core.dart';
import 'package:ui/ui.dart';

class ReviewsScreen extends ConsumerStatefulWidget {
  final String restaurantId;

  const ReviewsScreen({super.key, required this.restaurantId});

  @override
  ConsumerState<ReviewsScreen> createState() => _ReviewsScreenState();
}

class _ReviewsScreenState extends ConsumerState<ReviewsScreen> {
  List<dynamic> _reviews = [];
  bool _isLoading = true;
  int _page = 1;
  bool _hasMore = true;
  double _averageRating = 0;
  int _totalCount = 0;
  late String _vendorId;

  @override
  void initState() {
    super.initState();
    _vendorId = widget.restaurantId;
    _loadVendorId();
  }

  Future<void> _loadVendorId() async {
    if (_vendorId.isEmpty) {
      try {
        final profile = await ref.read(vendorRepositoryProvider).getProfile();
        _vendorId = profile['restaurant']?['id']?.toString() ?? '';
      } catch (_) {}
    }
    _loadReviews(reset: true);
  }

  Future<void> _loadReviews({bool reset = false}) async {
    if (_vendorId.isEmpty) return;
    if (reset) { _page = 1; _hasMore = true; }
    setState(() => _isLoading = true);
    try {
      final apiClient = ref.read(apiClientProvider);
      final res = await apiClient.dio.get('/restaurants/$_vendorId/reviews', queryParameters: {'page': _page});
      final data = res.data['data'] ?? res.data;
      final items = data is List ? data : (data['data'] ?? data['reviews'] ?? []);
      final avg = data['summary']?['average_rating'] ?? data['average_rating'] ?? 0;
      final total = data['summary']?['total_reviews'] ?? data['total_count'] ?? items.length;
      if (mounted) {
        setState(() {
          if (reset || _page == 1) { _reviews = items; }
          else { _reviews = [..._reviews, ...items]; }
          _averageRating = (avg is num) ? avg.toDouble() : 0;
          _totalCount = (total is num) ? total.toInt() : items.length;
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
    _loadReviews();
  }

  Future<void> _replyToReview(String reviewId, dynamic review) async {
    final replyCtrl = TextEditingController();
    final refundCtrl = TextEditingController();
    bool issueRefund = false;
    String refundType = 'partial';

    final result = await showDialog<Map<String, dynamic>>(
      context: context,
      builder: (ctx) => StatefulBuilder(
        builder: (ctx, setDialogState) => AlertDialog(
          title: const Text('Reply to Review'),
          content: SingleChildScrollView(
            child: Column(
              mainAxisSize: MainAxisSize.min,
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                TextField(
                  controller: replyCtrl,
                  maxLines: 3,
                  decoration: const InputDecoration(
                    hintText: 'Write your reply...',
                    border: OutlineInputBorder(),
                  ),
                ),
                const SizedBox(height: 16),
                Row(
                  children: [
                    Checkbox(
                      value: issueRefund,
                      onChanged: (v) => setDialogState(() => issueRefund = v ?? false),
                      activeColor: WeloColors.sovereignOrange,
                    ),
                    const Text('Issue refund', style: TextStyle(fontWeight: FontWeight.w600, fontSize: 14)),
                  ],
                ),
                if (issueRefund) ...[
                  const SizedBox(height: 8),
                  Row(
                    children: [
                      Expanded(
                        child: ChoiceChip(
                          label: const Text('Partial'),
                          selected: refundType == 'partial',
                          onSelected: (_) => setDialogState(() => refundType = 'partial'),
                          selectedColor: WeloColors.sovereignOrange.withValues(alpha: 0.118),
                        ),
                      ),
                      const SizedBox(width: 12),
                      Expanded(
                        child: ChoiceChip(
                          label: const Text('Full'),
                          selected: refundType == 'full',
                          onSelected: (_) => setDialogState(() => refundType = 'full'),
                          selectedColor: WeloColors.sovereignOrange.withValues(alpha: 0.118),
                        ),
                      ),
                    ],
                  ),
                  if (refundType == 'partial') ...[
                    const SizedBox(height: 12),
                    TextField(
                      controller: refundCtrl,
                      decoration: const InputDecoration(
                        labelText: 'Refund Amount (₹)',
                        border: OutlineInputBorder(),
                        prefixText: '₹ ',
                      ),
                      keyboardType: TextInputType.number,
                    ),
                  ],
                  const SizedBox(height: 8),
                  Text('The refund amount will be deducted from your next payout.',
                    style: TextStyle(fontSize: 11, color: Colors.grey[500])),
                ],
              ],
            ),
          ),
          actions: [
            TextButton(onPressed: () => Navigator.pop(ctx), child: const Text('Cancel')),
            TextButton(
              onPressed: () {
                final data = <String, dynamic>{'reply': replyCtrl.text};
                if (issueRefund) {
                  if (refundType == 'full') {
                    data['refund_amount'] = 'full';
                  } else {
                    final amount = double.tryParse(refundCtrl.text);
                    if (amount == null || amount <= 0) {
                      ScaffoldMessenger.of(ctx).showSnackBar(
                        const SnackBar(content: Text('Enter a valid refund amount')));
                      return;
                    }
                    data['refund_amount'] = amount;
                  }
                }
                Navigator.pop(ctx, data);
              },
              child: const Text('Post Reply'),
            ),
          ],
        ),
      ),
    );
    if (result != null && (result['reply'] as String).isNotEmpty) {
      try {
        await ref.read(vendorRepositoryProvider).replyToReview(
          reviewId, result['reply'] as String,
          refundAmount: result['refund_amount'],
        );
        _loadReviews(reset: true);
        if (mounted) {
          ScaffoldMessenger.of(context).showSnackBar(
            SnackBar(content: Text(result['refund_amount'] != null ? 'Reply posted & refund processed!' : 'Reply posted!')));
        }
      } catch (e) {
        if (mounted) {
          ScaffoldMessenger.of(context).showSnackBar(
            SnackBar(content: Text('Failed: $e')));
        }
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: const WeloAppBar(title: 'Reviews'),
      body: Column(
        children: [
          if (_reviews.isNotEmpty || (!_isLoading && _reviews.isEmpty))
            _buildSummaryHeader(),
          Expanded(
            child: _isLoading && _reviews.isEmpty
                ? const Center(child: CircularProgressIndicator(color: WeloColors.sovereignOrange))
                : RefreshIndicator(
                    onRefresh: () => _loadReviews(reset: true),
                    color: WeloColors.sovereignOrange,
                    child: _reviews.isEmpty
                        ? ListView(
                            physics: const AlwaysScrollableScrollPhysics(),
                            children: const [
                              SizedBox(height: 100),
                              WeloEmptyState(
                                icon: Icons.reviews_outlined,
                                title: 'No reviews yet',
                                subtitle: 'Customers haven\'t left any reviews',
                              ),
                            ],
                          )
                        : ListView.builder(
                            physics: const AlwaysScrollableScrollPhysics(),
                            padding: const EdgeInsets.symmetric(horizontal: 16),
                            itemCount: _reviews.length + (_hasMore ? 1 : 0),
                            itemBuilder: (context, index) {
                              if (index == _reviews.length) {
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
                              return _buildReviewCard(_reviews[index]);
                            },
                          ),
                  ),
          ),
        ],
      ),
    );
  }

  Widget _buildSummaryHeader() {
    return Container(
      width: double.infinity,
      margin: const EdgeInsets.fromLTRB(16, 12, 16, 4),
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(14),
        boxShadow: [BoxShadow(color: Colors.black.withValues(alpha: 0.04), blurRadius: 10, offset: const Offset(0, 2))],
      ),
      child: Row(
        children: [
          Text(_averageRating.toStringAsFixed(1),
            style: GoogleFonts.spaceGrotesk(fontSize: 32, fontWeight: FontWeight.w900, color: WeloColors.sovereignOrange)),
          const SizedBox(width: 12),
          Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Row(children: List.generate(5, (i) => Icon(
                i < _averageRating.round() ? Icons.star : Icons.star_border,
                size: 18, color: i < _averageRating.round() ? Colors.amber : Colors.grey[300]))),
              const SizedBox(height: 4),
              Text('$_totalCount review${_totalCount == 1 ? '' : 's'}',
                style: TextStyle(fontSize: 13, color: Colors.grey[600])),
            ],
          ),
        ],
      ),
    );
  }

  Widget _buildReviewCard(dynamic review) {
    final id = review['id']?.toString() ?? '';
    final customerName = review['customer_name'] ?? review['user']?['name'] ?? 'Anonymous';
    final rating = (review['rating'] is num) ? review['rating'].toDouble() : 0.0;
    final title = review['title'] ?? '';
    final comment = review['comment'] ?? review['body'] ?? '';
    final vendorReply = review['vendor_reply'] as String? ?? '';
    final createdAt = review['created_at'] ?? '';
    final helpfulCount = review['helpful_count'] ?? review['helpfulCount'] ?? 0;

    String dateStr = '';
    if (createdAt is String && createdAt.isNotEmpty) {
      try {
        final dt = DateTime.parse(createdAt).toLocal();
        final h = dt.hour > 12 ? dt.hour - 12 : (dt.hour == 0 ? 12 : dt.hour);
        dateStr = '$h:${dt.minute.toString().padLeft(2, '0')} ${dt.hour >= 12 ? "PM" : "AM"}, ${dt.day}/${dt.month}/${dt.year}';
      } catch (_) {}
    }

    return WeloCard(
      margin: const EdgeInsets.only(bottom: 10),
      padding: const EdgeInsets.all(14),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              Flexible(
                child: Text(customerName, style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 14),
                  overflow: TextOverflow.ellipsis),
              ),
              Row(mainAxisSize: MainAxisSize.min, children: List.generate(5, (i) => Icon(
                i < rating.round() ? Icons.star : Icons.star_border,
                size: 14, color: i < rating.round() ? Colors.amber : Colors.grey[300]))),
            ],
          ),
          if (title.isNotEmpty) ...[
            const SizedBox(height: 6),
            Text(title, style: GoogleFonts.spaceGrotesk(fontSize: 13, fontWeight: FontWeight.w700),
              overflow: TextOverflow.ellipsis),
          ],
          const SizedBox(height: 6),
          Text(comment, style: TextStyle(fontSize: 13, color: Colors.grey[700]),
            maxLines: 3, overflow: TextOverflow.ellipsis),
          const SizedBox(height: 8),

          // Vendor Reply
          if (vendorReply.isNotEmpty)
            Container(
              width: double.infinity,
              margin: const EdgeInsets.only(bottom: 8),
              padding: const EdgeInsets.all(10),
              decoration: BoxDecoration(
                color: WeloColors.sovereignOrange.withValues(alpha: 0.031),
                borderRadius: BorderRadius.circular(8),
                border: Border.all(color: WeloColors.sovereignOrange.withValues(alpha: 0.118)),
              ),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Row(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Icon(Icons.reply, size: 14, color: WeloColors.sovereignOrange),
                      const SizedBox(width: 6),
                      Expanded(
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            const Text('Your reply', style: TextStyle(fontSize: 11, fontWeight: FontWeight.bold, color: WeloColors.sovereignOrange)),
                            const SizedBox(height: 2),
                            Text(vendorReply, style: const TextStyle(fontSize: 12)),
                          ],
                        ),
                      ),
                    ],
                  ),
                  // Refund info
                  if (review['refund_status'] == 'processed')
                    Padding(
                      padding: const EdgeInsets.only(top: 8),
                      child: Row(
                        children: [
                          Icon(Icons.monetization_on, size: 14, color: Colors.green[700]),
                          const SizedBox(width: 6),
                          Text('Refund issued: ₹${review['refund_amount']}',
                            style: TextStyle(fontSize: 12, fontWeight: FontWeight.w600, color: Colors.green[700])),
                        ],
                      ),
                    ),
                ],
              ),
            ),

          Row(
            children: [
              if (dateStr.isNotEmpty)
                Text(dateStr, style: TextStyle(fontSize: 11, color: Colors.grey[400])),
              const Spacer(),
              if (helpfulCount > 0)
                Padding(
                  padding: const EdgeInsets.only(right: 12),
                  child: Row(mainAxisSize: MainAxisSize.min, children: [
                    Icon(Icons.thumb_up_outlined, size: 13, color: Colors.grey[400]),
                    const SizedBox(width: 4),
                    Text('$helpfulCount', style: TextStyle(fontSize: 11, color: Colors.grey[400])),
                  ]),
                ),
              if (vendorReply.isEmpty)
                GestureDetector(
                  onTap: () => _replyToReview(id, review),
                  child: Container(
                    padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
                    decoration: BoxDecoration(
                      color: WeloColors.sovereignOrange.withValues(alpha: 0.047),
                      borderRadius: BorderRadius.circular(8),
                    ),
                    child: Row(mainAxisSize: MainAxisSize.min, children: [
                      Icon(Icons.reply, size: 12, color: WeloColors.sovereignOrange),
                      const SizedBox(width: 4),
                      Text('Reply', style: TextStyle(fontSize: 11, fontWeight: FontWeight.bold, color: WeloColors.sovereignOrange)),
                    ]),
                  ),
                ),
            ],
          ),
        ],
      ),
    );
  }
}
