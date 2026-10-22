import 'package:flutter/material.dart';
import 'package:ui/ui.dart';
import 'mini_games_play_screen.dart';

class GamesScreen extends StatefulWidget {
  const GamesScreen({super.key});

  @override
  State<GamesScreen> createState() => _GamesScreenState();
}

class _GamesScreenState extends State<GamesScreen> {
  String? _activeGame;

  final games = [
    {
      'id': 'flavor_finder',
      'title': 'Flavor Finder',
      'description': 'Match the heritage ingredients in record time.',
      'icon': Icons.psychology,
      'color': WeloColors.sovereignOrange,
    },
    {
      'id': 'chef_dash',
      'title': 'Artisan Dash',
      'description': 'Deliver orders in a high-speed food catch simulation.',
      'icon': Icons.speed,
      'color': Colors.green.shade600,
    },
    {
      'id': 'spice_master',
      'title': 'Spice Master',
      'description': 'Identify complex aromatic profiles from emoji clues.',
      'icon': Icons.spa,
      'color': Colors.orange.shade600,
    },
  ];

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: WeloColors.sovereignBg,
      appBar: const WeloAppBar(title: 'CULINARIA ARENA'),
      body: SingleChildScrollView(
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            _buildHeader(),
            _buildMissionsSection(),
            const SizedBox(height: 32),
          ],
        ),
      ),
    );
  }

  Widget _buildHeader() {
    return Container(
      margin: const EdgeInsets.all(16),
      padding: const EdgeInsets.all(24),
      decoration: BoxDecoration(
        gradient: LinearGradient(
          colors: [WeloColors.sovereignOrange, WeloColors.sovereignOrange.withValues(alpha: 0.706)],
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
        ),
        borderRadius: BorderRadius.circular(32),
      ),
      child: Row(
        children: [
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  'PLAY WHILE YOU WAIT',
                  style: TextStyle(
                    color: Colors.white.withValues(alpha: 0.706),
                    fontSize: 10,
                    fontWeight: FontWeight.w900,
                    letterSpacing: 2,
                  ),
                ),
                const SizedBox(height: 8),
                const Text(
                  'Culinaria Arena',
                  style: TextStyle(color: Colors.white, fontSize: 24, fontWeight: FontWeight.w900),
                ),
                const SizedBox(height: 4),
                Text(
                  'Fun games to pass time while your order is on the way.',
                  style: TextStyle(color: Colors.white.withValues(alpha: 0.706), fontSize: 13),
                ),
              ],
            ),
          ),
          Icon(Icons.sports_esports, size: 60, color: Colors.white.withValues(alpha: 0.784)),
        ],
      ),
    );
  }

  Widget _buildMissionsSection() {
    return Padding(
      padding: const EdgeInsets.all(16),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const Text('Mini Games', style: TextStyle(fontSize: 18, fontWeight: FontWeight.w900)),
          const SizedBox(height: 16),
          ...games.map((game) => _buildGameCard(game)),
        ],
      ),
    );
  }

  Widget _buildGameCard(Map<String, dynamic> game) {
    final isActive = _activeGame == game['id'];
    return GestureDetector(
      onTap: () => setState(() => _activeGame = isActive ? null : game['id']),
      child: Container(
        margin: const EdgeInsets.only(bottom: 12),
        padding: const EdgeInsets.all(20),
        decoration: BoxDecoration(
          color: WeloColors.sovereignSurface,
          borderRadius: BorderRadius.circular(24),
          border: isActive ? Border.all(color: WeloColors.sovereignOrange, width: 2) : null,
          boxShadow: [
            BoxShadow(color: Colors.black.withValues(alpha: 0.031), blurRadius: 20, offset: const Offset(0, 4)),
          ],
        ),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                Container(
                  padding: const EdgeInsets.all(10),
                  decoration: BoxDecoration(
                    color: game['color'],
                    borderRadius: BorderRadius.circular(12),
                  ),
                  child: Icon(game['icon'] as IconData, color: Colors.white, size: 24),
                ),
                const SizedBox(width: 16),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        (game['title'] as String).toUpperCase(),
                        style: const TextStyle(fontWeight: FontWeight.w900, fontSize: 16),
                      ),
                      const SizedBox(height: 4),
                      Text(
                        game['description'],
                        style: TextStyle(color: WeloColors.textBody, fontSize: 12),
                      ),
                    ],
                  ),
                ),
              ],
            ),
            const SizedBox(height: 16),
            Row(
              mainAxisAlignment: MainAxisAlignment.end,
              children: [
                GestureDetector(
                  onTap: () {
                    Navigator.push(
                      context,
                      MaterialPageRoute(
                        builder: (_) => MiniGamesPlayScreen(
                          gameId: game['id'] as String,
                          gameTitle: game['title'] as String,
                        ),
                      ),
                    );
                  },
                  child: Container(
                    padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
                    decoration: BoxDecoration(
                      color: WeloColors.sovereignText,
                      borderRadius: BorderRadius.circular(12),
                    ),
                    child: const Text('LAUNCH', style: TextStyle(color: Colors.white, fontSize: 10, fontWeight: FontWeight.w900, letterSpacing: 1)),
                  ),
                ),
              ],
            ),
          ],
        ),
      ),
    );
  }
}
