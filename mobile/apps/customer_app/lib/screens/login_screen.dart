import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:ui/ui.dart';
import 'package:core/core.dart';
import 'register_screen.dart';
import 'forgot_password_screen.dart';

class LoginScreen extends ConsumerStatefulWidget {
  final bool showBack;
  const LoginScreen({super.key, this.showBack = false});

  @override
  ConsumerState<LoginScreen> createState() => _LoginScreenState();
}

class _LoginScreenState extends ConsumerState<LoginScreen> {
  final _emailController = TextEditingController();
  final _passwordController = TextEditingController();
  bool _isLoading = false;
  bool _obscurePassword = true;
  String? _error;

  @override
  void dispose() {
    _emailController.dispose();
    _passwordController.dispose();
    super.dispose();
  }

  Future<void> _handleLogin() async {
    if (_emailController.text.isEmpty || _passwordController.text.isEmpty) {
      setState(() => _error = 'Please fill in all fields.');
      return;
    }

    setState(() { _isLoading = true; _error = null; });

    try {
      final authNotifier = ref.read(authProvider.notifier);
      await authNotifier.login(_emailController.text, _passwordController.text);
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
              if (widget.showBack)
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
              Text('Welcome back,\nfood lover.',
                style: GoogleFonts.playfairDisplay(
                  fontSize: 32, fontWeight: FontWeight.w700, height: 1.2,
                ),
              ),
              const SizedBox(height: 12),
              Text('Sign in to continue your culinary journey.',
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
                      Icon(Icons.error_outline, color: WeloColors.error, size: 18),
                      const SizedBox(width: 8),
                      Expanded(child: Text(_error!, style: TextStyle(color: WeloColors.error, fontSize: 13))),
                    ],
                  ),
                ),

              Text('Email', style: GoogleFonts.inter(
                fontSize: 13, fontWeight: FontWeight.w600, color: WeloColors.textDark,
              )),
              const SizedBox(height: 8),
              TextField(
                controller: _emailController,
                keyboardType: TextInputType.emailAddress,
                style: GoogleFonts.inter(fontSize: 15, color: WeloColors.sovereignText),
                decoration: InputDecoration(
                  hintText: 'anurag@welo.com',
                  hintStyle: GoogleFonts.inter(color: WeloColors.textLight, fontSize: 15),
                  filled: true,
                  fillColor: WeloColors.sovereignElevated,
                  border: OutlineInputBorder(
                    borderRadius: BorderRadius.circular(16),
                    borderSide: BorderSide.none,
                  ),
                  focusedBorder: OutlineInputBorder(
                    borderRadius: BorderRadius.circular(16),
                    borderSide: const BorderSide(color: WeloColors.sovereignOrange, width: 1.5),
                  ),
                  contentPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 16),
                ),
              ),
              const SizedBox(height: 20),

              Text('Password', style: GoogleFonts.inter(
                fontSize: 13, fontWeight: FontWeight.w600, color: WeloColors.textDark,
              )),
              const SizedBox(height: 8),
              TextField(
                controller: _passwordController,
                obscureText: _obscurePassword,
                style: GoogleFonts.inter(fontSize: 15, color: WeloColors.sovereignText),
                decoration: InputDecoration(
                  hintText: '••••••••',
                  hintStyle: GoogleFonts.inter(color: WeloColors.textLight, fontSize: 15),
                  filled: true,
                  fillColor: WeloColors.sovereignElevated,
                  border: OutlineInputBorder(
                    borderRadius: BorderRadius.circular(16),
                    borderSide: BorderSide.none,
                  ),
                  focusedBorder: OutlineInputBorder(
                    borderRadius: BorderRadius.circular(16),
                    borderSide: const BorderSide(color: WeloColors.sovereignOrange, width: 1.5),
                  ),
                  contentPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 16),
                  suffixIcon: IconButton(
                    icon: Icon(
                      _obscurePassword ? Icons.visibility_off_rounded : Icons.visibility_rounded,
                      color: WeloColors.textLight, size: 22,
                    ),
                    onPressed: () => setState(() => _obscurePassword = !_obscurePassword),
                  ),
                ),
              ),
              const SizedBox(height: 8),
              Align(
                alignment: Alignment.centerRight,
                child: TextButton(
                  onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => const ForgotPasswordScreen())),
                  child: Text('Forgot Password?', style: TextStyle(color: WeloColors.sovereignOrange, fontSize: 13, fontWeight: FontWeight.w600)),
                ),
              ),
              const SizedBox(height: 16),

              SizedBox(
                width: double.infinity,
                height: 56,
                child: ElevatedButton(
                  onPressed: _isLoading ? null : _handleLogin,
                  style: ElevatedButton.styleFrom(
                    backgroundColor: WeloColors.sovereignOrange,
                    foregroundColor: Colors.white,
                    shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(20)),
                    elevation: 0,
                  ),
                  child: _isLoading
                      ? const SizedBox(width: 22, height: 22, child: CircularProgressIndicator(color: Colors.white, strokeWidth: 2))
                      : Text('SIGN IN', style: GoogleFonts.spaceGrotesk(
                          fontSize: 14, fontWeight: FontWeight.w700, letterSpacing: 1.5,
                        )),
                ),
              ),
              const SizedBox(height: 24),

              Center(
                child: TextButton(
                  onPressed: () {
                    Navigator.push(context, MaterialPageRoute(builder: (_) => const RegisterScreen()));
                  },
                  child: RichText(
                    text: TextSpan(
                      text: "Don't have an account? ",
                      style: GoogleFonts.inter(fontSize: 14, color: WeloColors.textBody),
                      children: [
                        TextSpan(
                          text: 'Create one',
                          style: GoogleFonts.inter(fontSize: 14, fontWeight: FontWeight.w700, color: WeloColors.sovereignOrange),
                        ),
                      ],
                    ),
                  ),
                ),
              ),
              const SizedBox(height: 40),
            ],
          ),
        ),
      ),
    );
  }
}
