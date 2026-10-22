import 'dart:convert';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:ui/ui.dart';
import 'package:shared_preferences/shared_preferences.dart';

class ScratchCardStorage {
  static Future<void> saveCard(Map<String, dynamic> card) async {
    final prefs = await SharedPreferences.getInstance();
    final raw = prefs.getString('scratch_cards') ?? '[]';
    final list = (jsonDecode(raw) as List).cast<Map<String, dynamic>>();
    card['saved_at'] = DateTime.now().toIso8601String();
    card['scratched'] = card['scratched'] ?? false;
    list.add(card);
    await prefs.setString('scratch_cards', jsonEncode(list));
  }

  static Future<void> markScratched(String code) async {
    final prefs = await SharedPreferences.getInstance();
    final raw = prefs.getString('scratch_cards') ?? '[]';
    final list = (jsonDecode(raw) as List).cast<Map<String, dynamic>>();
    for (var card in list) {
      if (card['code'] == code) {
        card['scratched'] = true;
        break;
      }
    }
    await prefs.setString('scratch_cards', jsonEncode(list));
  }

  static Future<List<Map<String, dynamic>>> loadCards() async {
    final prefs = await SharedPreferences.getInstance();
    final raw = prefs.getString('scratch_cards') ?? '[]';
    return (jsonDecode(raw) as List).cast<Map<String, dynamic>>();
  }
}

class ScratchCardHistoryScreen extends ConsumerStatefulWidget {
  const ScratchCardHistoryScreen({super.key});

  @override
  ConsumerState<ScratchCardHistoryScreen> createState() => _ScratchCardHistoryScreenState();
}

class _ScratchCardHistoryScreenState extends ConsumerState<ScratchCardHistoryScreen> {
  List<Map<String, dynamic>> _cards = [];
  bool _isLoading = true;

  @override
  void initState() {
    super.initState();
    _loadCards();
  }

  Future<void> _loadCards() async {
    final cards = await ScratchCardStorage.loadCards();
    if (mounted) {
      setState(() {
        _cards = cards.reversed.toList();
        _isLoading = false;
      });
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: const WeloAppBar(title: 'My Scratch Cards'),
      body: _isLoading
          ? const Center(child: CircularProgressIndicator(color: WeloColors.sovereignOrange))
          : _cards.isEmpty
              ? Center(
                  child: Column(
                    mainAxisAlignment: MainAxisAlignment.center,
                    children: [
                      Icon(Icons.card_giftcard, size: 64, color: Colors.grey[300]),
                      const SizedBox(height: 16),
                      Text('No scratch cards yet', style: TextStyle(fontSize: 16, color: Colors.grey[500])),
                      const SizedBox(height: 8),
                      Text('Complete an order to receive mystery scratch cards!', style: TextStyle(fontSize: 13, color: Colors.grey[400])),
                    ],
                  ),
                )
              : ListView.builder(
                  padding: const EdgeInsets.all(16),
                  itemCount: _cards.length,
                  itemBuilder: (context, index) {
                    final card = _cards[index];
                    final scratched = card['scratched'] == true;
                    final savedAt = card['saved_at'] != null
                        ? DateTime.tryParse(card['saved_at'])
                        : null;

                    return Container(
                      margin: const EdgeInsets.only(bottom: 12),
                      decoration: BoxDecoration(
                        color: Colors.white,
                        borderRadius: BorderRadius.circular(16),
                        border: Border.all(
                          color: scratched
                              ? Colors.green.withValues(alpha: 0.3)
                              : Colors.orange.withValues(alpha: 0.3),
                          width: 1.5,
                        ),
                        boxShadow: [
                          BoxShadow(
                            color: Colors.black.withValues(alpha: 0.05),
                            blurRadius: 10,
                            offset: const Offset(0, 4),
                          ),
                        ],
                      ),
                      child: ListTile(
                        contentPadding: const EdgeInsets.all(16),
                        leading: Container(
                          width: 48,
                          height: 48,
                          decoration: BoxDecoration(
                            color: scratched
                                ? Colors.green.withValues(alpha: 0.1)
                                : Colors.orange.withValues(alpha: 0.1),
                            borderRadius: BorderRadius.circular(12),
                          ),
                          child: Icon(
                            scratched ? Icons.check_circle : Icons.card_giftcard,
                            color: scratched ? Colors.green : Colors.orange,
                          ),
                        ),
                        title: Text(
                          card['brand'] ?? '',
                          style: GoogleFonts.spaceGrotesk(fontWeight: FontWeight.w900, fontSize: 14),
                        ),
                        subtitle: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            const SizedBox(height: 4),
                            Text(card['title'] ?? '', style: const TextStyle(fontSize: 12)),
                            if (savedAt != null)
                              Text(
                                '${scratched ? 'Scratched' : 'Received'}: ${savedAt.day}/${savedAt.month}/${savedAt.year}',
                                style: TextStyle(fontSize: 10, color: Colors.grey[500]),
                              ),
                          ],
                        ),
                        trailing: scratched
                            ? IconButton(
                                icon: const Icon(Icons.copy, size: 18, color: Colors.green),
                                onPressed: () {
                                  Clipboard.setData(ClipboardData(text: card['code'] ?? ''));
                                  ScaffoldMessenger.of(context).showSnackBar(
                                    const SnackBar(content: Text('Code copied!')),
                                  );
                                },
                              )
                            : Container(
                                padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
                                decoration: BoxDecoration(
                                  color: Colors.orange,
                                  borderRadius: BorderRadius.circular(8),
                                ),
                                child: const Text('UNSCRATCHED', style: TextStyle(color: Colors.white, fontSize: 8, fontWeight: FontWeight.w900)),
                              ),
                      ),
                    );
                  },
                ),
    );
  }
}
