import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';
import '../theme/welo_colors.dart';

class WeloChip extends StatelessWidget {
  final String label;
  final bool isSelected;
  final VoidCallback? onTap;
  final IconData? icon;
  final Color selectedColor;
  final Color unselectedColor;

  const WeloChip({
    super.key,
    required this.label,
    this.isSelected = false,
    this.onTap,
    this.icon,
    this.selectedColor = WeloColors.sovereignOrange,
    this.unselectedColor = WeloColors.sovereignElevated,
  });

  @override
  Widget build(BuildContext context) {
    return GestureDetector(
      onTap: onTap,
      child: AnimatedContainer(
        duration: const Duration(milliseconds: 200),
        padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 8),
        decoration: BoxDecoration(
          color: isSelected ? selectedColor : unselectedColor,
          borderRadius: BorderRadius.circular(24),
          border: isSelected
              ? null
              : Border.all(color: WeloColors.sovereignElevated),
        ),
        child: Row(
          mainAxisSize: MainAxisSize.min,
          children: [
            if (icon != null) ...[
              Icon(
                icon,
                size: 14,
                color: isSelected ? Colors.white : WeloColors.textBody,
              ),
              const SizedBox(width: 4),
            ],
            Text(
              label,
              style: GoogleFonts.inter(
                fontSize: 13,
                fontWeight: FontWeight.w600,
                color: isSelected ? Colors.white : WeloColors.textBody,
              ),
            ),
          ],
        ),
      ),
    );
  }
}
