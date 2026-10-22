import 'package:flutter/material.dart';
import '../theme/welo_colors.dart';

class WeloDivider extends StatelessWidget {
  final double height;
  final double thickness;
  final Color? color;
  final EdgeInsetsGeometry padding;

  const WeloDivider({
    super.key,
    this.height = 24,
    this.thickness = 1,
    this.color,
    this.padding = const EdgeInsets.symmetric(horizontal: 16),
  });

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: padding,
      child: Divider(
        height: height,
        thickness: thickness,
        color: color ?? WeloColors.sovereignElevated,
      ),
    );
  }
}
