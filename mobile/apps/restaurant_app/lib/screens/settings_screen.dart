import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:ui/ui.dart';
import 'package:core/core.dart';
import 'permission_status_page.dart';
import 'battery_optimization_guide.dart';
import 'set_location_screen.dart';
import 'gst_screen.dart';

class SettingsScreen extends ConsumerStatefulWidget {
  const SettingsScreen({super.key});

  @override
  ConsumerState<SettingsScreen> createState() => _SettingsScreenState();
}

class _SettingsScreenState extends ConsumerState<SettingsScreen> {
  int _selectedTab = 0;
  final _tabs = ['Profile', 'Compliance', 'Banking', 'Hours', 'GST'];
  bool _loading = true;
  bool _saving = false;
  bool _locationSet = false;
  bool _hasPendingPermissions = false;

  final _nameCtrl = TextEditingController();
  final _phoneCtrl = TextEditingController();
  final _descCtrl = TextEditingController();
  final _addressCtrl = TextEditingController();

  final _fssaiCtrl = TextEditingController();
  final _gstCtrl = TextEditingController();
  final _panCtrl = TextEditingController();

  final _accHolderCtrl = TextEditingController();
  final _bankNameCtrl = TextEditingController();
  final _accNumCtrl = TextEditingController();
  final _ifscCtrl = TextEditingController();

  // Operating hours state
  final List<Map<String, dynamic>> _hours = List.generate(7, (i) => {
    'day': ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'][i],
    'open': '09:00',
    'close': '23:00',
    'is_open': true,
  });

  @override
  void initState() {
    super.initState();
    _loadProfile();
    _checkPermissions();
  }

  Future<void> _checkPermissions() async {
    final pending = await hasPendingPermissions();
    if (mounted) setState(() => _hasPendingPermissions = pending);
  }

  @override
  void dispose() {
    _nameCtrl.dispose();
    _phoneCtrl.dispose();
    _descCtrl.dispose();
    _addressCtrl.dispose();
    _fssaiCtrl.dispose();
    _gstCtrl.dispose();
    _panCtrl.dispose();
    _accHolderCtrl.dispose();
    _bankNameCtrl.dispose();
    _accNumCtrl.dispose();
    _ifscCtrl.dispose();
    super.dispose();
  }

  Future<void> _loadProfile() async {
    try {
      final profile = await ref.read(vendorRepositoryProvider).getProfile();
      final r = profile['restaurant'] as Map<String, dynamic>? ?? {};
      _nameCtrl.text = r['name']?.toString() ?? '';
      _phoneCtrl.text = r['phone']?.toString() ?? '';
      _descCtrl.text = r['description']?.toString() ?? '';
      _addressCtrl.text = r['address']?.toString() ?? '';
      _fssaiCtrl.text = r['fssai_number']?.toString() ?? '';
      _gstCtrl.text = r['gst_number']?.toString() ?? '';
      _panCtrl.text = r['pan_number']?.toString() ?? '';
      _accHolderCtrl.text = r['bank_holder_name']?.toString() ?? '';
      _bankNameCtrl.text = r['bank_name']?.toString() ?? '';
      _accNumCtrl.text = r['bank_account_number']?.toString() ?? '';
      _ifscCtrl.text = r['bank_ifsc']?.toString() ?? '';
      _locationSet = r['latitude'] != null && r['longitude'] != null;

      // Load operating hours
      if (r['operating_hours'] != null) {
        final hoursData = r['operating_hours'] as Map<String, dynamic>? ?? {};
        for (var i = 0; i < _hours.length; i++) {
          final dayData = hoursData[_hours[i]['day']] as Map<String, dynamic>?;
          if (dayData != null) {
            _hours[i]['open'] = dayData['open'] ?? '09:00';
            _hours[i]['close'] = dayData['close'] ?? '23:00';
            _hours[i]['is_open'] = dayData['is_open'] ?? true;
          }
        }
      }

      if (mounted) setState(() => _loading = false);
    } catch (_) {
      if (mounted) setState(() => _loading = false);
    }
  }

  Future<void> _saveHours() async {
    setState(() => _saving = true);
    try {
      final hoursMap = <String, dynamic>{};
      for (final h in _hours) {
        hoursMap[h['day']] = {
          'open': h['open'],
          'close': h['close'],
          'is_open': h['is_open'],
        };
      }
      await ref.read(vendorRepositoryProvider).updateOperatingHours(hoursMap);
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('Operating hours saved!')),
        );
      }
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('Failed to save hours.')),
        );
      }
    } finally {
      if (mounted) setState(() => _saving = false);
    }
  }

  Future<void> _saveProfile() async {
    setState(() => _saving = true);
    try {
      await ref.read(vendorRepositoryProvider).updateProfile({
        'name': _nameCtrl.text,
        'phone': _phoneCtrl.text,
        'description': _descCtrl.text,
        'address': _addressCtrl.text,
      });
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('Profile saved!')),
        );
      }
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('Failed to save profile.')),
        );
      }
    } finally {
      if (mounted) setState(() => _saving = false);
    }
  }

  Future<void> _saveCompliance() async {
    setState(() => _saving = true);
    try {
      await ref.read(vendorRepositoryProvider).updateProfile({
        'fssai_number': _fssaiCtrl.text,
        'gst_number': _gstCtrl.text,
        'pan_number': _panCtrl.text,
      });
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('Compliance info saved!')),
        );
      }
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('Failed to save.')),
        );
      }
    } finally {
      if (mounted) setState(() => _saving = false);
    }
  }

  Future<void> _saveBanking() async {
    setState(() => _saving = true);
    try {
      await ref.read(vendorRepositoryProvider).updateProfile({
        'bank_holder_name': _accHolderCtrl.text,
        'bank_name': _bankNameCtrl.text,
        'bank_account_number': _accNumCtrl.text,
        'bank_ifsc': _ifscCtrl.text,
      });
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('Banking info saved!')),
        );
      }
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('Failed to save.')),
        );
      }
    } finally {
      if (mounted) setState(() => _saving = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: const WeloAppBar(title: 'Settings'),
      body: _loading
          ? const Center(child: CircularProgressIndicator())
          : Column(
              children: [
                Container(
                  height: 44,
                  margin: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
                  child: ListView.builder(
                    scrollDirection: Axis.horizontal,
                    itemCount: _tabs.length,
                    itemBuilder: (context, index) {
                      return Padding(
                        padding: const EdgeInsets.only(right: 8),
                        child: WeloChip(
                          label: _tabs[index],
                          isSelected: index == _selectedTab,
                          onTap: () => setState(() => _selectedTab = index),
                        ),
                      );
                    },
                  ),
                ),
                Padding(
                  padding: const EdgeInsets.symmetric(horizontal: 16),
                  child: WeloCard(
                    padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
                    onTap: () {
                      Navigator.of(context).push(
                        MaterialPageRoute(builder: (_) => const PermissionStatusPage()),
                      );
                    },
                    child: Row(
                      children: [
                        Icon(Icons.notifications_active_outlined, size: 20, color: WeloColors.sovereignOrange),
                        const SizedBox(width: 12),
                        Expanded(
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              const Text('Notification Permissions', style: TextStyle(fontWeight: FontWeight.bold, fontSize: 14)),
                              Text('WhatsApp-call level alerts', style: TextStyle(fontSize: 12, color: WeloColors.textBody)),
                            ],
                          ),
                        ),
                        if (_hasPendingPermissions)
                          Container(
                            margin: const EdgeInsets.only(right: 8),
                            padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
                            decoration: BoxDecoration(
                              color: WeloColors.error.withValues(alpha: 0.098),
                              borderRadius: BorderRadius.circular(8),
                            ),
                            child: Text('⚠️ Pending', style: TextStyle(fontSize: 11, fontWeight: FontWeight.bold, color: WeloColors.error)),
                          ),
                        Icon(Icons.chevron_right, size: 20, color: WeloColors.textLight),
                      ],
                    ),
                  ),
                ),
                const SizedBox(height: 8),
                Expanded(
                  child: SingleChildScrollView(
                    padding: const EdgeInsets.all(16),
                    child: _buildTabContent(),
                  ),
                ),
              ],
            ),
    );
  }

  Widget _buildTabContent() {
    if (_selectedTab == 4) return const GstScreen();
    return switch (_selectedTab) {
      0 => _buildProfileTab(),
      1 => _buildComplianceTab(),
      2 => _buildBankingTab(),
      3 => _buildHoursTab(),
      _ => const SizedBox.shrink(),
    };
  }

  Widget _buildProfileTab() {
    return Column(
      children: [
        if (!_locationSet)
          Container(
            width: double.infinity,
            margin: const EdgeInsets.only(bottom: 12),
            child: OutlinedButton.icon(
              onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => const SetLocationScreen())).then((_) => _loadProfile()),
              icon: const Icon(Icons.location_on, size: 18),
              label: const Text('Set Restaurant Location'),
              style: OutlinedButton.styleFrom(foregroundColor: WeloColors.sovereignOrange),
            ),
          ),
        WeloTextField(label: 'Restaurant Name', controller: _nameCtrl),
        const SizedBox(height: 12),
        WeloTextField(label: 'Phone', controller: _phoneCtrl, keyboardType: TextInputType.phone),
        const SizedBox(height: 12),
        WeloTextField(label: 'Description', controller: _descCtrl, maxLines: 3),
        const SizedBox(height: 12),
        WeloTextField(label: 'Address', controller: _addressCtrl),
        const SizedBox(height: 20),
        WeloPrimaryButton(text: 'Save Profile', isLoading: _saving, onPressed: _saveProfile),
      ],
    );
  }

  Widget _buildComplianceTab() {
    return Column(
      children: [
        WeloTextField(label: 'FSSAI License', controller: _fssaiCtrl),
        const SizedBox(height: 12),
        WeloTextField(label: 'GST Number', controller: _gstCtrl),
        const SizedBox(height: 12),
        WeloTextField(label: 'PAN Number', controller: _panCtrl),
        const SizedBox(height: 20),
        WeloPrimaryButton(text: 'Save Compliance', isLoading: _saving, onPressed: _saveCompliance),
      ],
    );
  }

  Widget _buildBankingTab() {
    return Column(
      children: [
        WeloTextField(label: 'Account Holder', controller: _accHolderCtrl),
        const SizedBox(height: 12),
        WeloTextField(label: 'Bank Name', controller: _bankNameCtrl),
        const SizedBox(height: 12),
        WeloTextField(label: 'Account Number', controller: _accNumCtrl, keyboardType: TextInputType.number),
        const SizedBox(height: 12),
        WeloTextField(label: 'IFSC Code', controller: _ifscCtrl),
        const SizedBox(height: 20),
        WeloPrimaryButton(text: 'Save Banking', isLoading: _saving, onPressed: _saveBanking),
      ],
    );
  }

  Future<void> _pickTime(int dayIndex, bool isOpen) async {
    final current = _hours[dayIndex];
    final initial = TimeOfDay(
      hour: int.tryParse((isOpen ? current['open'] : current['close']).toString().split(':')[0]) ?? 9,
      minute: int.tryParse((isOpen ? current['open'] : current['close']).toString().split(':')[1]) ?? 0,
    );
    final picked = await showTimePicker(context: context, initialTime: initial);
    if (picked != null) {
      final formatted = '${picked.hour.toString().padLeft(2, '0')}:${picked.minute.toString().padLeft(2, '0')}';
      setState(() {
        if (isOpen) {
          _hours[dayIndex]['open'] = formatted;
        } else {
          _hours[dayIndex]['close'] = formatted;
        }
      });
    }
  }

  Widget _buildHoursTab() {
    return Column(
      children: [
        ..._hours.asMap().entries.map((entry) {
          final i = entry.key;
          final h = entry.value;
          final open = h['open'] as String;
          final close = h['close'] as String;
          final isOpen = h['is_open'] as bool;
          final day = h['day'] as String;

          return Padding(
            padding: const EdgeInsets.only(bottom: 12),
            child: WeloCard(
              padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
              child: Row(
                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                children: [
                  SizedBox(width: 40, child: Text(day, style: const TextStyle(fontWeight: FontWeight.w600))),
                  if (isOpen) ...[
                    GestureDetector(
                      onTap: () => _pickTime(i, true),
                      child: Text(_formatTime(open), style: const TextStyle(color: WeloColors.sovereignOrange, fontWeight: FontWeight.w600)),
                    ),
                    const SizedBox(width: 4),
                    const Icon(Icons.arrow_forward, size: 14, color: WeloColors.textLight),
                    const SizedBox(width: 4),
                    GestureDetector(
                      onTap: () => _pickTime(i, false),
                      child: Text(_formatTime(close), style: const TextStyle(color: WeloColors.sovereignOrange, fontWeight: FontWeight.w600)),
                    ),
                  ] else
                    const Text('Closed', style: TextStyle(color: WeloColors.textLight)),
                  Switch(
                    value: isOpen,
                    onChanged: (v) => setState(() => _hours[i]['is_open'] = v),
                  ),
                ],
              ),
            ),
          );
        }),
        const SizedBox(height: 12),
        WeloPrimaryButton(text: 'Save Hours', isLoading: _saving, onPressed: _saveHours),
      ],
    );
  }

  String _formatTime(String hhmm) {
    final parts = hhmm.split(':');
    if (parts.length != 2) return hhmm;
    final h = int.tryParse(parts[0]) ?? 0;
    final m = parts[1];
    final period = h >= 12 ? 'PM' : 'AM';
    final hour12 = h == 0 ? 12 : (h > 12 ? h - 12 : h);
    return '$hour12:$m $period';
  }
}
