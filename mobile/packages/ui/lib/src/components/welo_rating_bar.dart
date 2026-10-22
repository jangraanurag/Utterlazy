import 'package:flutter/material.dart';
import '../theme/welo_colors.dart';

class WeloRatingBar extends StatelessWidget {
  final double rating;
  final int maxRating;
  final double starSize;
  final bool interactive;
  final ValueChanged<double>? onRatingChanged;
  final Color activeColor;
  final Color inactiveColor;

  const WeloRatingBar({
    super.key,
    required this.rating,
    this.maxRating = 5,
    this.starSize = 16,
    this.interactive = false,
    this.onRatingChanged,
    this.activeColor = WeloColors.sovereignGold,
    this.inactiveColor = WeloColors.sovereignElevated,
  });

  @override
  Widget build(BuildContext context) {
    return Row(
      mainAxisSize: MainAxisSize.min,
      children: List.generate(maxRating, (index) {
        final starValue = index + 1.0;
        final isFilled = starValue <= rating;
        final isHalf = !isFilled && starValue - 0.5 <= rating;

        return GestureDetector(
          onTap: interactive
              ? () => onRatingChanged?.call(starValue)
              : null,
          child: Padding(
            padding: const EdgeInsets.only(right: 2),
            child: Icon(
              isFilled
                  ? Icons.star_rounded
                  : isHalf
                      ? Icons.star_half_rounded
                      : Icons.star_outline_rounded,
              size: starSize,
              color: (isFilled || isHalf) ? activeColor : inactiveColor,
            ),
          ),
        );
      }),
    );
  }
}
