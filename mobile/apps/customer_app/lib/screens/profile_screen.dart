import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:core/core.dart';
import 'package:ui/ui.dart';
import 'package:api/api.dart';
import 'login_screen.dart';
import 'referral_screen.dart';
import 'gift_card_screen.dart';
import 'favorites_screen.dart';
import 'restaurant_onboarding_screen.dart';
import 'notification_settings_screen.dart';
import 'wallet_screen.dart';
import 'games_screen.dart';
import 'scratch_card_history_screen.dart';
import 'my_complaints_screen.dart';
import 'my_reviews_screen.dart';

class ProfileScreen extends ConsumerStatefulWidget {
  const ProfileScreen({super.key});

  @override
  ConsumerState<ProfileScreen> createState() => _ProfileScreenState();
}

class _ProfileScreenState extends ConsumerState<ProfileScreen> {
  Map<String, dynamic>? _profile;

  @override
  void initState() {
    super.initState();
    _loadProfile();
  }

  Future<void> _loadProfile() async {
    final isAuth = ref.read(authProvider).isAuthenticated;
    if (!isAuth) return;
    try {
      final profile = await ref.read(authProvider.notifier).getProfile();
      if (mounted) setState(() { _profile = profile; });
    } catch (e) {
      // Silently handle profile load failure
    }
  }

  @override
  Widget build(BuildContext context) {
    final isAuth = ref.watch(authProvider).isAuthenticated;
    final user = _profile?['user'];

    if (!isAuth) {
      return _buildLoggedOutView();
    }

    return Scaffold(
      appBar: const WeloAppBar(title: 'Profile'),
      body: SingleChildScrollView(
        padding: const EdgeInsets.all(16),
        child: Column(
          children: [
            CircleAvatar(
              radius: 40,
              backgroundColor: WeloColors.sovereignOrange,
              child: Text(
                (user?['name'] ?? 'U')[0].toUpperCase(),
                style: GoogleFonts.spaceGrotesk(fontSize: 28, fontWeight: FontWeight.w900, color: Colors.white),
              ),
            ),
            const SizedBox(height: 12),
            Text(user?['name'] ?? 'User', style: GoogleFonts.spaceGrotesk(fontSize: 22, fontWeight: FontWeight.w900)),
            const SizedBox(height: 4),
            Text(user?['email'] ?? '', style: GoogleFonts.inter(fontSize: 14, color: WeloColors.textBody)),
            const SizedBox(height: 8),
            GestureDetector(
              onTap: () => Navigator.push(context, MaterialPageRoute(builder: (_) => const WalletScreen())).then((_) => _loadProfile()),
              child: Container(
                padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 10),
                decoration: BoxDecoration(
                  color: WeloColors.sovereignOrange.withValues(alpha: 0.1),
                  borderRadius: BorderRadius.circular(20),
                ),
                child: Row(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    const Icon(Icons.account_balance_wallet_rounded, size: 16, color: WeloColors.sovereignOrange),
                    const SizedBox(width: 6),
                    Text(
                      'Wallet Balance: ₹${user?['wallet_balance'] != null ? double.parse(user['wallet_balance'].toString()).toStringAsFixed(2) : '0.00'}',
                      style: const TextStyle(color: WeloColors.sovereignOrange, fontSize: 13, fontWeight: FontWeight.bold),
                    ),
                  ],
                ),
              ),
            ),
            const SizedBox(height: 24),

            _buildSectionHeader('Loyalty & Rewards'),
            _buildTile(Icons.account_balance_wallet_outlined, 'UtterLazy Wallet', () => Navigator.push(context, MaterialPageRoute(builder: (_) => const WalletScreen())).then((_) => _loadProfile())),
            _buildTile(Icons.card_giftcard, 'Gift Cards', () => Navigator.push(context, MaterialPageRoute(builder: (_) => const GiftCardScreen()))),
            _buildTile(Icons.share, 'Refer & Earn', () => Navigator.push(context, MaterialPageRoute(builder: (_) => const ReferralScreen()))),
            _buildTile(Icons.style, 'My Scratch Cards', () => Navigator.push(context, MaterialPageRoute(builder: (_) => const ScratchCardHistoryScreen()))),

            _buildSectionHeader('Discover'),
            _buildTile(Icons.favorite, 'My Favorites', () => Navigator.push(context, MaterialPageRoute(builder: (_) => const FavoritesScreen()))),
            _buildTile(Icons.sports_esports, 'Culinaria Arena', () => Navigator.push(context, MaterialPageRoute(builder: (_) => const GamesScreen()))),
            _buildTile(Icons.storefront, 'Become a Partner', () => Navigator.push(context, MaterialPageRoute(builder: (_) => const RestaurantOnboardingScreen()))),

            _buildSectionHeader('Account'),
            _buildTile(Icons.person_outline, 'Edit Profile', () => _showEditProfileDialog(context)),
            _buildTile(Icons.location_on_outlined, 'Saved Addresses', () => Navigator.push(context, MaterialPageRoute(builder: (_) => const _SavedAddressesScreen())).then((_) => _loadProfile())),
            _buildTile(Icons.payment_outlined, 'Payment Methods', () => _showPaymentMethodsDialog(context)),
            _buildTile(Icons.notifications_outlined, 'Notification Settings', () => Navigator.push(context, MaterialPageRoute(builder: (_) => const NotificationSettingsScreen()))),
            _buildTile(Icons.help_outline, 'Help & Support', () => _showHelpSupportDialog(context)),
            _buildTile(Icons.settings_voice, 'Voice Assistants', () => _showAssistantDialog(context)),
            _buildTile(Icons.rate_review_outlined, 'My Reviews', () => Navigator.push(context, MaterialPageRoute(builder: (_) => const MyReviewsScreen()))),
            _buildTile(Icons.report_problem_outlined, 'My Complaints', () => Navigator.push(context, MaterialPageRoute(builder: (_) => const MyComplaintsScreen()))),
            _buildTile(Icons.info_outline, 'About UtterLazy', () => _showAboutDialog(context)),
            const SizedBox(height: 16),
            _buildTile(Icons.logout, 'Sign Out', () {
              ref.read(authProvider.notifier).logout();
              setState(() { _profile = null; });
            }, isDestructive: true),
          ],
        ),
      ),
    );
  }

  Widget _buildLoggedOutView() {
    return Scaffold(
      appBar: const WeloAppBar(title: 'Profile'),
      body: Center(
        child: Padding(
          padding: const EdgeInsets.all(32),
          child: Column(
            mainAxisAlignment: MainAxisAlignment.center,
            children: [
              Container(
                padding: const EdgeInsets.all(32),
                decoration: BoxDecoration(
                  color: WeloColors.sovereignOrange.withValues(alpha: 0.078),
                  shape: BoxShape.circle,
                ),
                child: const Icon(Icons.person_outline, size: 48, color: WeloColors.sovereignOrange),
              ),
              const SizedBox(height: 24),
              Text('Sign in to your account', style: GoogleFonts.spaceGrotesk(fontSize: 20, fontWeight: FontWeight.w900)),
              const SizedBox(height: 8),
              Text('Access orders, favorites, rewards & more.',
                style: GoogleFonts.inter(fontSize: 14, color: WeloColors.textBody), textAlign: TextAlign.center),
              const SizedBox(height: 32),
              SizedBox(
                width: double.infinity, height: 56,
                child: ElevatedButton(
                  onPressed: () async {
                    final result = await Navigator.push(context, MaterialPageRoute(builder: (_) => const LoginScreen(showBack: true)));
                    if (result == true) _loadProfile();
                  },
                  style: ElevatedButton.styleFrom(
                    backgroundColor: WeloColors.sovereignOrange,
                    foregroundColor: Colors.white,
                    shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(20)),
                  ),
                  child: Text('SIGN IN', style: GoogleFonts.spaceGrotesk(fontSize: 14, fontWeight: FontWeight.w700, letterSpacing: 1.5)),
                ),
              ),
              const SizedBox(height: 16),
              TextButton(
                onPressed: () async {
                  final result = await Navigator.push(context, MaterialPageRoute(builder: (_) => const LoginScreen(showBack: true)));
                  if (result == true) _loadProfile();
                },
                child: RichText(
                  text: TextSpan(
                    text: "Don't have an account? ",
                    style: GoogleFonts.inter(fontSize: 14, color: WeloColors.textBody),
                    children: [
                      TextSpan(text: 'Create one', style: GoogleFonts.inter(
                        fontSize: 14, fontWeight: FontWeight.w700, color: WeloColors.sovereignOrange,
                      )),
                    ],
                  ),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }

  Widget _buildSectionHeader(String title) {
    return Padding(
      padding: const EdgeInsets.only(top: 16, bottom: 8),
      child: Align(
        alignment: Alignment.centerLeft,
        child: Text(title, style: GoogleFonts.inter(fontSize: 14, fontWeight: FontWeight.bold, color: WeloColors.textBody)),
      ),
    );
  }

  Widget _buildTile(IconData icon, String title, VoidCallback onTap, {bool isDestructive = false}) {
    return WeloCard(
      margin: const EdgeInsets.only(bottom: 8),
      padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 14),
      onTap: onTap,
      child: Row(
        children: [
          Icon(icon, size: 20, color: isDestructive ? WeloColors.error : WeloColors.textBody),
          const SizedBox(width: 12),
          Expanded(
            child: Text(title, style: TextStyle(fontSize: 15, color: isDestructive ? WeloColors.error : WeloColors.sovereignText)),
          ),
          const Icon(Icons.chevron_right, size: 20, color: WeloColors.textLight),
        ],
      ),
    );
  }

  void _showEditProfileDialog(BuildContext context) {
    final nameController = TextEditingController(text: _profile?['user']?['name'] ?? '');
    final phoneController = TextEditingController(text: _profile?['user']?['phone'] ?? '');
    showDialog(
      context: context,
      builder: (context) => AlertDialog(
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(24)),
        title: Text('Edit Profile', style: GoogleFonts.spaceGrotesk(fontWeight: FontWeight.w900)),
        content: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            TextField(
              controller: nameController,
              decoration: InputDecoration(
                labelText: 'Name',
                border: OutlineInputBorder(borderRadius: BorderRadius.circular(12)),
                contentPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
              ),
            ),
            const SizedBox(height: 12),
            TextField(
              controller: phoneController,
              decoration: InputDecoration(
                labelText: 'Phone',
                border: OutlineInputBorder(borderRadius: BorderRadius.circular(12)),
                contentPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
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
                await apiClient.dio.patch('/auth/profile', data: {
                  'name': nameController.text.trim(),
                  'phone': phoneController.text.trim(),
                });
                if (!mounted) return;
                Navigator.pop(context);
                _loadProfile();
                ScaffoldMessenger.of(context).showSnackBar(
                  const SnackBar(content: Text('Profile updated!'), backgroundColor: Colors.green),
                );
              } catch (e) {
                if (!mounted) return;
                ScaffoldMessenger.of(context).showSnackBar(
                  SnackBar(content: Text('Failed: $e'), backgroundColor: Colors.red),
                );
              }
            },
            style: ElevatedButton.styleFrom(backgroundColor: WeloColors.sovereignOrange, foregroundColor: Colors.white),
            child: const Text('Save'),
          ),
        ],
      ),
    );
  }

  void _showPaymentMethodsDialog(BuildContext context) {
    showDialog(
      context: context,
      builder: (context) => AlertDialog(
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(24)),
        title: Text('Payment Methods', style: GoogleFonts.spaceGrotesk(fontWeight: FontWeight.w900)),
        content: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            ListTile(
              leading: const Icon(Icons.payments_outlined, color: WeloColors.sovereignOrange),
              title: const Text('Cash on Delivery'),
              subtitle: const Text('Pay when your order arrives'),
              trailing: Icon(Icons.check_circle, color: Colors.green[600]),
            ),
            const Divider(),
            ListTile(
              leading: const Icon(Icons.account_balance_outlined),
              title: const Text('UPI / Online Payment'),
              subtitle: const Text('Pay via UPI, cards, or net banking'),
            ),
            const Divider(),
            ListTile(
              leading: const Icon(Icons.account_balance_wallet_outlined),
              title: const Text('UtterLazy Wallet'),
              subtitle: const Text('Use your wallet balance'),
            ),
          ],
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(context),
            child: const Text('Close'),
          ),
        ],
      ),
    );
  }

  void _showHelpSupportDialog(BuildContext context) {
    showDialog(
      context: context,
      builder: (context) => AlertDialog(
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(24)),
        title: Text('Help & Support', style: GoogleFonts.spaceGrotesk(fontWeight: FontWeight.w900)),
        content: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            ListTile(
              leading: const Icon(Icons.phone, color: Colors.green),
              title: const Text('Call Support'),
              subtitle: const Text('1800-UTTERLAZY (Toll Free)'),
              onTap: () {},
            ),
            const Divider(),
            ListTile(
              leading: const Icon(Icons.email_outlined, color: WeloColors.sovereignOrange),
              title: const Text('Email Us'),
              subtitle: const Text('support@utterlazy.com'),
              onTap: () {},
            ),
            const Divider(),
            ListTile(
              leading: const Icon(Icons.chat_bubble_outline, color: Colors.blue),
              title: const Text('Live Chat'),
              subtitle: const Text('Chat with our support team'),
              onTap: () {},
            ),
          ],
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(context),
            child: const Text('Close'),
          ),
        ],
      ),
    );
  }

  void _showAssistantDialog(BuildContext context) {
    showDialog(
      context: context,
      builder: (ctx) => AlertDialog(
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(24)),
        title: Text('Voice Assistants', style: GoogleFonts.spaceGrotesk(fontWeight: FontWeight.w900)),
        content: SizedBox(
          width: double.maxFinite,
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              const Text('Order food using your voice with:', style: TextStyle(fontSize: 14)),
              const SizedBox(height: 16),
              ListTile(
                leading: Image.asset('assets/google_assistant.png', width: 32, height: 32, errorBuilder: (_, _, _) => Icon(Icons.assistant, size: 32, color: Colors.blue)),
                title: const Text('Google Assistant'),
                subtitle: const Text('"Hey Google, ask UtterLazy to order biryani"'),
              ),
              const Divider(),
              ListTile(
                leading: Image.asset('assets/alexa.png', width: 32, height: 32, errorBuilder: (_, _, _) => Icon(Icons.assistant_navigation, size: 32, color: Colors.cyan)),
                title: const Text('Amazon Alexa'),
                subtitle: const Text('"Alexa, ask UtterLazy to order food"'),
              ),
              const Divider(),
              Row(
                children: [
                  Icon(Icons.auto_awesome, size: 20, color: WeloColors.sovereignOrange),
                  const SizedBox(width: 8),
                  Text('Gemini & Meta AI also supported', style: TextStyle(fontSize: 12, color: Colors.grey.shade600)),
                ],
              ),
              const SizedBox(height: 16),
            ],
          ),
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(ctx),
            child: const Text('Got it!'),
          ),
        ],
      ),
    );
  }

  void _showAboutDialog(BuildContext context) {
    showAboutDialog(
      context: context,
      applicationName: 'UtterLazy',
      applicationVersion: '1.0.0',
      applicationIcon: Container(
        padding: const EdgeInsets.all(8),
        decoration: BoxDecoration(
          color: WeloColors.sovereignOrange,
          borderRadius: BorderRadius.circular(12),
        ),
        child: const Icon(Icons.restaurant, color: Colors.white, size: 24),
      ),
      children: [
        Text('Masters of slow-cooked Dum Biryani and delicate Galouti Kebabs.', style: GoogleFonts.inter(fontSize: 14)),
        const SizedBox(height: 12),
        Text('Authentic Awadhi royal dining delivered to your doorstep.', style: GoogleFonts.inter(fontSize: 14)),
        const SizedBox(height: 12),
        Text('© 2026 UtterLazy. All rights reserved.', style: TextStyle(color: Colors.grey[500], fontSize: 12)),
      ],
    );
  }
}

class _SavedAddressesScreen extends ConsumerStatefulWidget {
  const _SavedAddressesScreen();

  @override
  ConsumerState<_SavedAddressesScreen> createState() => _SavedAddressesScreenState();
}

class _SavedAddressesScreenState extends ConsumerState<_SavedAddressesScreen> {
  late final AddressRepository _addressRepository;
  List<Address> _addresses = [];
  bool _isLoading = true;

  @override
  void initState() {
    super.initState();
    _addressRepository = AddressRepository(ref.read(apiClientProvider));
    _loadAddresses();
  }

  Future<void> _loadAddresses() async {
    try {
      final addresses = await _addressRepository.getAddresses();
      if (mounted) {
        setState(() {
          _addresses = addresses;
          _isLoading = false;
        });
      }
    } catch (e) {
      if (mounted) {
        setState(() => _isLoading = false);
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('Failed to load addresses')),
        );
      }
    }
  }

  Future<void> _deleteAddress(String id) async {
    try {
      await _addressRepository.destroyAddress(id);
      _loadAddresses();
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('Failed to delete address')),
        );
      }
    }
  }

  void _showAddAddressDialog() {
    final labelController = TextEditingController(text: 'Home');
    final flatController = TextEditingController();
    final streetController = TextEditingController();
    final landmarkController = TextEditingController();
    final cityController = TextEditingController(text: 'Bhiwani');
    final stateController = TextEditingController(text: 'Haryana');
    final pincodeController = TextEditingController();
    final formKey = GlobalKey<FormState>();

    showModalBottomSheet(
      context: context,
      backgroundColor: Colors.white,
      isScrollControlled: true,
      shape: const RoundedRectangleBorder(
        borderRadius: BorderRadius.vertical(top: Radius.circular(32)),
      ),
      builder: (ctx) {
        return Padding(
          padding: EdgeInsets.fromLTRB(24, 24, 24, MediaQuery.of(ctx).viewInsets.bottom + 24),
          child: Form(
            key: formKey,
            child: SingleChildScrollView(
              child: Column(
                mainAxisSize: MainAxisSize.min,
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Center(
                    child: Container(width: 40, height: 4, decoration: BoxDecoration(color: Colors.grey[300], borderRadius: BorderRadius.circular(2))),
                  ),
                  const SizedBox(height: 24),
                  Text('Add Delivery Address', style: GoogleFonts.spaceGrotesk(fontSize: 20, fontWeight: FontWeight.w900)),
                  const SizedBox(height: 20),
                  Row(
                    children: ['Home', 'Work', 'Other'].map((label) {
                      return Padding(
                        padding: const EdgeInsets.only(right: 8),
                        child: ChoiceChip(
                          label: Text(label.toUpperCase(), style: TextStyle(fontWeight: FontWeight.w900, fontSize: 10, color: labelController.text == label ? Colors.white : Colors.black45)),
                          selected: labelController.text == label,
                          selectedColor: WeloColors.sovereignOrange,
                          backgroundColor: const Color(0xFFF8F9FA),
                          onSelected: (val) {
                            if (val) labelController.text = label;
                          },
                        ),
                      );
                    }).toList(),
                  ),
                  const SizedBox(height: 16),
                  TextFormField(
                    controller: flatController,
                    decoration: const InputDecoration(labelText: 'Flat / Building', hintText: 'e.g. Flat 405'),
                    style: const TextStyle(fontWeight: FontWeight.w700),
                  ),
                  const SizedBox(height: 12),
                  TextFormField(
                    controller: streetController,
                    decoration: const InputDecoration(labelText: 'Street / Area', hintText: 'e.g. Main Street'),
                    style: const TextStyle(fontWeight: FontWeight.w700),
                  ),
                  const SizedBox(height: 12),
                  TextFormField(
                    controller: landmarkController,
                    decoration: const InputDecoration(labelText: 'Landmark (Optional)', hintText: 'e.g. Near Park'),
                    style: const TextStyle(fontWeight: FontWeight.w700),
                  ),
                  const SizedBox(height: 12),
                  Row(
                    children: [
                      Expanded(
                        child: TextFormField(
                          controller: cityController,
                          decoration: const InputDecoration(labelText: 'City'),
                          style: const TextStyle(fontWeight: FontWeight.w700),
                          validator: (v) => (v == null || v.trim().isEmpty) ? 'Required' : null,
                        ),
                      ),
                      const SizedBox(width: 12),
                      Expanded(
                        child: TextFormField(
                          controller: pincodeController,
                          decoration: const InputDecoration(labelText: 'Pincode', hintText: 'e.g. 123456'),
                          style: const TextStyle(fontWeight: FontWeight.w700),
                        ),
                      ),
                    ],
                  ),
                  const SizedBox(height: 24),
                  SizedBox(
                    width: double.infinity,
                    height: 52,
                    child: ElevatedButton(
                      style: ElevatedButton.styleFrom(
                        backgroundColor: Colors.black,
                        foregroundColor: Colors.white,
                        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
                      ),
                      onPressed: () async {
                        if (formKey.currentState?.validate() ?? false) {
                          Navigator.pop(ctx);
                          try {
                            await _addressRepository.storeAddress({
                              'label': labelController.text,
                              'flat_building': flatController.text,
                              'street': streetController.text,
                              'landmark': landmarkController.text.isEmpty ? null : landmarkController.text,
                              'city': cityController.text,
                              'state': stateController.text,
                              'pincode': pincodeController.text,
                            });
                            _loadAddresses();
                            if (mounted) {
                              ScaffoldMessenger.of(context).showSnackBar(
                                const SnackBar(content: Text('Address added!'), backgroundColor: Colors.green),
                              );
                            }
                          } catch (e) {
                            if (mounted) {
                              ScaffoldMessenger.of(context).showSnackBar(
                                SnackBar(content: Text('Failed to save address: $e'), backgroundColor: Colors.red),
                              );
                            }
                          }
                        }
                      },
                      child: const Text('SAVE ADDRESS', style: TextStyle(fontWeight: FontWeight.w900, letterSpacing: 1.0)),
                    ),
                  ),
                ],
              ),
            ),
          ),
        );
      },
    );
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: const WeloAppBar(title: 'Saved Addresses'),
      floatingActionButton: FloatingActionButton(
        onPressed: _showAddAddressDialog,
        backgroundColor: WeloColors.sovereignOrange,
        child: const Icon(Icons.add, color: Colors.white),
      ),
      body: _isLoading
          ? const Center(child: CircularProgressIndicator(color: WeloColors.sovereignOrange))
          : _addresses.isEmpty
              ? Center(
                  child: Column(
                    mainAxisAlignment: MainAxisAlignment.center,
                    children: [
                      Icon(Icons.location_off, size: 64, color: Colors.grey[300]),
                      const SizedBox(height: 16),
                      Text('No saved addresses', style: TextStyle(fontSize: 16, color: Colors.grey[500])),
                      const SizedBox(height: 8),
                      Text('Tap + to add your first address', style: TextStyle(fontSize: 13, color: Colors.grey[400])),
                    ],
                  ),
                )
              : ListView.builder(
                  padding: const EdgeInsets.all(16),
                  itemCount: _addresses.length,
                  itemBuilder: (context, index) {
                    final addr = _addresses[index];
                    return Card(
                      margin: const EdgeInsets.only(bottom: 12),
                      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
                      child: ListTile(
                        contentPadding: const EdgeInsets.all(16),
                        leading: CircleAvatar(
                          backgroundColor: WeloColors.sovereignOrange.withValues(alpha: 0.1),
                          child: Icon(
                            addr.label.toLowerCase() == 'home' ? Icons.home : Icons.location_on,
                            color: WeloColors.sovereignOrange,
                          ),
                        ),
                        title: Text(addr.label.toUpperCase(), style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 13)),
                        subtitle: Text('${addr.flatBuilding}, ${addr.street}', maxLines: 2, overflow: TextOverflow.ellipsis),
                        trailing: IconButton(
                          icon: const Icon(Icons.delete_outline, color: Colors.red, size: 20),
                          onPressed: () => _deleteAddress(addr.id),
                        ),
                      ),
                    );
                  },
                ),
    );
  }
}
