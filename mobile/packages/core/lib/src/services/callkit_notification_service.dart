import 'dart:async';

import 'package:flutter_callkit_incoming/entities/android_params.dart';
import 'package:flutter_callkit_incoming/entities/call_kit_params.dart';
import 'package:flutter_callkit_incoming/entities/ios_params.dart';
import 'package:flutter_callkit_incoming/flutter_callkit_incoming.dart';
import 'package:flutter_callkit_incoming/entities/call_event.dart';
import 'package:flutter/foundation.dart';
import 'package:flutter_local_notifications/flutter_local_notifications.dart';

/// Handles WhatsApp-call level incoming order/delivery notifications
/// using flutter_callkit_incoming for full-screen lock-screen alerts.
/// Works even when app is killed on Android.
class CallKitNotificationService {
  static final CallKitNotificationService _instance =
      CallKitNotificationService._internal();
  factory CallKitNotificationService() => _instance;
  CallKitNotificationService._internal();

  bool _initialized = false;
  void Function(Map<String, dynamic> data)? _onCallAccept;
  void Function(Map<String, dynamic> data)? _onCallDecline;
  final FlutterLocalNotificationsPlugin _localNotifications =
      FlutterLocalNotificationsPlugin();

  Future<void> initialize({
    void Function(Map<String, dynamic> data)? onCallAccept,
    void Function(Map<String, dynamic> data)? onCallDecline,
  }) async {
    if (_initialized) return;
    _initialized = true;

    _onCallAccept = onCallAccept;
    _onCallDecline = onCallDecline;

    // Listen for callkit events (accept/decline from lock screen)
    FlutterCallkitIncoming.onEvent.listen((CallEvent? event) {
      if (event == null) return;

      debugPrint('CallKit event: ${event.event}');

      switch (event.event) {
        case Event.actionCallAccept:
          _handleCallAccept(event);
          break;
        case Event.actionCallDecline:
          _handleCallDecline(event);
          break;
        case Event.actionCallEnded:
          _handleCallEnded(event);
          break;
        case Event.actionCallTimeout:
          _handleCallTimeout(event);
          break;
        default:
          break;
      }
    });
  }

  /// Show a full-screen incoming order notification (like WhatsApp voice call)
  Future<void> showIncomingOrderCall({
    required String orderId,
    required String callerName,
    required String amount,
    required String appType, // 'restaurant' or 'rider'
    Map<String, dynamic>? extraData,
  }) async {
    final String callTitle =
        appType == 'restaurant' ? 'New Order Received' : 'New Delivery Request';
    final String callSubtitle = appType == 'restaurant'
        ? 'Order #$callerName — ₹$amount'
        : 'Order #$callerName — Payout ₹$amount';

    final config = CallKitParams(
      id: orderId,
      nameCaller: callTitle,
      handle: callSubtitle,
      avatar: 'https://your-domain.com/assets/logo.png',
      appName: appType == 'restaurant' ? 'UtterLazy Partner' : 'UtterLazy Delivery',
      duration: 30000,
      extra: extraData ?? {},
      type: 0,
      android: AndroidParams(
        isCustomNotification: true,
        isShowLogo: false,
        ringtonePath: 'asset://assets/sounds/order_ringtone.mp3',
        backgroundColor: '#FF6B00',
        actionColor: '#FF6B00',
        textColor: '#FFFFFF',
        incomingCallNotificationChannelName: appType == 'restaurant'
            ? 'New Order Alerts'
            : 'New Delivery Alerts',
        isShowFullLockedScreen: true,
        isImportant: true,
      ),
      ios: IOSParams(
        handleType: 'generic',
        supportsVideo: false,
        maximumCallGroups: 1,
        maximumCallsPerCallGroup: 1,
        audioSessionMode: 'default',
        audioSessionActive: true,
        configureAudioSession: true,
        ringtonePath: 'asset://assets/sounds/order_ringtone.mp3',
      ),
    );

    await FlutterCallkitIncoming.showCallkitIncoming(config);
  }

  /// Show incoming order notification using flutter_local_notifications
  /// for when CallKit is not available (iOS foreground, etc.)
  Future<void> showIncomingOrderLocalNotification({
    required int id,
    required String title,
    required String body,
    required String channelId,
    required String channelName,
    String? payload,
  }) async {
    await _localNotifications.show(
      id,
      title,
      body,
      NotificationDetails(
        android: AndroidNotificationDetails(
          channelId,
          channelName,
          importance: Importance.max,
          priority: Priority.high,
          playSound: true,
          enableVibration: true,
          fullScreenIntent: true,
          category: AndroidNotificationCategory.call,
          timeoutAfter: 60000,
        ),
        iOS: const DarwinNotificationDetails(
          presentAlert: true,
          presentBadge: true,
          presentSound: true,
          interruptionLevel: InterruptionLevel.timeSensitive,
        ),
      ),
      payload: payload,
    );
  }

  /// Cancel a specific incoming call notification
  Future<void> cancelCall(String callId) async {
    await FlutterCallkitIncoming.endCall(callId);
  }

  /// Cancel all incoming call notifications
  Future<void> cancelAllCalls() async {
    await FlutterCallkitIncoming.endAllCalls();
  }

  void _handleCallAccept(CallEvent event) {
    debugPrint('CallKit: Call accepted - ${event.body?.id}');
    final data = event.body?.extra;
    if (data != null && data is Map) {
      final Map<String, dynamic> typedData = Map<String, dynamic>.from(data);
      _onCallAccept?.call(typedData);
    }
  }

  void _handleCallDecline(CallEvent event) {
    debugPrint('CallKit: Call declined - ${event.body?.id}');
    final data = event.body?.extra;
    if (data != null && data is Map) {
      final Map<String, dynamic> typedData = Map<String, dynamic>.from(data);
      _onCallDecline?.call(typedData);
    }
  }

  void _handleCallEnded(CallEvent event) {
    debugPrint('CallKit: Call ended - ${event.body?.id}');
  }

  void _handleCallTimeout(CallEvent event) {
    debugPrint('CallKit: Call timeout - ${event.body?.id}');
  }
}
