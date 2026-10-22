import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:ui/ui.dart';
import 'package:core/core.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'permission_status_page.dart';
import 'set_location_screen.dart';

class OnlineToggleScreen extends ConsumerStatefulWidget {
  const OnlineToggleScreen({super.key});

  @override
  ConsumerState<OnlineToggleScreen> createState() => _OnlineToggleScreenState();
}

class _OnlineToggleScreenState extends ConsumerState<OnlineToggleScreen> {
  bool _isOnline = true;
  bool _isLoading = true;
  bool _permissionsLoading = true;
  bool _locationSet = false;
  bool _autoAccept = false;
  PermissionCheckResult? _permResult;

  @override
  void initState() {
    super.initState();
    _loadData();
    _checkPermissions();
  }

  Future<void> _checkPermissions() async {
    setState(() => _permissionsLoading = true);
    final result = await PermissionHelper.checkAll(isRider: false);
    if (mounted) {
      setState(() {
        _permResult = result;
        _permissionsLoading = false;
      });
    }
  }

  Future<void> _loadData() async {
    setState(() => _isLoading = true);
    try {
      final vendorRepo = ref.read(vendorRepositoryProvider);
      final profile = await vendorRepo.getProfile();
      if (mounted) {
        final rest = profile['restaurant'] as Map<String, dynamic>?;
        setState(() {
          _isOnline = rest?['is_accepting_orders'] == 1 || rest?['is_accepting_orders'] == true;
          _autoAccept = rest?['auto_accept_orders'] == 1 || rest?['auto_accept_orders'] == true;
          _locationSet = rest?['latitude'] != null && rest?['longitude'] != null;
          _isLoading = false;
        });
        if (_isOnline) {
          BackgroundServiceManager.start('vendor');
        } else {
          BackgroundServiceManager.stop();
        }
      }
    } catch (e) {
      if (mounted) setState(() => _isLoading = false);
    }
  }

  Future<void> _handleToggleAutoAccept(bool val) async {
    setState(() => _autoAccept = val);
    final prefs = await SharedPreferences.getInstance();
    await prefs.setBool('vendor_auto_accept', val);
    try {
      final vendorRepo = ref.read(vendorRepositoryProvider);
      await vendorRepo.updateProfile({'auto_accept_orders': val});
    } catch (_) {}
  }

  Future<void> _handleToggle(bool val) async {
    if (!val) {
      _showOfflineDialog();
      return;
    }

    if (!_locationSet) {
      final set = await Navigator.push<bool>(
        context,
        MaterialPageRoute(builder: (_) => const SetLocationScreen()),
      );
      if (set != true) return;
      _loadData();
      return;
    }

    await PermissionHelper.checkAndRequestPermissions(
      isRider: false,
      context: context,
    );

    setState(() => _isOnline = true);
    try {
      final vendorRepo = ref.read(vendorRepositoryProvider);
      await vendorRepo.resumeOrders();
      _loadData();
      _checkPermissions();
    } catch (e) {
      setState(() => _isOnline = false);
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('Failed to update status.')),
        );
      }
    }
  }

  void _showOfflineDialog() {
    showDialog(
      context: context,
      builder: (context) => Dialog(
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(24)),
        child: Padding(
          padding: const EdgeInsets.all(24),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Row(
                children: [
                  Container(
                    padding: const EdgeInsets.all(8),
                    decoration: BoxDecoration(
                      color: WeloColors.error.withValues(alpha: 0.078),
                      shape: BoxShape.circle,
                    ),
                    child: const Icon(
                      Icons.power_settings_new_rounded,
                      color: WeloColors.error,
                      size: 24,
                    ),
                  ),
                  const SizedBox(width: 12),
                  const Text(
                    'Go Offline Options',
                    style: TextStyle(
                      fontWeight: FontWeight.bold,
                      fontSize: 18,
                    ),
                  ),
                ],
              ),
              const SizedBox(height: 16),
              const Text(
                'Choose how long you want to remain offline:',
                style: TextStyle(color: Colors.black54, fontSize: 13),
              ),
              const SizedBox(height: 20),
              
              _buildOfflineOptionCard(
                title: 'Go Offline for 2 Hours',
                subtitle: 'Temporary break. Auto-opens after 2 hours.',
                icon: Icons.timer_outlined,
                color: WeloColors.sovereignOrange,
                onTap: () async {
                  Navigator.pop(context);
                  await _goOffline('2_hours');
                },
              ),
              const SizedBox(height: 10),
              _buildOfflineOptionCard(
                title: 'Till Day End',
                subtitle: 'Will auto-reset and open at 6:00 AM tomorrow.',
                icon: Icons.brightness_3_outlined,
                color: Colors.indigo,
                onTap: () async {
                  Navigator.pop(context);
                  await _goOffline('till_day_end');
                },
              ),
              const SizedBox(height: 10),
              _buildOfflineOptionCard(
                title: 'Till I Go Online Manually',
                subtitle: 'Stays offline indefinitely. Will NOT reset at 6:00 AM.',
                icon: Icons.do_not_disturb_on_total_silence_rounded,
                color: WeloColors.error,
                onTap: () async {
                  Navigator.pop(context);
                  await _goOffline('till_manual');
                },
              ),
              const SizedBox(height: 10),
              _buildOfflineOptionCard(
                title: 'Till Custom Date & Time',
                subtitle: 'Select a specific date and time to auto-resume.',
                icon: Icons.date_range_outlined,
                color: WeloColors.sovereignTeal,
                onTap: () async {
                  Navigator.pop(context);
                  await _selectCustomDateTime();
                },
              ),
              
              const SizedBox(height: 20),
              Row(
                mainAxisAlignment: MainAxisAlignment.end,
                children: [
                  TextButton(
                    onPressed: () => Navigator.pop(context),
                    child: const Text('Cancel', style: TextStyle(fontWeight: FontWeight.bold, color: Colors.grey)),
                  ),
                ],
              ),
            ],
          ),
        ),
      ),
    );
  }

  Widget _buildOfflineOptionCard({
    required String title,
    required String subtitle,
    required IconData icon,
    required Color color,
    required VoidCallback onTap,
  }) {
    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(12),
      child: Container(
        padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
        decoration: BoxDecoration(
          border: Border.all(color: Colors.grey.shade200),
          borderRadius: BorderRadius.circular(12),
        ),
        child: Row(
          children: [
            Icon(icon, color: color, size: 20),
            const SizedBox(width: 10),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(title, style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 12)),
                  const SizedBox(height: 1),
                  Text(subtitle, style: const TextStyle(fontSize: 9, color: Colors.black38)),
                ],
              ),
            ),
            const Icon(Icons.chevron_right, color: Colors.grey, size: 16),
          ],
        ),
      ),
    );
  }

  Future<void> _goOffline(String option, {String? customDatetime}) async {
    setState(() => _isLoading = true);
    try {
      final vendorRepo = ref.read(vendorRepositoryProvider);
      await vendorRepo.goOffline(option, customDatetime: customDatetime);
      await _loadData();
    } catch (_) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('Failed to update status.')),
        );
      }
    } finally {
      if (mounted) setState(() => _isLoading = false);
    }
  }

  Future<void> _selectCustomDateTime() async {
    final date = await showDatePicker(
      context: context,
      initialDate: DateTime.now().add(const Duration(days: 1)),
      firstDate: DateTime.now(),
      lastDate: DateTime.now().add(const Duration(days: 30)),
    );
    if (date == null) return;

    if (!mounted) return;
    final time = await showTimePicker(
      context: context,
      initialTime: const TimeOfDay(hour: 0, minute: 0),
    );
    if (time == null) return;

    final selectedDateTime = DateTime(
      date.year,
      date.month,
      date.day,
      time.hour,
      time.minute,
    );

    if (selectedDateTime.isBefore(DateTime.now())) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('Please select a future time.')),
        );
      }
      return;
    }

    await _goOffline('custom', customDatetime: selectedDateTime.toIso8601String());
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: const WeloAppBar(title: 'Restaurant Status'),
      body: _isLoading
          ? const Center(child: CircularProgressIndicator(color: WeloColors.sovereignOrange))
          : RefreshIndicator(
              onRefresh: _loadData,
              color: WeloColors.sovereignOrange,
              child: ListView(
                padding: const EdgeInsets.all(16),
                physics: const AlwaysScrollableScrollPhysics(),
                children: [
                  if (!_isLoading && !_locationSet)
                    _buildLocationBanner(),
                  if (!_permissionsLoading && _permResult != null && _permResult!.hasMissing)
                    _buildPermissionBanner(),
                  WeloCard(
                    padding: const EdgeInsets.all(24),
                    child: Column(
                      children: [
                        Container(
                          width: 80,
                          height: 80,
                          decoration: BoxDecoration(
                            color: _isOnline ? WeloColors.success.withValues(alpha: 0.078) : WeloColors.error.withValues(alpha: 0.078),
                            shape: BoxShape.circle,
                          ),
                          child: Icon(
                            _isOnline ? Icons.check_circle : Icons.cancel,
                            size: 48,
                            color: _isOnline ? WeloColors.success : WeloColors.error,
                          ),
                        ),
                        const SizedBox(height: 16),
                        Text(
                          _isOnline ? 'Restaurant is Open' : 'Restaurant is Closed',
                          style: TextStyle(
                            fontSize: 20,
                            fontWeight: FontWeight.bold,
                            color: _isOnline ? WeloColors.success : WeloColors.error,
                          ),
                        ),
                        const SizedBox(height: 8),
                        Text(
                          _isOnline ? 'Accepting new orders' : 'Not accepting orders',
                          style: const TextStyle(color: WeloColors.textBody),
                        ),
                        const SizedBox(height: 24),
                        SwitchListTile(
                          title: const Text('Online Status'),
                          value: _isOnline,
                          activeThumbColor: WeloColors.success,
                          onChanged: _handleToggle,
                        ),
                      ],
                    ),
                  ),
                  const SizedBox(height: 16),
                  WeloCard(
                    padding: const EdgeInsets.all(16),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        const Text('Auto-Accept Orders', style: TextStyle(fontWeight: FontWeight.bold, fontSize: 15)),
                        const SizedBox(height: 4),
                        const Text('Automatically accept incoming orders without manual confirmation', style: TextStyle(color: WeloColors.textBody, fontSize: 13)),
                        const SizedBox(height: 8),
                        SwitchListTile(
                          title: const Text('Enable'),
                          value: _autoAccept,
                          activeThumbColor: WeloColors.success,
                          onChanged: _handleToggleAutoAccept,
                          contentPadding: EdgeInsets.zero,
                        ),
                      ],
                    ),
                  ),
                ],
              ),
            ),
    );
  }

  Widget _buildLocationBanner() {
    return Padding(
      padding: const EdgeInsets.only(bottom: 12),
      child: Material(
        color: WeloColors.error.withValues(alpha: 0.078),
        borderRadius: BorderRadius.circular(12),
        child: InkWell(
          borderRadius: BorderRadius.circular(12),
          onTap: () async {
            final set = await Navigator.push<bool>(
              context,
              MaterialPageRoute(builder: (_) => const SetLocationScreen()),
            );
            if (set == true) _loadData();
          },
          child: Padding(
            padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
            child: Row(
              children: [
                const Icon(Icons.location_off, color: WeloColors.error, size: 22),
                const SizedBox(width: 12),
                const Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        'Location not set',
                        style: TextStyle(fontWeight: FontWeight.bold, fontSize: 13, color: WeloColors.error),
                      ),
                      SizedBox(height: 2),
                      Text(
                        'Set your restaurant location on the map before going online.',
                        style: TextStyle(fontSize: 12, color: WeloColors.textBody),
                      ),
                    ],
                  ),
                ),
                Icon(Icons.chevron_right, color: WeloColors.textLight),
              ],
            ),
          ),
        ),
      ),
    );
  }

  Widget _buildPermissionBanner() {
    final count = _permResult!.denied.length;
    return Padding(
      padding: const EdgeInsets.only(bottom: 12),
      child: Material(
        color: WeloColors.warning.withValues(alpha: 0.078),
        borderRadius: BorderRadius.circular(12),
        child: InkWell(
          borderRadius: BorderRadius.circular(12),
          onTap: () {
            Navigator.of(context).push(
              MaterialPageRoute(builder: (_) => const PermissionStatusPage()),
            ).then((_) => _checkPermissions());
          },
          child: Padding(
            padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
            child: Row(
              children: [
                Icon(Icons.warning_amber_rounded, color: WeloColors.warning, size: 22),
                const SizedBox(width: 12),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        '$count permission${count > 1 ? 's' : ''} not granted',
                        style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 13),
                      ),
                      const SizedBox(height: 2),
                      Text(
                        'Notifications may not work when app is closed. Tap to fix.',
                        style: TextStyle(fontSize: 12, color: WeloColors.textBody),
                      ),
                    ],
                  ),
                ),
                Icon(Icons.chevron_right, color: WeloColors.textLight),
              ],
            ),
          ),
        ),
      ),
    );
  }
}
