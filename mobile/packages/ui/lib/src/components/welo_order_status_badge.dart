import 'package:flutter/material.dart';
import '../theme/welo_colors.dart';

class WeloOrderStatusBadge extends StatelessWidget {
  final String status;

  const WeloOrderStatusBadge({super.key, required this.status});

  @override
  Widget build(BuildContext context) {
    final (color, label) = _getStatusStyle(status);
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
      decoration: BoxDecoration(
        color: color.withValues(alpha: 0.098),
        borderRadius: BorderRadius.circular(8),
      ),
      child: Text(
        label,
        style: TextStyle(
          fontSize: 11,
          fontWeight: FontWeight.w700,
          color: color,
        ),
      ),
    );
  }

  (Color, String) _getStatusStyle(String status) {
    return switch (status) {
      'pending' => (WeloColors.warning, 'Pending'),
      'confirmed' => (WeloColors.info, 'Confirmed'),
      'preparing' => (const Color(0xFFF97316), 'Preparing'),
      'ready_for_pickup' => (WeloColors.sovereignTeal, 'Ready'),
      'out_for_delivery' => (const Color(0xFF8B5CF6), 'On the Way'),
      'delivered' => (WeloColors.success, 'Delivered'),
      'cancelled' => (WeloColors.error, 'Cancelled'),
      'abandoned' => (WeloColors.textLight, 'Abandoned'),
      _ => (WeloColors.textLight, status),
    };
  }
}
