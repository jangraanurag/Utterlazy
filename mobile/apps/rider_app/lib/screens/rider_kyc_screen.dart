import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:image_picker/image_picker.dart';
import 'package:api/api.dart';
import 'package:ui/ui.dart';

class RiderKycScreen extends ConsumerStatefulWidget {
  const RiderKycScreen({super.key});

  @override
  ConsumerState<RiderKycScreen> createState() => _RiderKycScreenState();
}

class _RiderKycScreenState extends ConsumerState<RiderKycScreen> {
  final ApiClient _apiClient = ApiClient();
  final ImagePicker _picker = ImagePicker();
  Map<String, dynamic>? _docData;
  bool _isLoading = true;
  bool _isUploading = false;

  static const _docTypes = [
    'aadhaar_front', 'aadhaar_back',
    'driving_license_front', 'driving_license_back',
    'pan_card', 'selfie', 'vehicle_rc', 'insurance',
  ];

  static const _docLabels = {
    'aadhaar_front': 'Aadhaar (Front)',
    'aadhaar_back': 'Aadhaar (Back)',
    'driving_license_front': 'Driving License (Front)',
    'driving_license_back': 'Driving License (Back)',
    'pan_card': 'PAN Card',
    'selfie': 'Selfie / Profile Photo',
    'vehicle_rc': 'Vehicle RC',
    'insurance': 'Vehicle Insurance',
  };

  static const _docIcons = {
    'aadhaar_front': Icons.badge_outlined,
    'aadhaar_back': Icons.badge_outlined,
    'driving_license_front': Icons.drive_eta_outlined,
    'driving_license_back': Icons.drive_eta_outlined,
    'pan_card': Icons.credit_card_outlined,
    'selfie': Icons.face_outlined,
    'vehicle_rc': Icons.description_outlined,
    'insurance': Icons.shield_outlined,
  };

  @override
  void initState() {
    super.initState();
    _loadDocuments();
  }

  Future<void> _loadDocuments() async {
    setState(() => _isLoading = true);
    try {
      final repo = DeliveryRepository(_apiClient);
      _docData = await repo.getDocuments();
    } catch (_) {}
    if (mounted) setState(() => _isLoading = false);
  }

  String _statusLabel(String status) {
    switch (status) {
      case 'approved': return 'Verified';
      case 'pending': return 'Pending';
      case 'rejected': return 'Rejected';
      case 'not_uploaded': return 'Not Uploaded';
      default: return status;
    }
  }

  Color _statusColor(String status) {
    switch (status) {
      case 'approved': return Colors.green;
      case 'pending': return Colors.orange;
      case 'rejected': return Colors.red;
      default: return Colors.grey;
    }
  }

  Color _statusBg(String status) {
    switch (status) {
      case 'approved': return Colors.green.withValues(alpha: 0.1);
      case 'pending': return Colors.orange.withValues(alpha: 0.1);
      case 'rejected': return Colors.red.withValues(alpha: 0.1);
      default: return Colors.grey.withValues(alpha: 0.1);
    }
  }

  Future<void> _pickAndUpload(String docType) async {
    final source = await showModalBottomSheet<ImageSource>(
      context: context,
      shape: const RoundedRectangleBorder(
        borderRadius: BorderRadius.vertical(top: Radius.circular(20)),
      ),
      builder: (ctx) => SafeArea(
        child: Padding(
          padding: const EdgeInsets.all(20),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              Text('Upload ${_docLabels[docType]}',
                style: GoogleFonts.spaceGrotesk(fontWeight: FontWeight.bold, fontSize: 16)),
              const SizedBox(height: 20),
              ListTile(
                leading: const Icon(Icons.camera_alt, color: WeloColors.sovereignOrange),
                title: const Text('Take Photo'),
                onTap: () => Navigator.pop(ctx, ImageSource.camera),
              ),
              ListTile(
                leading: const Icon(Icons.photo_library, color: WeloColors.sovereignOrange),
                title: const Text('Choose from Gallery'),
                onTap: () => Navigator.pop(ctx, ImageSource.gallery),
              ),
            ],
          ),
        ),
      ),
    );
    if (source == null) return;

    try {
      final XFile? picked = await _picker.pickImage(source: source, maxWidth: 1920, maxHeight: 1920);
      if (picked == null) return;

      String? docNumber;
      final needsNumber = ['aadhaar_front', 'pan_card', 'driving_license_front', 'vehicle_rc', 'insurance'];
      if (needsNumber.contains(docType)) {
        docNumber = await _showNumberDialog(docType);
        if (docNumber == null) return;
      }

      setState(() => _isUploading = true);
      final repo = DeliveryRepository(_apiClient);
      await repo.uploadDocument(docType, picked.path, documentNumber: docNumber);
      await _loadDocuments();
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text('Upload failed: $e'), backgroundColor: Colors.red),
        );
      }
    } finally {
      if (mounted) setState(() => _isUploading = false);
    }
  }

  Future<String?> _showNumberDialog(String docType) {
    final controller = TextEditingController();
    final labels = {
      'aadhaar_front': 'Aadhaar Number',
      'pan_card': 'PAN Number',
      'driving_license_front': 'Driving License Number',
      'vehicle_rc': 'RC Number',
      'insurance': 'Insurance Policy Number',
    };
    return showDialog<String>(
      context: context,
      builder: (ctx) => AlertDialog(
        title: Text('Enter ${labels[docType] ?? 'Document'} Number'),
        content: TextField(
          controller: controller,
          decoration: InputDecoration(
            hintText: labels[docType],
            border: const OutlineInputBorder(),
          ),
          keyboardType: TextInputType.text,
          textCapitalization: TextCapitalization.characters,
        ),
        actions: [
          TextButton(onPressed: () => Navigator.pop(ctx), child: const Text('Skip')),
          ElevatedButton(
            onPressed: () => Navigator.pop(ctx, controller.text.trim()),
            style: ElevatedButton.styleFrom(backgroundColor: WeloColors.sovereignOrange),
            child: const Text('Submit', style: TextStyle(color: Colors.white)),
          ),
        ],
      ),
    );
  }

  String? _verificationBanner() {
    if (_docData == null) return null;
    final vs = _docData!['verification_status'] as String? ?? 'unverified';
    switch (vs) {
      case 'verified': return null;
      case 'pending':
        return 'Your documents are being reviewed. You will be notified once verified.';
      case 'rejected':
        final notes = _docData!['verification_notes'] as String?;
        return notes ?? 'Some documents were rejected. Please re-upload the rejected documents.';
      case 'unverified':
        return 'Upload all required documents to get verified and start receiving orders.';
      default:
        return null;
    }
  }

  Color _bannerColor() {
    final vs = _docData!['verification_status'] as String? ?? 'unverified';
    switch (vs) {
      case 'verified': return Colors.green;
      case 'pending': return Colors.orange;
      case 'rejected': return Colors.red;
      default: return Colors.grey;
    }
  }

  @override
  Widget build(BuildContext context) {
    final banner = _verificationBanner();
    final vs = _docData?['verification_status'] as String? ?? 'unverified';
    final isFullyVerified = vs == 'verified';

    return Scaffold(
      appBar: AppBar(
        title: Text('KYC Documents', style: GoogleFonts.spaceGrotesk(fontWeight: FontWeight.bold)),
        backgroundColor: WeloColors.sovereignOrange,
        foregroundColor: Colors.white,
      ),
      body: _isLoading
          ? const Center(child: CircularProgressIndicator(color: WeloColors.sovereignOrange))
          : RefreshIndicator(
              onRefresh: _loadDocuments,
              child: ListView(
                padding: const EdgeInsets.all(16),
                children: [
                  if (banner != null)
                    Container(
                      padding: const EdgeInsets.all(14),
                      margin: const EdgeInsets.only(bottom: 16),
                      decoration: BoxDecoration(
                        color: _bannerColor().withValues(alpha: 0.1),
                        borderRadius: BorderRadius.circular(12),
                        border: Border.all(color: _bannerColor().withValues(alpha: 0.3)),
                      ),
                      child: Row(
                        children: [
                          Icon(Icons.info_outline, color: _bannerColor(), size: 20),
                          const SizedBox(width: 10),
                          Expanded(child: Text(banner, style: TextStyle(color: _bannerColor(), fontSize: 13))),
                        ],
                      ),
                    ),

                  if (isFullyVerified)
                    Container(
                      padding: const EdgeInsets.all(16),
                      margin: const EdgeInsets.only(bottom: 16),
                      decoration: BoxDecoration(
                        color: Colors.green.withValues(alpha: 0.08),
                        borderRadius: BorderRadius.circular(16),
                        border: Border.all(color: Colors.green.withValues(alpha: 0.2)),
                      ),
                      child: Row(
                        children: [
                          const Icon(Icons.verified, color: Colors.green, size: 28),
                          const SizedBox(width: 12),
                          Expanded(
                            child: Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                Text('KYC Verified', style: GoogleFonts.spaceGrotesk(fontWeight: FontWeight.bold, fontSize: 16, color: Colors.green)),
                                const Text('You are fully verified. You can now receive orders.',
                                  style: TextStyle(fontSize: 12, color: Colors.grey)),
                              ],
                            ),
                          ),
                        ],
                      ),
                    ),

                  Text('Required Documents', style: GoogleFonts.spaceGrotesk(fontWeight: FontWeight.bold, fontSize: 15)),
                  const SizedBox(height: 8),
                  ..._docTypes.map((type) => _buildDocTile(type)),

                  const SizedBox(height: 20),
                  Text('Document Expiry', style: GoogleFonts.spaceGrotesk(fontWeight: FontWeight.bold, fontSize: 15)),
                  const SizedBox(height: 4),
                  Text('Keep your Driving License, Vehicle RC, and Insurance up to date.',
                    style: TextStyle(fontSize: 12, color: Colors.grey[600])),
                  const SizedBox(height: 16),
                  _buildExpiryTile('Driving License', 'driving_license_front'),
                  _buildExpiryTile('Vehicle RC', 'vehicle_rc'),
                  _buildExpiryTile('Insurance', 'insurance'),
                ],
              ),
            ),

      floatingActionButton: _isUploading
          ? const FloatingActionButton(
              onPressed: null,
              child: CircularProgressIndicator(color: Colors.white, strokeWidth: 3),
            )
          : null,
    );
  }

  Widget _buildDocTile(String type) {
    final doc = (_docData?['documents'] as Map<String, dynamic>?)?[type] as Map<String, dynamic>?;
    final status = doc?['status'] as String? ?? 'not_uploaded';
    final rejectionReason = doc?['rejection_reason'] as String?;

    return Container(
      margin: const EdgeInsets.only(bottom: 8),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(14),
        border: Border.all(color: Colors.grey.shade200),
      ),
      child: InkWell(
        borderRadius: BorderRadius.circular(14),
        onTap: status == 'rejected' || status == 'not_uploaded'
            ? () => _pickAndUpload(type)
            : null,
        child: Padding(
          padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
          child: Row(
            children: [
              Container(
                padding: const EdgeInsets.all(8),
                decoration: BoxDecoration(
                  color: WeloColors.sovereignOrange.withValues(alpha: 0.08),
                  borderRadius: BorderRadius.circular(10),
                ),
                child: Icon(_docIcons[type], color: WeloColors.sovereignOrange, size: 20),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(_docLabels[type] ?? type,
                      style: const TextStyle(fontWeight: FontWeight.w600, fontSize: 13)),
                    if (rejectionReason != null) ...[
                      const SizedBox(height: 2),
                      Text(rejectionReason,
                        style: TextStyle(fontSize: 10, color: Colors.red[400])),
                    ],
                  ],
                ),
              ),
              Container(
                padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
                decoration: BoxDecoration(
                  color: _statusBg(status),
                  borderRadius: BorderRadius.circular(8),
                ),
                child: Text(
                  _statusLabel(status).toUpperCase(),
                  style: TextStyle(
                    fontSize: 9, fontWeight: FontWeight.w900,
                    color: _statusColor(status),
                  ),
                ),
              ),
              if (status == 'rejected' || status == 'not_uploaded') ...[
                const SizedBox(width: 4),
                const Icon(Icons.upload_file, size: 18, color: WeloColors.sovereignOrange),
              ],
            ],
          ),
        ),
      ),
    );
  }

  Widget _buildExpiryTile(String label, String docType) {
    return Container(
      margin: const EdgeInsets.only(bottom: 8),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(14),
        border: Border.all(color: Colors.grey.shade200),
      ),
      child: InkWell(
        borderRadius: BorderRadius.circular(14),
        onTap: () => _showExpiryPicker(label),
        child: Padding(
          padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
          child: Row(
            children: [
              const Icon(Icons.calendar_today, color: WeloColors.sovereignOrange, size: 20),
              const SizedBox(width: 12),
              Expanded(
                child: Text(label, style: const TextStyle(fontWeight: FontWeight.w500, fontSize: 13)),
              ),
              const Icon(Icons.chevron_right, color: Colors.grey, size: 20),
            ],
          ),
        ),
      ),
    );
  }

  Future<void> _showExpiryPicker(String label) async {
    final now = DateTime.now();
    final picked = await showDatePicker(
      context: context,
      initialDate: now.add(const Duration(days: 365)),
      firstDate: now,
      lastDate: now.add(const Duration(days: 365 * 10)),
      helpText: 'Set $label Expiry Date',
    );
    if (picked != null && mounted) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text('$label expiry set to ${picked.toLocal().toString().split(' ')[0]}')),
      );
    }
  }
}
