import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:ui/ui.dart';
import 'package:core/core.dart';

class ReferralScreen extends ConsumerStatefulWidget {
  const ReferralScreen({super.key});

  @override
  ConsumerState<ReferralScreen> createState() => _ReferralScreenState();
}

class _ReferralScreenState extends ConsumerState<ReferralScreen> {
  String _referralCode = '...';
  bool _isLoading = true;

  @override
  void initState() {
    super.initState();
    _loadReferralCode();
  }

  Future<void> _loadReferralCode() async {
    try {
      final apiClient = ref.read(apiClientProvider);
      final res = await apiClient.dio.get('/referrals/my-code');
      if (mounted) {
        setState(() {
          _referralCode = res.data['code'] ?? 'UTTERLAZY123';
          _isLoading = false;
        });
      }
    } catch (e) {
      if (mounted) setState(() { _referralCode = 'UTTERLAZY123'; _isLoading = false; });
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: const WeloAppBar(title: 'Refer & Earn'),
      body: _isLoading
          ? const Center(child: CircularProgressIndicator(color: WeloColors.sovereignOrange))
          : SingleChildScrollView(
              padding: const EdgeInsets.all(16),
              child: Column(
                children: [
                  Container(
                    width: double.infinity,
                    padding: const EdgeInsets.all(24),
                    decoration: BoxDecoration(
                      gradient: LinearGradient(colors: [WeloColors.sovereignOrange, WeloColors.sovereignGold]),
                      borderRadius: BorderRadius.circular(20),
                    ),
                    child: Column(
                      children: [
                        const Icon(Icons.card_giftcard, size: 60, color: Colors.white),
                        const SizedBox(height: 16),
                        const Text('Invite Friends, Earn Rewards!', style: TextStyle(color: Colors.white, fontSize: 22, fontWeight: FontWeight.bold)),
                        const SizedBox(height: 8),
                        Text('You get ₹100, They get ₹50', style: const TextStyle(color: Colors.white70, fontSize: 14)),
                      ],
                    ),
                  ),
                  const SizedBox(height: 24),
                  WeloCard(
                    padding: const EdgeInsets.all(20),
                    child: Column(
                      children: [
                        const Text('Your Referral Code', style: TextStyle(color: WeloColors.textBody)),
                        const SizedBox(height: 8),
                        Container(
                          padding: const EdgeInsets.symmetric(horizontal: 24, vertical: 12),
                          decoration: BoxDecoration(
                            color: WeloColors.sovereignOrange.withValues(alpha: 0.059),
                            borderRadius: BorderRadius.circular(12),
                            border: Border.all(color: WeloColors.sovereignOrange.withValues(alpha: 0.196)),
                          ),
                          child: Row(
                            mainAxisAlignment: MainAxisAlignment.center,
                            children: [
                              Text(_referralCode, style: GoogleFonts.spaceGrotesk(fontSize: 24, fontWeight: FontWeight.bold, letterSpacing: 4, color: WeloColors.sovereignOrange)),
                              const SizedBox(width: 12),
                              IconButton(
                                icon: const Icon(Icons.copy, color: WeloColors.sovereignOrange),
                                onPressed: () {
                                  Clipboard.setData(ClipboardData(text: _referralCode));
                                  ScaffoldMessenger.of(context).showSnackBar(
                                    const SnackBar(content: Text('Code copied!')),
                                  );
                                },
                              ),
                            ],
                          ),
                        ),
                        const SizedBox(height: 16),
                        SizedBox(
                          width: double.infinity,
                          child: ElevatedButton.icon(
                            onPressed: () {},
                            icon: const Icon(Icons.share, size: 18),
                            label: const Text('Share Code'),
                            style: ElevatedButton.styleFrom(
                              backgroundColor: WeloColors.sovereignOrange,
                              foregroundColor: Colors.white,
                              padding: const EdgeInsets.symmetric(vertical: 14),
                              shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                            ),
                          ),
                        ),
                      ],
                    ),
                  ),
                  const SizedBox(height: 24),
                  const Align(
                    alignment: Alignment.centerLeft,
                    child: Text('How it works', style: TextStyle(fontSize: 18, fontWeight: FontWeight.bold)),
                  ),
                  const SizedBox(height: 12),
                  _buildStep(1, 'Share your referral code with friends'),
                  _buildStep(2, 'They sign up using your code'),
                  _buildStep(3, 'You both earn wallet rewards!'),
                ],
              ),
            ),
    );
  }

  Widget _buildStep(int number, String text) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 12),
      child: Row(
        children: [
          Container(
            width: 28,
            height: 28,
            decoration: BoxDecoration(color: WeloColors.sovereignOrange, shape: BoxShape.circle),
            child: Center(child: Text('$number', style: const TextStyle(color: Colors.white, fontWeight: FontWeight.bold, fontSize: 12))),
          ),
          const SizedBox(width: 12),
          Expanded(child: Text(text, style: const TextStyle(fontSize: 14))),
        ],
      ),
    );
  }
}
