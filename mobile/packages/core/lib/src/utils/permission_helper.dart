import 'dart:io';
import 'package:flutter/material.dart';
import 'package:permission_handler/permission_handler.dart' as ph;

class PermissionInfo {
  final String key;
  final String label;
  final String description;
  final ph.Permission permission;
  final bool isCritical;

  const PermissionInfo({
    required this.key,
    required this.label,
    required this.description,
    required this.permission,
    this.isCritical = true,
  });

  Future<bool> get isGranted async => await permission.status.isGranted;

  Future<bool> request() async {
    final status = await permission.request();
    return status.isGranted;
  }
}

class PermissionCheckResult {
  final List<PermissionInfo> allPermissions;
  final List<PermissionInfo> granted;
  final List<PermissionInfo> denied;

  bool get allGranted => denied.isEmpty;
  bool get hasMissing => denied.isNotEmpty;

  const PermissionCheckResult({
    required this.allPermissions,
    required this.granted,
    required this.denied,
  });
}

class PermissionHelper {
  PermissionHelper._();

  static List<PermissionInfo> requiredPermissions({bool isRider = false}) {
    final list = <PermissionInfo>[
      PermissionInfo(
        key: 'notification',
        label: 'Notifications',
        description: 'Show order alerts in notification drawer',
        permission: ph.Permission.notification,
      ),
      PermissionInfo(
        key: 'overlay',
        label: 'Display Over Other Apps',
        description: 'Show full-screen lock-screen alerts for incoming orders',
        permission: ph.Permission.systemAlertWindow,
      ),
      PermissionInfo(
        key: 'alarm',
        label: 'Exact Alarm',
        description: 'Schedule alarms to restart background service (Android 14+)',
        permission: ph.Permission.scheduleExactAlarm,
      ),
    ];
    // Skip battery optimization check on Vivo — standard Android API doesn't
    // reflect Vivo's custom battery management, so it always reports as missing.
    if (detectedOem != 'vivo') {
      list.add(PermissionInfo(
        key: 'battery',
        label: 'Battery Optimization',
        description: 'Prevent the OS from killing the app when backgrounded',
        permission: ph.Permission.ignoreBatteryOptimizations,
      ));
    }
    if (isRider) {
      list.add(PermissionInfo(
        key: 'location',
        label: 'Location',
        description: 'Share your position with customers for live tracking',
        permission: ph.Permission.location,
        isCritical: true,
      ));
      list.add(PermissionInfo(
        key: 'background_location',
        label: 'Background Location',
        description: 'Keep sharing location even when app is closed',
        permission: ph.Permission.locationAlways,
        isCritical: true,
      ));
    }
    return list;
  }

  static Future<PermissionCheckResult> checkAll({bool isRider = false}) async {
    final permissions = requiredPermissions(isRider: isRider);

    final List<PermissionInfo> granted = [];
    final List<PermissionInfo> denied = [];

    for (final p in permissions) {
      final g = await p.isGranted;
      if (g) {
        granted.add(p);
      } else {
        denied.add(p);
      }
    }

    return PermissionCheckResult(
      allPermissions: permissions,
      granted: granted,
      denied: denied,
    );
  }

  static Future<bool> checkAndRequestPermissions({
    required bool isRider,
    required BuildContext context,
  }) async {
    final result = await checkAll(isRider: isRider);
    if (result.allGranted) return true;

    for (final p in result.denied) {
      await p.request();
    }
    // Re-check after requesting
    final finalResult = await checkAll(isRider: isRider);
    return finalResult.allGranted;
  }

  // --- OEM Auto-Start Detection ---

  static String get detectedOem {
    if (!Platform.isAndroid) return '';
    final version = Platform.operatingSystemVersion;
    final manufacturer = version.split('/').first.toLowerCase();
    if (manufacturer.contains('xiaomi') || manufacturer.contains('mi')) return 'xiaomi';
    if (manufacturer.contains('vivo')) return 'vivo';
    if (manufacturer.contains('oppo')) return 'oppo';
    if (manufacturer.contains('realme')) return 'realme';
    if (manufacturer.contains('oneplus')) return 'oneplus';
    return '';
  }

  static bool get hasOemAutoStart => detectedOem.isNotEmpty;

  static Future<void> openSystemAppSettings() async {
    await ph.openAppSettings();
  }

  /// Opens OEM auto-start settings if available, otherwise system settings.
  static Future<void> openAutoStartSettings() async {
    // On most devices, the standard app settings page contains auto-start/permissions
    await openSystemAppSettings();
  }
}
