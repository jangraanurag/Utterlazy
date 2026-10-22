import 'dart:math' as math;

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:razorpay_flutter/razorpay_flutter.dart';
import 'package:api/api.dart';
import 'package:core/core.dart';
import 'package:ui/ui.dart';
import 'package:dio/dio.dart';
import 'order_success_screen.dart';
import 'login_screen.dart';

class CheckoutScreen extends ConsumerStatefulWidget {
  const CheckoutScreen({super.key});

  @override
  ConsumerState<CheckoutScreen> createState() => _CheckoutScreenState();
}

class _CheckoutScreenState extends ConsumerState<CheckoutScreen> {
  late final OrderRepository _orderRepository;
  late final AddressRepository _addressRepository;
  late final RestaurantRepository _restaurantRepository;
  List<Address> _addresses = [];
  Address? _selectedAddress;
  Restaurant? _restaurant;
  double _calculatedDistance = 0.0;
  bool _isOutOfRange = false;
  double _walletBalance = 0.0;
  bool _isProcessing = false;
  String _paymentMode = 'cod';
  String _checkoutError = '';
  final TextEditingController _promoCodeCtrl = TextEditingController();
  bool _promoLoading = false;
  String? _promoError;

  bool _isBusinessOrder = false;
  final TextEditingController _companyNameController = TextEditingController();
  final TextEditingController _companyGstController = TextEditingController();

  @override
  void dispose() {
    _companyNameController.dispose();
    _companyGstController.dispose();
    _promoCodeCtrl.dispose();
    super.dispose();
  }

  @override
  void initState() {
    super.initState();
    final apiClient = ref.read(apiClientProvider);
    _orderRepository = OrderRepository(apiClient);
    _addressRepository = AddressRepository(apiClient);
    _restaurantRepository = RestaurantRepository(apiClient);
    _loadAddresses();
    _loadRestaurant();
    _loadWalletBalance();
  }

  Future<void> _loadWalletBalance() async {
    try {
      final apiClient = ref.read(apiClientProvider);
      final res = await apiClient.dio.get('/wallet/balance');
      if (mounted) {
        setState(() {
          _walletBalance = double.parse(res.data['balance'].toString());
        });
      }
    } catch (e) {
      debugPrint('Failed to load wallet balance: $e');
    }
  }

  Future<void> _applyPromoCode() async {
    final code = _promoCodeCtrl.text.trim();
    if (code.isEmpty) return;
    setState(() { _promoLoading = true; _promoError = null; });
    try {
      final cartNotifier = ref.read(cartProvider.notifier);
      final subtotal = ref.read(cartProvider).totalPrice;
      final repo = CouponRepository(ref.read(apiClientProvider));
      final result = await repo.validateCoupon(code, subtotal);
      final data = result['coupon'] ?? result;
      final discount = double.tryParse(data['discount']?.toString() ?? '0') ?? 0;
      if (discount > 0) {
        cartNotifier.applyDiscount(
          couponCode: code,
          amount: discount,
          label: data['title'] ?? code,
          type: 'coupon',
        );
      } else {
        setState(() { _promoError = 'Invalid or expired code'; });
      }
    } catch (e) {
      if (mounted) setState(() { _promoError = 'Invalid promo code'; });
    } finally {
      if (mounted) setState(() { _promoLoading = false; });
    }
  }

  Future<void> _loadRestaurant() async {
    try {
      final cartState = ref.read(cartProvider);
      final rId = cartState.restaurantId;
      if (rId != null) {
        final rest = await _restaurantRepository.getRestaurant(rId);
        if (mounted) {
          setState(() {
            _restaurant = rest;
          });
          _calculateDistance();
        }
      }
    } catch (e) {
      debugPrint('Failed to load restaurant details: $e');
    }
  }

  double _haversineDistance(double lat1, double lon1, double lat2, double lon2) {
    const r = 6371; // Earth's radius in km
    final dLat = _toRadians(lat2 - lat1);
    final dLon = _toRadians(lon2 - lon1);
    final a = math.sin(dLat / 2) * math.sin(dLat / 2) +
        math.cos(_toRadians(lat1)) * math.cos(_toRadians(lat2)) * math.sin(dLon / 2) * math.sin(dLon / 2);
    final c = 2 * math.atan2(math.sqrt(a), math.sqrt(1 - a));
    return r * c;
  }

  double _toRadians(double degree) {
    return degree * math.pi / 180;
  }

  void _calculateDistance() {
    if (_selectedAddress == null || _restaurant == null) {
      setState(() {
        _calculatedDistance = 0.0;
        _isOutOfRange = false;
      });
      return;
    }
    final lat1 = _selectedAddress!.latitude;
    final lng1 = _selectedAddress!.longitude;
    final lat2 = _restaurant!.latitude;
    final lng2 = _restaurant!.longitude;
    if (lat1 == null || lng1 == null || lat2 == null || lng2 == null) {
      setState(() {
        _calculatedDistance = 0.0;
        _isOutOfRange = false;
      });
      return;
    }
    final dist = _haversineDistance(lat1, lng1, lat2, lng2);
    setState(() {
      _calculatedDistance = dist;
      _isOutOfRange = dist > 10.0;
    });
  }

  Future<void> _loadAddresses() async {
    try {
      final res = await _addressRepository.getAddresses();
      if (mounted) {
        setState(() {
          _addresses = res;
          _checkoutError = ''; // Clear error on success
          if (_addresses.isNotEmpty) {
            _selectedAddress = _addresses.first;
            _calculateDistance();
          }
        });
      }
    } catch (e) {
      if (mounted) {
        String errMsg = 'Failed to load addresses.';
        if (e is DioException) {
          errMsg = e.response?.data?['message'] ?? e.response?.data?['error'] ?? errMsg;
        }
        setState(() {
          _checkoutError = errMsg;
        });
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    final isAuth = ref.watch(authProvider).isAuthenticated;

    if (!isAuth) {
      return _buildLoginPrompt();
    }

    final cartState = ref.watch(cartProvider);
    final subtotal = cartState.totalPrice;
    final discount = cartState.discountAmount;
    const deliveryFee = 39.0;
    const platformFee = 5.0;
    const taxes = 24.0;
    final total = subtotal - discount + deliveryFee + platformFee + taxes;

    return Scaffold(
      backgroundColor: const Color(0xFFF6F7F9),
      appBar: AppBar(
        title: const Text('CHECKOUT', style: TextStyle(fontSize: 13, fontWeight: FontWeight.w900, letterSpacing: 1)),
        centerTitle: true,
      ),
      body: Column(
        children: [
          Expanded(
            child: SingleChildScrollView(
              padding: const EdgeInsets.all(20),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  _buildSectionHeader(Icons.location_on, 'DELIVERY ADDRESS'),
                  _buildAddressSelector(),
                  const SizedBox(height: 24),
                  // Promo Code Input
                  _buildSectionHeader(Icons.local_offer, 'HAVE A PROMO CODE?'),
                  const SizedBox(height: 8),
                  Row(
                    children: [
                      Expanded(
                        child: TextField(
                          controller: _promoCodeCtrl,
                          decoration: InputDecoration(
                            hintText: 'Enter promo code',
                            border: OutlineInputBorder(borderRadius: BorderRadius.circular(12)),
                            contentPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
                            errorText: _promoError,
                          ),
                          style: const TextStyle(fontSize: 14, fontWeight: FontWeight.w600),
                        ),
                      ),
                      const SizedBox(width: 8),
                      ElevatedButton(
                        onPressed: _promoLoading ? null : _applyPromoCode,
                        style: ElevatedButton.styleFrom(
                          backgroundColor: WeloColors.sovereignOrange,
                          foregroundColor: Colors.white,
                          padding: const EdgeInsets.symmetric(horizontal: 20, vertical: 14),
                          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                        ),
                        child: _promoLoading
                            ? const SizedBox(width: 18, height: 18, child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white))
                            : const Text('Apply', style: TextStyle(fontWeight: FontWeight.bold)),
                      ),
                    ],
                  ),
                  const SizedBox(height: 24),
                  _buildSectionHeader(Icons.receipt_long, 'BILL SUMMARY'),
                  _buildBillCard(subtotal, discount, deliveryFee, platformFee, taxes, total),
                  
                  // Distance Warning Banner
                  if (_isOutOfRange) ...[
                    const SizedBox(height: 24),
                    Container(
                      padding: const EdgeInsets.all(16),
                      decoration: BoxDecoration(
                        color: Colors.red.shade50,
                        borderRadius: BorderRadius.circular(16),
                        border: Border.all(color: Colors.red.shade100),
                      ),
                      child: Row(
                        children: [
                          const Icon(Icons.location_off_outlined, color: Colors.red),
                          const SizedBox(width: 12),
                          Expanded(
                            child: Text(
                              'SELECTED DELIVERY ADDRESS IS ${_calculatedDistance.toStringAsFixed(1)} KM AWAY. WE ONLY DELIVER WITHIN A 10 KM RADIUS.',
                              style: TextStyle(
                                color: Colors.red.shade900,
                                fontSize: 10,
                                fontWeight: FontWeight.w900,
                                letterSpacing: 0.5,
                              ),
                            ),
                          ),
                        ],
                      ),
                    ),
                  ],

                  // Checkout Error Banner (e.g. Distance error or verification failure)
                  if (_checkoutError.isNotEmpty) ...[
                    const SizedBox(height: 24),
                    Container(
                      padding: const EdgeInsets.all(16),
                      decoration: BoxDecoration(
                        color: Colors.red.shade50,
                        borderRadius: BorderRadius.circular(16),
                        border: Border.all(color: Colors.red.shade100),
                      ),
                      child: Row(
                        children: [
                          const Icon(Icons.error_outline, color: Colors.red),
                          const SizedBox(width: 12),
                          Expanded(
                            child: Text(
                              _checkoutError.toUpperCase(),
                              style: TextStyle(
                                color: Colors.red.shade900,
                                fontSize: 10,
                                fontWeight: FontWeight.w900,
                                letterSpacing: 0.5,
                              ),
                            ),
                          ),
                        ],
                      ),
                    ),
                  ],

                  const SizedBox(height: 24),
                  _buildSectionHeader(Icons.payment, 'PAYMENT METHOD'),
                  _buildPaymentToggle(total),
                  const SizedBox(height: 24),
                  _buildSectionHeader(Icons.business_center, 'BUSINESS ORDERING'),
                  _buildBusinessOrderingCard(),
                  const SizedBox(height: 24),
                  _buildCulinariaNudge(),
                ],
              ),
            ),
          ),
          _buildBottomBar(total),
        ],
      ),
    );
  }

  Widget _buildSectionHeader(IconData icon, String title) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 12),
      child: Row(
        children: [
          Icon(icon, size: 16, color: Colors.black45),
          const SizedBox(width: 8),
          Text(title, style: const TextStyle(fontSize: 11, fontWeight: FontWeight.w900, color: Colors.black45, letterSpacing: 1)),
        ],
      ),
    );
  }

  Widget _buildAddressSelector() {
    return GestureDetector(
      onTap: _showAddressSelectorBottomSheet,
      child: Container(
        padding: const EdgeInsets.all(20),
        decoration: BoxDecoration(
          color: Colors.white,
          borderRadius: BorderRadius.circular(24),
          border: Border.all(color: Colors.black.withValues(alpha: 0.05)),
        ),
        child: Column(
          children: [
            if (_selectedAddress != null)
              Row(
                children: [
                  const CircleAvatar(backgroundColor: Color(0xFFF1F1F1), child: Icon(Icons.home, color: Colors.black, size: 18)),
                  const SizedBox(width: 16),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(_selectedAddress!.label.toUpperCase(), style: const TextStyle(fontWeight: FontWeight.w900, fontSize: 14)),
                        const SizedBox(height: 2),
                        Text('${_selectedAddress!.flatBuilding}, ${_selectedAddress!.street}', style: const TextStyle(color: Colors.black54, fontSize: 12), maxLines: 1, overflow: TextOverflow.ellipsis),
                      ],
                    ),
                  ),
                  const Text('CHANGE', style: TextStyle(color: WeloColors.sovereignOrange, fontWeight: FontWeight.w900, fontSize: 11)),
                ],
              )
            else
              Row(
                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                children: [
                  Expanded(
                    child: Row(
                      children: [
                        CircleAvatar(backgroundColor: WeloColors.sovereignOrange.withValues(alpha: 0.1), child: const Icon(Icons.location_off, color: WeloColors.sovereignOrange, size: 18)),
                        const SizedBox(width: 16),
                        const Expanded(child: Text('No address selected', style: TextStyle(color: Colors.black54, fontSize: 13, fontWeight: FontWeight.bold), overflow: TextOverflow.ellipsis)),
                      ],
                    ),
                  ),
                  const Text('SELECT', style: TextStyle(color: WeloColors.sovereignOrange, fontWeight: FontWeight.w900, fontSize: 11)),
                ],
              ),
          ],
        ),
      ),
    );
  }

  Widget _buildBillCard(double subtotal, double discount, double delivery, double platformFee, double taxes, double total) {
    final cartState = ref.watch(cartProvider);
    return Container(
      padding: const EdgeInsets.all(24),
      decoration: BoxDecoration(color: Colors.white, borderRadius: BorderRadius.circular(24), border: Border.all(color: Colors.black.withValues(alpha: 0.05))),
      child: Column(
        children: [
          _buildBillRow('Item Total', '₹${subtotal.toInt()}'),
          const SizedBox(height: 12),
          _buildBillRow('Delivery Fee | 2.4 km', '₹${delivery.toInt()}'),
          const SizedBox(height: 12),
          _buildBillRow('Platform Fee', '₹${platformFee.toInt()}'),
          const SizedBox(height: 12),
          if (discount > 0 && cartState.discountLabel != null) ...[
            _buildBillRow(cartState.discountLabel!, '-₹${discount.toInt()}', isDiscount: true),
            const SizedBox(height: 12),
          ],
          _buildBillRow('Taxes & Fees', '₹${taxes.toInt()}'),
          const Padding(
            padding: EdgeInsets.symmetric(vertical: 20),
            child: Divider(height: 1, color: Color(0xFFF1F1F1)),
          ),
          _buildBillRow('TO PAY', '₹${total.toInt()}', isBold: true),
        ],
      ),
    );
  }

  Widget _buildBillRow(String label, String value, {bool isDiscount = false, bool isBold = false}) {
    return Row(
      mainAxisAlignment: MainAxisAlignment.spaceBetween,
      children: [
        Flexible(child: Text(label, style: TextStyle(fontSize: isBold ? 14 : 12, fontWeight: isBold ? FontWeight.w900 : FontWeight.w600, color: isDiscount ? Colors.green : (isBold ? Colors.black : Colors.black45)), overflow: TextOverflow.ellipsis)),
        const SizedBox(width: 8),
        Text(value, style: TextStyle(fontSize: isBold ? 18 : 12, fontWeight: FontWeight.w900, color: isDiscount ? Colors.green : Colors.black)),
      ],
    );
  }

  Widget _buildPaymentToggle(double orderTotal) {
    return Container(
      decoration: BoxDecoration(color: Colors.white, borderRadius: BorderRadius.circular(24), border: Border.all(color: Colors.black.withValues(alpha: 0.05))),
      child: Column(
        children: [
          _buildPaymentOption('cod', 'Cash on Delivery', Icons.payments_outlined),
          const Divider(height: 1, indent: 60, color: Color(0xFFF1F1F1)),
          _buildPaymentOption('online', 'Online Payment / UPI', Icons.account_balance_outlined),
          const Divider(height: 1, indent: 60, color: Color(0xFFF1F1F1)),
          _buildPaymentOption(
            'wallet',
            'UtterLazy Wallet (₹${_walletBalance.toStringAsFixed(2)})',
            Icons.account_balance_wallet_outlined,
            enabled: _walletBalance >= orderTotal,
          ),
        ],
      ),
    );
  }

  Widget _buildPaymentOption(String value, String label, IconData icon, {bool enabled = true}) {
    final bool isSelected = _paymentMode == value;
    return Opacity(
      opacity: enabled ? 1.0 : 0.4,
      child: ListTile(
        onTap: enabled ? () => setState(() => _paymentMode = value) : null,
        leading: Icon(icon, color: Colors.black, size: 20),
        title: Text(
          label,
          style: TextStyle(
            fontSize: 13,
            fontWeight: FontWeight.bold,
            color: enabled ? Colors.black : Colors.black38,
          ),
          overflow: TextOverflow.ellipsis,
        ),
        subtitle: !enabled && value == 'wallet'
            ? const Text('INSUFFICIENT BALANCE', style: TextStyle(color: Colors.red, fontSize: 10, fontWeight: FontWeight.bold))
            : null,
        trailing: isSelected
            ? const Icon(Icons.check_circle, color: WeloColors.sovereignOrange, size: 20)
            : const Icon(Icons.circle_outlined, color: Colors.black12, size: 20),
        contentPadding: const EdgeInsets.symmetric(horizontal: 20, vertical: 4),
      ),
    );
  }

  Widget _buildCulinariaNudge() {
    return Container(
      padding: const EdgeInsets.all(20),
      decoration: BoxDecoration(color: Colors.blue.withValues(alpha: 0.05), borderRadius: BorderRadius.circular(24), border: Border.all(color: Colors.blue.withValues(alpha: 0.1))),
      child: Row(
        children: [
          const Icon(Icons.sports_esports, color: Colors.blue, size: 24),
          const SizedBox(width: 16),
          const Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text('ARENA IS OPEN', style: TextStyle(color: Colors.blue, fontSize: 10, fontWeight: FontWeight.w900, letterSpacing: 1)),
                const SizedBox(height: 2),
                Text('Play while we prepare your meal.', style: TextStyle(fontSize: 11, fontWeight: FontWeight.w600, color: Colors.black54)),
              ],
            ),
          ),
          const Icon(Icons.chevron_right, color: Colors.blue, size: 20),
        ],
      ),
    );
  }

  Widget _buildBusinessOrderingCard() {
    return Container(
      padding: const EdgeInsets.all(20),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(24),
        border: Border.all(color: Colors.black.withValues(alpha: 0.05)),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              Expanded(
                child: Row(
                  children: [
                    Icon(
                      Icons.business_center_outlined,
                      color: _isBusinessOrder ? WeloColors.sovereignOrange : Colors.black45,
                      size: 20,
                    ),
                    const SizedBox(width: 16),
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(
                            'Claim GST Input Credit',
                            style: TextStyle(
                              fontSize: 13,
                              fontWeight: FontWeight.bold,
                              color: _isBusinessOrder ? Colors.black : Colors.black87,
                            ),
                          ),
                          const Text(
                            'Enter GST details for business invoice',
                            style: TextStyle(fontSize: 10, color: Colors.black45),
                          ),
                        ],
                      ),
                    ),
                  ],
                ),
              ),
              Switch(
                value: _isBusinessOrder,
                onChanged: (val) {
                  setState(() {
                    _isBusinessOrder = val;
                  });
                },
                activeThumbColor: WeloColors.sovereignOrange,
              ),
            ],
          ),
          if (_isBusinessOrder) ...[
            const SizedBox(height: 16),
            const Divider(height: 1, color: Color(0xFFF1F1F1)),
            const SizedBox(height: 16),
            TextField(
              controller: _companyNameController,
              textCapitalization: TextCapitalization.words,
              decoration: InputDecoration(
                labelText: 'COMPANY NAME',
                labelStyle: const TextStyle(fontSize: 9, fontWeight: FontWeight.bold, letterSpacing: 1, color: Colors.black38),
                hintText: 'e.g. Acme Corporation',
                hintStyle: const TextStyle(fontSize: 13, color: Colors.black26),
                contentPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
                border: OutlineInputBorder(borderRadius: BorderRadius.circular(12), borderSide: const BorderSide(color: Color(0xFFE5E7EB))),
                enabledBorder: OutlineInputBorder(borderRadius: BorderRadius.circular(12), borderSide: const BorderSide(color: Color(0xFFE5E7EB))),
                focusedBorder: OutlineInputBorder(borderRadius: BorderRadius.circular(12), borderSide: const BorderSide(color: WeloColors.sovereignOrange)),
              ),
              style: const TextStyle(fontSize: 13, fontWeight: FontWeight.bold),
            ),
            const SizedBox(height: 16),
            TextField(
              controller: _companyGstController,
              textCapitalization: TextCapitalization.characters,
              decoration: InputDecoration(
                labelText: 'GST NUMBER',
                labelStyle: const TextStyle(fontSize: 9, fontWeight: FontWeight.bold, letterSpacing: 1, color: Colors.black38),
                hintText: 'e.g. 07AAAAA1111A1Z1',
                hintStyle: const TextStyle(fontSize: 13, color: Colors.black26),
                contentPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
                border: OutlineInputBorder(borderRadius: BorderRadius.circular(12), borderSide: const BorderSide(color: Color(0xFFE5E7EB))),
                enabledBorder: OutlineInputBorder(borderRadius: BorderRadius.circular(12), borderSide: const BorderSide(color: Color(0xFFE5E7EB))),
                focusedBorder: OutlineInputBorder(borderRadius: BorderRadius.circular(12), borderSide: const BorderSide(color: WeloColors.sovereignOrange)),
              ),
              style: const TextStyle(fontSize: 13, fontWeight: FontWeight.bold),
            ),
          ],
        ],
      ),
    );
  }

  Widget _buildBottomBar(double total) {
    return Container(
      padding: const EdgeInsets.fromLTRB(24, 20, 24, 40),
      decoration: const BoxDecoration(color: Colors.white, boxShadow: [BoxShadow(color: Colors.black12, blurRadius: 20, offset: Offset(0, -5))]),
      child: Row(
        children: [
          Expanded(
            child: Column(
              mainAxisSize: MainAxisSize.min,
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text('₹${total.toInt()}', style: const TextStyle(fontSize: 20, fontWeight: FontWeight.w900)),
                const Text('VIEW DETAILED BILL', style: TextStyle(fontSize: 9, fontWeight: FontWeight.w900, color: WeloColors.sovereignOrange, letterSpacing: 0.5)),
              ],
            ),
          ),
          GestureDetector(
            onTap: (_isProcessing || _isOutOfRange) ? null : _handlePlaceOrder,
            child: Container(
              height: 56,
              padding: const EdgeInsets.symmetric(horizontal: 32),
              decoration: BoxDecoration(
                color: (_selectedAddress == null || _isOutOfRange) ? Colors.grey[400] : Colors.black, 
                borderRadius: BorderRadius.circular(16), 
                boxShadow: (_selectedAddress == null || _isOutOfRange) ? null : [
                  BoxShadow(color: Colors.black.withValues(alpha: 0.2), blurRadius: 15, offset: const Offset(0, 8))
                ]
              ),
              alignment: Alignment.center,
              child: _isProcessing 
                  ? const SizedBox(width: 20, height: 20, child: CircularProgressIndicator(color: Colors.white, strokeWidth: 2))
                  : Text(
                      'PROCEED TO PAY', 
                      style: TextStyle(
                        color: (_selectedAddress == null || _isOutOfRange) ? Colors.black38 : Colors.white, 
                        fontSize: 13, 
                        fontWeight: FontWeight.w900, 
                        letterSpacing: 1
                      )
                    ),
            ),
          ),
        ],
      ),
    );
  }

  Future<void> _handlePlaceOrder() async {
    if (_selectedAddress == null) {
      setState(() {
        _checkoutError = 'Please select a delivery address to proceed.';
      });
      _showAddressSelectorBottomSheet();
      return;
    }

    if (_isBusinessOrder) {
      if (_companyNameController.text.trim().isEmpty) {
        setState(() {
          _checkoutError = 'Please enter company name.';
        });
        return;
      }
      if (_companyGstController.text.trim().isEmpty) {
        setState(() {
          _checkoutError = 'Please enter company GST number.';
        });
        return;
      }
      final gstPattern = RegExp(r'^[0-9]{2}[A-Z]{5}[0-9]{4}[A-Z]{1}[1-9A-Z]{1}Z[0-9A-Z]{1}$');
      if (!gstPattern.hasMatch(_companyGstController.text.trim().toUpperCase())) {
        setState(() {
          _checkoutError = 'Please enter a valid 15-digit GSTIN.';
        });
        return;
      }
    }
    
    setState(() {
      _isProcessing = true;
      _checkoutError = '';
    });
    
    try {
      final cartState = ref.read(cartProvider);
      final res = await _orderRepository.checkout({
        'restaurant_id': cartState.restaurantId,
        'address_id': _selectedAddress!.id,
        'payment_mode': _paymentMode == 'online' ? 'prepaid' : (_paymentMode == 'wallet' ? 'wallet' : 'cod'),
        'items': cartState.allItems.map((ci) => ci.toCheckoutJson()).toList(),
        'is_business_order': _isBusinessOrder,
        'company_name': _isBusinessOrder ? _companyNameController.text.trim() : null,
        'company_gst': _isBusinessOrder ? _companyGstController.text.trim().toUpperCase() : null,
        if (cartState.appliedCouponCode != null) 'coupon_code': cartState.appliedCouponCode!,
      });
      
      if (_paymentMode == 'online') {
        try {
          final apiClient = ref.read(apiClientProvider);
          final payOrderRes = await apiClient.dio.post('/orders/${res.id}/pay/create');
          final payOrder = payOrderRes.data;
          
          setState(() {
            _isProcessing = false;
          });
          
          if (mounted) {
            _showPaymentSimulatorDialog(res, payOrder);
          }
        } catch (payError) {
          String payMsg = 'Payment initiation failed.';
          if (payError is DioException) {
            payMsg = payError.response?.data?['error'] ?? payError.response?.data?['message'] ?? payMsg;
          }
          setState(() {
            _isProcessing = false;
            _checkoutError = payMsg;
          });
        }
      } else {
        ref.read(cartProvider.notifier).clearCart();
        if (mounted) {
          Navigator.pushReplacement(context, MaterialPageRoute(builder: (context) => OrderSuccessScreen(order: res)));
        }
      }
    } catch (e) {
      String errMsg = 'Checkout failed. Please try again.';
      if (e is DioException) {
        errMsg = e.response?.data?['error'] ?? e.response?.data?['message'] ?? errMsg;
      } else {
        errMsg = e.toString();
      }
      if (mounted) {
        setState(() {
          _isProcessing = false;
          _checkoutError = errMsg;
        });
      }
    }
  }

  void _showPaymentSimulatorDialog(Order order, Map<String, dynamic> payOrder) {
    final razorpayOrderId = payOrder['razorpay_order']?['id'] ?? '';
    final razorpayKeyId = payOrder['key_id'] ?? '';
    final amount = order.totalAmount;

    if (razorpayOrderId.isEmpty || razorpayKeyId.isEmpty) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Payment setup failed. Try again.')),
      );
      return;
    }

    // Initialize Razorpay
    final razorpay = Razorpay();
    razorpay.on(Razorpay.EVENT_PAYMENT_SUCCESS, (PaymentSuccessResponse response) async {
      // Payment success — verify with backend
      setState(() => _isProcessing = true);
      try {
        final apiClient = ref.read(apiClientProvider);
        await apiClient.dio.post(
          '/orders/${order.id}/pay/verify',
          data: {
            'razorpay_order_id': response.orderId ?? razorpayOrderId,
            'razorpay_payment_id': response.paymentId ?? '',
            'razorpay_signature': response.signature ?? '',
          },
        );
        ref.read(cartProvider.notifier).clearCart();
        if (mounted) {
          Navigator.pushReplacement(
            context,
            MaterialPageRoute(builder: (context) => OrderSuccessScreen(order: order)),
          );
        }
      } catch (verifyError) {
        String verifyMsg = 'Payment verification failed.';
        if (verifyError is DioException) {
          verifyMsg = verifyError.response?.data?['error'] ?? verifyMsg;
        }
        if (mounted) {
          ScaffoldMessenger.of(context).showSnackBar(
            SnackBar(content: Text(verifyMsg), backgroundColor: Colors.red),
          );
          setState(() => _isProcessing = false);
        }
      }
    });

    razorpay.on(Razorpay.EVENT_PAYMENT_ERROR, (PaymentFailureResponse response) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text('Payment failed: ${response.message ?? "Unknown error"}'),
            backgroundColor: Colors.red,
          ),
        );
        setState(() => _isProcessing = false);
      }
    });

    razorpay.on(Razorpay.EVENT_EXTERNAL_WALLET, (ExternalWalletResponse response) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text('External wallet: ${response.walletName}')),
        );
      }
    });

    // Open Razorpay checkout
    try {
      var options = {
        'key': razorpayKeyId,
        'amount': (amount * 100).toInt(), // Razorpay expects paise
        'name': 'UtterLazy',
        'order_id': razorpayOrderId,
        'description': 'Order #${order.id.substring(0, 8).toUpperCase()}',
        'timeout': 300, // 5 minutes
        'prefill': {
          'contact': order.customerPhone ?? '',
          'email': '',
        },
        'theme': {
          'color': '#FF6B00',
        },
        // Allow customer to cancel and switch to COD
        'modal': {
          'ondismiss': () {
            // Customer dismissed Razorpay — show option to switch to COD
            _showSwitchToCodDialog(order);
          },
        },
      };
      razorpay.open(options);
    } catch (e) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text('Failed to open payment: $e'), backgroundColor: Colors.red),
      );
      setState(() => _isProcessing = false);
    }
  }

  void _showSwitchToCodDialog(Order order) {
    showDialog(
      context: context,
      builder: (ctx) => AlertDialog(
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(20)),
        title: const Text('Payment Cancelled'),
        content: const Text('Do you want to pay with Cash on Delivery instead?'),
        actions: [
          TextButton(
            onPressed: () {
              Navigator.pop(ctx);
              setState(() {
                _checkoutError = 'Payment cancelled';
              });
            },
            child: const Text('Cancel Order'),
          ),
          ElevatedButton(
            style: ElevatedButton.styleFrom(backgroundColor: WeloColors.sovereignOrange),
            onPressed: () async {
              Navigator.pop(ctx);
              setState(() => _isProcessing = true);
              try {
                final apiClient = ref.read(apiClientProvider);
                await apiClient.dio.post('/orders/${order.id}/convert-to-cod');
                ref.read(cartProvider.notifier).clearCart();
                if (mounted) {
                  Navigator.pushReplacement(
                    context,
                    MaterialPageRoute(builder: (context) => OrderSuccessScreen(order: order)),
                  );
                }
              } catch (e) {
                if (mounted) {
                  ScaffoldMessenger.of(context).showSnackBar(
                    SnackBar(content: Text('Failed: $e'), backgroundColor: Colors.red),
                  );
                  setState(() => _isProcessing = false);
                }
              }
            },
            child: const Text('Pay Cash on Delivery', style: TextStyle(color: Colors.white)),
          ),
        ],
      ),
    );
  }

  Widget _buildLoginPrompt() {
    return Scaffold(
      appBar: AppBar(
        title: const Text('CHECKOUT', style: TextStyle(fontSize: 13, fontWeight: FontWeight.w900, letterSpacing: 1)),
        centerTitle: true,
      ),
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
                child: const Icon(Icons.lock_outline, size: 48, color: WeloColors.sovereignOrange),
              ),
              const SizedBox(height: 24),
              Text('Sign in to place order', style: GoogleFonts.spaceGrotesk(fontSize: 20, fontWeight: FontWeight.w900)),
              const SizedBox(height: 8),
              Text('You need to be signed in to complete your order.',
                style: GoogleFonts.inter(fontSize: 14, color: WeloColors.textBody), textAlign: TextAlign.center),
              const SizedBox(height: 32),
              SizedBox(
                width: double.infinity, height: 56,
                child: ElevatedButton(
                  onPressed: () async {
                    final result = await Navigator.push(context, MaterialPageRoute(
                      builder: (_) => const LoginScreen(showBack: true)));
                    if (result == true) {
                      _loadAddresses();
                      _loadRestaurant();
                      _loadWalletBalance();
                    }
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
                onPressed: () => Navigator.pop(context),
                child: Text('Go Back', style: GoogleFonts.inter(fontSize: 14, color: WeloColors.textBody)),
              ),
            ],
          ),
        ),
      ),
    );
  }

  void _showAddressSelectorBottomSheet() {
    showModalBottomSheet(
      context: context,
      backgroundColor: Colors.white,
      shape: const RoundedRectangleBorder(
        borderRadius: BorderRadius.vertical(top: Radius.circular(32)),
      ),
      isScrollControlled: true,
      builder: (context) {
        return StatefulBuilder(
          builder: (context, setSheetState) {
            return Container(
              padding: EdgeInsets.fromLTRB(24, 24, 24, MediaQuery.of(context).viewInsets.bottom + 24),
              child: Column(
                mainAxisSize: MainAxisSize.min,
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Center(
                    child: Container(
                      width: 40,
                      height: 4,
                      decoration: BoxDecoration(color: Colors.grey[300], borderRadius: BorderRadius.circular(2)),
                    ),
                  ),
                  const SizedBox(height: 24),
                  Text(
                    'Select Delivery Address',
                    style: GoogleFonts.spaceGrotesk(fontSize: 20, fontWeight: FontWeight.w900),
                  ),
                  const SizedBox(height: 16),
                  ConstrainedBox(
                    constraints: BoxConstraints(maxHeight: MediaQuery.of(context).size.height * 0.4),
                    child: _addresses.isEmpty
                        ? Padding(
                            padding: const EdgeInsets.symmetric(vertical: 32.0),
                            child: Center(
                              child: Text(
                                'No addresses saved yet.',
                                style: TextStyle(color: Colors.grey[500], fontSize: 14),
                              ),
                            ),
                          )
                        : ListView.builder(
                            shrinkWrap: true,
                            itemCount: _addresses.length,
                            itemBuilder: (context, index) {
                              final addr = _addresses[index];
                              final isSel = _selectedAddress?.id == addr.id;
                              return InkWell(
                                onTap: () {
                                  setState(() {
                                    _selectedAddress = addr;
                                    _checkoutError = '';
                                    _calculateDistance();
                                  });
                                  Navigator.pop(context);
                                },
                                child: Container(
                                  margin: const EdgeInsets.only(bottom: 12),
                                  padding: const EdgeInsets.all(16),
                                  decoration: BoxDecoration(
                                    color: isSel ? WeloColors.sovereignOrange.withValues(alpha: 0.02) : Colors.transparent,
                                    borderRadius: BorderRadius.circular(20),
                                    border: Border.all(
                                      color: isSel ? WeloColors.sovereignOrange : const Color(0xFFE9ECEF),
                                      width: isSel ? 1.5 : 1.0,
                                    ),
                                  ),
                                  child: Row(
                                    children: [
                                      Icon(
                                        addr.label.toLowerCase() == 'home'
                                            ? Icons.home_outlined
                                            : addr.label.toLowerCase() == 'work'
                                                ? Icons.business_center_outlined
                                                : Icons.location_on_outlined,
                                        color: isSel ? WeloColors.sovereignOrange : Colors.black45,
                                      ),
                                      const SizedBox(width: 16),
                                      Expanded(
                                        child: Column(
                                          crossAxisAlignment: CrossAxisAlignment.start,
                                          children: [
                                            Text(
                                              addr.label.toUpperCase(),
                                              style: TextStyle(
                                                fontWeight: FontWeight.w900,
                                                fontSize: 12,
                                                color: isSel ? WeloColors.sovereignOrange : Colors.black,
                                              ),
                                            ),
                                            const SizedBox(height: 4),
                                            Text(
                                              '${addr.flatBuilding}, ${addr.street}',
                                              style: TextStyle(
                                                color: isSel ? Colors.black87 : Colors.black54,
                                                fontSize: 13,
                                                fontWeight: FontWeight.w500,
                                              ),
                                              maxLines: 2,
                                              overflow: TextOverflow.ellipsis,
                                            ),
                                          ],
                                        ),
                                      ),
                                      if (isSel)
                                        const Icon(Icons.check_circle, color: WeloColors.sovereignOrange),
                                    ],
                                  ),
                                ),
                              );
                            },
                          ),
                  ),
                  const SizedBox(height: 16),
                  SizedBox(
                    width: double.infinity,
                    height: 52,
                    child: OutlinedButton.icon(
                      style: OutlinedButton.styleFrom(
                        side: const BorderSide(color: WeloColors.sovereignOrange, width: 1.5),
                        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
                      ),
                      onPressed: () {
                        Navigator.pop(context);
                        _showAddAddressDialog();
                      },
                      icon: const Icon(Icons.add, color: WeloColors.sovereignOrange),
                      label: const Text(
                        'ADD NEW ADDRESS',
                        style: TextStyle(color: WeloColors.sovereignOrange, fontWeight: FontWeight.w900, fontSize: 13, letterSpacing: 0.5),
                      ),
                    ),
                  ),
                ],
              ),
            );
          },
        );
      },
    );
  }

  void _showAddAddressDialog() {
    final formKey = GlobalKey<FormState>();
    final labelController = TextEditingController(text: 'Home');
    final flatController = TextEditingController();
    final streetController = TextEditingController();
    final landmarkController = TextEditingController();
    final cityController = TextEditingController(text: 'Bengaluru');
    final stateController = TextEditingController(text: 'Karnataka');
    final pincodeController = TextEditingController();
    
    final locationState = ref.read(locationProvider);
    final lat = locationState.latitude;
    final lng = locationState.longitude;

    showModalBottomSheet(
      context: context,
      backgroundColor: Colors.white,
      isScrollControlled: true,
      shape: const RoundedRectangleBorder(
        borderRadius: BorderRadius.vertical(top: Radius.circular(32)),
      ),
      builder: (context) {
        return Padding(
          padding: EdgeInsets.fromLTRB(24, 24, 24, MediaQuery.of(context).viewInsets.bottom + 24),
          child: Form(
            key: formKey,
            child: SingleChildScrollView(
              child: Column(
                mainAxisSize: MainAxisSize.min,
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Center(
                    child: Container(
                      width: 40,
                      height: 4,
                      decoration: BoxDecoration(color: Colors.grey[300], borderRadius: BorderRadius.circular(2)),
                    ),
                  ),
                  const SizedBox(height: 24),
                  Text(
                    'Add Delivery Address',
                    style: GoogleFonts.spaceGrotesk(fontSize: 20, fontWeight: FontWeight.w900),
                  ),
                  const SizedBox(height: 4),
                  Text(
                    'Auto-populating coordinates from: ${locationState.name}',
                    style: const TextStyle(fontSize: 11, color: Colors.black38, fontWeight: FontWeight.bold),
                  ),
                  const SizedBox(height: 20),
                  
                  // Geocode prefilling
                  FutureBuilder<Map<String, dynamic>?>(
                    future: LocationService().reverseGeocode(lat, lng),
                    builder: (context, snapshot) {
                      if (snapshot.connectionState == ConnectionState.done && snapshot.hasData) {
                        final reverseGeocodeData = snapshot.data!;
                        if (reverseGeocodeData['results'] != null && (reverseGeocodeData['results'] as List).isNotEmpty) {
                          final result = (reverseGeocodeData['results'] as List).first;
                          final components = result['address_components'] as List?;
                          if (components != null) {
                            String? city;
                            String? state;
                            String? pincode;
                            for (final c in components) {
                              final types = c['types'] as List?;
                              if (types != null) {
                                if (types.contains('locality')) {
                                  city = c['long_name'] as String?;
                                } else if (types.contains('administrative_area_level_1')) {
                                  state = c['long_name'] as String?;
                                } else if (types.contains('postal_code')) {
                                  pincode = c['long_name'] as String?;
                                }
                              }
                            }
                            if (city != null && cityController.text == 'Bengaluru') {
                              WidgetsBinding.instance.addPostFrameCallback((_) {
                                cityController.text = city!;
                              });
                            }
                            if (state != null && stateController.text == 'Karnataka') {
                              WidgetsBinding.instance.addPostFrameCallback((_) {
                                stateController.text = state!;
                              });
                            }
                            if (pincode != null && pincodeController.text.isEmpty) {
                              WidgetsBinding.instance.addPostFrameCallback((_) {
                                pincodeController.text = pincode!;
                              });
                            }
                          }
                          if (streetController.text.isEmpty && result['formatted_address'] != null) {
                            WidgetsBinding.instance.addPostFrameCallback((_) {
                              streetController.text = result['formatted_address'] as String;
                            });
                          }
                        }
                      }
                      return const SizedBox.shrink();
                    },
                  ),
                  
                  Row(
                    children: ['Home', 'Work', 'Other'].map((label) {
                      return StatefulBuilder(
                        builder: (context, setBubbleState) {
                          final isSel = labelController.text == label;
                          return Container(
                            margin: const EdgeInsets.only(right: 8),
                            child: ChoiceChip(
                              label: Text(label.toUpperCase(), style: TextStyle(fontWeight: FontWeight.w900, fontSize: 10, color: isSel ? Colors.white : Colors.black45)),
                              selected: isSel,
                              selectedColor: WeloColors.sovereignOrange,
                              backgroundColor: const Color(0xFFF8F9FA),
                              onSelected: (val) {
                                if (val) {
                                  setState(() {
                                    labelController.text = label;
                                  });
                                  (context as Element).markNeedsBuild();
                                }
                              },
                            ),
                          );
                        },
                      );
                    }).toList(),
                  ),
                  const SizedBox(height: 16),

                  _buildFormField(flatController, 'Flat / House / Building No.', 'e.g. Flat 405, Tower B'),
                  const SizedBox(height: 12),
                  _buildFormField(streetController, 'Street / Area', 'e.g. The Imperial Estate, 123 Main St'),
                  const SizedBox(height: 12),
                  _buildFormField(landmarkController, 'Landmark (Optional)', 'e.g. Opposite Royal Park', isRequired: false),
                  const SizedBox(height: 12),
                  Row(
                    children: [
                      Expanded(child: _buildFormField(cityController, 'City', 'e.g. Bengaluru')),
                      const SizedBox(width: 12),
                      Expanded(child: _buildFormField(pincodeController, 'Pincode', 'e.g. 560001')),
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
                          Navigator.pop(context);
                          setState(() => _isProcessing = true);
                          
                          try {
                            final newAddress = await _addressRepository.storeAddress({
                              'label': labelController.text,
                              'flat_building': flatController.text,
                              'street': streetController.text,
                              'landmark': landmarkController.text.isEmpty ? null : landmarkController.text,
                              'city': cityController.text,
                              'state': stateController.text,
                              'pincode': pincodeController.text,
                              'latitude': lat,
                              'longitude': lng,
                            });
                            
                            await _loadAddresses();
                            setState(() {
                              _selectedAddress = newAddress;
                              _isProcessing = false;
                              _checkoutError = '';
                              _calculateDistance();
                            });
                          } catch (e) {
                            String errMsg = 'Failed to save address.';
                            if (e is DioException) {
                              errMsg = e.response?.data?['message'] ?? e.response?.data?['error'] ?? errMsg;
                            }
                            setState(() {
                              _isProcessing = false;
                              _checkoutError = errMsg;
                            });
                          }
                        }
                      },
                      child: const Text(
                        'SAVE ADDRESS',
                        style: TextStyle(fontWeight: FontWeight.w900, letterSpacing: 1.0),
                      ),
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

  Widget _buildFormField(TextEditingController controller, String label, String hint, {bool isRequired = true}) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(label, style: const TextStyle(fontSize: 11, fontWeight: FontWeight.w900, color: Colors.black45)),
        const SizedBox(height: 6),
        TextFormField(
          controller: controller,
          validator: isRequired 
              ? (val) => (val == null || val.trim().isEmpty) ? 'Required field' : null
              : null,
          style: const TextStyle(fontSize: 14, fontWeight: FontWeight.w700),
          decoration: InputDecoration(
            hintText: hint,
            hintStyle: const TextStyle(color: Colors.black26, fontSize: 13),
            filled: true,
            fillColor: const Color(0xFFF8F9FA),
            border: OutlineInputBorder(borderRadius: BorderRadius.circular(12), borderSide: BorderSide.none),
            contentPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
          ),
        ),
      ],
    );
  }
}
