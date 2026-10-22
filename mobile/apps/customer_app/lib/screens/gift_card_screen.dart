import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:ui/ui.dart';
import 'package:core/core.dart';

class GiftCardScreen extends ConsumerStatefulWidget {
  const GiftCardScreen({super.key});

  @override
  ConsumerState<GiftCardScreen> createState() => _GiftCardScreenState();
}

class _GiftCardScreenState extends ConsumerState<GiftCardScreen> {
  double _balance = 0.0;
  bool _isLoading = true;
  final _redeemController = TextEditingController();

  @override
  void initState() {
    super.initState();
    _loadBalance();
  }

  Future<void> _loadBalance() async {
    try {
      final apiClient = ref.read(apiClientProvider);
      final res = await apiClient.dio.get('/gift-cards/balance');
      if (mounted) {
        setState(() {
          _balance = double.parse((res.data['balance'] ?? 0).toString());
          _isLoading = false;
        });
      }
    } catch (e) {
      if (mounted) setState(() => _isLoading = false);
    }
  }

  Future<void> _redeemCode() async {
    final code = _redeemController.text.trim();
    if (code.isEmpty) return;
    try {
      final apiClient = ref.read(apiClientProvider);
      final res = await apiClient.dio.post('/gift-cards/redeem', data: {'code': code});
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text(res.data['message'] ?? 'Gift card redeemed!'), backgroundColor: Colors.green),
        );
        _redeemController.clear();
        _loadBalance();
      }
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text('Redemption failed: $e'), backgroundColor: Colors.red),
        );
      }
    }
  }

  @override
  void dispose() {
    _redeemController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: const WeloAppBar(title: 'Gift Cards'),
      body: _isLoading
          ? const Center(child: CircularProgressIndicator(color: WeloColors.sovereignOrange))
          : SingleChildScrollView(
              padding: const EdgeInsets.all(16),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  WeloCard(
                    padding: const EdgeInsets.all(20),
                    child: Row(
                      mainAxisAlignment: MainAxisAlignment.spaceBetween,
                      children: [
                        Expanded(
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              const Text('Gift Card Balance', style: TextStyle(color: WeloColors.textBody)),
                              const SizedBox(height: 4),
                              Text('₹${_balance.toStringAsFixed(0)}', style: GoogleFonts.spaceGrotesk(fontSize: 28, fontWeight: FontWeight.bold, color: WeloColors.sovereignOrange)),
                            ],
                          ),
                        ),
                        ElevatedButton(
                          onPressed: () => _showPurchaseDialog(context),
                          style: ElevatedButton.styleFrom(backgroundColor: WeloColors.sovereignOrange, foregroundColor: Colors.white),
                          child: const Text('Buy Gift Card'),
                        ),
                      ],
                    ),
                  ),
                  const SizedBox(height: 24),
                  const Text('Choose Amount', style: TextStyle(fontSize: 18, fontWeight: FontWeight.bold)),
                  const SizedBox(height: 12),
                  Wrap(
                    spacing: 12,
                    runSpacing: 12,
                    children: [200, 500, 1000, 2000, 5000].map((amount) =>
                      GestureDetector(
                        onTap: () => _showPurchaseDialog(context, amount: amount),
                        child: Container(
                          width: 100,
                          padding: const EdgeInsets.symmetric(vertical: 16),
                          decoration: BoxDecoration(
                            border: Border.all(color: WeloColors.sovereignOrange.withValues(alpha: 0.196)),
                            borderRadius: BorderRadius.circular(12),
                          ),
                          child: Center(child: Text('₹$amount', style: const TextStyle(fontWeight: FontWeight.bold, color: WeloColors.sovereignOrange))),
                        ),
                      ),
                    ).toList(),
                  ),
                  const SizedBox(height: 24),
                  const Text('Have a gift card?', style: TextStyle(fontSize: 18, fontWeight: FontWeight.bold)),
                  const SizedBox(height: 12),
                  Row(
                    children: [
                      Expanded(
                        child: TextField(
                          controller: _redeemController,
                          decoration: InputDecoration(
                            hintText: 'Enter gift card code',
                            border: OutlineInputBorder(borderRadius: BorderRadius.circular(12)),
                            contentPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
                          ),
                        ),
                      ),
                      const SizedBox(width: 8),
                      IconButton(
                        onPressed: _redeemCode,
                        icon: const Icon(Icons.redeem, color: WeloColors.sovereignOrange),
                      ),
                    ],
                  ),
                ],
              ),
            ),
    );
  }

  void _showPurchaseDialog(BuildContext context, {int amount = 500}) {
    final recipientController = TextEditingController();
    final messageController = TextEditingController();
    showDialog(
      context: context,
      builder: (context) => AlertDialog(
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(24)),
        title: Text('Buy Gift Card - ₹$amount', style: GoogleFonts.spaceGrotesk(fontWeight: FontWeight.w900)),
        content: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            TextField(
              controller: recipientController,
              decoration: InputDecoration(
                labelText: 'Recipient Email',
                hintText: 'friend@email.com',
                border: OutlineInputBorder(borderRadius: BorderRadius.circular(12)),
              ),
              keyboardType: TextInputType.emailAddress,
            ),
            const SizedBox(height: 12),
            TextField(
              controller: messageController,
              decoration: InputDecoration(
                labelText: 'Message (optional)',
                hintText: 'Happy Birthday!',
                border: OutlineInputBorder(borderRadius: BorderRadius.circular(12)),
              ),
            ),
          ],
        ),
        actions: [
          TextButton(onPressed: () => Navigator.pop(context), child: const Text('Cancel')),
          ElevatedButton(
            onPressed: () async {
              try {
                final apiClient = ref.read(apiClientProvider);
                await apiClient.dio.post('/gift-cards/purchase', data: {
                  'amount': amount,
                  'recipient_email': recipientController.text.trim(),
                  'message': messageController.text.trim().isEmpty ? null : messageController.text.trim(),
                });
                if (!mounted) return;
                Navigator.pop(context);
                ScaffoldMessenger.of(context).showSnackBar(
                  const SnackBar(content: Text('Gift card purchased!'), backgroundColor: Colors.green),
                );
              } catch (e) {
                if (!mounted) return;
                ScaffoldMessenger.of(context).showSnackBar(
                  SnackBar(content: Text('Purchase failed: $e'), backgroundColor: Colors.red),
                );
              }
            },
            style: ElevatedButton.styleFrom(backgroundColor: WeloColors.sovereignOrange, foregroundColor: Colors.white),
            child: const Text('Purchase'),
          ),
        ],
      ),
    );
  }
}
