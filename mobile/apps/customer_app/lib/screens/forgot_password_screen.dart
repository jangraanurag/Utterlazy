import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:ui/ui.dart';
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
  int _step = 1; // 1=email, 2=otp, 3=reset
  bool _isLoading = false;
  String? _error;
  String? _resetToken;

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
      _resetToken = await ref.read(authProvider.notifier).verifyPasswordResetOtp(
        _emailController.text.trim(), _otpController.text.trim());
      setState(() => _step = 3);
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
        _emailController.text.trim(), _resetToken!, _passwordController.text);
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('Password reset! You can now login.'), backgroundColor: Colors.green));
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
      backgroundColor: WeloColors.sovereignBg,
      appBar: AppBar(
        backgroundColor: Colors.transparent,
        elevation: 0,
        leading: IconButton(
          icon: const Icon(Icons.arrow_back_ios_new_rounded, size: 20, color: Colors.black),
          onPressed: () => Navigator.pop(context),
        ),
      ),
      body: SafeArea(
        child: SingleChildScrollView(
          padding: const EdgeInsets.symmetric(horizontal: 32, vertical: 24),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text('Reset Password',
                style: GoogleFonts.playfairDisplay(fontSize: 32, fontWeight: FontWeight.w700)),
              const SizedBox(height: 8),
              Text(_step == 1 ? 'Enter your email to receive an OTP.'
                  : _step == 2 ? 'Enter the OTP sent to your email.'
                  : 'Choose a new password.',
                style: GoogleFonts.inter(fontSize: 15, color: WeloColors.textBody)),
              const SizedBox(height: 40),

              if (_error != null)
                Container(
                  margin: const EdgeInsets.only(bottom: 16),
                  padding: const EdgeInsets.all(12),
                  decoration: BoxDecoration(
                    color: WeloColors.error.withValues(alpha: 0.078),
                    borderRadius: BorderRadius.circular(12),
                    border: Border.all(color: WeloColors.error.withValues(alpha: 0.196)),
                  ),
                  child: Row(
                    children: [
                      Icon(Icons.error_outline, color: WeloColors.error, size: 18),
                      const SizedBox(width: 8),
                      Expanded(child: Text(_error!, style: TextStyle(color: WeloColors.error, fontSize: 13))),
                    ],
                  ),
                ),

              if (_step == 1) _buildEmailStep(),
              if (_step == 2) _buildOtpStep(),
              if (_step == 3) _buildResetStep(),
            ],
          ),
        ),
      ),
    );
  }

  Widget _buildEmailStep() {
    return Column(
      children: [
        TextField(
          controller: _emailController,
          keyboardType: TextInputType.emailAddress,
          decoration: InputDecoration(
            hintText: 'anurag@welo.com',
            filled: true, fillColor: WeloColors.sovereignElevated,
            border: OutlineInputBorder(borderRadius: BorderRadius.circular(16), borderSide: BorderSide.none),
            contentPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 16),
          ),
        ),
        const SizedBox(height: 24),
        SizedBox(
          width: double.infinity, height: 56,
          child: ElevatedButton(
            onPressed: _isLoading ? null : _requestOtp,
            style: ElevatedButton.styleFrom(
              backgroundColor: WeloColors.sovereignOrange, foregroundColor: Colors.white,
              shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(20)),
            ),
            child: _isLoading
                ? const SizedBox(width: 22, height: 22, child: CircularProgressIndicator(color: Colors.white, strokeWidth: 2))
                : Text('SEND OTP', style: GoogleFonts.spaceGrotesk(fontSize: 14, fontWeight: FontWeight.w700, letterSpacing: 1.5)),
          ),
        ),
      ],
    );
  }

  Widget _buildOtpStep() {
    return Column(
      children: [
        TextField(
          controller: _otpController,
          keyboardType: TextInputType.number,
          maxLength: 6,
          decoration: InputDecoration(
            hintText: '123456',
            filled: true, fillColor: WeloColors.sovereignElevated,
            border: OutlineInputBorder(borderRadius: BorderRadius.circular(16), borderSide: BorderSide.none),
            contentPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 16),
            counterText: '',
          ),
          textAlign: TextAlign.center,
          style: const TextStyle(fontSize: 24, fontWeight: FontWeight.bold, letterSpacing: 8),
        ),
        const SizedBox(height: 24),
        SizedBox(
          width: double.infinity, height: 56,
          child: ElevatedButton(
            onPressed: _isLoading ? null : _verifyOtp,
            style: ElevatedButton.styleFrom(
              backgroundColor: Colors.black, foregroundColor: Colors.white,
              shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(20)),
            ),
            child: _isLoading
                ? const SizedBox(width: 22, height: 22, child: CircularProgressIndicator(color: Colors.white, strokeWidth: 2))
                : Text('VERIFY OTP', style: GoogleFonts.spaceGrotesk(fontSize: 14, fontWeight: FontWeight.w700, letterSpacing: 1.5)),
          ),
        ),
        const SizedBox(height: 16),
        TextButton(
          onPressed: _requestOtp,
          child: Text('Resend OTP', style: TextStyle(color: WeloColors.sovereignOrange, fontWeight: FontWeight.w600)),
        ),
      ],
    );
  }

  Widget _buildResetStep() {
    return Column(
      children: [
        TextField(
          controller: _passwordController,
          obscureText: true,
          decoration: InputDecoration(
            hintText: 'New password',
            filled: true, fillColor: WeloColors.sovereignElevated,
            border: OutlineInputBorder(borderRadius: BorderRadius.circular(16), borderSide: BorderSide.none),
            contentPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 16),
          ),
        ),
        const SizedBox(height: 16),
        TextField(
          controller: _confirmController,
          obscureText: true,
          decoration: InputDecoration(
            hintText: 'Confirm new password',
            filled: true, fillColor: WeloColors.sovereignElevated,
            border: OutlineInputBorder(borderRadius: BorderRadius.circular(16), borderSide: BorderSide.none),
            contentPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 16),
          ),
        ),
        const SizedBox(height: 24),
        SizedBox(
          width: double.infinity, height: 56,
          child: ElevatedButton(
            onPressed: _isLoading ? null : _resetPassword,
            style: ElevatedButton.styleFrom(
              backgroundColor: Colors.green, foregroundColor: Colors.white,
              shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(20)),
            ),
            child: _isLoading
                ? const SizedBox(width: 22, height: 22, child: CircularProgressIndicator(color: Colors.white, strokeWidth: 2))
                : Text('RESET PASSWORD', style: GoogleFonts.spaceGrotesk(fontSize: 14, fontWeight: FontWeight.w700, letterSpacing: 1.5)),
          ),
        ),
      ],
    );
  }
}
