import 'package:flutter/foundation.dart';
import 'dart:async';
import 'package:flutter_tts/flutter_tts.dart';
import 'package:audioplayers/audioplayers.dart';
import 'package:vibration/vibration.dart';

class NotificationAlertService {
  static final NotificationAlertService _instance = NotificationAlertService._internal();
  factory NotificationAlertService() => _instance;
  NotificationAlertService._internal();

  final FlutterTts _tts = FlutterTts();
  final AudioPlayer _alarmPlayer = AudioPlayer();
  bool _isAlerting = false;
  bool _ttsReady = false;

  bool get isAlerting => _isAlerting;

  Future<void> initialize() async {
    try {
      await _tts.setLanguage('en-IN');
      await _tts.setSpeechRate(0.45);
      await _tts.setVolume(1.0);
      await _tts.setPitch(1.1);
      _ttsReady = true;
    } catch (e) {
      debugPrint('NotificationAlertService: TTS init error: $e');
      _ttsReady = false;
    }
  }

  Future<void> speak(String text) async {
    if (!_ttsReady) {
      try {
        await _tts.setLanguage('en-IN');
        await _tts.setSpeechRate(0.45);
        await _tts.setVolume(1.0);
        _ttsReady = true;
      } catch (_) {
        return;
      }
    }
    try {
      await _tts.speak(text);
    } catch (e) {
      debugPrint('NotificationAlertService: TTS speak error: $e');
    }
  }

  Future<void> playAlarmSound() async {
    try {
      await _alarmPlayer.setReleaseMode(ReleaseMode.loop);
      await _alarmPlayer.setVolume(1.0);
      await _alarmPlayer.play(AssetSource('sounds/alarm.wav'));
    } catch (e) {
      debugPrint('NotificationAlertService: Alarm sound error: $e');
    }
  }

  Future<void> playOrderSound() async {
    try {
      await _alarmPlayer.setReleaseMode(ReleaseMode.loop);
      await _alarmPlayer.setVolume(1.0);
      await _alarmPlayer.play(AssetSource('sounds/order_ringtone.mp3'));
    } catch (e) {
      debugPrint('NotificationAlertService: Order sound error: $e');
    }
  }

  Future<void> vibrateForAlert() async {
    try {
      if (await Vibration.hasVibrator() == true) {
        Vibration.vibrate(pattern: [0, 500, 200, 500, 200, 1000], repeat: 0);
      }
    } catch (e) {
      debugPrint('NotificationAlertService: Vibration error: $e');
    }
  }

  Future<void> vibrateShort() async {
    try {
      if (await Vibration.hasVibrator() == true) {
        Vibration.vibrate(duration: 500);
      }
    } catch (_) {}
  }

  Future<void> startOrderAlert(String orderId, double amount, {String? address}) async {
    if (_isAlerting) return;
    _isAlerting = true;

    final String details = address != null
        ? 'New order received! Order number ${orderId.substring(0, 8).toUpperCase()}, amount ${amount.toInt()} rupees. Deliver to $address.'
        : 'New order received! Order number ${orderId.substring(0, 8).toUpperCase()}, amount ${amount.toInt()} rupees. Tap to view and accept.';

    await Future.wait([
      playOrderSound(),
      vibrateForAlert(),
      speak(details),
    ]);
  }

  Future<void> startDeliveryAlert(String orderId, double payout, {String? address}) async {
    if (_isAlerting) return;
    _isAlerting = true;

    final String details = address != null
        ? 'New delivery request! Order ${orderId.substring(0, 8).toUpperCase()}, payout ${payout.toInt()} rupees. Deliver to $address. Tap to accept.'
        : 'New delivery request! Payout ${payout.toInt()} rupees. Tap to accept.';

    await Future.wait([
      playAlarmSound(),
      vibrateForAlert(),
      speak(details),
    ]);
  }

  Future<void> startRiderArrivalAlert() async {
    await playAlarmSound();
    await vibrateShort();
    await speak('Rider has arrived at restaurant');
  }

  Future<void> stopAll() async {
    _isAlerting = false;
    try {
      await _alarmPlayer.stop();
      Vibration.cancel();
      await _tts.stop();
    } catch (_) {}
  }

  void dispose() {
    _alarmPlayer.dispose();
    _tts.stop();
  }
}
