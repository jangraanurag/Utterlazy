import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:ui/ui.dart';
import 'package:api/api.dart';
import 'package:core/core.dart';
import 'login_screen.dart';
import 'order_tracking_screen.dart';
import 'download_invoice_screen.dart';
import 'cart_screen.dart';

class OrderHistoryScreen extends ConsumerStatefulWidget {
  const OrderHistoryScreen({super.key});

  @override
  ConsumerState<OrderHistoryScreen> createState() => _OrderHistoryScreenState();
}

class _OrderHistoryScreenState extends ConsumerState<OrderHistoryScreen> {
  late final OrderRepository _repository;
  List<dynamic> _orders = [];
  bool _isLoading = true;
  String? _processingId;
  String? _error;

  @override
  void initState() {
    super.initState();
    _repository = OrderRepository(ref.read(apiClientProvider));
    if (ref.read(authProvider).isAuthenticated) {
      _loadOrders();
    }
    ref.listen(authProvider, (prev, next) {
      if (next.isAuthenticated && !(prev?.isAuthenticated ?? false)) {
        _loadOrders();
      }
    });
  }

  Future<void> _loadOrders() async {
    try {
      final res = await _repository.getOrders();
      if (mounted) {
        setState(() {
          final raw = res['data'] ?? res['recent_orders'] ?? res['orders'] ?? [];
          _orders = raw is List ? raw : [];
          _error = null;
          _isLoading = false;
        });
      }
    } catch (e) {
      debugPrint('OrderHistoryScreen._loadOrders error: $e');
      if (mounted) setState(() { _isLoading = false; _error = e.toString(); });
    }
  }

  Future<void> _handlePayOnline(String orderId) async {
    setState(() => _processingId = orderId);
    try {
      final res = await _repository.payOnline(orderId);
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(res['message'] ?? 'Payment Successful')),
      );
      await _loadOrders();
    } catch (e) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(e.toString())),
      );
    } finally {
      if (mounted) setState(() => _processingId = null);
    }
  }

  Future<void> _handleReorder(String orderId) async {
    setState(() => _processingId = orderId);
    try {
      final data = await _repository.reorder(orderId);
      final itemCount = data['item_count'] ?? 0;
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text('$itemCount items added to cart!'),
            backgroundColor: WeloColors.sovereignOrange,
            action: SnackBarAction(
              label: 'VIEW CART',
              textColor: Colors.white,
              onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => CartScreen())),
            ),
          ),
        );
      }
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text('Reorder failed: $e'), backgroundColor: Colors.red),
        );
      }
    }
    if (mounted) setState(() => _processingId = null);
  }

  @override
  Widget build(BuildContext context) {
    final isAuth = ref.watch(authProvider).isAuthenticated;

    if (!isAuth) {
      return Scaffold(
        appBar: AppBar(
          title: Text('ORDER VAULT', style: GoogleFonts.spaceGrotesk(fontWeight: FontWeight.w900, fontSize: 14, letterSpacing: 2, color: WeloColors.sovereignText)),
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
                  decoration: BoxDecoration(color: WeloColors.sovereignOrange.withValues(alpha: 0.078), shape: BoxShape.circle),
                  child: const Icon(Icons.receipt_long_outlined, size: 48, color: WeloColors.sovereignOrange),
                ),
                const SizedBox(height: 24),
                Text('Sign in to view orders', style: GoogleFonts.spaceGrotesk(fontSize: 20, fontWeight: FontWeight.w900)),
                const SizedBox(height: 8),
                Text('Your order history will appear here.',
                  style: GoogleFonts.inter(fontSize: 14, color: WeloColors.textBody), textAlign: TextAlign.center),
                const SizedBox(height: 32),
                SizedBox(
                  width: double.infinity, height: 56,
                  child: ElevatedButton(
                    onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => const LoginScreen(showBack: true))),
                    style: ElevatedButton.styleFrom(
                      backgroundColor: WeloColors.sovereignOrange, foregroundColor: Colors.white,
                      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(20)),
                    ),
                    child: Text('SIGN IN', style: GoogleFonts.spaceGrotesk(fontSize: 14, fontWeight: FontWeight.w700, letterSpacing: 1.5)),
                  ),
                ),
              ],
            ),
          ),
        ),
      );
    }

    return Scaffold(
      backgroundColor: WeloColors.sovereignBg,
      appBar: AppBar(
        title: Text(
          'ORDER VAULT',
          style: GoogleFonts.spaceGrotesk(
            fontWeight: FontWeight.w900,
            fontSize: 14,
            letterSpacing: 2,
            color: WeloColors.sovereignText,
          ),
        ),
        centerTitle: true,
      ),
      body: RefreshIndicator(
        onRefresh: _loadOrders,
        color: WeloColors.sovereignOrange,
        child: _isLoading
            ? const Center(child: CircularProgressIndicator(color: WeloColors.sovereignOrange))
            : _orders.isEmpty
                ? SingleChildScrollView(
                    physics: const AlwaysScrollableScrollPhysics(),
                    child: SizedBox(
                      height: MediaQuery.of(context).size.height - 200,
                      child: _error != null
                          ? Center(
                              child: Padding(
                                padding: const EdgeInsets.all(24),
                                child: Text('Error loading orders: $_error',
                                  textAlign: TextAlign.center,
                                  style: const TextStyle(color: WeloColors.error, fontSize: 13)),
                              ),
                            )
                          : _buildEmptyState(),
                    ),
                  )
                : ListView.builder(
                    physics: const AlwaysScrollableScrollPhysics(),
                    padding: const EdgeInsets.all(24),
                    itemCount: _orders.length,
                    itemBuilder: (context, index) => _buildOrderCard(_orders[index]),
                  ),
      ),
    );
  }

  Widget _buildEmptyState() {
    return Center(
      child: Column(
        mainAxisAlignment: MainAxisAlignment.center,
        children: [
          Icon(Icons.receipt_long, size: 80, color: WeloColors.sovereignText.withValues(alpha: 0.1)),
          const SizedBox(height: 24),
          Text(
            'Your Vault is Empty',
            style: GoogleFonts.playfairDisplay(fontSize: 24, fontWeight: FontWeight.bold),
          ),
          const SizedBox(height: 8),
          Text(
            'Begin your culinary mission today.',
            style: TextStyle(color: WeloColors.sovereignText.withValues(alpha: 0.5)),
          ),
        ],
      ),
    );
  }

  Widget _buildOrderCard(dynamic order) {
    final bool isCOD = order['payment_mode'] == 'cod';
    final bool isActive = ['pending', 'confirmed', 'preparing', 'ready_for_pickup', 'out_for_delivery'].contains(order['status']);
    
    return Container(
      margin: const EdgeInsets.only(bottom: 24),
      decoration: BoxDecoration(
        color: WeloColors.sovereignSurface,
        borderRadius: BorderRadius.circular(32),
        boxShadow: [
          BoxShadow(color: Colors.black.withValues(alpha: 0.03), blurRadius: 30, offset: const Offset(0, 10)),
        ],
      ),
      child: Column(
        children: [
          Padding(
            padding: const EdgeInsets.all(24),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Row(
                  mainAxisAlignment: MainAxisAlignment.spaceBetween,
                  children: [
                    Flexible(
                      child: Text(
                        '#${order['id'].toString().substring(0, 8).toUpperCase()}',
                        style: GoogleFonts.spaceGrotesk(fontWeight: FontWeight.w700, fontSize: 16),
                        overflow: TextOverflow.ellipsis,
                      ),
                    ),
                    _buildStatusBadge(order['status']),
                  ],
                ),
                const SizedBox(height: 12),
                Text(
                  order['restaurant']['name'],
                  style: GoogleFonts.playfairDisplay(fontSize: 22, fontWeight: FontWeight.w700),
                ),
                const Divider(height: 32, color: Colors.black12),
                Row(
                  mainAxisAlignment: MainAxisAlignment.spaceBetween,
                  children: [
                    Text(
                      '₹${order['grand_total']}',
                      style: const TextStyle(fontSize: 20, fontWeight: FontWeight.w900, color: WeloColors.sovereignOrange),
                    ),
                    Row(
                      children: [
                        if (isCOD && isActive)
                          Flexible(
                            child: TextButton.icon(
                              onPressed: _processingId == order['id'] ? null : () => _handlePayOnline(order['id']),
                              icon: const Icon(Icons.payments, size: 18),
                              label: Text(_processingId == order['id'] ? 'PROCESSING...' : 'PAY ONLINE'),
                              style: TextButton.styleFrom(
                                foregroundColor: WeloColors.sovereignTeal,
                                textStyle: const TextStyle(fontWeight: FontWeight.bold, fontSize: 12),
                              ),
                            ),
                          ),
                        if (order['status'] == 'delivered')
                          Padding(
                            padding: const EdgeInsets.only(right: 8.0),
                            child: TextButton.icon(
                              onPressed: () {
                                Navigator.push(
                                  context,
                                  MaterialPageRoute(
                                    builder: (_) => DownloadInvoiceScreen(orderId: order['id'].toString()),
                                  ),
                                );
                              },
                              icon: const Icon(Icons.receipt_long, size: 16),
                              label: const Text('INVOICE'),
                              style: TextButton.styleFrom(
                                foregroundColor: WeloColors.sovereignOrange,
                                textStyle: const TextStyle(fontWeight: FontWeight.bold, fontSize: 11),
                              ),
                            ),
                          ),
                        if (['delivered', 'completed'].contains(order['status']))
                          Padding(
                            padding: const EdgeInsets.only(right: 8.0),
                            child: TextButton.icon(
                              onPressed: (_processingId == order['id']) ? null : () => _handleReorder(order['id']),
                              icon: Icon(_processingId == order['id'] ? Icons.hourglass_top : Icons.replay, size: 16),
                              label: Text(_processingId == order['id'] ? 'ADDING...' : 'ORDER AGAIN'),
                              style: TextButton.styleFrom(
                                foregroundColor: WeloColors.sovereignOrange,
                                textStyle: const TextStyle(fontWeight: FontWeight.bold, fontSize: 11),
                              ),
                            ),
                          ),
                        const SizedBox(width: 8),
                        ElevatedButton(
                          onPressed: () {
                            final orderObj = Order.fromJson(order is Map<String, dynamic>
                                ? order
                                : Map<String, dynamic>.from(order as Map));
                            Navigator.push(
                              context,
                              MaterialPageRoute(
                                builder: (_) => OrderTrackingScreen(order: orderObj),
                              ),
                            );
                          },
                          style: ElevatedButton.styleFrom(
                            backgroundColor: WeloColors.sovereignText,
                            foregroundColor: Colors.white,
                            shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
                            elevation: 0,
                          ),
                          child: Text(isActive ? 'TRACK' : 'VIEW', style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 11)),
                        ),
                      ],
                    ),
                  ],
                ),
              ],
            ),
          ),
          if (order['delivery_otp'] != null && ['ready_for_pickup', 'out_for_delivery'].contains(order['status']))
            Container(
              padding: const EdgeInsets.symmetric(horizontal: 24, vertical: 16),
              decoration: const BoxDecoration(
                color: WeloColors.sovereignOrange,
                borderRadius: BorderRadius.vertical(bottom: Radius.circular(32)),
              ),
              child: Row(
                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                children: [
                  const Text('DELIVERY OTP', style: TextStyle(color: Colors.white, fontWeight: FontWeight.w900, letterSpacing: 1, fontSize: 10)),
                  Text(
                    order['delivery_otp'],
                    style: GoogleFonts.spaceGrotesk(color: Colors.white, fontWeight: FontWeight.bold, fontSize: 20, letterSpacing: 4),
                  ),
                ],
              ),
            ),
        ],
      ),
    );
  }

  Widget _buildStatusBadge(String status) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
      decoration: BoxDecoration(
        color: WeloColors.sovereignTeal.withValues(alpha: 0.1),
        borderRadius: BorderRadius.circular(8),
      ),
      child: Text(
        status.toUpperCase(),
        style: const TextStyle(color: WeloColors.sovereignTeal, fontSize: 8, fontWeight: FontWeight.w900, letterSpacing: 1),
      ),
    );
  }
}
