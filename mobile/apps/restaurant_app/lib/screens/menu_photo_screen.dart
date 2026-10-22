import 'dart:io';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:image_picker/image_picker.dart';
import 'package:dio/dio.dart';
import 'package:ui/ui.dart';
import 'package:core/core.dart';

class MenuPhotoScreen extends ConsumerStatefulWidget {
  const MenuPhotoScreen({super.key});

  @override
  ConsumerState<MenuPhotoScreen> createState() => _MenuPhotoScreenState();
}

class _MenuPhotoScreenState extends ConsumerState<MenuPhotoScreen> {
  File? _photo;
  Map<String, dynamic>? _extracted;
  bool _analyzing = false;
  bool _saving = false;

  Future<void> _pickPhoto() async {
    final source = await showDialog<ImageSource>(
      context: context,
      builder: (ctx) => AlertDialog(
        title: const Text('Select Menu Photo'),
        content: Column(mainAxisSize: MainAxisSize.min, children: [
          ListTile(leading: const Icon(Icons.camera_alt), title: const Text('Camera'), onTap: () => Navigator.pop(ctx, ImageSource.camera)),
          ListTile(leading: const Icon(Icons.photo_library), title: const Text('Gallery'), onTap: () => Navigator.pop(ctx, ImageSource.gallery)),
        ]),
      ),
    );
    if (source == null) return;

    final xFile = await ImagePicker().pickImage(source: source, imageQuality: 85, maxWidth: 2048);
    if (xFile == null) return;

    setState(() {
      _photo = File(xFile.path);
      _extracted = null;
    });
    _analyzePhoto();
  }

  Future<void> _analyzePhoto() async {
    if (_photo == null) return;
    setState(() => _analyzing = true);

    try {
      final apiClient = ref.read(apiClientProvider);
      final formData = FormData.fromMap({
        'photo': await MultipartFile.fromFile(_photo!.path, filename: 'menu.jpg'),
      });
      final response = await apiClient.dio.post('/vendor/menu/photo/analyze', data: formData);
      setState(() => _extracted = response.data['extracted']);
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text('Analysis failed: $e'), backgroundColor: Colors.red),
        );
      }
    }
    setState(() => _analyzing = false);
  }

  Future<void> _confirmSave() async {
    if (_extracted == null) return;
    setState(() => _saving = true);

    try {
      final apiClient = ref.read(apiClientProvider);
      final vendorState = ref.read(vendorAuthProvider);
      await apiClient.dio.post('/vendor/menu/photo/confirm', data: {
        'restaurant_id': vendorState.restaurantId!,
        'extracted': _extracted,
      });

      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('Menu created! Items added to your catalog.'), backgroundColor: Colors.green),
        );
        Navigator.pop(context, true);
      }
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text('Save failed: $e'), backgroundColor: Colors.red),
        );
      }
    }
    setState(() => _saving = false);
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('AI Menu Scanner'),
        backgroundColor: Colors.white,
        elevation: 1,
      ),
      body: SingleChildScrollView(
        padding: const EdgeInsets.all(20),
        child: Column(
          children: [
            if (_photo != null)
              ClipRRect(
                borderRadius: BorderRadius.circular(20),
                child: Image.file(_photo!, height: 250, width: double.infinity, fit: BoxFit.cover),
              ),
            const SizedBox(height: 20),
            ElevatedButton.icon(
              onPressed: _analyzing ? null : _pickPhoto,
              icon: Icon(_photo == null ? Icons.camera_alt : Icons.refresh),
              label: Text(_photo == null ? '📸 Take Menu Photo' : '🔄 Retake Photo'),
              style: ElevatedButton.styleFrom(
                backgroundColor: WeloColors.primary,
                foregroundColor: Colors.white,
                minimumSize: const Size(double.infinity, 56),
                shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
              ),
            ),

            if (_analyzing) ...[
              const SizedBox(height: 40),
              const CircularProgressIndicator(color: WeloColors.primary),
              const SizedBox(height: 16),
              const Text('AI is reading your menu...', style: TextStyle(color: Colors.grey)),
            ],

            if (_extracted != null && !_analyzing) ...[
              const SizedBox(height: 24),
              Row(
                children: [
                  const Icon(Icons.auto_awesome, color: WeloColors.primary, size: 20),
                  const SizedBox(width: 8),
                  Text('Extracted Menu', style: TextStyle(fontSize: 18, fontWeight: FontWeight.w900)),
                  const Spacer(),
                  Text('${_extracted!['categories']?.length ?? 0} sections', style: TextStyle(color: Colors.grey.shade600, fontSize: 12)),
                ],
              ),
              const SizedBox(height: 16),
              ...(_extracted!['categories'] as List? ?? []).map((cat) => Card(
                    margin: const EdgeInsets.only(bottom: 12),
                    shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
                    child: Padding(
                      padding: const EdgeInsets.all(16),
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(cat['name'], style: const TextStyle(fontWeight: FontWeight.w900, fontSize: 16)),
                          const SizedBox(height: 8),
                          ...(cat['items'] as List? ?? []).map((item) => Padding(
                                padding: const EdgeInsets.only(bottom: 8),
                                child: Row(
                                  children: [
                                    Icon(item['is_veg'] ? Icons.eco : Icons.restaurant, size: 16, color: item['is_veg'] ? Colors.green : Colors.red.shade400),
                                    const SizedBox(width: 8),
                                    Expanded(child: Text(item['name_english'] ?? item['name'], style: const TextStyle(fontSize: 14))),
                                    Text('₹${item['price']}', style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 14)),
                                  ],
                                ),
                              )),
                        ],
                      ),
                    ),
                  )),

              const SizedBox(height: 24),
              ElevatedButton.icon(
                onPressed: _saving ? null : _confirmSave,
                icon: _saving ? const SizedBox(width: 20, height: 20, child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white)) : const Icon(Icons.check),
                label: Text(_saving ? 'Creating Menu...' : '✅ Save All Items to Menu'),
                style: ElevatedButton.styleFrom(
                  backgroundColor: Colors.green,
                  foregroundColor: Colors.white,
                  minimumSize: const Size(double.infinity, 56),
                  shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
                ),
              ),
            ],
          ],
        ),
      ),
    );
  }
}
