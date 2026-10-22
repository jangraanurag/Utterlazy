import 'dart:async';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:ui/ui.dart';
import 'package:api/api.dart';
import 'package:core/core.dart';
import 'package:restaurant_app/main.dart';

class IncomingOrderScreen extends ConsumerStatefulWidget {
  final String orderId;

  const IncomingOrderScreen({super.key, required this.orderId});

  @override
  ConsumerState<IncomingOrderScreen> createState() => _IncomingOrderScreenState();
}

class _IncomingOrderScreenState extends ConsumerState<IncomingOrderScreen> {
  bool _isLoading = true;
  Order? _order;
  String? _error;
  int _remainingSeconds = 300;
  Timer? _timer;
  int _extraPrepMinutes = 0;

  @override
  void initState() {
    super.initState();
    _fetchOrder();
  }

  @override
  void dispose() {
    _timer?.cancel();
    super.dispose();
  }

  Future<void> _fetchOrder() async {
    try {
      final vendorRepo = ref.read(vendorRepositoryProvider);
      final orders = await vendorRepo.getActiveOrders();
      final found = orders.where((o) => o.id == widget.orderId).toList();

      if (found.isNotEmpty) {
        final order = found.first;
        if (mounted) {
          setState(() {
            _order = order;
            _isLoading = false;
          });
          if (order.status == 'pending') {
            _startCountdown(order);
          } else {
            // Order already handled, go to orders screen
            _navigateToOrders();
          }
        }
      } else {
        // Check if order was already accepted/rejected by fetching all orders
        try {
          final allOrders = await vendorRepo.getAllOrders();
          final foundAll = allOrders.where((o) => o.id == widget.orderId).toList();
          if (foundAll.isNotEmpty) {
            _navigateToOrders();
            return;
          }
        } catch (_) {}

        if (mounted) {
          setState(() {
            _error = 'Order not found';
            _isLoading = false;
          });
        }
      }
    } catch (e) {
      if (mounted) {
        setState(() {
          _error = 'Failed to load order';
          _isLoading = false;
        });
      }
    }
  }

  void _startCountdown(Order order) {
    try {
      if (order.createdAt != null) {
        final createdTime = DateTime.parse(order.createdAt!).toLocal();
        final elapsed = DateTime.now().difference(createdTime).inSeconds;
        _remainingSeconds = (300 - elapsed).clamp(0, 300);
      }
    } catch (_) {}

    _timer = Timer.periodic(const Duration(seconds: 1), (timer) {
      if (_remainingSeconds > 0) {
        if (mounted) setState(() => _remainingSeconds--);
      } else {
        _timer?.cancel();
        _handleReject();
      }
    });
  }

  void _navigateToOrders() {
    Navigator.of(context, rootNavigator: true).pushAndRemoveUntil(
      MaterialPageRoute(builder: (_) => const VendorShell(initialIndex: 1)),
      (route) => false,
    );
  }

  Future<void> _handleAccept() async {
    _timer?.cancel();
    try {
      final vendorRepo = ref.read(vendorRepositoryProvider);
      await vendorRepo.acceptOrder(widget.orderId, extraMinutes: _extraPrepMinutes);
      _navigateToOrders();
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('Failed to accept order.')),
        );
      }
    }
  }

  Future<void> _handleReject() async {
    _timer?.cancel();
    try {
      final vendorRepo = ref.read(vendorRepositoryProvider);
      await vendorRepo.rejectOrder(widget.orderId, 'Restaurant busy');
    } catch (_) {}
    _navigateToOrders();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: Colors.white,
      body: SafeArea(
        child: _isLoading
            ? const Center(child: CircularProgressIndicator(color: WeloColors.sovereignOrange))
            : _error != null
                ? _buildErrorView()
                : _order != null && _order!.status == 'pending'
                    ? _buildIncomingView()
                    : _buildAlreadyHandledView(),
      ),
    );
  }

  Widget _buildErrorView() {
    return Center(
      child: Padding(
        padding: const EdgeInsets.all(32),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            const Icon(Icons.error_outline, size: 64, color: WeloColors.error),
            const SizedBox(height: 16),
            Text(_error!, style: const TextStyle(fontSize: 16, color: WeloColors.textBody)),
            const SizedBox(height: 24),
            ElevatedButton(
              onPressed: _navigateToOrders,
              style: ElevatedButton.styleFrom(
                backgroundColor: WeloColors.sovereignOrange,
                foregroundColor: Colors.white,
              ),
              child: const Text('Go to Orders'),
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildAlreadyHandledView() {
    return Center(
      child: Padding(
        padding: const EdgeInsets.all(32),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            const Icon(Icons.check_circle, size: 64, color: WeloColors.success),
            const SizedBox(height: 16),
            const Text('Order already processed', style: TextStyle(fontSize: 16, color: WeloColors.textBody)),
            const SizedBox(height: 24),
            ElevatedButton(
              onPressed: _navigateToOrders,
              style: ElevatedButton.styleFrom(
                backgroundColor: WeloColors.sovereignOrange,
                foregroundColor: Colors.white,
              ),
              child: const Text('Go to Orders'),
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildIncomingView() {
    final order = _order!;
    final minutes = _remainingSeconds ~/ 60;
    final seconds = _remainingSeconds % 60;
    final timeStr = '$minutes:${seconds.toString().padLeft(2, '0')}';

    return SingleChildScrollView(
      padding: const EdgeInsets.symmetric(horizontal: 24, vertical: 32),
      child: Column(
        children: [
          const SizedBox(height: 40),
          Container(
            padding: const EdgeInsets.all(20),
            decoration: BoxDecoration(
              color: WeloColors.sovereignOrange.withValues(alpha: 0.078),
              shape: BoxShape.circle,
            ),
            child: const Icon(
              Icons.notifications_active,
              color: WeloColors.sovereignOrange,
              size: 60,
            ),
          ),
          const SizedBox(height: 24),
          const Text(
            'NEW INCOMING ORDER!',
            style: TextStyle(
              fontWeight: FontWeight.bold,
              fontSize: 26,
              color: Colors.black,
            ),
          ),
          const SizedBox(height: 12),
          Text(
            'Order #${order.id.substring(0, 8).toUpperCase()}',
            style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 18, color: Colors.black54),
          ),
          const SizedBox(height: 8),
          Text(
            '₹${order.totalAmount.toInt()}',
            style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 32, color: WeloColors.sovereignOrange),
          ),
          const SizedBox(height: 24),
          // Order details card
          Container(
            width: double.infinity,
            padding: const EdgeInsets.all(16),
            decoration: BoxDecoration(
              color: Colors.grey.shade50,
              borderRadius: BorderRadius.circular(16),
              border: Border.all(color: Colors.grey.shade200),
            ),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                if (order.customerName != null) ...[
                  _buildDetailRow(Icons.person, order.customerName!),
                  const SizedBox(height: 8),
                ],
                _buildDetailRow(Icons.location_on, order.customerAddress),
                const SizedBox(height: 8),
                _buildDetailRow(Icons.receipt_long, order.itemSummary.isNotEmpty ? order.itemSummary : '${order.itemsCount} items'),
              ],
            ),
          ),
          const SizedBox(height: 24),
          // Timer
          Container(
            padding: const EdgeInsets.symmetric(horizontal: 24, vertical: 12),
            decoration: BoxDecoration(
              color: WeloColors.error.withValues(alpha: 0.059),
              borderRadius: BorderRadius.circular(16),
              border: Border.all(color: WeloColors.error.withValues(alpha: 0.196), width: 1.5),
            ),
            child: Row(
              mainAxisSize: MainAxisSize.min,
              children: [
                const Icon(Icons.timer, color: WeloColors.error, size: 20),
                const SizedBox(width: 8),
                Text(
                  'Auto-cancels in $timeStr',
                  style: const TextStyle(
                    fontWeight: FontWeight.w800,
                    fontSize: 16,
                    color: WeloColors.error,
                  ),
                ),
              ],
            ),
          ),
          const SizedBox(height: 24),
          // Extra prep time
          const Text(
            'Need extra prep time?',
            style: TextStyle(fontWeight: FontWeight.w700, fontSize: 14, color: Colors.black87),
          ),
          const SizedBox(height: 10),
          Row(
            mainAxisAlignment: MainAxisAlignment.center,
            children: [
              _buildStepperBtn(Icons.remove, () {
                if (_extraPrepMinutes > 0) {
                  setState(() => _extraPrepMinutes--);
                }
              }),
              Container(
                width: 120,
                alignment: Alignment.center,
                child: Text(
                  '+$_extraPrepMinutes min',
                  style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 18),
                ),
              ),
              _buildStepperBtn(Icons.add, () {
                if (_extraPrepMinutes < 5) {
                  setState(() => _extraPrepMinutes++);
                }
              }),
            ],
          ),
          const SizedBox(height: 32),
          // Accept / Reject buttons
          Row(
            children: [
              Expanded(
                child: OutlinedButton(
                  onPressed: _handleReject,
                  style: OutlinedButton.styleFrom(
                    foregroundColor: WeloColors.error,
                    side: const BorderSide(color: WeloColors.error, width: 1.5),
                    padding: const EdgeInsets.symmetric(vertical: 14),
                    shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
                  ),
                  child: const Text('Reject', style: TextStyle(fontSize: 15, fontWeight: FontWeight.bold)),
                ),
              ),
              const SizedBox(width: 12),
              Expanded(
                flex: 2,
                child: ElevatedButton(
                  onPressed: _handleAccept,
                  style: ElevatedButton.styleFrom(
                    backgroundColor: WeloColors.success,
                    foregroundColor: Colors.white,
                    elevation: 2,
                    padding: const EdgeInsets.symmetric(vertical: 14),
                    shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
                  ),
                  child: const Text('Accept Order', style: TextStyle(fontSize: 15, fontWeight: FontWeight.bold)),
                ),
              ),
            ],
          ),
          const SizedBox(height: 40),
        ],
      ),
    );
  }

  Widget _buildDetailRow(IconData icon, String text) {
    return Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Icon(icon, size: 18, color: WeloColors.textLight),
        const SizedBox(width: 8),
        Expanded(
          child: Text(text, style: const TextStyle(fontSize: 13, color: Colors.black87)),
        ),
      ],
    );
  }

  Widget _buildStepperBtn(IconData icon, VoidCallback onTap) {
    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(12),
      child: Container(
        padding: const EdgeInsets.all(8),
        decoration: BoxDecoration(
          color: Colors.grey.shade100,
          border: Border.all(color: Colors.grey.shade300),
          borderRadius: BorderRadius.circular(12),
        ),
        child: Icon(icon, size: 20, color: Colors.black87),
      ),
    );
  }
}