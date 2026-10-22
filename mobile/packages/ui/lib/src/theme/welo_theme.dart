import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';

import 'welo_colors.dart';

class WeloTheme {
  static ThemeData get lightTheme {
    return ThemeData(
      useMaterial3: true,
      brightness: Brightness.light,
      colorScheme: ColorScheme.light(
        primary: WeloColors.primary,
        secondary: WeloColors.secondary,
        surface: WeloColors.surfaceLight,
        error: WeloColors.error,
        onPrimary: Colors.white,
        onSecondary: Colors.white,
        onSurface: WeloColors.textDark,
      ),
      scaffoldBackgroundColor: WeloColors.backgroundLight,
      textTheme: _textTheme(WeloColors.textDark, WeloColors.textBody),
      appBarTheme: _appBarTheme(WeloColors.surfaceLight, WeloColors.textDark),
      elevatedButtonTheme: _elevatedButtonTheme(),
      cardTheme: _cardTheme(WeloColors.surfaceLight),
    );
  }

  static ThemeData get darkTheme {
    return ThemeData(
      useMaterial3: true,
      brightness: Brightness.dark,
      colorScheme: ColorScheme.dark(
        primary: WeloColors.primary,
        secondary: WeloColors.secondary,
        surface: const Color(0xFF141A2B),
        error: const Color(0xFFFF6B6B),
        onPrimary: Colors.white,
        onSecondary: Colors.white,
        onSurface: const Color(0xFFE8E6E1),
      ),
      scaffoldBackgroundColor: const Color(0xFF0A0E1A),
      textTheme: _textTheme(const Color(0xFFE8E6E1), const Color(0xFFA8A6A1)),
      appBarTheme: _appBarTheme(const Color(0xFF141A2B), const Color(0xFFE8E6E1)),
      elevatedButtonTheme: _elevatedButtonTheme(),
      cardTheme: _cardTheme(const Color(0xFF141A2B)),
    );
  }

  static TextTheme _textTheme(Color primaryText, Color secondaryText) {
    return GoogleFonts.interTextTheme().copyWith(
      displayLarge: GoogleFonts.spaceGrotesk(fontSize: 32, fontWeight: FontWeight.w900, color: primaryText, letterSpacing: -0.5),
      displayMedium: GoogleFonts.spaceGrotesk(fontSize: 28, fontWeight: FontWeight.w900, color: primaryText, letterSpacing: -0.5),
      displaySmall: GoogleFonts.spaceGrotesk(fontSize: 24, fontWeight: FontWeight.w900, color: primaryText),
      titleLarge: GoogleFonts.playfairDisplay(fontSize: 22, fontWeight: FontWeight.w700, color: primaryText),
      titleMedium: GoogleFonts.playfairDisplay(fontSize: 18, fontWeight: FontWeight.w700, color: primaryText),
      titleSmall: GoogleFonts.spaceGrotesk(fontSize: 16, fontWeight: FontWeight.w700, color: primaryText),
      bodyLarge: GoogleFonts.inter(fontSize: 16, fontWeight: FontWeight.w400, color: primaryText),
      bodyMedium: GoogleFonts.inter(fontSize: 14, fontWeight: FontWeight.w400, color: primaryText),
      bodySmall: GoogleFonts.inter(fontSize: 12, fontWeight: FontWeight.w500, color: secondaryText),
      labelLarge: GoogleFonts.spaceGrotesk(fontSize: 12, fontWeight: FontWeight.w900, letterSpacing: 0.1, color: primaryText),
      labelMedium: GoogleFonts.spaceGrotesk(fontSize: 11, fontWeight: FontWeight.w900, letterSpacing: 0.1, color: primaryText),
      labelSmall: GoogleFonts.spaceGrotesk(fontSize: 10, fontWeight: FontWeight.w700, letterSpacing: 0.15, color: secondaryText),
    );
  }

  static AppBarTheme _appBarTheme(Color bg, Color text) {
    return AppBarTheme(
      backgroundColor: bg,
      elevation: 0,
      centerTitle: true,
      iconTheme: IconThemeData(color: text),
      titleTextStyle: GoogleFonts.spaceGrotesk(fontSize: 16, fontWeight: FontWeight.w900, letterSpacing: 0.05, color: text),
    );
  }

  static ElevatedButtonThemeData _elevatedButtonTheme() {
    return ElevatedButtonThemeData(
      style: ElevatedButton.styleFrom(
        backgroundColor: WeloColors.primary,
        foregroundColor: Colors.white,
        textStyle: GoogleFonts.spaceGrotesk(fontSize: 14, fontWeight: FontWeight.w700, letterSpacing: 0.15),
        padding: const EdgeInsets.symmetric(vertical: 18, horizontal: 32),
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(20)),
        elevation: 0,
        shadowColor: WeloColors.primary.withValues(alpha: 0.2),
      ),
    );
  }

  static CardThemeData _cardTheme(Color bg) {
    return CardThemeData(
      color: bg,
      elevation: 0,
      shadowColor: Colors.transparent,
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(24), side: const BorderSide(color: WeloColors.cardBorder, width: 1)),
      margin: EdgeInsets.zero,
    );
  }
}
