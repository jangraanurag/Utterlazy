import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:speech_to_text/speech_to_text.dart' as stt;
import 'package:api/api.dart';
import 'package:core/core.dart';
import 'package:ui/ui.dart';

class SearchScreen extends ConsumerStatefulWidget {
  const SearchScreen({super.key});

  @override
  ConsumerState<SearchScreen> createState() => _SearchScreenState();
}

class _SearchScreenState extends ConsumerState<SearchScreen> {
  final _searchController = TextEditingController();
  List<Restaurant> _restaurants = [];
  final List<MenuItem> _menuItems = [];
  bool _isLoading = false;
  bool _hasSearched = false;
  bool _isListening = false;
  stt.SpeechToText? _speech;

  @override
  void initState() {
    super.initState();
    _initSpeech();
  }

  void _initSpeech() async {
    _speech = stt.SpeechToText();
    await _speech?.initialize();
  }

  @override
  void dispose() {
    _searchController.dispose();
    super.dispose();
  }

  Future<void> _startListening() async {
    if (_speech == null || !_speech!.isAvailable) return;
    setState(() => _isListening = true);
    await _speech!.listen(
      onResult: (result) {
        if (result.finalResult) {
          setState(() {
            _searchController.text = result.recognizedWords;
            _isListening = false;
          });
          _voiceSearch(result.recognizedWords);
        }
      },
      // ignore: deprecated_member_use
      localeId: 'hi_IN',
      // ignore: deprecated_member_use
      listenFor: const Duration(seconds: 10),
    );
  }

  void _stopListening() {
    _speech?.stop();
    setState(() => _isListening = false);
  }

  Future<void> _search(String query) async {
    if (query.trim().isEmpty) return;
    setState(() => _isLoading = true);
    try {
      final apiClient = ref.read(apiClientProvider);
      final restaurantRepo = RestaurantRepository(apiClient);
      final data = await restaurantRepo.searchCatalog(query);
      setState(() {
        _restaurants = (data['restaurants'] as List?)?.map((r) => Restaurant.fromJson(r)).toList() ?? [];
        _hasSearched = true;
        _isLoading = false;
      });
    } catch (e) {
      setState(() => _isLoading = false);
    }
  }

  Future<void> _voiceSearch(String text) async {
    if (text.trim().isEmpty) return;
    setState(() => _isLoading = true);
    try {
      final apiClient = ref.read(apiClientProvider);
      final restaurantRepo = RestaurantRepository(apiClient);
      final data = await restaurantRepo.voiceSearch(text);
      setState(() {
        _restaurants = (data['restaurants'] as List?)?.map((r) => Restaurant.fromJson(r)).toList() ?? [];
        _hasSearched = true;
        _isLoading = false;
      });
    } catch (e) {
      setState(() => _isLoading = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: TextField(
          controller: _searchController,
          autofocus: true,
          decoration: InputDecoration(
            hintText: 'Search restaurants, dishes...',
            border: InputBorder.none,
            suffixIcon: GestureDetector(
              onTap: _isListening ? _stopListening : _startListening,
              child: Icon(
                _isListening ? Icons.mic : Icons.mic_none,
                color: _isListening ? WeloColors.sovereignOrange : Colors.grey,
              ),
            ),
            prefixIcon: const Icon(Icons.search, color: Colors.grey),
          ),
          onChanged: (val) {
            if (val.trim().isNotEmpty) {
              _search(val);
            } else {
              setState(() {
                _hasSearched = false;
                _restaurants = [];
              });
            }
          },
          onSubmitted: _search,
        ),
        backgroundColor: Colors.white,
        elevation: 1,
        shadowColor: Colors.black12,
      ),
      backgroundColor: Colors.grey.shade50,
      body: _isLoading
          ? const Center(child: CircularProgressIndicator(color: WeloColors.sovereignOrange))
          : !_hasSearched
              ? _buildSuggestions()
              : _buildResults(),
    );
  }

  Widget _buildSuggestions() {
    final recent = ['Burger King', 'Biryani', 'Pizza Hut'];
    final trending = ['Biryani', 'Pizza', 'Burger', 'Chinese', 'North Indian', 'South Indian', 'Desserts', 'Healthy'];
    
    return SingleChildScrollView(
      padding: const EdgeInsets.all(20),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              const Text('Recent Searches', style: TextStyle(fontSize: 16, fontWeight: FontWeight.w900)),
              Text('CLEAR', style: TextStyle(fontSize: 12, fontWeight: FontWeight.bold, color: WeloColors.sovereignOrange)),
            ],
          ),
          const SizedBox(height: 12),
          Wrap(
            spacing: 8,
            runSpacing: 8,
            children: recent.map((s) => ActionChip(
              label: Row(
                mainAxisSize: MainAxisSize.min,
                children: [
                  const Icon(Icons.history, size: 14, color: Colors.black54),
                  const SizedBox(width: 4),
                  Text(s, style: const TextStyle(fontSize: 13)),
                ],
              ),
              backgroundColor: Colors.white,
              side: BorderSide(color: Colors.grey.shade300),
              shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(20)),
              onPressed: () {
                _searchController.text = s;
                _search(s);
              },
            )).toList(),
          ),
          const SizedBox(height: 32),
          const Text('Trending Categories', style: TextStyle(fontSize: 16, fontWeight: FontWeight.w900)),
          const SizedBox(height: 16),
          Wrap(
            spacing: 8,
            runSpacing: 8,
            children: trending.map((s) => ActionChip(
              label: Text(s, style: const TextStyle(fontSize: 13, fontWeight: FontWeight.bold)),
              backgroundColor: WeloColors.sovereignOrange.withValues(alpha: 0.1),
              side: BorderSide(color: WeloColors.sovereignOrange.withValues(alpha: 0.3)),
              shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(20)),
              onPressed: () {
                _searchController.text = s;
                _search(s);
              },
            )).toList(),
          ),
          const SizedBox(height: 40),
          Center(
            child: Column(
              children: [
                Icon(_isListening ? Icons.mic : Icons.mic_none, size: 64, color: _isListening ? WeloColors.sovereignOrange : Colors.grey.shade300),
                const SizedBox(height: 16),
                Text(
                  _isListening ? 'Listening... Speak now' : 'Try voice search',
                  style: TextStyle(fontSize: 16, color: _isListening ? WeloColors.sovereignOrange : Colors.grey.shade500),
                ),
                if (_isListening) ...[
                  const SizedBox(height: 8),
                  Text('Say: "Chicken biryani" or "2 paneer butter masala"',
                    style: TextStyle(fontSize: 12, color: Colors.grey.shade400)),
                ],
              ],
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildResults() {
    if (_restaurants.isEmpty && _menuItems.isEmpty) {
      return const Center(child: Text('No results found', style: TextStyle(fontSize: 18, color: Colors.grey)));
    }
    return ListView(
      padding: const EdgeInsets.all(20),
      children: [
        if (_restaurants.isNotEmpty) ...[
          const Text('Restaurants', style: TextStyle(fontSize: 18, fontWeight: FontWeight.w900)),
          const SizedBox(height: 12),
          ..._restaurants.map((r) => Card(
            margin: const EdgeInsets.only(bottom: 8),
            shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
            child: ListTile(
              contentPadding: const EdgeInsets.all(12),
              leading: CircleAvatar(
                radius: 24,
                backgroundColor: WeloColors.sovereignOrange.withValues(alpha: 0.1),
                child: Text(r.name[0], style: const TextStyle(fontWeight: FontWeight.bold, color: WeloColors.sovereignOrange)),
              ),
              title: Text(r.name, style: const TextStyle(fontWeight: FontWeight.w700)),
              subtitle: Text(r.description ?? '', maxLines: 1, overflow: TextOverflow.ellipsis),
            ),
          )),
        ],
      ],
    );
  }
}
