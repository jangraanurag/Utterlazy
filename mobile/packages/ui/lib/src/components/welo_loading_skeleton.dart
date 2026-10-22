import 'package:flutter/material.dart';
import '../theme/welo_colors.dart';

class WeloLoadingSkeleton extends StatelessWidget {
  final double width;
  final double height;
  final double borderRadius;

  const WeloLoadingSkeleton({
    super.key,
    this.width = double.infinity,
    this.height = 16,
    this.borderRadius = 8,
  });

  @override
  Widget build(BuildContext context) {
    return AnimatedContainer(
      duration: const Duration(milliseconds: 800),
      width: width,
      height: height,
      decoration: BoxDecoration(
        color: WeloColors.sovereignElevated,
        borderRadius: BorderRadius.circular(borderRadius),
      ),
    );
  }

  static Widget card() {
    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: WeloColors.sovereignSurface,
        borderRadius: BorderRadius.circular(20),
      ),
      child: const Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          WeloLoadingSkeleton(height: 120, borderRadius: 12),
          SizedBox(height: 12),
          WeloLoadingSkeleton(width: 200, height: 16),
          SizedBox(height: 8),
          WeloLoadingSkeleton(width: 120, height: 12),
        ],
      ),
    );
  }

  static Widget list({int count = 5}) {
    return Column(
      children: List.generate(count, (_) => Padding(
        padding: const EdgeInsets.only(bottom: 12),
        child: card(),
      )),
    );
  }
}
