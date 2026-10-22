import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:ui/ui.dart';
import 'package:core/core.dart';
import 'package:image_picker/image_picker.dart';

class RiderDocumentScreen extends ConsumerStatefulWidget {
  const RiderDocumentScreen({super.key});

  @override
  ConsumerState<RiderDocumentScreen> createState() => _RiderDocumentScreenState();
}

class _RiderDocumentScreenState extends ConsumerState<RiderDocumentScreen> {
  Map<String, dynamic>? documentsData;
  bool loading = true;
  String? error;
  final Map<String, bool> uploading = {};

  static const documentLabels = {
    'aadhaar_front': 'Aadhaar Card (Front)',
    'aadhaar_back': 'Aadhaar Card (Back)',
    'driving_license_front': 'Driving License (Front)',
    'driving_license_back': 'Driving License (Back)',
    'pan_card': 'PAN Card',
    'selfie': 'Selfie Photo',
    'vehicle_rc': 'Vehicle RC',
    'insurance': 'Insurance',
  };

  static const requiredDocuments = [
    'aadhaar_front',
    'aadhaar_back',
    'driving_license_front',
    'pan_card',
    'selfie',
  ];

  @override
  void initState() {
    super.initState();
    _fetchDocuments();
  }

  Future<void> _fetchDocuments() async {
    setState(() {
      loading = true;
      error = null;
    });
    try {
      final repo = ref.read(deliveryRepositoryProvider);
      documentsData = await repo.getDocuments();
    } catch (e) {
      error = e.toString();
    }
    setState(() => loading = false);
  }

  Future<void> _pickAndUpload(String docType) async {
    final picker = ImagePicker();
    final source = await showDialog<ImageSource>(
      context: context,
      builder: (ctx) => AlertDialog(
        title: const Text('Select Source'),
        content: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            ListTile(
              leading: const Icon(Icons.camera_alt),
              title: const Text('Camera'),
              onTap: () => Navigator.pop(ctx, ImageSource.camera),
            ),
            ListTile(
              leading: const Icon(Icons.photo_library),
              title: const Text('Gallery'),
              onTap: () => Navigator.pop(ctx, ImageSource.gallery),
            ),
          ],
        ),
      ),
    );
    if (source == null) return;

    final xFile = await picker.pickImage(source: source, imageQuality: 80);
    if (xFile == null) return;

    setState(() => uploading[docType] = true);
    try {
      final repo = ref.read(deliveryRepositoryProvider);
      await repo.uploadDocument(docType, xFile.path);
      await _fetchDocuments();
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('Document uploaded for verification'), backgroundColor: WeloColors.sovereignOrange),
        );
      }
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text('Upload failed: $e'), backgroundColor: Colors.red),
        );
      }
    }
    setState(() => uploading[docType] = false);
  }

  @override
  Widget build(BuildContext context) {
    final verificationStatus = documentsData?['verification_status'] as String?;
    final isVerified = documentsData?['is_verified'] as bool? ?? false;
    final documents = documentsData?['documents'] as Map<String, dynamic>? ?? {};

    return Scaffold(
      appBar: const WeloAppBar(title: 'Document Verification'),
      body: RefreshIndicator(
        onRefresh: _fetchDocuments,
        color: WeloColors.sovereignOrange,
        child: loading
            ? const Center(child: CircularProgressIndicator(color: WeloColors.sovereignOrange))
            : error != null
                ? Center(child: Text('Error: $error'))
                : SingleChildScrollView(
                    padding: const EdgeInsets.all(16),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        _buildStatusBanner(verificationStatus, isVerified),
                        const SizedBox(height: 24),
                        ...documentLabels.entries.map((entry) => _buildDocumentCard(
                              entry.key,
                              entry.value,
                              documents[entry.key] as Map<String, dynamic>?,
                            )),
                      ],
                    ),
                  ),
      ),
    );
  }

  Widget _buildStatusBanner(String? status, bool isVerified) {
    final statusInfo = isVerified
        ? ('You are verified! You can go online and start delivering.', WeloColors.sovereignOrange, Icons.verified)
        : ({
            'unverified': ('Upload your documents to start delivering', Colors.grey, Icons.info),
            'pending': ('Documents under review. We will notify you soon.', Colors.orange, Icons.hourglass_top),
            'verified': ('You are verified! You can go online and start delivering.', WeloColors.sovereignOrange, Icons.verified),
            'rejected': ('Some documents were rejected. Please re-upload.', Colors.red, Icons.error),
          }[status] ??
          ('Upload your documents to start delivering', Colors.grey, Icons.info));
    final info = statusInfo;

    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: info.$2.withValues(alpha: 0.1),
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: info.$2.withValues(alpha: 0.3)),
      ),
      child: Row(
        children: [
          Icon(info.$3, color: info.$2, size: 32),
          const SizedBox(width: 12),
          Expanded(
            child: Text(
              info.$1,
              style: TextStyle(color: info.$2, fontWeight: FontWeight.w600),
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildDocumentCard(String type, String label, Map<String, dynamic>? doc) {
    final status = doc?['status'] as String? ?? 'not_uploaded';
    final isRequired = requiredDocuments.contains(type);
    final isUploading = uploading[type] ?? false;

    final statusConfig = {
      'not_uploaded': (Colors.grey, 'Not Uploaded', Icons.upload_file),
      'pending': (Colors.orange, 'Under Review', Icons.hourglass_top),
      'approved': (WeloColors.sovereignOrange, 'Approved', Icons.check_circle),
      'rejected': (Colors.red, 'Rejected', Icons.cancel),
    };
    final config = statusConfig[status]!;

    return Card(
      margin: const EdgeInsets.only(bottom: 12),
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Row(
          children: [
            Icon(config.$3, color: config.$1, size: 28),
            const SizedBox(width: 12),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Row(
                    children: [
                      Text(label, style: const TextStyle(fontWeight: FontWeight.w600)),
                      if (isRequired) ...[
                        const SizedBox(width: 6),
                        Text('*', style: TextStyle(color: Colors.red, fontWeight: FontWeight.bold)),
                      ],
                    ],
                  ),
                  const SizedBox(height: 4),
                  if (status == 'rejected' && doc?['rejection_reason'] != null)
                    Text(
                      doc!['rejection_reason'].toString(),
                      style: TextStyle(color: Colors.red.shade700, fontSize: 12),
                    )
                  else
                    Text(config.$2, style: TextStyle(color: config.$1, fontSize: 12)),
                ],
              ),
            ),
            if (isUploading)
              const SizedBox(
                width: 24,
                height: 24,
                child: CircularProgressIndicator(strokeWidth: 2, color: WeloColors.sovereignOrange),
              )
            else
              ElevatedButton(
                onPressed: status == 'approved' ? null : () => _pickAndUpload(type),
                style: ElevatedButton.styleFrom(
                  backgroundColor: status == 'approved' ? Colors.grey : WeloColors.sovereignOrange,
                  foregroundColor: Colors.white,
                  shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                  padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
                ),
                child: Text(status == 'approved' ? 'Done' : status == 'rejected' ? 'Re-upload' : 'Upload'),
              ),
          ],
        ),
      ),
    );
  }
}
