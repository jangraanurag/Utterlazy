import 'package:flutter/material.dart';

class WeloColors {
  // Welo Sovereign Marketplace (Regal Artisan Light Edition)
  static const Color sovereignBg = Color(0xFFFDF9F4);
  static const Color sovereignSurface = Color(0xFFFFFFFF);
  static const Color sovereignElevated = Color(0xFFF4F1ED);
  static const Color sovereignOrange = Color(0xFFFF6B00);
  static const Color sovereignTeal = Color(0xFF007B8A);
  static const Color sovereignGold = Color(0xFFB08D57);
  static const Color sovereignText = Color(0xFF1A1A1A);
  static const Color sovereignTextVariant = Color(0xFF5E5E5E);

  // Legacy Aliases for Compatibility (Transitioning to Light)
  static const Color sovereignNavy = sovereignBg;
  static const Color sovereignNavySurface = sovereignSurface;
  static const Color sovereignBlue = sovereignTeal;

  // Status Colors
  static const Color success = Color(0xFF22C55E);
  static const Color warning = Color(0xFFF59E0B);
  static const Color error = Color(0xFFEF4444);
  static const Color info = Color(0xFF007B8A);

  // Missing Aliases for Theme and Apps
  static const Color primary = sovereignOrange;
  static const Color primaryLight = Color(0xFFFFE6D5);
  static const Color primaryDark = Color(0xFFE66000);
  static const Color secondary = sovereignTeal;
  static const Color surfaceLight = sovereignSurface;
  static const Color backgroundLight = sovereignBg;
  static const Color textDark = sovereignText;
  static const Color textBody = sovereignTextVariant;
  static const Color textLight = Color(0xFF9E9E9E);

  // Web-matching semantic tokens
  static const Color shadowBase = Color(0x0A000000);
  static const Color glassOverlay = Color(0x33FFFFFF);
  static const Color cardBorder = Color(0x0D000000);
}
