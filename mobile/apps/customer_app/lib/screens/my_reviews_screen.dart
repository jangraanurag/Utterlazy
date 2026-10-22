import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:intl/intl.dart';
import 'package:core/core.dart';
import 'package:ui/ui.dart';

class MyReviewsScreen extends ConsumerStatefulWidget {
  const MyReviewsScreen({super.key});

  @override
  ConsumerState<MyReviewsScreen> createState() => _MyReviewsScreenState();
}

class _MyReviewsScreenState extends ConsumerState<MyReviewsScreen> {
  List<dynamic> _reviews = [];
  bool _isLoading = true;
  bool _hasMore = true;
  int _page = 1;

  @override
  void initState() {
    super.initState();
    _loadReviews();
  }

  Future<void> _loadReviews({bool reset = false}) async {
    if (reset) {
      _page = 1;
      _hasMore = true;
    }
    try {
      final apiClient = ref.read(apiClientProvider);
      final res = await apiClient.dio.get('/my-reviews', queryParameters: {'page': _page});
      final data = res.data['data'] ?? res.data;
      final items = data is List ? data : (data['data'] ?? data['reviews'] ?? []);
      if (mounted) {
        setState(() {
          if (reset || _page == 1) {
            _reviews = items;
          } else {
            _reviews = [..._reviews, ...items];
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
    await _loadReviews();
  }

  String _formatDate(String? dateStr) {
    if (dateStr == null || dateStr.isEmpty) return '';
    try {
      final date = DateTime.parse(dateStr);
      final now = DateTime.now();
      final diff = now.difference(date);
      if (diff.inDays == 0) return 'Today';
      if (diff.inDays == 1) return 'Yesterday';
      if (diff.inDays < 7) return '${diff.inDays} days ago';
      return DateFormat('MMM d, yyyy').format(date);
    } catch (e) {
      return dateStr;
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: const WeloAppBar(title: 'My Reviews'),
      body: _isLoading && _reviews.isEmpty
          ? const Center(child: CircularProgressIndicator(color: WeloColors.sovereignGold))
          : RefreshIndicator(
              onRefresh: () => _loadReviews(reset: true),
              color: WeloColors.sovereignGold,
              child: _reviews.isEmpty
                  ? ListView(
                      physics: const AlwaysScrollableScrollPhysics(),
                      children: const [
                        SizedBox(height: 100),
                        WeloEmptyState(
                          icon: Icons.rate_review,
                          title: 'No Reviews Yet',
                          subtitle: 'Rate your orders to help others!',
                        ),
                      ],
                    )
                  : ListView.builder(
                      physics: const AlwaysScrollableScrollPhysics(),
                      padding: const EdgeInsets.all(16),
                      itemCount: _reviews.length + (_hasMore ? 1 : 0),
                      itemBuilder: (context, index) {
                        if (index == _reviews.length) {
                          return Center(
                            child: Padding(
                              padding: const EdgeInsets.all(16),
                              child: GestureDetector(
                                onTap: _loadMore,
                                child: Text('Load More', style: TextStyle(color: WeloColors.sovereignGold, fontWeight: FontWeight.bold)),
                              ),
                            ),
                          );
                        }
                        return _buildReviewCard(_reviews[index]);
                      },
                    ),
            ),
    );
  }

  Widget _buildReviewCard(dynamic review) {
    final restaurant = review['restaurant_name'] ?? review['restaurant'] ?? '';
    final rating = review['rating'] ?? 0;
    final title = review['title'] ?? '';
    final comment = review['comment'] ?? '';
    final date = _formatDate(review['created_at'] ?? review['date']);

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
                  restaurant,
                  style: const TextStyle(fontWeight: FontWeight.bold),
                  overflow: TextOverflow.ellipsis,
                ),
              ),
              Row(
                mainAxisSize: MainAxisSize.min,
                children: List.generate(5, (i) => Icon(
                  i < (rating as int) ? Icons.star_rounded : Icons.star_outline_rounded,
                  size: 16,
                  color: WeloColors.sovereignGold,
                )),
              ),
            ],
          ),
          if (date.isNotEmpty) ...[
            const SizedBox(height: 4),
            Text(date, style: const TextStyle(color: WeloColors.textBody, fontSize: 12)),
          ],
          if ((title as String).isNotEmpty) ...[
            const SizedBox(height: 8),
            Text(title, style: const TextStyle(fontWeight: FontWeight.w600)),
          ],
          if ((comment as String).isNotEmpty) ...[
            const SizedBox(height: 4),
            Text(comment, style: const TextStyle(color: WeloColors.textBody, fontSize: 13)),
          ],
        ],
      ),
    );
  }
}
