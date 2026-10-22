import 'package:flutter/material.dart';
import 'package:core/core.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'permission_status_page.dart';

const _kPrefKey = 'permission_guide_shown';

Future<bool> hasPendingPermissions() async {
  final result = await PermissionHelper.checkAll(isRider: false);
  return !result.allGranted;
}

Future<void> showBatteryOptimizationGuide(BuildContext context, {bool force = false}) async {
  final result = await PermissionHelper.checkAll(isRider: false);
  if (result.allGranted) return;

  if (!force) {
    final prefs = await SharedPreferences.getInstance();
    final alreadyShown = prefs.getBool(_kPrefKey) ?? false;
    if (alreadyShown) return;
    await prefs.setBool(_kPrefKey, true);
  }

  if (!context.mounted) return;

  Navigator.of(context).push(
    MaterialPageRoute(builder: (_) => const PermissionStatusPage()),
  );
}
