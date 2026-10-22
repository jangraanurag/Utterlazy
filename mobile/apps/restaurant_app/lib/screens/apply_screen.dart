import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:ui/ui.dart';
import 'package:core/core.dart';

class ApplyScreen extends ConsumerStatefulWidget {
  const ApplyScreen({super.key});

  @override
  ConsumerState<ApplyScreen> createState() => _ApplyScreenState();
}

class _ApplyScreenState extends ConsumerState<ApplyScreen> {
  final _formKey = GlobalKey<FormState>();
  bool _loading = false;
  String? _error;
  String? _success;

  final _restaurantName = TextEditingController();
  final _ownerName = TextEditingController();
  final _email = TextEditingController();
  final _phone = TextEditingController();
  final _address = TextEditingController();
  final _city = TextEditingController();
  final _stateCtrl = TextEditingController();
  final _pincode = TextEditingController();
  final _fssai = TextEditingController();
  final _gst = TextEditingController();
  final _pan = TextEditingController();

  @override
  void dispose() {
    _restaurantName.dispose();
    _ownerName.dispose();
    _email.dispose();
    _phone.dispose();
    _address.dispose();
    _city.dispose();
    _stateCtrl.dispose();
    _pincode.dispose();
    _fssai.dispose();
    _gst.dispose();
    _pan.dispose();
    super.dispose();
  }

  Future<void> _handleSubmit() async {
    if (!_formKey.currentState!.validate()) return;
    setState(() {
      _loading = true;
      _error = null;
      _success = null;
    });
    try {
      final apiClient = ref.read(apiClientProvider);
      await apiClient.dio.post('/onboarding/apply', data: {
        'restaurant_name': _restaurantName.text,
        'owner_name': _ownerName.text,
        'email': _email.text,
        'phone': _phone.text,
        'address': _address.text,
        'city': _city.text,
        'state': _stateCtrl.text,
        'pincode': _pincode.text,
        'fssai_number': _fssai.text.isEmpty ? null : _fssai.text,
        'gst_number': _gst.text.isEmpty ? null : _gst.text,
        'pan_number': _pan.text.isEmpty ? null : _pan.text,
      });
      setState(() {
        _success = 'Application submitted! We will review it within 24-48 hours.';
        _loading = false;
      });
    } catch (e) {
      setState(() {
        _error = 'Failed to submit. Please try again.';
        _loading = false;
      });
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: const WeloAppBar(title: 'Apply as Restaurant'),
      body: Form(
        key: _formKey,
        child: ListView(
          padding: const EdgeInsets.all(16),
          children: [
            if (_error != null)
              Container(
                padding: const EdgeInsets.all(12),
                margin: const EdgeInsets.only(bottom: 12),
                decoration: BoxDecoration(
                  color: WeloColors.error.withValues(alpha: 0.078),
                  borderRadius: BorderRadius.circular(8),
                ),
                child: Text(_error!, style: const TextStyle(color: WeloColors.error)),
              ),
            if (_success != null)
              Container(
                padding: const EdgeInsets.all(12),
                margin: const EdgeInsets.only(bottom: 12),
                decoration: BoxDecoration(
                  color: WeloColors.success.withValues(alpha: 0.078),
                  borderRadius: BorderRadius.circular(8),
                ),
                child: Text(_success!, style: const TextStyle(color: WeloColors.success)),
              )
            else ...[
              WeloTextField(label: 'Restaurant Name', controller: _restaurantName),
              const SizedBox(height: 12),
              WeloTextField(label: 'Owner Name', controller: _ownerName),
              const SizedBox(height: 12),
              WeloTextField(label: 'Email', controller: _email, keyboardType: TextInputType.emailAddress),
              const SizedBox(height: 12),
              WeloTextField(label: 'Phone', controller: _phone, keyboardType: TextInputType.phone),
              const SizedBox(height: 12),
              WeloTextField(label: 'Address', controller: _address, maxLines: 2),
              const SizedBox(height: 12),
              Row(
                children: [
                  Expanded(child: WeloTextField(label: 'City', controller: _city)),
                  const SizedBox(width: 12),
                  Expanded(child: WeloTextField(label: 'State', controller: _stateCtrl)),
                ],
              ),
              const SizedBox(height: 12),
              WeloTextField(label: 'Pincode', controller: _pincode, keyboardType: TextInputType.number),
              const SizedBox(height: 16),
              const Text('Compliance (optional)', style: TextStyle(fontWeight: FontWeight.bold, fontSize: 14)),
              const SizedBox(height: 8),
              WeloTextField(label: 'FSSAI Number', controller: _fssai),
              const SizedBox(height: 12),
              WeloTextField(label: 'GST Number', controller: _gst),
              const SizedBox(height: 12),
              WeloTextField(label: 'PAN Number', controller: _pan),
              const SizedBox(height: 24),
              WeloPrimaryButton(text: 'Submit Application', isLoading: _loading, onPressed: _handleSubmit),
            ],
            const SizedBox(height: 40),
          ],
        ),
      ),
    );
  }
}
