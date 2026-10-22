import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:ui/ui.dart';
import 'package:core/core.dart';
import 'package:dio/dio.dart';

class WriteReviewScreen extends ConsumerStatefulWidget {
  final String restaurantId;
  final String orderId;
  final String restaurantName;

  const WriteReviewScreen({
    super.key,
    required this.restaurantId,
    required this.orderId,
    required this.restaurantName,
  });

  @override
  ConsumerState<WriteReviewScreen> createState() => _WriteReviewScreenState();
}

class _WriteReviewScreenState extends ConsumerState<WriteReviewScreen> {
  int _rating = 0;
  int _foodRating = 0;
  int _deliveryRating = 0;
  int _packagingRating = 0;
  final _titleController = TextEditingController();
  final _commentController = TextEditingController();
  bool _isSubmitting = false;
  final List<String> _selectedImages = [];

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: const WeloAppBar(title: 'Write Review'),
      body: SingleChildScrollView(
        padding: const EdgeInsets.all(16),
        child: Column(
          children: [
            Text(widget.restaurantName, style: const TextStyle(fontSize: 18, fontWeight: FontWeight.bold)),
            const SizedBox(height: 4),
            const Text('How was your order?', style: TextStyle(color: WeloColors.textBody)),
            const SizedBox(height: 24),

            Row(
              mainAxisAlignment: MainAxisAlignment.center,
              children: List.generate(5, (i) => GestureDetector(
                onTap: () => setState(() => _rating = i + 1),
                child: Padding(
                  padding: const EdgeInsets.all(4),
                  child: Icon(
                    i < _rating ? Icons.star_rounded : Icons.star_outline_rounded,
                    size: 40,
                    color: i < _rating ? WeloColors.sovereignGold : WeloColors.sovereignElevated,
                  ),
                ),
              )),
            ),
            const SizedBox(height: 4),
            Text(_rating == 0 ? 'Tap to rate' : ['', 'Terrible', 'Bad', 'Okay', 'Good', 'Excellent'][_rating],
              style: const TextStyle(color: WeloColors.textBody)),
            const SizedBox(height: 24),

            Row(
              mainAxisAlignment: MainAxisAlignment.spaceEvenly,
              children: [
                _buildSubRating('Food', _foodRating, (v) => setState(() => _foodRating = v)),
                _buildSubRating('Delivery', _deliveryRating, (v) => setState(() => _deliveryRating = v)),
                _buildSubRating('Packaging', _packagingRating, (v) => setState(() => _packagingRating = v)),
              ],
            ),
            const SizedBox(height: 24),

            WeloTextField(hintText: 'Review title (optional)', controller: _titleController),
            const SizedBox(height: 12),
            WeloTextField(hintText: 'Tell us about your experience...', controller: _commentController, maxLines: 4),
            const SizedBox(height: 16),
            Align(
              alignment: Alignment.centerLeft,
              child: const Text('Add Photos', style: TextStyle(fontWeight: FontWeight.bold, fontSize: 13)),
            ),
            const SizedBox(height: 8),
            SingleChildScrollView(
              scrollDirection: Axis.horizontal,
              child: Row(
                children: [
                  GestureDetector(
                    onTap: () {
                      setState(() {
                        if (_selectedImages.length < 5) {
                          _selectedImages.add('https://images.unsplash.com/photo-1546069901-ba9599a7e63c?auto=format&fit=crop&w=200&q=80');
                        }
                      });
                    },
                    child: Container(
                      width: 80,
                      height: 80,
                      decoration: BoxDecoration(
                        color: Colors.grey.shade100,
                        border: Border.all(color: Colors.grey.shade300, style: BorderStyle.solid),
                        borderRadius: BorderRadius.circular(12),
                      ),
                      child: const Column(
                        mainAxisAlignment: MainAxisAlignment.center,
                        children: [
                          Icon(Icons.add_a_photo, color: Colors.grey, size: 24),
                          SizedBox(height: 4),
                          Text('Add', style: TextStyle(color: Colors.grey, fontSize: 12)),
                        ],
                      ),
                    ),
                  ),
                  ..._selectedImages.asMap().entries.map((entry) {
                    return Container(
                      margin: const EdgeInsets.only(left: 12),
                      width: 80,
                      height: 80,
                      decoration: BoxDecoration(
                        borderRadius: BorderRadius.circular(12),
                        image: DecorationImage(image: NetworkImage(entry.value), fit: BoxFit.cover),
                      ),
                      child: Align(
                        alignment: Alignment.topRight,
                        child: GestureDetector(
                          onTap: () => setState(() => _selectedImages.removeAt(entry.key)),
                          child: Container(
                            margin: const EdgeInsets.all(4),
                            padding: const EdgeInsets.all(2),
                            decoration: const BoxDecoration(color: Colors.black54, shape: BoxShape.circle),
                            child: const Icon(Icons.close, color: Colors.white, size: 14),
                          ),
                        ),
                      ),
                    );
                  }).toList(),
                ],
              ),
            ),
            const SizedBox(height: 24),

            WeloPrimaryButton(
              text: _isSubmitting ? 'Submitting...' : 'Submit Review',
              onPressed: () {
                if (_rating > 0 && _commentController.text.isNotEmpty && !_isSubmitting) {
                  _submitReview();
                }
              },
              isLoading: _isSubmitting,
            ),
          ],
        ),
      ),
    );
  }

  Future<void> _submitReview() async {
    setState(() => _isSubmitting = true);
    try {
      final apiClient = ref.read(apiClientProvider);
      await apiClient.dio.post('/reviews', data: {
        'restaurant_id': widget.restaurantId,
        'order_id': widget.orderId,
        'rating': _rating,
        'title': _titleController.text,
        'comment': _commentController.text,
        'food_rating': _foodRating,
        'delivery_rating': _deliveryRating,
        'packaging_rating': _packagingRating,
        'images': _selectedImages,
      });
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('Review submitted!'), backgroundColor: WeloColors.success),
        );
        Navigator.pop(context, true);
      }
    } catch (e) {
      if (mounted) {
        String msg = 'Failed to submit review.';
        if (e is DioException) {
          msg = e.response?.data?['error'] ?? e.response?.data?['message'] ?? msg;
        }
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text(msg), backgroundColor: Colors.red),
        );
      }
    } finally {
      if (mounted) {
        setState(() => _isSubmitting = false);
      }
    }
  }

  Widget _buildSubRating(String label, int rating, ValueChanged<int> onChanged) {
    return Column(
      children: [
        Text(label, style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 13)),
        const SizedBox(height: 4),
        Row(
          mainAxisSize: MainAxisSize.min,
          children: List.generate(5, (i) => GestureDetector(
            onTap: () => onChanged(i + 1),
            child: Icon(
              i < rating ? Icons.star_rounded : Icons.star_outline_rounded,
              size: 20,
              color: i < rating ? WeloColors.sovereignGold : WeloColors.sovereignElevated,
            ),
          )),
        ),
      ],
    );
  }
}
