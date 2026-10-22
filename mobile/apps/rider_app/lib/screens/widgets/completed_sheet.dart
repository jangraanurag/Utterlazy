import 'dart:io';
import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:ui/ui.dart';
import 'package:core/core.dart';
import 'package:image_picker/image_picker.dart';

class CompletedSheet extends ConsumerStatefulWidget {
  const CompletedSheet({super.key});

  @override
  ConsumerState<CompletedSheet> createState() => _CompletedSheetState();
}

class _CompletedSheetState extends ConsumerState<CompletedSheet> {
  File? _deliveryPhoto;
  bool _uploading = false;

  Future<void> _takePhoto() async {
    final xFile = await ImagePicker().pickImage(source: ImageSource.camera, imageQuality: 80, maxWidth: 1920);
    if (xFile == null) return;
    setState(() => _deliveryPhoto = File(xFile.path));
    _uploadPhoto();
  }

  Future<void> _uploadPhoto() async {
    if (_deliveryPhoto == null || _uploading) return;
    setState(() => _uploading = true);
    try {
      final apiClient = ref.read(apiClientProvider);
      final riderState = ref.read(riderStateProvider);
      final orderId = riderState.currentOrder?.id;
      if (orderId == null) return;

      final formData = FormData.fromMap({
        'photo': await MultipartFile.fromFile(_deliveryPhoto!.path, filename: 'delivery.jpg'),
      });
      await apiClient.dio.post('/rider/orders/$orderId/delivery-photo', data: formData);
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('📸 Delivery photo saved!'), backgroundColor: Colors.green),
        );
      }
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text('Photo upload failed'), backgroundColor: Colors.red),
        );
      }
    }
    setState(() => _uploading = false);
  }

  @override
  Widget build(BuildContext context) {
    final riderState = ref.watch(riderStateProvider);
    final notifier = ref.read(riderStateProvider.notifier);

    return Container(
      padding: const EdgeInsets.all(24),
      decoration: const BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.vertical(top: Radius.circular(24)),
        boxShadow: [BoxShadow(color: Colors.black12, blurRadius: 10, offset: Offset(0, -5))],
      ),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          Container(width: 40, height: 4, decoration: BoxDecoration(color: WeloColors.sovereignElevated, borderRadius: BorderRadius.circular(2))),
          const SizedBox(height: 20),
          Container(width: 72, height: 72, decoration: BoxDecoration(color: WeloColors.success.withValues(alpha: 0.078), shape: BoxShape.circle),
            child: const Icon(Icons.check_circle, size: 48, color: WeloColors.success),
          ),
          const SizedBox(height: 16),
          const Text('Delivery Complete!', style: TextStyle(fontSize: 22, fontWeight: FontWeight.bold)),
          const SizedBox(height: 8),
          Text('Order #${riderState.currentOrder?.id ?? "—"} delivered successfully', style: const TextStyle(color: WeloColors.textBody)),
          const SizedBox(height: 16),

          // Delivery Photo Section
          Container(
            width: double.infinity,
            padding: const EdgeInsets.all(12),
            decoration: BoxDecoration(
              color: _deliveryPhoto != null ? Colors.green.withValues(alpha: 0.039) : WeloColors.sovereignElevated.withValues(alpha: 0.314),
              borderRadius: BorderRadius.circular(16),
              border: Border.all(color: _deliveryPhoto != null ? Colors.green.withValues(alpha: 0.235) : Colors.grey.withValues(alpha: 0.157)),
            ),
            child: _deliveryPhoto != null
                ? Row(
                    children: [
                      ClipRRect(borderRadius: BorderRadius.circular(8), child: Image.file(_deliveryPhoto!, width: 48, height: 48, fit: BoxFit.cover)),
                      const SizedBox(width: 12),
                      const Expanded(child: Text('✅ Proof photo saved', style: TextStyle(fontWeight: FontWeight.w600, color: Colors.green))),
                    ],
                  )
                : Row(
                    children: [
                      const Icon(Icons.camera_alt, color: WeloColors.textBody),
                      const SizedBox(width: 12),
                      const Expanded(child: Text('Take delivery photo for proof', style: TextStyle(color: WeloColors.textBody, fontSize: 13))),
                      ElevatedButton.icon(
                        onPressed: _uploading ? null : _takePhoto,
                        icon: _uploading ? const SizedBox(width: 16, height: 16, child: CircularProgressIndicator(strokeWidth: 2)) : const Icon(Icons.camera, size: 18),
                        label: const Text('Photo'),
                        style: ElevatedButton.styleFrom(
                          backgroundColor: WeloColors.sovereignOrange,
                          foregroundColor: Colors.white,
                          padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
                          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                          textStyle: const TextStyle(fontSize: 12, fontWeight: FontWeight.bold),
                        ),
                      ),
                    ],
                  ),
          ),
          const SizedBox(height: 16),

          // Earnings
          WeloCard(
            padding: const EdgeInsets.all(16),
            child: Column(
              children: [
                const Text('Earnings', style: TextStyle(color: WeloColors.textBody, fontSize: 13)),
                const SizedBox(height: 8),
                Text('₹${riderState.payout.toStringAsFixed(0)}', style: const TextStyle(fontSize: 32, fontWeight: FontWeight.bold, color: WeloColors.success)),
                const SizedBox(height: 12),
                Row(mainAxisAlignment: MainAxisAlignment.spaceEvenly, children: [
                  _buildPayoutItem('Base', '₹35'),
                  _buildPayoutItem('Distance', '₹15'),
                  _buildPayoutItem('Tip', '₹10'),
                ]),
              ],
            ),
          ),
          const SizedBox(height: 16),

          SizedBox(width: double.infinity, height: 56,
            child: ElevatedButton(
              onPressed: () => notifier.completeOrderCycle(),
              style: ElevatedButton.styleFrom(backgroundColor: WeloColors.sovereignOrange, foregroundColor: Colors.white, shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16))),
              child: const Text('Done', style: TextStyle(fontSize: 16, fontWeight: FontWeight.bold)),
            ),
          ),
          const SizedBox(height: 8),
        ],
      ),
    );
  }

  Widget _buildPayoutItem(String label, String value) {
    return Column(children: [
      Text(value, style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 15)),
      const SizedBox(height: 2),
      Text(label, style: const TextStyle(color: WeloColors.textBody, fontSize: 11)),
    ]);
  }
}

