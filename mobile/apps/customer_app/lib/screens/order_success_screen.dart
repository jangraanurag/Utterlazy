import 'dart:math' as math;

import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import 'package:google_fonts/google_fonts.dart';

import 'package:api/api.dart';
import 'package:ui/ui.dart';
import 'package:core/core.dart';
import 'package:url_launcher/url_launcher.dart';
import 'order_tracking_screen.dart';
import 'games_screen.dart';
import 'scratch_card_history_screen.dart';

class OrderSuccessScreen extends ConsumerStatefulWidget {
  final Order order;
  const OrderSuccessScreen({super.key, required this.order});

  @override
  ConsumerState<OrderSuccessScreen> createState() => _OrderSuccessScreenState();
}

class _OrderSuccessScreenState extends ConsumerState<OrderSuccessScreen> with TickerProviderStateMixin {
  ScratchCardReward? _scratchReward;
  bool _isLoadingReward = true;
  bool _isScratched = false;
  List<ConfettiParticle> _confettis = [];
  bool _showConfetti = false;
  late AnimationController _confettiController;

  @override
  void initState() {
    super.initState();
    _loadScratchReward();
    _confettiController = AnimationController(
      vsync: this,
      duration: const Duration(seconds: 4),
    )..addListener(() {
        _updateConfetti();
      });
  }

  @override
  void dispose() {
    _confettiController.dispose();
    super.dispose();
  }

  Future<void> _loadScratchReward() async {
    try {
      final repo = ref.read(couponRepositoryProvider);
      final reward = await repo.getScratchCard();
      if (mounted) {
        setState(() {
          _scratchReward = reward;
          _isLoadingReward = false;
        });
        // Save scratch card to history
        if (reward != null) {
          ScratchCardStorage.saveCard(reward.toJson());
        }
      }
    } catch (e) {
      if (mounted) {
        setState(() {
          _isLoadingReward = false;
        });
      }
    }
  }

  void _updateConfetti() {
    if (_confettis.isEmpty) return;
    setState(() {
      for (final p in _confettis) {
        p.update();
      }
    });
  }

  void _triggerConfetti() {
    final random = math.Random();
    final List<Color> colors = [
      Colors.amber,
      Colors.orange,
      Colors.pink,
      Colors.green,
      Colors.cyan,
      Colors.blue,
      Colors.purple,
    ];

    final List<ConfettiParticle> list = [];
    for (int i = 0; i < 60; i++) {
      list.add(ConfettiParticle(
        x: random.nextDouble() * 100,
        y: -10 - random.nextDouble() * 50,
        vx: (random.nextDouble() - 0.5) * 3,
        vy: random.nextDouble() * 4 + 2,
        size: random.nextDouble() * 8 + 6,
        color: colors[random.nextInt(colors.length)],
        rotation: random.nextDouble() * math.pi * 2,
        rotationSpeed: (random.nextDouble() - 0.5) * 0.2,
        shape: random.nextInt(3),
      ));
    }

    setState(() {
      _confettis = list;
      _showConfetti = true;
    });

    _confettiController.reset();
    _confettiController.forward().then((_) {
      if (mounted) {
        setState(() {
          _showConfetti = false;
          _confettis = [];
        });
      }
    });
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: WeloColors.sovereignBg,
      body: Stack(
        children: [
          SafeArea(
            child: SingleChildScrollView(
              physics: const BouncingScrollPhysics(),
              child: Padding(
                padding: const EdgeInsets.symmetric(horizontal: 24.0, vertical: 32.0),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.center,
                  children: [
                    const SizedBox(height: 16),
                    // Premium Animated Circle
                    Container(
                      width: 100,
                      height: 100,
                      decoration: BoxDecoration(
                        color: Colors.green.withValues(alpha: 0.08),
                        shape: BoxShape.circle,
                      ),
                      child: const Center(
                        child: Icon(
                          Icons.check_circle_rounded,
                          color: Colors.green,
                          size: 64,
                        ),
                      ),
                    ),
                    const SizedBox(height: 24),
                    Text(
                      'ORDER CONFIRMED',
                      style: GoogleFonts.spaceGrotesk(
                        fontSize: 24,
                        fontWeight: FontWeight.w900,
                        letterSpacing: -0.5,
                        color: WeloColors.sovereignText,
                      ),
                    ),
                    const SizedBox(height: 12),
                    Text(
                      'Your culinary mission has been dispatched successfully.',
                      textAlign: TextAlign.center,
                      style: TextStyle(
                        color: WeloColors.sovereignText.withValues(alpha: 0.6),
                        fontSize: 14,
                        height: 1.5,
                      ),
                    ),
                    const SizedBox(height: 32),

                    // Mystery Scratch Card Section
                    if (_isLoadingReward)
                      const SizedBox(
                        height: 240,
                        child: Center(
                          child: CircularProgressIndicator(color: WeloColors.sovereignOrange),
                        ),
                      )
                    else if (_scratchReward != null) ...[
                      Text(
                        '🎁 Mystery Scratch Card!',
                        style: GoogleFonts.spaceGrotesk(
                          fontSize: 18,
                          fontWeight: FontWeight.w900,
                          color: WeloColors.sovereignText,
                        ),
                      ),
                      const SizedBox(height: 4),
                      Text(
                        'SCRATCH TO REVEAL A PREMIUM COUPON REWARD',
                        style: TextStyle(
                          fontSize: 9,
                          fontWeight: FontWeight.w900,
                          color: WeloColors.sovereignText.withValues(alpha: 0.5),
                          letterSpacing: 1.2,
                        ),
                      ),
                      const SizedBox(height: 16),
                      PremiumScratchCard(
                        reward: _scratchReward!,
                        onScratchComplete: () {
                          setState(() {
                            _isScratched = true;
                          });
                          ScratchCardStorage.markScratched(_scratchReward!.code);
                          _triggerConfetti();
                        },
                      ),
                      const SizedBox(height: 12),
                      Text(
                        _isScratched
                            ? 'COUPON REVEALED! COPY THE CODE TO REDEEM.'
                            : 'SWIPE OR DRAG TO SCRATCH',
                        style: TextStyle(
                          fontSize: 9,
                          fontWeight: FontWeight.w900,
                          letterSpacing: 1.0,
                          color: _isScratched ? Colors.green : WeloColors.sovereignOrange,
                        ),
                      ),
                      const SizedBox(height: 32),
                    ],

                    // Summary Card
                    Container(
                      padding: const EdgeInsets.all(24),
                      decoration: BoxDecoration(
                        color: WeloColors.sovereignSurface,
                        borderRadius: BorderRadius.circular(24),
                        boxShadow: [
                          BoxShadow(
                            color: Colors.black.withValues(alpha: 0.015),
                            blurRadius: 20,
                            offset: const Offset(0, 8),
                          )
                        ],
                      ),
                      child: Column(
                        children: [
                          Row(
                            mainAxisAlignment: MainAxisAlignment.spaceBetween,
                            children: [
                              const Text('ORDER ID',
                                  style: TextStyle(fontWeight: FontWeight.w700, fontSize: 11, color: Colors.black38)),
                              Text(
                                '#${widget.order.id.substring(0, 8).toUpperCase()}',
                                style: GoogleFonts.spaceGrotesk(fontWeight: FontWeight.bold, fontSize: 12),
                              ),
                            ],
                          ),
                          const Divider(height: 20, color: Color(0xFFF1F1F1)),
                          Row(
                            mainAxisAlignment: MainAxisAlignment.spaceBetween,
                            children: [
                              const Text('RESTAURANT',
                                  style: TextStyle(fontWeight: FontWeight.w700, fontSize: 11, color: Colors.black38)),
                              Flexible(
                                child: Text(
                                  widget.order.restaurantName,
                                  style: const TextStyle(fontWeight: FontWeight.w900, fontSize: 12),
                                  overflow: TextOverflow.ellipsis,
                                ),
                              ),
                            ],
                          ),
                          const Divider(height: 20, color: Color(0xFFF1F1F1)),
                          Row(
                            mainAxisAlignment: MainAxisAlignment.spaceBetween,
                            children: [
                              const Text('TOTAL PAY',
                                  style: TextStyle(fontWeight: FontWeight.w700, fontSize: 11, color: Colors.black38)),
                              Text(
                                '₹${widget.order.totalAmount.toInt()}',
                                style: const TextStyle(fontWeight: FontWeight.w900, fontSize: 14, color: WeloColors.sovereignOrange),
                              ),
                            ],
                          ),

                        ],
                      ),
                    ),
                    const SizedBox(height: 40),

                    // Tracking Button
                    WeloPrimaryButton(
                      text: 'TRACK MISSION LIVE',
                      onPressed: () {
                        Navigator.pushReplacement(
                          context,
                          MaterialPageRoute(
                            builder: (context) => OrderTrackingScreen(order: widget.order),
                          ),
                        );
                      },
                    ),
                    const SizedBox(height: 16),
                    SizedBox(
                      width: double.infinity,
                      child: OutlinedButton.icon(
                        onPressed: () {
                          Navigator.push(
                            context,
                            MaterialPageRoute(builder: (_) => const GamesScreen()),
                          );
                        },
                        icon: const Icon(Icons.sports_esports, size: 18),
                        label: const Text('PLAY WHILE YOU WAIT', style: TextStyle(fontWeight: FontWeight.bold, fontSize: 12, letterSpacing: 0.5)),
                        style: OutlinedButton.styleFrom(
                          foregroundColor: WeloColors.sovereignOrange,
                          side: const BorderSide(color: WeloColors.sovereignOrange),
                          padding: const EdgeInsets.symmetric(vertical: 16),
                          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
                        ),
                      ),
                    ),
                    const SizedBox(height: 16),
                  ],
                ),
              ),
            ),
          ),
          // Confetti Particle Layer on top
          _buildConfettiOverlay(),
        ],
      ),
    );
  }

  Widget _buildConfettiOverlay() {
    if (!_showConfetti) return const SizedBox.shrink();
    return Positioned.fill(
      child: IgnorePointer(
        child: CustomPaint(
          painter: ConfettiPainter(_confettis),
        ),
      ),
    );
  }
}

// Custom Confetti Particle definition
class ConfettiParticle {
  double x;
  double y;
  double vx;
  double vy;
  double size;
  Color color;
  double rotation;
  double rotationSpeed;
  int shape; // 0: square, 1: circle, 2: triangle

  ConfettiParticle({
    required this.x,
    required this.y,
    required this.vx,
    required this.vy,
    required this.size,
    required this.color,
    required this.rotation,
    required this.rotationSpeed,
    required this.shape,
  });

  void update() {
    x += vx;
    y += vy;
    vy += 0.15; // gravity acceleration
    vx *= 0.98; // air drag resistance
    rotation += rotationSpeed;
  }
}

class ConfettiPainter extends CustomPainter {
  final List<ConfettiParticle> particles;
  ConfettiPainter(this.particles);

  @override
  void paint(Canvas canvas, Size size) {
    for (final p in particles) {
      final paint = Paint()
        ..color = p.color
        ..style = PaintingStyle.fill;

      canvas.save();

      final actualX = (p.x / 100) * size.width;
      final actualY = p.y;

      if (actualY > size.height) {
        canvas.restore();
        continue;
      }

      canvas.translate(actualX, actualY);
      canvas.rotate(p.rotation);

      if (p.shape == 0) {
        // Square
        canvas.drawRect(
          Rect.fromCenter(center: Offset.zero, width: p.size, height: p.size),
          paint,
        );
      } else if (p.shape == 1) {
        // Circle
        canvas.drawCircle(Offset.zero, p.size / 2, paint);
      } else {
        // Triangle
        final path = Path()
          ..moveTo(0, -p.size / 2)
          ..lineTo(p.size / 2, p.size / 2)
          ..lineTo(-p.size / 2, p.size / 2)
          ..close();
        canvas.drawPath(path, paint);
      }
      canvas.restore();
    }
  }

  @override
  bool shouldRepaint(ConfettiPainter oldDelegate) => true;
}

// Dashed Line Painter for Voucher Details
class DashedLinePainter extends CustomPainter {
  @override
  void paint(Canvas canvas, Size size) {
    final paint = Paint()
      ..color = Colors.black.withValues(alpha: 0.08)
      ..strokeWidth = 1
      ..style = PaintingStyle.stroke;
    double max = size.width;
    double dashWidth = 5;
    double dashSpace = 3;
    double startX = 0;
    while (startX < max) {
      canvas.drawLine(Offset(startX, 0), Offset(startX + dashWidth, 0), paint);
      startX += dashWidth + dashSpace;
    }
  }

  @override
  bool shouldRepaint(CustomPainter oldDelegate) => false;
}

// Scratch Layer Painter using saveLayer and BlendMode.clear
class ScratchPainter extends CustomPainter {
  final Path scratchPath;

  ScratchPainter({required this.scratchPath});

  @override
  void paint(Canvas canvas, Size size) {
    final rect = Offset.zero & size;

    canvas.saveLayer(rect, Paint());

    // 1. Draw gold scratchable overlay
    final paintGold = Paint();
    final grad = LinearGradient(
      colors: const [
        Color(0xFFFFE082),
        Color(0xFFFFD54F),
        Color(0xFFFFF8E1),
        Color(0xFFFFC107),
        Color(0xFFFFA000),
      ],
      begin: Alignment.topLeft,
      end: Alignment.bottomRight,
    );
    paintGold.shader = grad.createShader(rect);

    final rrect = RRect.fromRectAndRadius(rect, const Radius.circular(16));
    canvas.drawRRect(rrect, paintGold);

    // 2. Draw mesh pattern
    final paintMesh = Paint()
      ..color = Colors.white.withValues(alpha: 0.22)
      ..style = PaintingStyle.stroke
      ..strokeWidth = 1.5;

    for (double i = -size.width; i < size.width; i += 24) {
      canvas.drawLine(Offset(i, 0), Offset(i + size.height, size.height), paintMesh);
      canvas.drawLine(Offset(i + size.width, 0), Offset(i, size.height), paintMesh);
    }

    // 3. Draw sparkle stars
    final paintSparkle = Paint()..color = Colors.white.withValues(alpha: 0.6);
    final sparkles = [
      const Offset(30, 40), const Offset(80, 20), const Offset(120, 50), const Offset(200, 30), const Offset(250, 45),
      const Offset(50, 140), const Offset(100, 150), const Offset(150, 120), const Offset(220, 130), const Offset(260, 110),
      const Offset(15, 90), const Offset(90, 85), const Offset(170, 75), const Offset(240, 80), const Offset(130, 25)
    ];
    for (final pt in sparkles) {
      canvas.drawCircle(pt, 2, paintSparkle);
    }

    // 4. DrawCentered Emoji
    final textPainterEmoji = TextPainter(
      text: const TextSpan(
        text: '🎁',
        style: TextStyle(fontSize: 44),
      ),
      textDirection: TextDirection.ltr,
    )..layout();
    textPainterEmoji.paint(canvas, Offset(size.width / 2 - textPainterEmoji.width / 2, size.height / 2 - 50));

    // 5. DrawCentered lucky scratch card title text
    final textPainterTitle = TextPainter(
      text: const TextSpan(
        text: 'LUCKY SCRATCH CARD',
        style: TextStyle(
          fontSize: 11,
          fontWeight: FontWeight.bold,
          color: Color(0xFF5C4308),
          letterSpacing: 0.5,
        ),
      ),
      textDirection: TextDirection.ltr,
    )..layout();
    textPainterTitle.paint(canvas, Offset(size.width / 2 - textPainterTitle.width / 2, size.height / 2 + 16));

    // 6. DrawCentered swipe instruction text
    final textPainterInstruction = TextPainter(
      text: const TextSpan(
        text: 'SWIPE TO REVEAL REWARD',
        style: TextStyle(
          fontSize: 10,
          fontWeight: FontWeight.w900,
          color: Color(0xFF9C7310),
          letterSpacing: 0.5,
        ),
      ),
      textDirection: TextDirection.ltr,
    )..layout();
    textPainterInstruction.paint(canvas, Offset(size.width / 2 - textPainterInstruction.width / 2, size.height / 2 + 32));

    // 7. Draw scratch path with BlendMode.clear to "erase" gold cover
    final paintEraser = Paint()
      ..blendMode = BlendMode.clear
      ..style = PaintingStyle.stroke
      ..strokeWidth = 35
      ..strokeCap = StrokeCap.round
      ..strokeJoin = StrokeJoin.round;

    canvas.drawPath(scratchPath, paintEraser);

    canvas.restore();
  }

  @override
  bool shouldRepaint(ScratchPainter oldDelegate) => true;
}

// Interactive Scratch Card Widget containing underneath voucher and overlay gestures
class PremiumScratchCard extends StatefulWidget {
  final ScratchCardReward reward;
  final VoidCallback onScratchComplete;

  const PremiumScratchCard({
    super.key,
    required this.reward,
    required this.onScratchComplete,
  });

  @override
  State<PremiumScratchCard> createState() => _PremiumScratchCardState();
}

class _PremiumScratchCardState extends State<PremiumScratchCard> {
  final Path _scratchPath = Path();
  bool _isScratched = false;
  final Set<int> _scratchedCells = {};

  final int _gridCols = 15;
  final int _gridRows = 10;

  void _checkScratch(Offset localPos, Size size) {
    if (localPos.dx < 0 || localPos.dx > size.width || localPos.dy < 0 || localPos.dy > size.height) return;

    final cellWidth = size.width / _gridCols;
    final cellHeight = size.height / _gridRows;

    final col = (localPos.dx / cellWidth).floor().clamp(0, _gridCols - 1);
    final row = (localPos.dy / cellHeight).floor().clamp(0, _gridRows - 1);

    final index = row * _gridCols + col;
    if (_scratchedCells.add(index)) {
      final percentage = (_scratchedCells.length / (_gridCols * _gridRows)) * 100;
      if (percentage > 45 && !_isScratched) {
        setState(() {
          _isScratched = true;
        });
        widget.onScratchComplete();
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    return Container(
      width: 280,
      height: 180,
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(16),
        boxShadow: [
          BoxShadow(
            color: Colors.black.withValues(alpha: 0.06),
            blurRadius: 20,
            offset: const Offset(0, 8),
          ),
        ],
      ),
      child: Stack(
        clipBehavior: Clip.none,
        children: [
          // 1. Underneath Voucher Design
          Padding(
            padding: const EdgeInsets.symmetric(horizontal: 16.0, vertical: 10.0),
            child: Column(
              mainAxisAlignment: MainAxisAlignment.spaceBetween,
              children: [
                Expanded(
                  child: Column(
                    mainAxisAlignment: MainAxisAlignment.center,
                    children: [
                      Container(
                        padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 2),
                        decoration: BoxDecoration(
                          color: WeloColors.sovereignOrange.withValues(alpha: 0.1),
                          borderRadius: BorderRadius.circular(20),
                        ),
                        child: Row(
                          mainAxisSize: MainAxisSize.min,
                          children: [
                            const Icon(
                              Icons.local_activity,
                              size: 11,
                              color: WeloColors.sovereignOrange,
                            ),
                            const SizedBox(width: 4),
                            Text(
                              widget.reward.brand.toUpperCase(),
                              style: const TextStyle(
                                fontSize: 9,
                                fontWeight: FontWeight.w900,
                                color: WeloColors.sovereignOrange,
                                letterSpacing: 0.5,
                              ),
                              maxLines: 1,
                              overflow: TextOverflow.ellipsis,
                            ),
                          ],
                        ),
                      ),
                      const SizedBox(height: 4),
                      Text(
                        widget.reward.title,
                        style: GoogleFonts.spaceGrotesk(
                          fontSize: 14,
                          fontWeight: FontWeight.w900,
                          color: WeloColors.sovereignText,
                        ),
                        textAlign: TextAlign.center,
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                      ),
                      const SizedBox(height: 2),
                      Text(
                        widget.reward.description,
                        style: TextStyle(
                          fontSize: 9,
                          fontWeight: FontWeight.w500,
                          color: WeloColors.sovereignText.withValues(alpha: 0.6),
                          height: 1.3,
                        ),
                        textAlign: TextAlign.center,
                        maxLines: 2,
                        overflow: TextOverflow.ellipsis,
                      ),
                      const SizedBox(height: 2),
                      Text(
                        widget.reward.expiry,
                        style: TextStyle(
                          fontSize: 8,
                          fontWeight: FontWeight.bold,
                          color: Colors.redAccent.withValues(alpha: 0.8),
                        ),
                        textAlign: TextAlign.center,
                        maxLines: 2,
                      ),
                    ],
                  ),
                ),

                // Dashed Line Separator
                CustomPaint(
                  size: const Size(double.infinity, 1),
                  painter: DashedLinePainter(),
                ),
                const SizedBox(height: 6),

                // Promo Code Copy Box and Redeem Action
                Row(
                  children: [
                    Expanded(
                      child: Container(
                        height: 32,
                        padding: const EdgeInsets.symmetric(horizontal: 12),
                        decoration: BoxDecoration(
                          color: const Color(0xFFF6F7F9),
                          borderRadius: BorderRadius.circular(8),
                          border: Border.all(
                            color: Colors.black.withValues(alpha: 0.05),
                          ),
                        ),
                        child: Row(
                          mainAxisAlignment: MainAxisAlignment.spaceBetween,
                          children: [
                            Expanded(
                              child: SelectableText(
                                widget.reward.code,
                                style: GoogleFonts.spaceGrotesk(
                                  fontSize: 11,
                                  fontWeight: FontWeight.w900,
                                  letterSpacing: 1.0,
                                ),
                                maxLines: 1,
                              ),
                            ),
                            IconButton(
                              padding: EdgeInsets.zero,
                              constraints: const BoxConstraints(),
                              icon: const Icon(
                                Icons.copy_rounded,
                                size: 16,
                                color: WeloColors.sovereignOrange,
                              ),
                              onPressed: () {
                                Clipboard.setData(ClipboardData(text: widget.reward.code));
                                ScaffoldMessenger.of(context).showSnackBar(
                                  SnackBar(
                                    content: Row(
                                      children: [
                                        const Icon(Icons.check_circle_rounded, color: Colors.white, size: 16),
                                        const SizedBox(width: 8),
                                        const Text(
                                          'Promo code copied successfully!',
                                          style: TextStyle(fontSize: 12, fontWeight: FontWeight.bold),
                                        ),
                                      ],
                                    ),
                                    behavior: SnackBarBehavior.floating,
                                    backgroundColor: WeloColors.sovereignText,
                                    duration: const Duration(seconds: 2),
                                    shape: RoundedRectangleBorder(
                                      borderRadius: BorderRadius.circular(20),
                                    ),
                                    margin: const EdgeInsets.all(24),
                                  ),
                                );
                              },
                            ),
                          ],
                        ),
                      ),
                    ),
                    const SizedBox(width: 8),
                    GestureDetector(
                      onTap: () async {
                        final uri = Uri.parse(widget.reward.link);
                        if (await canLaunchUrl(uri)) {
                          await launchUrl(uri, mode: LaunchMode.externalApplication);
                        }
                      },
                      child: Container(
                        height: 32,
                        padding: const EdgeInsets.symmetric(horizontal: 16),
                        decoration: BoxDecoration(
                          color: WeloColors.sovereignOrange,
                          borderRadius: BorderRadius.circular(8),
                          boxShadow: [
                            BoxShadow(
                              color: WeloColors.sovereignOrange.withValues(alpha: 0.15),
                              blurRadius: 8,
                              offset: const Offset(0, 3),
                            ),
                          ],
                        ),
                        alignment: Alignment.center,
                        child: const Text(
                          'REDEEM',
                          style: TextStyle(
                            color: Colors.white,
                            fontSize: 9,
                            fontWeight: FontWeight.w900,
                            letterSpacing: 1.0,
                          ),
                        ),
                      ),
                    ),
                  ],
                ),
              ],
            ),
          ),

          // 2. Left Cutout Shape
          Positioned(
            left: -6,
            top: 90 - 12,
            child: Container(
              width: 12,
              height: 24,
              decoration: BoxDecoration(
                color: WeloColors.sovereignBg,
                borderRadius: const BorderRadius.only(
                  topRight: Radius.circular(12),
                  bottomRight: Radius.circular(12),
                ),
                border: Border.all(
                  color: Colors.black.withValues(alpha: 0.03),
                  width: 1,
                ),
              ),
            ),
          ),

          // 3. Right Cutout Shape
          Positioned(
            right: -6,
            top: 90 - 12,
            child: Container(
              width: 12,
              height: 24,
              decoration: BoxDecoration(
                color: WeloColors.sovereignBg,
                borderRadius: const BorderRadius.only(
                  topLeft: Radius.circular(12),
                  bottomLeft: Radius.circular(12),
                ),
                border: Border.all(
                  color: Colors.black.withValues(alpha: 0.03),
                  width: 1,
                ),
              ),
            ),
          ),

          // 4. Scratch Layer Overlay
          if (!_isScratched)
            Positioned.fill(
              child: ClipRRect(
                borderRadius: BorderRadius.circular(16),
                child: RepaintBoundary(
                  child: GestureDetector(
                    onPanStart: (details) {
                      setState(() {
                        _scratchPath.moveTo(details.localPosition.dx, details.localPosition.dy);
                      });
                      _checkScratch(details.localPosition, const Size(280, 180));
                    },
                    onPanUpdate: (details) {
                      setState(() {
                        _scratchPath.lineTo(details.localPosition.dx, details.localPosition.dy);
                      });
                      _checkScratch(details.localPosition, const Size(280, 180));
                    },
                    child: CustomPaint(
                      painter: ScratchPainter(scratchPath: _scratchPath),
                    ),
                  ),
                ),
              ),
            ),
        ],
      ),
    );
  }
}
