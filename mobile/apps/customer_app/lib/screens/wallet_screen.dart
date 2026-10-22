import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:core/core.dart';
import 'package:ui/ui.dart';
import 'package:dio/dio.dart';
import 'package:intl/intl.dart';

class WalletScreen extends ConsumerStatefulWidget {
  const WalletScreen({super.key});

  @override
  ConsumerState<WalletScreen> createState() => _WalletScreenState();
}

class _WalletScreenState extends ConsumerState<WalletScreen> {
  double _balance = 0.0;
  List<dynamic> _transactions = [];
  bool _isLoading = true;
  String _errorMsg = '';

  @override
  void initState() {
    super.initState();
    _loadWalletData();
  }

  Future<void> _loadWalletData() async {
    setState(() {
      _isLoading = true;
      _errorMsg = '';
    });
    try {
      final apiClient = ref.read(apiClientProvider);
      final res = await apiClient.dio.get('/wallet/balance');
      setState(() {
        _balance = double.parse(res.data['balance'].toString());
        _transactions = res.data['transactions'] ?? [];
        _isLoading = false;
      });
    } catch (e) {
      String msg = 'Failed to load wallet details.';
      if (e is DioException) {
        msg = e.response?.data?['error'] ?? e.response?.data?['message'] ?? msg;
      }
      setState(() {
        _errorMsg = msg;
        _isLoading = false;
      });
    }
  }

  Future<void> _depositMoney(double amount) async {
    try {
      final apiClient = ref.read(apiClientProvider);
      final res = await apiClient.dio.post('/wallet/add', data: {'amount': amount});
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text(res.data['message'] ?? 'Successfully deposited money.'),
            backgroundColor: Colors.green,
          ),
        );
        _loadWalletData();
      }
    } catch (e) {
      if (mounted) {
        String msg = 'Deposit failed.';
        if (e is DioException) {
          msg = e.response?.data?['error'] ?? e.response?.data?['message'] ?? msg;
        }
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text(msg), backgroundColor: Colors.red),
        );
      }
    }
  }

  Future<void> _transferMoney(String recipient, double amount) async {
    try {
      final apiClient = ref.read(apiClientProvider);
      final res = await apiClient.dio.post('/wallet/transfer', data: {
        'recipient': recipient,
        'amount': amount,
      });
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text(res.data['message'] ?? 'Successfully transferred money.'),
            backgroundColor: Colors.green,
          ),
        );
        _loadWalletData();
      }
    } catch (e) {
      if (mounted) {
        String msg = 'Transfer failed.';
        if (e is DioException) {
          msg = e.response?.data?['error'] ?? e.response?.data?['message'] ?? msg;
        }
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text(msg), backgroundColor: Colors.red),
        );
      }
    }
  }

  void _showDepositDialog() {
    final controller = TextEditingController();
    showDialog(
      context: context,
      builder: (context) {
        return AlertDialog(
          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(24)),
          title: Text('Add Money to Wallet', style: GoogleFonts.spaceGrotesk(fontWeight: FontWeight.bold)),
          content: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              Text('Simulating payment gateway deposit.', style: TextStyle(color: Colors.grey[600], fontSize: 12)),
              const SizedBox(height: 16),
              TextField(
                controller: controller,
                keyboardType: const TextInputType.numberWithOptions(decimal: true),
                decoration: InputDecoration(
                  hintText: 'Enter amount (e.g. ₹500)',
                  prefixIcon: const Icon(Icons.currency_rupee, size: 18),
                  filled: true,
                  fillColor: const Color(0xFFF8F9FA),
                  border: OutlineInputBorder(borderRadius: BorderRadius.circular(16), borderSide: BorderSide.none),
                ),
              ),
            ],
          ),
          actions: [
            TextButton(
              onPressed: () => Navigator.pop(context),
              child: const Text('CANCEL', style: TextStyle(color: Colors.grey)),
            ),
            ElevatedButton(
              style: ElevatedButton.styleFrom(
                backgroundColor: WeloColors.sovereignOrange,
                shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
              ),
              onPressed: () {
                final amt = double.tryParse(controller.text);
                if (amt != null && amt > 0) {
                  Navigator.pop(context);
                  _depositMoney(amt);
                }
              },
              child: const Text('ADD MONEY', style: TextStyle(color: Colors.white, fontWeight: FontWeight.bold)),
            ),
          ],
        );
      },
    );
  }

  void _showTransferDialog() {
    final recipientController = TextEditingController();
    final amountController = TextEditingController();
    showDialog(
      context: context,
      builder: (context) {
        return AlertDialog(
          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(24)),
          title: Text('Transfer to Friend', style: GoogleFonts.spaceGrotesk(fontWeight: FontWeight.bold)),
          content: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              TextField(
                controller: recipientController,
                keyboardType: TextInputType.emailAddress,
                decoration: InputDecoration(
                  hintText: "Friend's Email or Phone",
                  prefixIcon: const Icon(Icons.person_outline, size: 18),
                  filled: true,
                  fillColor: const Color(0xFFF8F9FA),
                  border: OutlineInputBorder(borderRadius: BorderRadius.circular(16), borderSide: BorderSide.none),
                ),
              ),
              const SizedBox(height: 12),
              TextField(
                controller: amountController,
                keyboardType: const TextInputType.numberWithOptions(decimal: true),
                decoration: InputDecoration(
                  hintText: 'Amount (₹)',
                  prefixIcon: const Icon(Icons.currency_rupee, size: 18),
                  filled: true,
                  fillColor: const Color(0xFFF8F9FA),
                  border: OutlineInputBorder(borderRadius: BorderRadius.circular(16), borderSide: BorderSide.none),
                ),
              ),
            ],
          ),
          actions: [
            TextButton(
              onPressed: () => Navigator.pop(context),
              child: const Text('CANCEL', style: TextStyle(color: Colors.grey)),
            ),
            ElevatedButton(
              style: ElevatedButton.styleFrom(
                backgroundColor: WeloColors.sovereignOrange,
                shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
              ),
              onPressed: () {
                final recipient = recipientController.text.trim();
                final amt = double.tryParse(amountController.text);
                if (recipient.isNotEmpty && amt != null && amt > 0) {
                  Navigator.pop(context);
                  _transferMoney(recipient, amt);
                }
              },
              child: const Text('SEND', style: TextStyle(color: Colors.white, fontWeight: FontWeight.bold)),
            ),
          ],
        );
      },
    );
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: const Color(0xFFF6F7F9),
      appBar: AppBar(
        title: const Text('UTTERLAZY WALLET', style: TextStyle(fontSize: 13, fontWeight: FontWeight.w900, letterSpacing: 1)),
        centerTitle: true,
      ),
      body: _isLoading
          ? const Center(child: CircularProgressIndicator())
          : _errorMsg.isNotEmpty
              ? Center(
                  child: Column(
                    mainAxisAlignment: MainAxisAlignment.center,
                    children: [
                      const Icon(Icons.error_outline, size: 48, color: Colors.red),
                      const SizedBox(height: 16),
                      Text(_errorMsg, style: const TextStyle(fontWeight: FontWeight.bold)),
                      const SizedBox(height: 16),
                      ElevatedButton(
                        onPressed: _loadWalletData,
                        child: const Text('RETRY'),
                      ),
                    ],
                  ),
                )
              : RefreshIndicator(
                  onRefresh: _loadWalletData,
                  child: SingleChildScrollView(
                    physics: const AlwaysScrollableScrollPhysics(),
                    padding: const EdgeInsets.all(20),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        // Wallet Balance Card
                        Container(
                          width: double.infinity,
                          padding: const EdgeInsets.all(24),
                          decoration: BoxDecoration(
                            gradient: const LinearGradient(
                              colors: [Color(0xFF2E3E5C), Color(0xFF1E283C)],
                              begin: Alignment.topLeft,
                              end: Alignment.bottomRight,
                            ),
                            borderRadius: BorderRadius.circular(32),
                            boxShadow: [
                              BoxShadow(
                                color: Colors.black.withValues(alpha: 0.15),
                                blurRadius: 20,
                                offset: const Offset(0, 10),
                              ),
                            ],
                          ),
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              const Text(
                                'CURRENT BALANCE',
                                style: TextStyle(color: Colors.white60, fontSize: 11, fontWeight: FontWeight.w900, letterSpacing: 1),
                              ),
                              const SizedBox(height: 8),
                              Row(
                                crossAxisAlignment: CrossAxisAlignment.baseline,
                                textBaseline: TextBaseline.alphabetic,
                                children: [
                                  const Text(
                                    '₹',
                                    style: TextStyle(color: Colors.white, fontSize: 28, fontWeight: FontWeight.w900),
                                  ),
                                  const SizedBox(width: 4),
                                  Flexible(
                                    child: Text(
                                      _balance.toStringAsFixed(2),
                                      style: GoogleFonts.spaceGrotesk(color: Colors.white, fontSize: 36, fontWeight: FontWeight.w900),
                                      overflow: TextOverflow.ellipsis,
                                    ),
                                  ),
                                ],
                              ),
                              const SizedBox(height: 24),
                              Row(
                                children: [
                                  Expanded(
                                    child: ElevatedButton.icon(
                                      style: ElevatedButton.styleFrom(
                                        backgroundColor: Colors.white,
                                        foregroundColor: Colors.black,
                                        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
                                        padding: const EdgeInsets.symmetric(vertical: 14),
                                      ),
                                      onPressed: _showDepositDialog,
                                      icon: const Icon(Icons.add, size: 18),
                                      label: const Text('ADD MONEY', style: TextStyle(fontWeight: FontWeight.w900, fontSize: 11, letterSpacing: 0.5)),
                                    ),
                                  ),
                                  const SizedBox(width: 16),
                                  Expanded(
                                    child: ElevatedButton.icon(
                                      style: ElevatedButton.styleFrom(
                                        backgroundColor: Colors.white.withValues(alpha: 0.15),
                                        foregroundColor: Colors.white,
                                        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
                                        padding: const EdgeInsets.symmetric(vertical: 14),
                                      ),
                                      onPressed: _showTransferDialog,
                                      icon: const Icon(Icons.send, size: 18),
                                      label: const Text('SEND MONEY', style: TextStyle(fontWeight: FontWeight.w900, fontSize: 11, letterSpacing: 0.5)),
                                    ),
                                  ),
                                ],
                              ),
                            ],
                          ),
                        ),
                        const SizedBox(height: 32),
                        // Transactions Header
                        const Text(
                          'RECENT TRANSACTIONS',
                          style: TextStyle(fontSize: 11, fontWeight: FontWeight.w900, color: Colors.black45, letterSpacing: 1),
                        ),
                        const SizedBox(height: 16),
                        if (_transactions.isEmpty)
                          Container(
                            width: double.infinity,
                            padding: const EdgeInsets.all(32),
                            decoration: BoxDecoration(
                              color: Colors.white,
                              borderRadius: BorderRadius.circular(24),
                              border: Border.all(color: Colors.black.withValues(alpha: 0.05)),
                            ),
                            child: const Center(
                              child: Text(
                                'No transactions yet.',
                                style: TextStyle(color: Colors.black38, fontSize: 13, fontWeight: FontWeight.w600),
                              ),
                            ),
                          )
                        else
                          ListView.builder(
                            shrinkWrap: true,
                            physics: const NeverScrollableScrollPhysics(),
                            itemCount: _transactions.length,
                            itemBuilder: (context, index) {
                              final tx = _transactions[index];
                              final isCredit = tx['type'] == 'credit';
                              final amount = double.parse(tx['amount'].toString());
                              final date = DateTime.parse(tx['created_at'].toString());
                              final formattedDate = DateFormat('dd MMM yyyy, hh:mm a').format(date.toLocal());

                              return Container(
                                margin: const EdgeInsets.only(bottom: 12),
                                padding: const EdgeInsets.all(16),
                                decoration: BoxDecoration(
                                  color: Colors.white,
                                  borderRadius: BorderRadius.circular(20),
                                  border: Border.all(color: Colors.black.withValues(alpha: 0.03)),
                                ),
                                child: Row(
                                  children: [
                                    CircleAvatar(
                                      backgroundColor: isCredit ? Colors.green.shade50 : Colors.red.shade50,
                                      child: Icon(
                                        isCredit ? Icons.add_circle_outline : Icons.remove_circle_outline,
                                        color: isCredit ? Colors.green : Colors.red,
                                      ),
                                    ),
                                    const SizedBox(width: 16),
                                    Expanded(
                                      child: Column(
                                        crossAxisAlignment: CrossAxisAlignment.start,
                                        children: [
                                          Text(
                                            tx['description'] ?? (isCredit ? 'Credit' : 'Debit'),
                                            style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 13),
                                          ),
                                          const SizedBox(height: 4),
                                          Text(
                                            formattedDate,
                                            style: const TextStyle(color: Colors.black38, fontSize: 11),
                                          ),
                                        ],
                                      ),
                                    ),
                                    Text(
                                      '${isCredit ? "+" : "-"}₹${amount.toStringAsFixed(0)}',
                                      style: TextStyle(
                                        color: isCredit ? Colors.green : Colors.red,
                                        fontWeight: FontWeight.w900,
                                        fontSize: 14,
                                      ),
                                    ),
                                  ],
                                ),
                              );
                            },
                          ),
                      ],
                    ),
                  ),
                ),
    );
  }
}
