import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:ui/ui.dart';
import 'package:core/core.dart';

class CampaignOptInScreen extends ConsumerStatefulWidget {
  const CampaignOptInScreen({super.key});

  @override
  ConsumerState<CampaignOptInScreen> createState() => _CampaignOptInScreenState();
}

class _CampaignOptInScreenState extends ConsumerState<CampaignOptInScreen> {
  List<dynamic> _campaigns = [];
  bool _isLoading = true;

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    setState(() => _isLoading = true);
    try {
      final campaigns = await ref.read(vendorRepositoryProvider).getAvailableCampaigns();
      if (mounted) setState(() { _campaigns = campaigns; _isLoading = false; });
    } catch (e) {
      if (mounted) setState(() => _isLoading = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: const WeloAppBar(title: 'Opt-In Campaigns'),
      body: _isLoading
          ? const Center(child: CircularProgressIndicator(color: WeloColors.sovereignOrange))
          : _campaigns.isEmpty
              ? Center(
                  child: Column(
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      Icon(Icons.campaign_outlined, size: 64, color: WeloColors.textLight),
                      const SizedBox(height: 16),
                      const Text('No active campaigns available'),
                    ],
                  ),
                )
              : RefreshIndicator(
                  onRefresh: _load,
                  child: ListView.builder(
                    padding: const EdgeInsets.all(16),
                    itemCount: _campaigns.length,
                    itemBuilder: (context, index) => _buildCampaignCard(_campaigns[index]),
                  ),
                ),
    );
  }

  Widget _buildCampaignCard(dynamic campaign) {
    final id = campaign['id'].toString();
    final name = campaign['name'] ?? '';
    final desc = campaign['description'] ?? '';
    final discountType = campaign['discount_type'] ?? 'flat';
    final discountValue = (campaign['discount_value'] ?? 0).toString();
    final maxDiscount = campaign['max_discount'];
    final shareRestaurant = (campaign['share_restaurant'] ?? 0).toString();
    final sharePlatform = (campaign['share_platform'] ?? 0).toString();
    final minOrder = (campaign['min_order_value'] ?? 0).toString();
    final optedIn = campaign['opted_in'] == true || campaign['opted_in'] == 1;

    return Card(
      margin: const EdgeInsets.only(bottom: 12),
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                Container(
                  padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
                  decoration: BoxDecoration(
                    color: optedIn ? Colors.green.shade600 : Colors.orange,
                    borderRadius: BorderRadius.circular(8),
                  ),
                  child: Text(optedIn ? 'OPTED IN' : 'AVAILABLE',
                    style: const TextStyle(color: Colors.white, fontSize: 10, fontWeight: FontWeight.bold)),
                ),
                const SizedBox(width: 12),
                Expanded(child: Text(name, style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 15))),
              ],
            ),
            if (desc.isNotEmpty) ...[
              const SizedBox(height: 4),
              Text(desc, style: const TextStyle(color: Colors.black54, fontSize: 12)),
            ],
            const SizedBox(height: 12),
            Container(
              padding: const EdgeInsets.all(12),
              decoration: BoxDecoration(color: Colors.grey.shade50, borderRadius: BorderRadius.circular(12)),
              child: Column(
                children: [
                  _row('Discount', '${discountType == "percentage" ? "$discountValue%" : "₹$discountValue"}'),
                  if (maxDiscount != null) _row('Max Discount', '₹$maxDiscount'),
                  if (minOrder != '0') _row('Min Order', '₹$minOrder'),
                  _row('You Bear', '$shareRestaurant%', valueColor: Colors.red),
                  _row('Platform Bears', '$sharePlatform%', valueColor: Colors.green),
                ],
              ),
            ),
            const SizedBox(height: 12),
            SizedBox(
              width: double.infinity,
              child: ElevatedButton(
                style: ElevatedButton.styleFrom(
                  backgroundColor: optedIn ? Colors.red.shade100 : Colors.green.shade600,
                  foregroundColor: optedIn ? Colors.red.shade900 : Colors.white,
                  shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                ),
                onPressed: () async {
                  try {
                    final repo = ref.read(vendorRepositoryProvider);
                    if (optedIn) {
                      await repo.optOutCampaign(id);
                    } else {
                      final confirm = await showDialog<bool>(
                        context: context,
                        builder: (ctx) => AlertDialog(
                          title: const Text('Acknowledge Discount Share'),
                          content: Text('You will bear $shareRestaurant% of the discount cost for this campaign. Continue?'),
                          actions: [
                            TextButton(onPressed: () => Navigator.pop(ctx, false), child: const Text('Cancel')),
                            ElevatedButton(onPressed: () => Navigator.pop(ctx, true), child: const Text('Confirm & Opt In')),
                          ],
                        ),
                      );
                      if (confirm != true) return;
                      await repo.optInCampaign(id);
                    }
                    _load();
                  } catch (e) {
                    if (mounted) ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text('Failed: $e')));
                  }
                },
                child: Text(optedIn ? 'Opt Out' : 'Opt In', style: const TextStyle(fontWeight: FontWeight.bold)),
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _row(String label, String value, {Color? valueColor}) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 2),
      child: Row(
        mainAxisAlignment: MainAxisAlignment.spaceBetween,
        children: [
          Text(label, style: const TextStyle(fontSize: 12, color: Colors.black54)),
          Text(value, style: TextStyle(fontSize: 12, fontWeight: FontWeight.bold, color: valueColor ?? Colors.black)),
        ],
      ),
    );
  }
}
