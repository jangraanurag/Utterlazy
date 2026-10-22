import 'dart:async';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:ui/ui.dart';
import 'package:core/core.dart';
import 'package:vibration/vibration.dart';
import 'package:dio/dio.dart';

class ApprovalOtpDialog extends ConsumerStatefulWidget {
  final String entityType;
  final String entityId;

  const ApprovalOtpDialog({
    super.key,
    required this.entityType,
    required this.entityId,
  });

  @override
  ConsumerState<ApprovalOtpDialog> createState() => _ApprovalOtpDialogState();
}

class _ApprovalOtpDialogState extends ConsumerState<ApprovalOtpDialog> {
  final _controllers = List.generate(6, (_) => TextEditingController());
  final _focusNodes = List.generate(6, (_) => FocusNode());
  String? _error;
  bool _isLoading = false;
  int _remainingSeconds = 600;
  Timer? _countdownTimer;
  int _attemptsLeft = 3;

  @override
  void initState() {
    super.initState();
    _focusNodes[0].requestFocus();
    _startCountdown();
  }

  @override
  void dispose() {
    for (final c in _controllers) { c.dispose(); }
    for (final f in _focusNodes) { f.dispose(); }
    _countdownTimer?.cancel();
    super.dispose();
  }

  void _startCountdown() {
    _countdownTimer = Timer.periodic(const Duration(seconds: 1), (timer) {
      if (_remainingSeconds <= 1) {
        timer.cancel();
        if (mounted) Navigator.pop(context);
        return;
      }
      setState(() => _remainingSeconds--);
    });
  }

  String get _formattedTime {
    final min = (_remainingSeconds ~/ 60).toString().padLeft(2, '0');
    final sec = (_remainingSeconds % 60).toString().padLeft(2, '0');
    return '$min:$sec';
  }

  Future<void> _verifyOtp() async {
    final otp = _controllers.map((c) => c.text).join();
    if (otp.length < 6) {
      setState(() => _error = 'Enter all 6 digits');
      return;
    }

    setState(() { _isLoading = true; _error = null; });

    try {
      final vendorRepo = ref.read(vendorRepositoryProvider);
      await vendorRepo.verifyApprovalOtp(widget.entityType, widget.entityId, otp);
      if (mounted) Navigator.pop(context, true);
    } on DioException catch (e) {
      final statusCode = e.response?.statusCode;
      final message = e.response?.data is Map
          ? (e.response!.data['message'] ?? e.response!.data['error'] ?? 'Invalid OTP')
          : 'Invalid OTP';

      if (statusCode == 422 || statusCode == 400) {
        _attemptsLeft--;
        try { await Vibration.vibrate(); } catch (_) {}
        setState(() {
          _error = _attemptsLeft > 0 ? '$message ($_attemptsLeft attempts left)' : 'No attempts left. Please request a new OTP.';
          _isLoading = false;
        });
        for (final c in _controllers) { c.clear(); }
        _focusNodes[0].requestFocus();
        if (_attemptsLeft <= 0) {
          await Future.delayed(const Duration(seconds: 2));
          if (mounted) Navigator.pop(context);
        }
      } else {
        setState(() { _error = message.toString(); _isLoading = false; });
      }
    } catch (e) {
      setState(() { _error = 'Something went wrong. Please try again.'; _isLoading = false; });
    }
  }

  @override
  Widget build(BuildContext context) {
    return Dialog(
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(24)),
      child: Padding(
        padding: const EdgeInsets.all(24),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            const Icon(Icons.lock_outline, size: 40, color: WeloColors.sovereignOrange),
            const SizedBox(height: 16),
            const Text(
              'Enter OTP',
              style: TextStyle(fontSize: 18, fontWeight: FontWeight.bold),
            ),
            const SizedBox(height: 8),
            const Text(
              'A 6-digit OTP has been sent to your registered email',
              textAlign: TextAlign.center,
              style: TextStyle(color: WeloColors.textBody, fontSize: 13),
            ),
            const SizedBox(height: 24),
            Row(
              mainAxisAlignment: MainAxisAlignment.spaceEvenly,
              children: List.generate(6, (index) {
                return SizedBox(
                  width: 44,
                  height: 52,
                  child: TextField(
                    controller: _controllers[index],
                    focusNode: _focusNodes[index],
                    textAlign: TextAlign.center,
                    keyboardType: TextInputType.number,
                    maxLength: 1,
                    style: const TextStyle(fontSize: 22, fontWeight: FontWeight.bold),
                    decoration: InputDecoration(
                      counterText: '',
                      border: OutlineInputBorder(
                        borderRadius: BorderRadius.circular(12),
                        borderSide: BorderSide(
                          color: _error != null ? WeloColors.error : WeloColors.sovereignElevated,
                        ),
                      ),
                      focusedBorder: OutlineInputBorder(
                        borderRadius: BorderRadius.circular(12),
                        borderSide: const BorderSide(
                          color: WeloColors.sovereignOrange,
                          width: 2,
                        ),
                      ),
                      errorBorder: OutlineInputBorder(
                        borderRadius: BorderRadius.circular(12),
                        borderSide: const BorderSide(color: WeloColors.error),
                      ),
                      filled: true,
                      fillColor: WeloColors.sovereignElevated.withValues(alpha: 0.392),
                    ),
                    onChanged: (value) {
                      if (value.length == 1 && index < 5) {
                        _focusNodes[index + 1].requestFocus();
                      } else if (value.isEmpty && index > 0) {
                        _focusNodes[index - 1].requestFocus();
                      }
                      if (index == 5 && value.isNotEmpty) {
                        _verifyOtp();
                      }
                    },
                  ),
                );
              }),
            ),
            if (_error != null) ...[
              const SizedBox(height: 12),
              Text(_error!, style: const TextStyle(color: WeloColors.error, fontSize: 12)),
            ],
            const SizedBox(height: 16),
            Text(
              'OTP expires in $_formattedTime',
              style: const TextStyle(color: WeloColors.textLight, fontSize: 12),
            ),
            const SizedBox(height: 20),
            Row(
              children: [
                Expanded(
                  child: OutlinedButton(
                    onPressed: _isLoading ? null : () => Navigator.pop(context),
                    style: OutlinedButton.styleFrom(
                      padding: const EdgeInsets.symmetric(vertical: 14),
                      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                    ),
                    child: const Text('Cancel'),
                  ),
                ),
                const SizedBox(width: 12),
                Expanded(
                  child: ElevatedButton(
                    onPressed: _isLoading ? null : _verifyOtp,
                    style: ElevatedButton.styleFrom(
                      backgroundColor: WeloColors.sovereignOrange,
                      foregroundColor: Colors.white,
                      padding: const EdgeInsets.symmetric(vertical: 14),
                      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                    ),
                    child: _isLoading
                        ? const SizedBox(
                            width: 18,
                            height: 18,
                            child: CircularProgressIndicator(
                              strokeWidth: 2,
                              valueColor: AlwaysStoppedAnimation(Colors.white),
                            ),
                          )
                        : const Text('Verify'),
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
