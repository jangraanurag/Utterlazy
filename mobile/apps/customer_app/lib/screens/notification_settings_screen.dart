import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:ui/ui.dart';
import 'package:core/core.dart';

class NotificationSettingsScreen extends ConsumerStatefulWidget {
  const NotificationSettingsScreen({super.key});

  @override
  ConsumerState<NotificationSettingsScreen> createState() => _NotificationSettingsScreenState();
}

class _NotificationSettingsScreenState extends ConsumerState<NotificationSettingsScreen> {
  bool orderUpdates = true;
  bool offers = true;
  bool promotions = true;
  bool newRestaurants = true;
  bool ratingReminders = true;
  bool _isLoading = true;

  @override
  void initState() {
    super.initState();
    _loadPreferences();
  }

  Future<void> _loadPreferences() async {
    try {
      final apiClient = ref.read(apiClientProvider);
      final res = await apiClient.dio.get('/notification-preferences');
      final data = res.data['preferences'] ?? res.data;
      if (mounted && data is Map) {
        setState(() {
          orderUpdates = data['order_updates'] ?? true;
          offers = data['offers'] ?? true;
          promotions = data['promotions'] ?? true;
          newRestaurants = data['new_restaurants'] ?? true;
          ratingReminders = data['rating_reminders'] ?? true;
          _isLoading = false;
        });
      } else {
        if (mounted) setState(() => _isLoading = false);
      }
    } catch (e) {
      if (mounted) setState(() => _isLoading = false);
    }
  }

  Future<void> _savePreferences() async {
    try {
      final apiClient = ref.read(apiClientProvider);
      await apiClient.dio.patch('/notification-preferences', data: {
        'order_updates': orderUpdates,
        'offers': offers,
        'promotions': promotions,
        'new_restaurants': newRestaurants,
        'rating_reminders': ratingReminders,
      });
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('Preferences saved!'), backgroundColor: Colors.green),
        );
      }
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text('Failed to save: $e'), backgroundColor: Colors.red),
        );
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('Notification Settings'),
        actions: [
          TextButton(
            onPressed: _savePreferences,
            child: const Text('SAVE', style: TextStyle(fontWeight: FontWeight.bold)),
          ),
        ],
      ),
      body: _isLoading
          ? const Center(child: CircularProgressIndicator(color: WeloColors.sovereignOrange))
          : ListView(
              padding: const EdgeInsets.all(16),
              children: [
                _buildToggle('Order Updates', 'Get notified about order status changes', orderUpdates, (v) => setState(() => orderUpdates = v)),
                _buildToggle('Offers & Deals', 'Receive notifications about offers and discounts', offers, (v) => setState(() => offers = v)),
                _buildToggle('Promotions', 'Get updates about promotions and campaigns', promotions, (v) => setState(() => promotions = v)),
                _buildToggle('New Restaurants', 'Be notified when new restaurants join UtterLazy', newRestaurants, (v) => setState(() => newRestaurants = v)),
                _buildToggle('Rating Reminders', 'Reminders to rate your orders', ratingReminders, (v) => setState(() => ratingReminders = v)),
              ],
            ),
    );
  }

  Widget _buildToggle(String title, String subtitle, bool value, ValueChanged<bool> onChanged) {
    return WeloCard(
      margin: const EdgeInsets.only(bottom: 10),
      padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 14),
      child: Row(
        children: [
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(title, style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 15)),
                const SizedBox(height: 2),
                Text(subtitle, style: const TextStyle(color: WeloColors.textBody, fontSize: 12)),
              ],
            ),
          ),
          Switch(value: value, activeThumbColor: WeloColors.success, onChanged: onChanged),
        ],
      ),
    );
  }
}
