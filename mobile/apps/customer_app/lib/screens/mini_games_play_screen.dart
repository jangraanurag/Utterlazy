import 'dart:async';
import 'dart:math' as math;
import 'package:flutter/material.dart';
import 'package:ui/ui.dart';

class MiniGamesPlayScreen extends StatefulWidget {
  final String gameId;
  final String gameTitle;

  const MiniGamesPlayScreen({
    super.key,
    required this.gameId,
    required this.gameTitle,
  });

  @override
  State<MiniGamesPlayScreen> createState() => _MiniGamesPlayScreenState();
}

class _MiniGamesPlayScreenState extends State<MiniGamesPlayScreen> {
  bool _isGameOver = false;
  bool _hasWon = false;
  int _score = 0;

  final List<String> _memoryEmojis = ['🍕', '🍔', '🌮', '🍜', '🍩', '🍣'];
  List<Map<String, dynamic>> _cards = [];
  int _firstFlippedIndex = -1;
  int _secondFlippedIndex = -1;
  bool _memoryBusy = false;
  int _memoryMoves = 0;

  double _basketX = 150.0;
  List<Map<String, dynamic>> _fallingItems = [];
  int _lives = 3;
  Timer? _spawnTimer;
  Timer? _gameTimer;
  double _screenWidth = 300.0;
  final double _basketWidth = 80.0;
  final double _itemSize = 40.0;

  int _triviaIndex = 0;
  int _triviaScore = 0;
  bool _triviaAnswered = false;
  String _selectedOption = '';
  final List<Map<String, dynamic>> _triviaQuestions = [
    {'emoji': '🍚🍛🫚🧅', 'answer': 'Biryani', 'options': ['Biryani', 'Pulao', 'Fried Rice', 'Khichdi']},
    {'emoji': '🫓🥔🧅🌶️', 'answer': 'Aloo Paratha', 'options': ['Aloo Paratha', 'Gobi Paratha', 'Thepla', 'Naan']},
    {'emoji': '🍞🧅🫚🌶️', 'answer': 'Pav Bhaji', 'options': ['Pav Bhaji', 'Vada Pav', 'Chole Bhature', 'Misal Pav']},
    {'emoji': '🥟🫚🌶️💨', 'answer': 'Momos', 'options': ['Samosa', 'Momos', 'Dumpling', 'Spring Roll']},
    {'emoji': '🍦🥛🌹🥜', 'answer': 'Kulfi', 'options': ['Ice Cream', 'Kulfi', 'Falooda', 'Rabri']},
  ];

  @override
  void initState() {
    super.initState();
    _startGame();
  }

  @override
  void dispose() {
    _spawnTimer?.cancel();
    _gameTimer?.cancel();
    super.dispose();
  }

  void _startGame() {
    setState(() {
      _isGameOver = false;
      _hasWon = false;
      _score = 0;
    });

    if (widget.gameId == 'flavor_finder') {
      _initMemoryGame();
    } else if (widget.gameId == 'chef_dash') {
      _initCatchGame();
    } else if (widget.gameId == 'spice_master') {
      _initTriviaGame();
    }
  }

  void _initMemoryGame() {
    List<String> deck = [..._memoryEmojis, ..._memoryEmojis];
    deck.shuffle();
    _cards = deck.map((emoji) => {'emoji': emoji, 'flipped': false, 'matched': false}).toList();
    _firstFlippedIndex = -1;
    _secondFlippedIndex = -1;
    _memoryBusy = false;
    _memoryMoves = 0;
  }

  void _flipCard(int index) {
    if (_memoryBusy || _cards[index]['flipped'] || _cards[index]['matched']) return;

    setState(() { _cards[index]['flipped'] = true; });

    if (_firstFlippedIndex == -1) {
      _firstFlippedIndex = index;
    } else {
      _secondFlippedIndex = index;
      _memoryMoves++;
      _memoryBusy = true;

      if (_cards[_firstFlippedIndex]['emoji'] == _cards[_secondFlippedIndex]['emoji']) {
        setState(() {
          _cards[_firstFlippedIndex]['matched'] = true;
          _cards[_secondFlippedIndex]['matched'] = true;
          _firstFlippedIndex = -1;
          _secondFlippedIndex = -1;
          _memoryBusy = false;
          if (_cards.every((c) => c['matched'])) {
            _hasWon = true;
            _isGameOver = true;
          }
        });
      } else {
        Timer(const Duration(milliseconds: 800), () {
          if (mounted) {
            setState(() {
              _cards[_firstFlippedIndex]['flipped'] = false;
              _cards[_secondFlippedIndex]['flipped'] = false;
              _firstFlippedIndex = -1;
              _secondFlippedIndex = -1;
              _memoryBusy = false;
            });
          }
        });
      }
    }
  }

  void _initCatchGame() {
    _lives = 3;
    _fallingItems = [];
    _spawnTimer?.cancel();
    _gameTimer?.cancel();

    _spawnTimer = Timer.periodic(const Duration(milliseconds: 1000), (timer) {
      if (!mounted || _isGameOver) return;
      final isBomb = math.Random().nextDouble() < 0.15;
      final foodList = ['🍕', '🍔', '🌮', '🍜', '🍩', '🍣', '🧁', '🍗'];
      final emoji = isBomb ? '💣' : foodList[math.Random().nextInt(foodList.length)];

      setState(() {
        _fallingItems.add({
          'x': math.Random().nextDouble() * (_screenWidth - _itemSize),
          'y': 0.0,
          'emoji': emoji,
          'isBomb': isBomb,
        });
      });
    });

    _gameTimer = Timer.periodic(const Duration(milliseconds: 30), (timer) {
      if (!mounted || _isGameOver) return;

      List<Map<String, dynamic>> remaining = [];
      for (var item in _fallingItems) {
        double y = item['y'] + 4.0;
        double x = item['x'];

        if (y >= 320.0 && y <= 360.0) {
          double basketLeft = _basketX - _basketWidth / 2;
          double basketRight = _basketX + _basketWidth / 2;

          if (x + _itemSize >= basketLeft && x <= basketRight) {
            if (item['isBomb']) {
              _lives--;
              if (_lives <= 0) {
                _isGameOver = true;
                _hasWon = _score >= 10;
              }
            } else {
              _score++;
              if (_score >= 15) {
                _hasWon = true;
                _isGameOver = true;
              }
            }
            continue;
          }
        }

        if (y > 400.0) {
          if (!item['isBomb']) {
            _lives--;
            if (_lives <= 0) {
              _isGameOver = true;
              _hasWon = _score >= 10;
            }
          }
          continue;
        }

        item['y'] = y;
        remaining.add(item);
      }

      setState(() { _fallingItems = remaining; });
    });
  }

  void _initTriviaGame() {
    _triviaIndex = 0;
    _triviaScore = 0;
    _triviaAnswered = false;
    _selectedOption = '';
    for (var q in _triviaQuestions) {
      (q['options'] as List).shuffle();
    }
  }

  void _answerTrivia(String option) {
    if (_triviaAnswered) return;

    setState(() {
      _triviaAnswered = true;
      _selectedOption = option;
      if (option == _triviaQuestions[_triviaIndex]['answer']) {
        _triviaScore++;
      }
    });

    Timer(const Duration(milliseconds: 1500), () {
      if (mounted) {
        setState(() {
          _triviaAnswered = false;
          _selectedOption = '';
          _triviaIndex++;
          if (_triviaIndex >= _triviaQuestions.length) {
            _isGameOver = true;
            _hasWon = _triviaScore >= 4;
          }
        });
      }
    });
  }

  @override
  Widget build(BuildContext context) {
    _screenWidth = MediaQuery.of(context).size.width - 48;

    return Scaffold(
      backgroundColor: Colors.white,
      appBar: AppBar(
        title: Text(widget.gameTitle.toUpperCase(), style: const TextStyle(fontWeight: FontWeight.w900, fontSize: 14)),
        centerTitle: true,
      ),
      body: SafeArea(
        child: Padding(
          padding: const EdgeInsets.all(24.0),
          child: Column(
            children: [
              _buildGameStatsHeader(),
              const SizedBox(height: 24),
              Expanded(
                child: Container(
                  decoration: BoxDecoration(
                    color: const Color(0xFFF8F9FA),
                    borderRadius: BorderRadius.circular(32),
                    border: Border.all(color: const Color(0xFFE9ECEF)),
                  ),
                  clipBehavior: Clip.antiAlias,
                  child: Stack(
                    children: [
                      if (!_isGameOver) _buildActiveGameContent(),
                      if (_isGameOver) _buildGameOverContent(),
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

  Widget _buildGameStatsHeader() {
    return Row(
      mainAxisAlignment: MainAxisAlignment.spaceBetween,
      children: [
        Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            const Text('SCORE', style: TextStyle(fontSize: 10, fontWeight: FontWeight.w900, color: Colors.black38, letterSpacing: 1)),
            Text('$_score', style: TextStyle(fontSize: 24, fontWeight: FontWeight.w900, color: WeloColors.sovereignOrange)),
          ],
        ),
        if (widget.gameId == 'chef_dash' && !_isGameOver)
          Row(
            children: List.generate(3, (i) => Icon(
              Icons.favorite,
              color: i < _lives ? Colors.red : Colors.grey[300],
              size: 24,
            )),
          ),
        if (widget.gameId == 'flavor_finder' && !_isGameOver)
          Text('MOVES: $_memoryMoves', style: const TextStyle(fontWeight: FontWeight.w900, fontSize: 14)),
        if (widget.gameId == 'spice_master' && !_isGameOver)
          Text('QUESTION: ${_triviaIndex + 1}/${_triviaQuestions.length}', style: const TextStyle(fontWeight: FontWeight.w900, fontSize: 14)),
      ],
    );
  }

  Widget _buildActiveGameContent() {
    if (widget.gameId == 'flavor_finder') {
      return GridView.builder(
        padding: const EdgeInsets.all(24),
        gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(
          crossAxisCount: 3,
          crossAxisSpacing: 12,
          mainAxisSpacing: 12,
        ),
        itemCount: _cards.length,
        itemBuilder: (context, index) {
          final card = _cards[index];
          final isOpen = card['flipped'] || card['matched'];

          return GestureDetector(
            onTap: () => _flipCard(index),
            child: AnimatedContainer(
              duration: const Duration(milliseconds: 300),
              decoration: BoxDecoration(
                color: isOpen ? Colors.white : WeloColors.sovereignOrange,
                borderRadius: BorderRadius.circular(20),
                border: Border.all(
                  color: isOpen ? WeloColors.sovereignOrange.withValues(alpha: 0.2) : Colors.transparent,
                  width: 2,
                ),
                boxShadow: isOpen ? null : [
                  BoxShadow(color: WeloColors.sovereignOrange.withValues(alpha: 0.3), blurRadius: 8, offset: const Offset(0, 4))
                ],
              ),
              alignment: Alignment.center,
              child: Text(
                isOpen ? card['emoji'] : '❓',
                style: TextStyle(fontSize: isOpen ? 32 : 24, color: isOpen ? null : Colors.white),
              ),
            ),
          );
        },
      );
    }

    if (widget.gameId == 'chef_dash') {
      return GestureDetector(
        onHorizontalDragUpdate: (details) {
          setState(() {
            _basketX = (details.localPosition.dx).clamp(_basketWidth / 2, _screenWidth - _basketWidth / 2);
          });
        },
        onTapDown: (details) {
          setState(() {
            _basketX = (details.localPosition.dx).clamp(_basketWidth / 2, _screenWidth - _basketWidth / 2);
          });
        },
        child: Container(
          color: Colors.transparent,
          child: Stack(
            children: [
              Positioned(
                top: 20, left: 0, right: 0,
                child: Text(
                  'DRAG BASKET TO CATCH FOOD!\nSCORE: $_score / 15',
                  textAlign: TextAlign.center,
                  style: const TextStyle(fontWeight: FontWeight.w900, color: Colors.black26, fontSize: 12),
                ),
              ),
              ..._fallingItems.map((item) {
                return Positioned(
                  left: item['x'],
                  top: item['y'],
                  child: Text(item['emoji'], style: const TextStyle(fontSize: 32)),
                );
              }),
              Positioned(
                bottom: 20,
                left: _basketX - _basketWidth / 2,
                child: Container(
                  width: _basketWidth,
                  height: 40,
                  decoration: BoxDecoration(
                    color: Colors.brown[400],
                    borderRadius: const BorderRadius.vertical(bottom: Radius.circular(20), top: Radius.circular(4)),
                    border: Border.all(color: Colors.brown[600]!, width: 2),
                  ),
                  alignment: Alignment.center,
                  child: const Text('🧺', style: TextStyle(fontSize: 24)),
                ),
              ),
            ],
          ),
        ),
      );
    }

    if (widget.gameId == 'spice_master') {
      if (_triviaIndex >= _triviaQuestions.length) return const SizedBox();
      final currentQ = _triviaQuestions[_triviaIndex];

      return Padding(
        padding: const EdgeInsets.all(24.0),
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            Text(currentQ['emoji'], style: const TextStyle(fontSize: 64)),
            const SizedBox(height: 16),
            const Text('What dish is described by these emojis?', style: TextStyle(fontWeight: FontWeight.bold, color: Colors.black38, fontSize: 13)),
            const SizedBox(height: 32),
            ...(currentQ['options'] as List<String>).map((opt) {
              Color btnColor = Colors.white;
              Color txtColor = Colors.black87;
              BorderSide borderSide = const BorderSide(color: Color(0xFFE9ECEF), width: 2);

              if (_triviaAnswered) {
                if (opt == currentQ['answer']) {
                  btnColor = Colors.green[50]!;
                  txtColor = Colors.green[700]!;
                  borderSide = BorderSide(color: Colors.green[400]!, width: 2);
                } else if (opt == _selectedOption) {
                  btnColor = Colors.red[50]!;
                  txtColor = Colors.red[700]!;
                  borderSide = BorderSide(color: Colors.red[400]!, width: 2);
                } else {
                  btnColor = Colors.grey[100]!;
                  txtColor = Colors.black26;
                }
              }

              return Container(
                margin: const EdgeInsets.only(bottom: 12),
                width: double.infinity,
                height: 52,
                child: ElevatedButton(
                  style: ElevatedButton.styleFrom(
                    backgroundColor: btnColor,
                    foregroundColor: txtColor,
                    elevation: 0,
                    shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
                    side: borderSide,
                  ),
                  onPressed: _triviaAnswered ? null : () => _answerTrivia(opt),
                  child: Text(opt, style: const TextStyle(fontWeight: FontWeight.w900, fontSize: 14)),
                ),
              );
            }),
          ],
        ),
      );
    }

    return const SizedBox();
  }

  Widget _buildGameOverContent() {
    return Center(
      child: Padding(
        padding: const EdgeInsets.all(32.0),
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            Text(_hasWon ? '🏆' : '💀', style: const TextStyle(fontSize: 72)),
            const SizedBox(height: 16),
            Text(
              _hasWon ? 'NICE WORK!' : 'GAME OVER',
              style: TextStyle(
                fontSize: 28,
                fontWeight: FontWeight.w900,
                color: _hasWon ? Colors.green : Colors.red,
                letterSpacing: 0.5,
              ),
            ),
            const SizedBox(height: 8),
            Text(
              _hasWon
                  ? 'Great game! Your score: $_score'
                  : 'Better luck next time! Your score: $_score',
              textAlign: TextAlign.center,
              style: const TextStyle(color: Colors.black54, fontSize: 13, height: 1.4),
            ),
            const SizedBox(height: 32),
            SizedBox(
              width: double.infinity,
              height: 52,
              child: ElevatedButton(
                style: ElevatedButton.styleFrom(
                  backgroundColor: Colors.black,
                  foregroundColor: Colors.white,
                  shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
                ),
                onPressed: () {
                  _startGame();
                },
                child: const Text('PLAY AGAIN', style: TextStyle(fontWeight: FontWeight.w900, letterSpacing: 1.0)),
              ),
            ),
            const SizedBox(height: 12),
            TextButton(
              onPressed: () => Navigator.pop(context),
              child: const Text('Go Back', style: TextStyle(color: Colors.black54, fontWeight: FontWeight.bold)),
            ),
          ],
        ),
      ),
    );
  }
}
