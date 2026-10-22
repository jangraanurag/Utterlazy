import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:ui/ui.dart';
import 'package:core/core.dart';

class RegisterScreen extends ConsumerStatefulWidget {
  const RegisterScreen({super.key});

  @override
  ConsumerState<RegisterScreen> createState() => _RegisterScreenState();
}

class _RegisterScreenState extends ConsumerState<RegisterScreen> {
  final _nameController = TextEditingController();
  final _emailController = TextEditingController();
  final _phoneController = TextEditingController();
  final _passwordController = TextEditingController();
  final _confirmPasswordController = TextEditingController();
  bool _isLoading = false;
  bool _obscurePassword = true;
  String? _error;

  @override
  void dispose() {
    _nameController.dispose();
    _emailController.dispose();
    _phoneController.dispose();
    _passwordController.dispose();
    _confirmPasswordController.dispose();
    super.dispose();
  }

  Future<void> _handleRegister() async {
    if (_nameController.text.isEmpty || _emailController.text.isEmpty ||
        _passwordController.text.isEmpty || _confirmPasswordController.text.isEmpty) {
      setState(() => _error = 'Please fill in all required fields.');
      return;
    }
    if (_passwordController.text != _confirmPasswordController.text) {
      setState(() => _error = 'Passwords do not match.');
      return;
    }
    if (_passwordController.text.length < 6) {
      setState(() => _error = 'Password must be at least 6 characters.');
      return;
    }

    setState(() { _isLoading = true; _error = null; });

    try {
      await ref.read(authProvider.notifier).register(
        _nameController.text.trim(),
        _emailController.text.trim(),
        _phoneController.text.trim(),
        _passwordController.text,
      );
      if (mounted) {
        Navigator.of(context).pop(true);
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
      body: SafeArea(
        child: SingleChildScrollView(
          padding: const EdgeInsets.symmetric(horizontal: 32.0, vertical: 40.0),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              IconButton(
                icon: const Icon(Icons.arrow_back_ios_new_rounded, size: 20),
                onPressed: () => Navigator.pop(context),
              ),
              const SizedBox(height: 20),
              Center(
                child: Column(
                  children: [
                    Image.asset(
                      'assets/logo.png',
                      height: 60,
                      fit: BoxFit.contain,
                    ),
                    const SizedBox(height: 12),
                    Text('Too Lazy? Just Order.', style: GoogleFonts.inter(fontSize: 11, color: WeloColors.sovereignOrange, letterSpacing: 1.5)),
                  ],
                ),
              ),
              const SizedBox(height: 48),
              Text('Join the\nculinary revolution.',
                style: GoogleFonts.playfairDisplay(
                  fontSize: 32, fontWeight: FontWeight.w700, height: 1.2,
                ),
              ),
              const SizedBox(height: 12),
              Text('Create your account to start ordering.',
                style: GoogleFonts.inter(fontSize: 15, color: WeloColors.textBody),
              ),
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
                      const Icon(Icons.error_outline, color: WeloColors.error, size: 18),
                      const SizedBox(width: 8),
                      Expanded(child: Text(_error!, style: const TextStyle(color: WeloColors.error, fontSize: 13))),
                    ],
                  ),
                ),

              _buildField('Full Name', _nameController, Icons.person_outline),
              const SizedBox(height: 16),
              _buildField('Email', _emailController, Icons.email_outlined, keyboardType: TextInputType.emailAddress),
              const SizedBox(height: 16),
              _buildField('Phone (optional)', _phoneController, Icons.phone_outlined, keyboardType: TextInputType.phone),
              const SizedBox(height: 16),
              _buildField('Password', _passwordController, Icons.lock_outline,
                obscure: true, suffix: IconButton(
                  icon: Icon(_obscurePassword ? Icons.visibility_off_rounded : Icons.visibility_rounded,
                    color: WeloColors.textLight, size: 22),
                  onPressed: () => setState(() => _obscurePassword = !_obscurePassword),
                )),
              const SizedBox(height: 16),
              _buildField('Confirm Password', _confirmPasswordController, Icons.lock_outline, obscure: true),
              const SizedBox(height: 32),

              SizedBox(
                width: double.infinity, height: 56,
                child: ElevatedButton(
                  onPressed: _isLoading ? null : _handleRegister,
                  style: ElevatedButton.styleFrom(
                    backgroundColor: WeloColors.sovereignOrange,
                    foregroundColor: Colors.white,
                    shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(20)),
                    elevation: 0,
                  ),
                  child: _isLoading
                      ? const SizedBox(width: 22, height: 22, child: CircularProgressIndicator(color: Colors.white, strokeWidth: 2))
                      : Text('CREATE ACCOUNT', style: GoogleFonts.spaceGrotesk(
                          fontSize: 13, fontWeight: FontWeight.w700, letterSpacing: 1,
                        )),
                ),
              ),
              const SizedBox(height: 24),
              Center(
                child: TextButton(
                  onPressed: () => Navigator.pop(context),
                  child: RichText(
                    text: TextSpan(
                      text: 'Already have an account? ',
                      style: GoogleFonts.inter(fontSize: 14, color: WeloColors.textBody),
                      children: [
                        TextSpan(text: 'Sign In', style: GoogleFonts.inter(
                          fontSize: 14, fontWeight: FontWeight.w700, color: WeloColors.sovereignOrange,
                        )),
                      ],
                    ),
                  ),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }

  Widget _buildField(String label, TextEditingController controller, IconData icon,
      {TextInputType keyboardType = TextInputType.text, bool obscure = false, Widget? suffix}) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(label, style: GoogleFonts.inter(fontSize: 13, fontWeight: FontWeight.w600, color: WeloColors.textDark)),
        const SizedBox(height: 8),
        TextField(
          controller: controller,
          keyboardType: keyboardType,
          obscureText: obscure,
          style: GoogleFonts.inter(fontSize: 15, color: WeloColors.sovereignText),
          decoration: InputDecoration(
            prefixIcon: Icon(icon, color: WeloColors.textLight, size: 20),
            suffixIcon: suffix,
            filled: true,
            fillColor: WeloColors.sovereignElevated,
            border: OutlineInputBorder(borderRadius: BorderRadius.circular(16), borderSide: BorderSide.none),
            focusedBorder: OutlineInputBorder(borderRadius: BorderRadius.circular(16),
              borderSide: const BorderSide(color: WeloColors.sovereignOrange, width: 1.5)),
            contentPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 16),
          ),
        ),
      ],
    );
  }
}
