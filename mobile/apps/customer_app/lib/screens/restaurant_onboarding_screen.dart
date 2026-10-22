import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:ui/ui.dart';
import 'package:core/core.dart';

class RestaurantOnboardingScreen extends ConsumerStatefulWidget {
  const RestaurantOnboardingScreen({super.key});

  @override
  ConsumerState<RestaurantOnboardingScreen> createState() => _RestaurantOnboardingScreenState();
}

class _RestaurantOnboardingScreenState extends ConsumerState<RestaurantOnboardingScreen> {
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
  final _state = TextEditingController();
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
    _state.dispose();
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
      final res = await apiClient.dio.post('/onboarding/apply', data: {
        'restaurant_name': _restaurantName.text,
        'owner_name': _ownerName.text,
        'email': _email.text,
        'phone': _phone.text,
        'address': _address.text,
        'city': _city.text,
        'state': _state.text,
        'pincode': _pincode.text,
        'fssai_number': _fssai.text,
        'gst_number': _gst.text,
        'pan_number': _pan.text,
      });
      setState(() => _success = res.data['message'] ?? 'Application submitted successfully!');
    } catch (e) {
      setState(() => _error = 'Submission failed. Please try again.');
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: const WeloAppBar(title: 'Partner With Us'),
      body: SingleChildScrollView(
        padding: const EdgeInsets.all(24),
        child: Form(
          key: _formKey,
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Center(
                child: Column(
                  children: [
                    const Text('KHAALO', style: TextStyle(fontSize: 28, fontWeight: FontWeight.bold, color: WeloColors.sovereignOrange)),
                    const SizedBox(height: 4),
                    Text('Join 10,000+ restaurants', style: TextStyle(color: WeloColors.textBody, fontSize: 13)),
                  ],
                ),
              ),
              const SizedBox(height: 24),
              if (_success != null)
                Container(
                  margin: const EdgeInsets.only(bottom: 16),
                  padding: const EdgeInsets.all(12),
                  decoration: BoxDecoration(color: Colors.green.shade50, borderRadius: BorderRadius.circular(12), border: Border.all(color: Colors.green.shade200)),
                  child: Text(_success!, style: TextStyle(color: Colors.green.shade700, fontSize: 13)),
                ),
              if (_error != null)
                Container(
                  margin: const EdgeInsets.only(bottom: 16),
                  padding: const EdgeInsets.all(12),
                  decoration: BoxDecoration(color: Colors.red.shade50, borderRadius: BorderRadius.circular(12), border: Border.all(color: Colors.red.shade200)),
                  child: Text(_error!, style: TextStyle(color: Colors.red.shade700, fontSize: 13)),
                ),
              _buildField('Restaurant Name', _restaurantName),
              _buildField('Owner Name', _ownerName),
              _buildField('Email', _email, keyboardType: TextInputType.emailAddress),
              _buildField('Phone', _phone, keyboardType: TextInputType.phone),
              _buildField('Address', _address),
              _buildField('City', _city),
              _buildField('State', _state),
              _buildField('Pincode', _pincode, keyboardType: TextInputType.number),
              _buildField('FSSAI Number', _fssai),
              _buildField('GST Number', _gst),
              _buildField('PAN Number', _pan),
              const SizedBox(height: 24),
              SizedBox(
                width: double.infinity,
                child: ElevatedButton(
                  onPressed: _loading ? null : _handleSubmit,
                  style: ElevatedButton.styleFrom(
                    backgroundColor: WeloColors.sovereignOrange,
                    foregroundColor: Colors.white,
                    padding: const EdgeInsets.symmetric(vertical: 16),
                    shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                  ),
                  child: _loading
                      ? const SizedBox(height: 20, width: 20, child: CircularProgressIndicator(color: Colors.white, strokeWidth: 2))
                      : const Text('SUBMIT APPLICATION', style: TextStyle(fontWeight: FontWeight.w900, letterSpacing: 1)),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }

  Widget _buildField(String label, TextEditingController controller, {TextInputType keyboardType = TextInputType.text}) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 16),
      child: TextFormField(
        controller: controller,
        keyboardType: keyboardType,
        validator: (v) => (v == null || v.isEmpty) ? 'Required' : null,
        decoration: InputDecoration(
          labelText: label,
          labelStyle: TextStyle(color: WeloColors.textBody, fontSize: 13),
          filled: true,
          fillColor: WeloColors.sovereignSurface,
          border: OutlineInputBorder(borderRadius: BorderRadius.circular(12), borderSide: BorderSide(color: WeloColors.textLight.withValues(alpha: 0.196))),
          enabledBorder: OutlineInputBorder(borderRadius: BorderRadius.circular(12), borderSide: BorderSide(color: WeloColors.textLight.withValues(alpha: 0.196))),
        ),
      ),
    );
  }
}
