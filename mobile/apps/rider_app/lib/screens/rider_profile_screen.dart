import 'dart:async';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:ui/ui.dart';
import 'package:core/core.dart';
import 'permission_status_page.dart';
import 'rider_kyc_screen.dart';

class RiderProfileScreen extends ConsumerWidget {
  const RiderProfileScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final authState = ref.watch(riderAuthProvider);
    final name = authState.name;
    final phone = authState.phone;
    final isVerified = authState.isVerified;
    final rating = authState.avgRating;
    final deliveries = authState.totalDeliveries;
    final vehicleType = authState.vehicleType;
    final vehicleNumber = authState.vehicleNumber;
    final verificationStatus = authState.riderData?['verification_status'] as String? ?? 'unverified';
    final partnerId = authState.riderData?['partner_id'] as String? ?? authState.riderData?['id']?.toString() ?? '';

    return Scaffold(
      appBar: const WeloAppBar(title: 'Profile'),
      body: RefreshIndicator(
        onRefresh: () => Future.delayed(const Duration(seconds: 1)),
        color: WeloColors.sovereignOrange,
        child: SingleChildScrollView(
          physics: const AlwaysScrollableScrollPhysics(),
          padding: const EdgeInsets.all(16),
          child: Column(
            children: [
              const SizedBox(height: 8),
              const CircleAvatar(
                radius: 40,
                backgroundColor: WeloColors.sovereignOrange,
                child: Icon(Icons.person, size: 40, color: Colors.white),
              ),
              const SizedBox(height: 12),
              Text(
                name,
                style: const TextStyle(fontSize: 22, fontWeight: FontWeight.bold),
              ),
              const SizedBox(height: 4),
              Text(
                phone.isNotEmpty ? '+91 $phone' : '',
                style: const TextStyle(color: WeloColors.textBody),
              ),
              const SizedBox(height: 8),
              _buildVerificationBadge(verificationStatus, isVerified),
              const SizedBox(height: 24),

              Row(
                children: [
                  _buildStatCard('Rating', rating > 0 ? rating.toStringAsFixed(1) : '—', Icons.star_rounded, WeloColors.sovereignGold),
                  const SizedBox(width: 12),
                  _buildStatCard('Deliveries', '$deliveries', Icons.check_circle_outline, WeloColors.success),
                  const SizedBox(width: 12),
                  _buildStatCard('Status', ref.watch(riderStateProvider).isOnline ? 'Online' : 'Offline', Icons.wifi_tethering, ref.watch(riderStateProvider).isOnline ? WeloColors.success : WeloColors.textBody),
                ],
              ),
              const SizedBox(height: 24),

              _buildSection(
                'Account',
                [
                  _buildInfoRow('Partner ID', 'KHA-$partnerId'),
                ],
              ),
              const SizedBox(height: 12),

              _buildSection(
                'Vehicle',
                [
                  _buildInfoRow('Type', vehicleType[0].toUpperCase() + vehicleType.substring(1)),
                  _buildInfoRow('Number', vehicleNumber),
                ],
              ),
              const SizedBox(height: 12),

              _buildSection(
                'KYC Status',
                [
                  _buildInfoRow('Status', _kycLabel(verificationStatus)),
                  if (verificationStatus == 'rejected')
                    _buildInfoRow('Reason', authState.riderData?['verification_notes'] as String? ?? ''),
                ],
              ),
              const SizedBox(height: 16),

              _buildSettingsTile(Icons.verified_user_outlined, 'KYC Documents', () {
                Navigator.of(context).push(
                  MaterialPageRoute(builder: (_) => const RiderKycScreen()),
                );
              }),
              _buildSettingsTile(Icons.person_outline, 'Edit Profile', () {}),
              _buildSettingsTile(Icons.directions_car_outlined, 'Vehicle Details', () {}),
              _buildSettingsTile(Icons.notifications_active_outlined, 'Notification Permissions', () {
                Navigator.of(context).push(
                  MaterialPageRoute(builder: (_) => const PermissionStatusPage()),
                );
              }),
              _buildSettingsTile(Icons.payment_outlined, 'Payment Settings', () {}),
              _buildSettingsTile(Icons.help_outline, 'Help & Support', () {}),
              _buildSettingsTile(Icons.logout, 'Logout', () {
                showDialog(
                  context: context,
                  builder: (ctx) => AlertDialog(
                    title: const Text('Logout'),
                    content: const Text('Are you sure you want to logout?'),
                    actions: [
                      TextButton(onPressed: () => Navigator.pop(ctx), child: const Text('Cancel')),
                      TextButton(
                        onPressed: () {
                          Navigator.pop(ctx);
                          ref.read(riderAuthProvider.notifier).logout();
                        },
                        child: const Text('Logout', style: TextStyle(color: WeloColors.error)),
                      ),
                    ],
                  ),
                );
              }, isDestructive: true),
            ],
          ),
        ),
      ),
    );
  }

  Widget _buildStatCard(String label, String value, IconData icon, Color color) {
    return Expanded(
      child: WeloCard(
        padding: const EdgeInsets.all(14),
        child: Column(
          children: [
            Icon(icon, color: color, size: 24),
            const SizedBox(height: 8),
            Text(value, style: const TextStyle(fontSize: 20, fontWeight: FontWeight.bold)),
            const SizedBox(height: 4),
            Text(label, style: const TextStyle(fontSize: 11, color: WeloColors.textBody)),
          ],
        ),
      ),
    );
  }

  Widget _buildSection(String title, List<Widget> children) {
    return WeloCard(
      padding: const EdgeInsets.all(16),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(title, style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 15)),
          const SizedBox(height: 12),
          ...children,
        ],
      ),
    );
  }

  Widget _buildInfoRow(String label, String value) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 8),
      child: Row(
        mainAxisAlignment: MainAxisAlignment.spaceBetween,
        children: [
          Text(label, style: const TextStyle(color: WeloColors.textBody, fontSize: 14)),
          Text(value, style: const TextStyle(fontWeight: FontWeight.w600, fontSize: 14)),
        ],
      ),
    );
  }

  String _kycLabel(String status) {
    switch (status) {
      case 'verified': return 'Verified ✓';
      case 'pending': return 'Under Review';
      case 'rejected': return 'Rejected — Re-upload';
      default: return 'Not Submitted';
    }
  }

  Widget _buildVerificationBadge(String verificationStatus, bool isVerified) {
    if (isVerified && verificationStatus == 'verified') {
      return Container(
        padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 6),
        decoration: BoxDecoration(
          color: WeloColors.success.withValues(alpha: 0.078),
          borderRadius: BorderRadius.circular(20),
        ),
        child: const Row(
          mainAxisSize: MainAxisSize.min,
          children: [
            Icon(Icons.verified, size: 14, color: WeloColors.success),
            SizedBox(width: 4),
            Text('Fully Verified', style: TextStyle(color: WeloColors.success, fontSize: 12, fontWeight: FontWeight.w600)),
          ],
        ),
      );
    }
    if (verificationStatus == 'pending') {
      return Container(
        padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 6),
        decoration: BoxDecoration(
          color: Colors.orange.withValues(alpha: 0.1),
          borderRadius: BorderRadius.circular(20),
        ),
        child: const Row(
          mainAxisSize: MainAxisSize.min,
          children: [
            Icon(Icons.access_time, size: 14, color: Colors.orange),
            SizedBox(width: 4),
            Text('KYC Pending', style: TextStyle(color: Colors.orange, fontSize: 12, fontWeight: FontWeight.w600)),
          ],
        ),
      );
    }
    if (verificationStatus == 'rejected') {
      return Container(
        padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 6),
        decoration: BoxDecoration(
          color: Colors.red.withValues(alpha: 0.1),
          borderRadius: BorderRadius.circular(20),
        ),
        child: const Row(
          mainAxisSize: MainAxisSize.min,
          children: [
            Icon(Icons.error_outline, size: 14, color: Colors.red),
            SizedBox(width: 4),
            Text('KYC Rejected', style: TextStyle(color: Colors.red, fontSize: 12, fontWeight: FontWeight.w600)),
          ],
        ),
      );
    }
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 6),
      decoration: BoxDecoration(
        color: Colors.grey.withValues(alpha: 0.1),
        borderRadius: BorderRadius.circular(20),
      ),
      child: const Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          Icon(Icons.pending_outlined, size: 14, color: Colors.grey),
          SizedBox(width: 4),
          Text('KYC Not Done', style: TextStyle(color: Colors.grey, fontSize: 12, fontWeight: FontWeight.w600)),
        ],
      ),
    );
  }

  Widget _buildSettingsTile(IconData icon, String title, VoidCallback onTap, {bool isDestructive = false}) {
    return WeloCard(
      margin: const EdgeInsets.only(bottom: 8),
      padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 14),
      onTap: onTap,
      child: Row(
        children: [
          Icon(
            icon,
            size: 20,
            color: isDestructive ? WeloColors.error : WeloColors.textBody,
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Text(
              title,
              style: TextStyle(
                fontSize: 15,
                color: isDestructive ? WeloColors.error : WeloColors.sovereignText,
              ),
            ),
          ),
          Icon(
            Icons.chevron_right,
            size: 20,
            color: WeloColors.textLight,
          ),
        ],
      ),
    );
  }
}
