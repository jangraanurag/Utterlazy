import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:ui/ui.dart';
import 'package:core/core.dart';

class MembershipScreen extends ConsumerStatefulWidget {
  const MembershipScreen({super.key});

  @override
  ConsumerState<MembershipScreen> createState() => _MembershipScreenState();
}

class _MembershipScreenState extends ConsumerState<MembershipScreen> {
  String? _currentPlan;
  bool _loading = true;
  bool _enabled = false;

  final plans = [
    {'name': 'Silver', 'price': '₹99/mo', 'delivery': 'Free delivery under ₹20', 'perks': ['Free delivery', '5% extra coins', 'Priority support']},
    {'name': 'Gold', 'price': '₹199/mo', 'delivery': 'Free delivery under ₹40', 'perks': ['Free delivery', '10% extra coins', 'Priority support', 'Exclusive deals']},
    {'name': 'Diamond', 'price': '₹499/mo', 'delivery': 'Unlimited free delivery', 'perks': ['Free delivery', '20% extra coins', 'Priority support', 'Exclusive deals', 'Early access']},
  ];

  @override
  void initState() {
    super.initState();
    _loadMembership();
  }

  Future<void> _loadMembership() async {
    try {
      final apiClient = ref.read(apiClientProvider);
      final res = await apiClient.dio.get('/membership');
      setState(() {
        _enabled = res.data['enabled'] == true;
        _currentPlan = res.data['membership']?['plan'] as String?;
      });
    } catch (_) {}
    setState(() => _loading = false);
  }

  Future<void> _subscribe(String plan) async {
    if (!_enabled) return;
    try {
      final apiClient = ref.read(apiClientProvider);
      await apiClient.dio.post('/membership/subscribe', data: {'plan': plan.toLowerCase()});
      setState(() => _currentPlan = plan);
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text('🎉 $plan membership activated!'), backgroundColor: WeloColors.sovereignOrange),
        );
      }
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text('Failed: $e'), backgroundColor: Colors.red),
        );
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('UtterLazy Membership'), backgroundColor: Colors.white, elevation: 1),
      body: _loading
          ? const Center(child: CircularProgressIndicator(color: WeloColors.sovereignOrange))
          : !_enabled
              ? const Center(child: Padding(
                  padding: EdgeInsets.all(32),
                  child: Text('Currently unavailable', style: TextStyle(color: Colors.grey, fontSize: 16), textAlign: TextAlign.center),
                ))
              : SingleChildScrollView(
                  padding: const EdgeInsets.all(20),
                  child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Container(
                    width: double.infinity,
                    padding: const EdgeInsets.all(24),
                    decoration: BoxDecoration(
                      gradient: const LinearGradient(colors: [Color(0xFFFF6B00), Color(0xFFFF8C42)]),
                      borderRadius: BorderRadius.circular(24),
                    ),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        const Text('UtterLazy ' 'Gold', style: TextStyle(fontSize: 32, fontWeight: FontWeight.w900, color: Colors.white)),
                        const SizedBox(height: 8),
                        Text(_currentPlan != null ? 'Current plan: $_currentPlan' : 'Choose your plan', style: const TextStyle(fontSize: 16, color: Colors.white70)),
                      ],
                    ),
                  ),
                  const SizedBox(height: 24),
                  const Text('Choose Your Plan', style: TextStyle(fontSize: 22, fontWeight: FontWeight.w900)),
                  const SizedBox(height: 16),
                  ...plans.map((plan) => Container(
                        margin: const EdgeInsets.only(bottom: 16),
                        decoration: BoxDecoration(
                          borderRadius: BorderRadius.circular(20),
                          border: Border.all(color: _currentPlan == plan['name'] ? WeloColors.sovereignOrange : Colors.grey.shade200, width: _currentPlan == plan['name'] ? 2 : 1),
                          color: _currentPlan == plan['name'] ? WeloColors.sovereignOrange.withValues(alpha: 0.039) : Colors.white,
                        ),
                        child: Padding(
                          padding: const EdgeInsets.all(20),
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Row(
                                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                                children: [
                                  Text(plan['name'] as String, style: const TextStyle(fontSize: 20, fontWeight: FontWeight.w900)),
                                  Text(plan['price'] as String, style: const TextStyle(fontSize: 22, fontWeight: FontWeight.w900, color: WeloColors.sovereignOrange)),
                                ],
                              ),
                              const SizedBox(height: 8),
                              Text(plan['delivery'] as String, style: TextStyle(color: Colors.grey.shade600, fontSize: 13)),
                              const SizedBox(height: 12),
                              ...(plan['perks'] as List).map((p) => Padding(
                                    padding: const EdgeInsets.only(bottom: 6),
                                    child: Row(children: [
                                      const Icon(Icons.check, size: 18, color: Colors.green),
                                      const SizedBox(width: 8),
                                      Text(p.toString(), style: const TextStyle(fontSize: 14)),
                                    ]),
                                  )),
                              const SizedBox(height: 16),
                              SizedBox(
                                width: double.infinity,
                                child: ElevatedButton(
                                  onPressed: _currentPlan == plan['name'] ? null : () => _subscribe(plan['name'] as String),
                                  style: ElevatedButton.styleFrom(
                                    backgroundColor: _currentPlan == plan['name'] ? Colors.grey.shade300 : WeloColors.sovereignOrange,
                                    foregroundColor: _currentPlan == plan['name'] ? Colors.grey : Colors.white,
                                    padding: const EdgeInsets.symmetric(vertical: 16),
                                    shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
                                  ),
                                  child: Text(_currentPlan == plan['name'] ? 'Current Plan' : 'Subscribe'),
                                ),
                              ),
                            ],
                          ),
                        ),
                      )),
                ],
              ),
            ),
    );
  }
}
