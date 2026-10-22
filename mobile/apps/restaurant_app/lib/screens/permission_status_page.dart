import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:ui/ui.dart';
import 'package:core/core.dart';

class PermissionStatusPage extends StatefulWidget {
  const PermissionStatusPage({super.key});

  @override
  State<PermissionStatusPage> createState() => _PermissionStatusPageState();
}

class _PermissionStatusPageState extends State<PermissionStatusPage> {
  PermissionCheckResult? _result;
  bool _loading = true;

  @override
  void initState() {
    super.initState();
    _refresh();
  }

  Future<void> _refresh() async {
    setState(() => _loading = true);
    final result = await PermissionHelper.checkAll(isRider: false);
    if (mounted) {
      setState(() {
        _result = result;
        _loading = false;
      });
    }
  }

  Future<void> _requestPermission(PermissionInfo info) async {
    final granted = await info.request();
    if (granted) {
      _refresh();
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: WeloAppBar(
        title: 'Notification Permissions',
        actions: [
          IconButton(
            icon: const Icon(Icons.refresh),
            onPressed: _refresh,
          ),
        ],
      ),
      body: _loading
          ? const Center(child: CircularProgressIndicator(color: WeloColors.sovereignOrange))
          : RefreshIndicator(
              onRefresh: _refresh,
              color: WeloColors.sovereignOrange,
              child: ListView(
                padding: const EdgeInsets.all(16),
                physics: const AlwaysScrollableScrollPhysics(),
                children: [
                  Text(
                    'Grant these permissions for WhatsApp-call-level notifications even when the app is closed:',
                    style: TextStyle(fontSize: 13, color: WeloColors.textBody),
                  ),
                  const SizedBox(height: 16),
                  ..._buildPermissionCards(),
                  const SizedBox(height: 12),
                  _buildOemAutoStartCard(),
                  const SizedBox(height: 20),
                  _buildTestNotificationButton(),
                ],
              ),
            ),
    );
  }

  List<Widget> _buildPermissionCards() {
    if (_result == null) return [];
    final permissions = _result!.allPermissions;
    return permissions.map((info) {
      final isGranted = _result!.granted.contains(info);
      return Padding(
        padding: const EdgeInsets.only(bottom: 12),
        child: WeloCard(
          padding: const EdgeInsets.all(16),
          child: Row(
            children: [
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Row(
                      children: [
                        Icon(
                          isGranted ? Icons.check_circle : Icons.warning_amber_rounded,
                          size: 20,
                          color: isGranted ? WeloColors.success : WeloColors.warning,
                        ),
                        const SizedBox(width: 8),
                        Expanded(
                          child: Text(
                            info.label,
                            style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 14),
                          ),
                        ),
                      ],
                    ),
                    const SizedBox(height: 4),
                    Text(
                      info.description,
                      style: TextStyle(fontSize: 12, color: WeloColors.textBody),
                    ),
                  ],
                ),
              ),
              const SizedBox(width: 12),
              isGranted
                ? Container(
                    padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
                    decoration: BoxDecoration(
                      color: WeloColors.success.withValues(alpha: 0.078),
                      borderRadius: BorderRadius.circular(12),
                    ),
                    child: Text('Granted', style: TextStyle(fontSize: 11, color: WeloColors.success, fontWeight: FontWeight.w600)),
                  )
                : TextButton(
                    onPressed: () => _requestPermission(info),
                    style: TextButton.styleFrom(foregroundColor: WeloColors.sovereignOrange),
                    child: const Text('Allow', style: TextStyle(fontWeight: FontWeight.w600)),
                  ),
            ],
          ),
        ),
      );
    }).toList();
  }

  Widget _buildOemAutoStartCard() {
    return WeloCard(
      padding: const EdgeInsets.all(16),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Icon(Icons.power_settings_new, size: 20, color: WeloColors.sovereignOrange),
              const SizedBox(width: 8),
              Text('Auto-Start', style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 14)),
            ],
          ),
          const SizedBox(height: 6),
          Text(
            PermissionHelper.hasOemAutoStart
              ? 'Your ${PermissionHelper.detectedOem.toUpperCase()} device has an auto-start manager. Enable this to ensure the app receives orders after a reboot.'
              : 'Enable auto-start in your device settings to keep the app running in the background.',
            style: TextStyle(fontSize: 12, color: WeloColors.textBody),
          ),
          const SizedBox(height: 10),
          SizedBox(
            width: double.infinity,
            child: OutlinedButton.icon(
              onPressed: PermissionHelper.openAutoStartSettings,
              icon: const Icon(Icons.open_in_new, size: 16),
              label: Text(PermissionHelper.hasOemAutoStart
                ? 'Open ${PermissionHelper.detectedOem.toUpperCase()} Auto-Start'
                : 'Open App Settings'),
              style: OutlinedButton.styleFrom(
                foregroundColor: WeloColors.sovereignOrange,
                side: BorderSide(color: WeloColors.sovereignOrange),
              ),
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildTestNotificationButton() {
    return SizedBox(
      width: double.infinity,
      child: ElevatedButton.icon(
        onPressed: () {
          HapticFeedback.heavyImpact();
          ScaffoldMessenger.of(context).showSnackBar(
            const SnackBar(
              content: Text('Test notification sent via FCM. Check your device.'),
              behavior: SnackBarBehavior.floating,
            ),
          );
        },
        icon: const Icon(Icons.notifications_active),
        label: const Text('Send Test Notification (via FCM)'),
        style: ElevatedButton.styleFrom(
          backgroundColor: WeloColors.sovereignOrange,
          foregroundColor: Colors.white,
          padding: const EdgeInsets.symmetric(vertical: 14),
          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
        ),
      ),
    );
  }
}
