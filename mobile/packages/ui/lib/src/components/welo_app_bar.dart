import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';
import '../theme/welo_colors.dart';

class WeloAppBar extends StatelessWidget implements PreferredSizeWidget {
  final String title;
  final bool showBack;
  final VoidCallback? onBackPressed;
  final List<Widget>? actions;
  final Widget? flexibleSpace;
  final Color? backgroundColor;
  final double elevation;

  const WeloAppBar({
    super.key,
    required this.title,
    this.showBack = false,
    this.onBackPressed,
    this.actions,
    this.flexibleSpace,
    this.backgroundColor,
    this.elevation = 0,
  });

  @override
  Size get preferredSize => const Size.fromHeight(kToolbarHeight);

  @override
  Widget build(BuildContext context) {
    return AppBar(
      backgroundColor: backgroundColor ?? WeloColors.sovereignSurface,
      elevation: elevation,
      centerTitle: true,
      leading: showBack
          ? IconButton(
              icon: const Icon(Icons.arrow_back_ios_new_rounded, size: 20),
              onPressed: onBackPressed ?? () => Navigator.pop(context),
            )
          : null,
      title: Text(
        title.toUpperCase(),
        style: GoogleFonts.spaceGrotesk(
          fontSize: 14,
          fontWeight: FontWeight.w900,
          letterSpacing: 1.5,
          color: WeloColors.sovereignText,
        ),
      ),
      actions: actions,
      flexibleSpace: flexibleSpace,
    );
  }
}
