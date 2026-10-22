import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:core/core.dart';

class ForgotPasswordScreen extends ConsumerStatefulWidget {
  const ForgotPasswordScreen({super.key});

  @override
  ConsumerState<ForgotPasswordScreen> createState() => _ForgotPasswordScreenState();
}

class _ForgotPasswordScreenState extends ConsumerState<ForgotPasswordScreen> {
  final _emailController = TextEditingController();
  final _otpController = TextEditingController();
  final _passwordController = TextEditingController();
  final _confirmController = TextEditingController();
  int _step = 1;
  bool _isLoading = false;
  String? _error;

  @override
  void dispose() {
    _emailController.dispose();
    _otpController.dispose();
    _passwordController.dispose();
    _confirmController.dispose();
    super.dispose();
  }

  Future<void> _requestOtp() async {
    if (_emailController.text.trim().isEmpty) {
      setState(() => _error = 'Please enter your email.');
      return;
    }
    setState(() { _isLoading = true; _error = null; });
    try {
      await ref.read(authProvider.notifier).requestPasswordResetOtp(_emailController.text.trim());
      setState(() => _step = 2);
    } catch (e) {
      setState(() => _error = e.toString());
    } finally {
      if (mounted) setState(() => _isLoading = false);
    }
  }

  Future<void> _verifyOtp() async {
    if (_otpController.text.trim().length != 6) {
      setState(() => _error = 'Please enter the 6-digit OTP.');
      return;
    }
    setState(() { _isLoading = true; _error = null; });
    try {
      final token = await ref.read(authProvider.notifier).verifyPasswordResetOtp(
        _emailController.text.trim(), _otpController.text.trim());
      if (token.isNotEmpty) setState(() => _step = 3);
    } catch (e) {
      setState(() => _error = e.toString());
    } finally {
      if (mounted) setState(() => _isLoading = false);
    }
  }

  Future<void> _resetPassword() async {
    if (_passwordController.text.length < 6) {
      setState(() => _error = 'Password must be at least 6 characters.');
      return;
    }
    if (_passwordController.text != _confirmController.text) {
      setState(() => _error = 'Passwords do not match.');
      return;
    }
    setState(() { _isLoading = true; _error = null; });
    try {
      await ref.read(authProvider.notifier).resetPassword(
        _emailController.text.trim(), '', _passwordController.text);
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('Password reset! Please login.'), backgroundColor: Colors.green));
        Navigator.pop(context);
      }
    } catch (e) {
      setState(() => _error = e.toString());
    } finally {
      if (mounted) setState(() => _isLoading = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Reset Password')),
      body: SingleChildScrollView(
        padding: const EdgeInsets.all(24),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(_step == 1 ? 'Enter your email to receive an OTP.'
                : _step == 2 ? 'Enter the OTP sent to your email.'
                : 'Choose a new password.',
              style: const TextStyle(fontSize: 15, color: Colors.black54)),
            const SizedBox(height: 24),
            if (_error != null)
              Container(
                padding: const EdgeInsets.all(12),
                decoration: BoxDecoration(color: Colors.red.shade50, borderRadius: BorderRadius.circular(12)),
                child: Row(children: [
                  const Icon(Icons.error_outline, color: Colors.red, size: 18),
                  const SizedBox(width: 8),
                  Expanded(child: Text(_error!, style: const TextStyle(color: Colors.red, fontSize: 13))),
                ]),
              ),
            if (_error != null) const SizedBox(height: 16),
            if (_step == 1) ...[
              TextField(controller: _emailController, decoration: const InputDecoration(labelText: 'Email', border: OutlineInputBorder())),
              const SizedBox(height: 24),
              SizedBox(width: double.infinity, child: ElevatedButton(
                onPressed: _isLoading ? null : _requestOtp,
                child: _isLoading ? const CircularProgressIndicator() : const Text('Send OTP'),
              )),
            ],
            if (_step == 2) ...[
              TextField(controller: _otpController, keyboardType: TextInputType.number, maxLength: 6,
                decoration: const InputDecoration(labelText: 'OTP', border: OutlineInputBorder(), counterText: '')),
              const SizedBox(height: 24),
              SizedBox(width: double.infinity, child: ElevatedButton(
                onPressed: _isLoading ? null : _verifyOtp,
                child: _isLoading ? const CircularProgressIndicator() : const Text('Verify OTP'),
              )),
            ],
            if (_step == 3) ...[
              TextField(controller: _passwordController, obscureText: true,
                decoration: const InputDecoration(labelText: 'New Password', border: OutlineInputBorder())),
              const SizedBox(height: 16),
              TextField(controller: _confirmController, obscureText: true,
                decoration: const InputDecoration(labelText: 'Confirm Password', border: OutlineInputBorder())),
              const SizedBox(height: 24),
              SizedBox(width: double.infinity, child: ElevatedButton(
                onPressed: _isLoading ? null : _resetPassword,
                child: _isLoading ? const CircularProgressIndicator() : const Text('Reset Password'),
              )),
            ],
          ],
        ),
      ),
    );
  }
}
