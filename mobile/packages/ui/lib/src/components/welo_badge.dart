import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';

import '../theme/welo_colors.dart';

class WeloBadge extends StatelessWidget {
  final String text;
  final IconData? icon;
  final Color backgroundColor;
  final Color textColor;

  const WeloBadge({
    super.key,
    required this.text,
    this.icon,
    this.backgroundColor = WeloColors.backgroundLight,
    this.textColor = WeloColors.textDark,
  });

  factory WeloBadge.primary({required String text, IconData? icon}) {
    return WeloBadge(
      text: text,
      icon: icon,
      backgroundColor: WeloColors.primaryLight,
      textColor: WeloColors.primaryDark,
    );
  }

  factory WeloBadge.success({required String text, IconData? icon}) {
    return WeloBadge(
      text: text,
      icon: icon,
      backgroundColor: WeloColors.success.withValues(alpha: 0.098),
      textColor: WeloColors.success,
    );
  }

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
      decoration: BoxDecoration(
        color: backgroundColor,
        borderRadius: BorderRadius.circular(8),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          if (icon != null) ...[
            Icon(icon, size: 12, color: textColor),
            const SizedBox(width: 4),
          ],
          Text(
            text,
            style: GoogleFonts.spaceGrotesk(
              color: textColor,
              fontSize: 12,
              fontWeight: FontWeight.w700,
              letterSpacing: 0.5,
            ),
          ),
        ],
      ),
    );
  }
}
