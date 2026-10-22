import 'dart:convert';
import 'dart:io';

import 'package:firebase_messaging/firebase_messaging.dart';
import 'package:flutter/foundation.dart';
import 'package:flutter/widgets.dart';
import 'package:flutter_local_notifications/flutter_local_notifications.dart';
import 'package:flutter_callkit_incoming/flutter_callkit_incoming.dart';
import 'package:flutter_callkit_incoming/entities/call_kit_params.dart';
import 'package:flutter_callkit_incoming/entities/android_params.dart';
import 'package:flutter_callkit_incoming/entities/ios_params.dart';
import 'package:flutter_callkit_incoming/entities/call_event.dart';

import 'package:api/api.dart';

import 'dart:async';
import 'dart:ui';
import 'package:audioplayers/audioplayers.dart';
import 'package:flutter_tts/flutter_tts.dart';
import 'package:vibration/vibration.dart';
import 'package:shared_preferences/shared_preferences.dart';

/// Parse route_info from FCM data and build a human-readable preview.
String? buildRoutePreview(Map<String, dynamic> data) {
  final totalStops = data['total_stops']?.toString();
  if (totalStops == null) return null;
  final stopCount = int.tryParse(totalStops);
  if (stopCount == null || stopCount <= 1) return null;

  final routeInfoRaw = data['route_info']?.toString();
  if (routeInfoRaw != null && routeInfoRaw.isNotEmpty) {
    try {
      final decoded = jsonDecode(routeInfoRaw);
      if (decoded is List && decoded.isNotEmpty) {
        final labels = decoded
            .map((s) => (s is Map ? s['label']?.toString() : s?.toString()) ?? '')
            .where((l) => l.isNotEmpty)
            .toList();
        if (labels.length >= 2) return '${labels.length} stops: ${labels.join(' → ')}';
      }
    } catch (_) {}
  }
  return '$stopCount stops on this route';
}

@pragma('vm:entry-point')
Future<void> firebaseMessagingBackgroundHandler(RemoteMessage message) async {
  debugPrint('FCM background message: ${message.messageId}');
  final data = message.data;
  if (data.isNotEmpty) {
    debugPrint('FCM background data: $data');

    WidgetsFlutterBinding.ensureInitialized();
    DartPluginRegistrant.ensureInitialized();

    // Store notification data to SharedPreferences for cold-start retrieval
    try {
      final prefs = await SharedPreferences.getInstance();
      await prefs.setString('last_notification_data', jsonEncode(data));
    } catch (_) {}
    
    try {
      final String? event = data['event'] ?? data['type'];
      if (event == 'order.new' || event == 'new_order' || event == 'NewOrderForVendor' || event == 'App\\Events\\NewOrderForVendor' || 
          event == 'rider.assigned' || event == 'rider_assigned' || event == 'new_assignment' || event == 'RiderAssigned' || event == 'App\\Events\\RiderAssigned') {
        
        final isVendor = event == 'order.new' || event == 'new_order' || event == 'NewOrderForVendor' || event == 'App\\Events\\NewOrderForVendor';
        final isRiderEvent = event == 'rider.assigned' || event == 'rider_assigned' || event == 'new_assignment' || event == 'RiderAssigned' || event == 'App\\Events\\RiderAssigned';

        // Detect app type from SharedPreferences
        String currentAppType = 'unknown';
        try {
          final prefs = await SharedPreferences.getInstance();
          if (prefs.containsKey('vendor_auth_token')) currentAppType = 'restaurant';
          else if (prefs.containsKey('rider_auth_token')) currentAppType = 'rider';
          else if (prefs.containsKey('auth_token')) currentAppType = 'customer';
        } catch (_) {}

        final isCustomerRiderAssigned = !isVendor && isRiderEvent && currentAppType == 'customer';

        // Customer rider_assigned: show normal notification, NOT CallKit
        if (isCustomerRiderAssigned) {
          final orderId = data['order_id']?.toString() ?? data['id']?.toString() ?? 'Unknown';
          try {
            final plugin = FlutterLocalNotificationsPlugin();
            await plugin.show(
              orderId.hashCode + 500000,
              'Rider Assigned',
              'Your order is on its way!',
              const NotificationDetails(
                android: AndroidNotificationDetails(
                  'general',
                  'General Notifications',
                  importance: Importance.max,
                  priority: Priority.high,
                  playSound: true,
                  enableVibration: true,
                ),
              ),
              payload: 'order:$orderId',
            );
          } catch (_) {}
          return;
        }

        final orderId = data['id']?.toString() ?? data['order_id']?.toString() ?? 'Unknown';
        final orderIdShort = orderId.length > 8 ? orderId.substring(0, 8).toUpperCase() : orderId.toUpperCase();
        
        final notificationsPlugin = FlutterLocalNotificationsPlugin();
        final tts = FlutterTts();
        final alarmPlayer = AudioPlayer();

        try {
          await tts.setLanguage('en-IN');
          await tts.setSpeechRate(0.45);
          await tts.setVolume(1.0);
          await tts.setPitch(1.1);
        } catch (_) {}

        final amount = data['grand_total'] ?? data['total_amount'] ?? data['delivery_fee'] ?? '45';

        // Build informative handle text for rider (distance + ETA + route from FCM data)
        String riderHandle;
        final routePreview = buildRoutePreview(data);
        if (routePreview != null) {
          riderHandle = routePreview;
        } else {
          final riderDistKm = data['rider_to_restaurant_distance_km'];
          final riderEta = data['rider_to_restaurant_eta_min'];
          if (riderDistKm != null || riderEta != null) {
            final distStr = riderDistKm != null
                ? (double.tryParse(riderDistKm.toString()) ?? 0) < 1
                    ? '${((double.tryParse(riderDistKm.toString()) ?? 0) * 1000).round()}m'
                    : '${riderDistKm}km'
                : '';
            final etaStr = riderEta != null ? '~${riderEta}min' : '';
            riderHandle = 'Order #$orderIdShort — $distStr $etaStr. Tap to accept.';
          } else {
            riderHandle = 'Order #$orderIdShort — Tap to accept.';
          }
        }

        // Show CallKit incoming call notification (works when app is killed on Android)
        // This gives WhatsApp-call level full-screen alert on lock screen
        try {
          final config = CallKitParams(
            id: orderId,
            nameCaller: isVendor ? 'New Order Received' : 'New Delivery Request',
            handle: isVendor
                ? 'Order #$orderIdShort — ₹$amount. Tap to accept.'
                : riderHandle,
            appName: isVendor ? 'UtterLazy Partner' : 'UtterLazy Delivery',
            duration: isVendor ? 300000 : 60000,
            extra: {
              'order_id': orderId,
              'type': isVendor ? 'new_order' : 'new_assignment',
              'screen': isVendor ? 'incoming_order' : 'assignment_detail',
              'grand_total': amount.toString(),
              if (data['trip_id'] != null) 'trip_id': data['trip_id'],
              if (data['total_stops'] != null) 'total_stops': data['total_stops'],
            },
            type: 0,
            android: AndroidParams(
              isCustomNotification: true,
              isShowLogo: false,
              ringtonePath: 'asset://assets/sounds/order_ringtone.mp3',
              backgroundColor: '#FF6B00',
              actionColor: '#FF6B00',
              textColor: '#FFFFFF',
              incomingCallNotificationChannelName: isVendor
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
        } catch (e) {
          debugPrint('CallKit incoming failed, falling back to local notification: $e');
          
          // Fallback to local notification if CallKit fails
          if (message.notification == null) {
            final title = isVendor ? 'New Order Received!' : 'New Delivery Request!';
            final body = isVendor
                ? 'Order #$orderIdShort — ₹$amount. Tap to accept.'
                : riderHandle;
            final payload = isVendor ? 'order:$orderId' : 'rider_order:$orderId';

            await notificationsPlugin.show(
              orderId.hashCode,
              title,
              body,
              NotificationDetails(
                android: AndroidNotificationDetails(
                  isVendor ? 'vendor_orders_channel' : 'rider_orders_channel',
                  isVendor ? 'Vendor Order Alerts' : 'Rider Delivery Alerts',
                  importance: Importance.max,
                  priority: Priority.high,
                  playSound: true,
                  enableVibration: true,
                  fullScreenIntent: true,
                  category: AndroidNotificationCategory.call,
                  timeoutAfter: isVendor ? 300000 : 60000,
                ),
              ),
              payload: payload,
            );
          }
        }

        try {
          await alarmPlayer.setReleaseMode(ReleaseMode.loop);
          await alarmPlayer.setVolume(1.0);
          await alarmPlayer.play(AssetSource('sounds/alarm.wav'));
        } catch (_) {}

        try {
          if (await Vibration.hasVibrator() == true) {
            Vibration.vibrate(pattern: [0, 500, 200, 500, 200, 1000], repeat: 0);
          }
        } catch (_) {}

        final spokenText = isVendor
            ? 'New order received! Order number $orderIdShort, amount $amount rupees.'
            : 'New delivery request! Order $orderIdShort. Tap to accept.';
        
        try {
          await tts.speak(spokenText);
        } catch (_) {}

        // Auto-stop alerts after 300 seconds for vendors or 60 seconds for riders
        final timeoutSeconds = isVendor ? 300 : 60;
        Timer(Duration(seconds: timeoutSeconds), () async {
          try {
            await alarmPlayer.stop();
            Vibration.cancel();
            await tts.stop();
            // End the callkit notification
            await FlutterCallkitIncoming.endCall(orderId);
          } catch (_) {}
        });
      }
    } catch (e) {
      debugPrint('FCM background handler error: $e');
    }
  }
}

class FcmService {
  static final FcmService _instance = FcmService._internal();
  factory FcmService() => _instance;
  FcmService._internal();

  final FirebaseMessaging _messaging = FirebaseMessaging.instance;
  final FlutterLocalNotificationsPlugin _localNotifications =
      FlutterLocalNotificationsPlugin();

  String? _currentToken;
  String? _appType;
  ApiClient? _apiClient;
  bool _initialized = false;
  void Function(Map<String, dynamic> data)? _onNotificationTap;

  Future<void> initialize({
    required String appType,
    required ApiClient apiClient,
    void Function(Map<String, dynamic> data)? onNotificationTap,
  }) async {
    if (_initialized) return;
    _initialized = true;
    _appType = appType;
    _apiClient = apiClient;
    _onNotificationTap = onNotificationTap;

    try {
      FirebaseMessaging.onBackgroundMessage(firebaseMessagingBackgroundHandler);

      await _requestPermission();
      await _initLocalNotifications();

      // Listen for CallKit tap events — opens app when user taps the call notification
      FlutterCallkitIncoming.onEvent.listen((event) {
        if (event == null) return;
        if (event.event == Event.actionCallEnded) {
          final extra = event.body;
          if (extra != null && extra['extra'] is Map) {
            final data = Map<String, dynamic>.from(extra['extra']);
            _onNotificationTap?.call(data);
          }
        }
      });

      // Fetch and register token asynchronously to avoid blocking app startup
      _messaging.getToken().then((token) {
        if (token != null) {
          _currentToken = token;
          registerToken(token);
        }
      }).catchError((err) {
        debugPrint('FCM: Failed to get token: $err');
      });

      _messaging.onTokenRefresh.listen((newToken) {
        _currentToken = newToken;
        registerToken(newToken);
      });

      FirebaseMessaging.onMessage.listen((RemoteMessage message) {
        _handleForegroundMessage(message);
      });

      FirebaseMessaging.onMessageOpenedApp.listen((RemoteMessage message) {
        onNotificationTap?.call(message.data);
      });

      _messaging.getInitialMessage().then((initialMessage) async {
        Map<String, dynamic>? data;
        if (initialMessage != null) {
          data = initialMessage.data;
        } else {
          // Cold start: check SharedPreferences for stored notification data
          try {
            final prefs = await SharedPreferences.getInstance();
            final storedData = prefs.getString('last_notification_data');
            if (storedData != null) {
              data = jsonDecode(storedData);
              await prefs.remove('last_notification_data');
            }
          } catch (_) {}
        }
        if (data != null && data.isNotEmpty) {
          Future.delayed(
            const Duration(seconds: 1),
            () => onNotificationTap?.call(data!),
          );
        }
      }).catchError((err) {
        debugPrint('FCM: Failed to get initial message: $err');
      });
    } catch (e) {
      debugPrint('FCM initialization failed: $e');
    }
  }

  Future<void> _requestPermission() async {
    final settings = await _messaging.requestPermission(
      alert: true,
      badge: true,
      sound: true,
      provisional: false,
      criticalAlert: Platform.isIOS,
    );

    if (settings.authorizationStatus == AuthorizationStatus.denied) {
      debugPrint('FCM: Notification permission denied');
    }
  }

  Future<void> _initLocalNotifications() async {
    const androidSettings = AndroidInitializationSettings('@mipmap/launcher_icon');
    const iosSettings = DarwinInitializationSettings(
      requestAlertPermission: false,
      requestBadgePermission: false,
      requestSoundPermission: false,
    );
    const settings = InitializationSettings(
      android: androidSettings,
      iOS: iosSettings,
    );

    await _localNotifications.initialize(
      settings,
      onDidReceiveNotificationResponse: (details) {
        if (details.payload != null) {
          debugPrint('Local notification tap: ${details.payload}');
        }
      },
    );

    if (Platform.isAndroid) {
      final androidPlugin = _localNotifications
          .resolvePlatformSpecificImplementation<AndroidFlutterLocalNotificationsPlugin>();

      final generalSound = const RawResourceAndroidNotificationSound('order_ringtone');
      final alarmSound = const RawResourceAndroidNotificationSound('alarm');

      await androidPlugin?.createNotificationChannel(
        AndroidNotificationChannel('general', 'General Notifications', importance: Importance.high, sound: generalSound),
      );
      await androidPlugin?.createNotificationChannel(
        AndroidNotificationChannel('restaurant_orders', 'New Orders', importance: Importance.max, enableVibration: true, playSound: true, sound: generalSound),
      );
      await androidPlugin?.createNotificationChannel(
        AndroidNotificationChannel('rider_assignments', 'Delivery Assignments', importance: Importance.max, enableVibration: true, playSound: true, sound: alarmSound),
      );
      await androidPlugin?.createNotificationChannel(
        AndroidNotificationChannel(
          'vendor_orders_channel',
          'Vendor Order Alerts',
          description: 'Incoming order alerts with accept/reject',
          importance: Importance.max,
          enableVibration: true,
          playSound: true,
          enableLights: true,
          showBadge: true,
          sound: generalSound,
        ),
      );
      await androidPlugin?.createNotificationChannel(
        AndroidNotificationChannel(
          'rider_orders_channel',
          'Rider Delivery Alerts',
          description: 'New delivery request alerts',
          importance: Importance.max,
          enableVibration: true,
          playSound: true,
          enableLights: true,
          showBadge: true,
          sound: alarmSound,
        ),
      );
    }
  }

  void _handleForegroundMessage(RemoteMessage message) {
    final data = message.data;
    if (data.isNotEmpty) {
      final event = data['event'] ?? data['type'] ?? '';
      // For order events in foreground, trigger the callback directly
      if (event == 'new_order' || event == 'NewOrderForVendor' || 
          event == 'order.new' || event == 'rider.assigned' ||
          event == 'new_assignment' || event == 'rider_assigned') {
        _onNotificationTap?.call(data);
        return;
      }
    }

    final notification = message.notification;
    if (notification == null) return;

    _localNotifications.show(
      notification.hashCode,
      notification.title,
      notification.body,
      NotificationDetails(
        android: AndroidNotificationDetails(
          _getChannelId(),
          _getChannelName(),
          importance: Importance.max,
          priority: Priority.high,
          fullScreenIntent: true,
          icon: 'launcher_icon',
        ),
        iOS: const DarwinNotificationDetails(
          presentAlert: true,
          presentBadge: true,
          presentSound: true,
        ),
      ),
      payload: jsonEncode(message.data),
    );
  }

  String _getChannelId() {
    return switch (_appType) {
      'restaurant' => 'restaurant_orders',
      'rider' => 'rider_assignments',
      _ => 'general',
    };
  }

  String _getChannelName() {
    return switch (_appType) {
      'restaurant' => 'New Orders',
      'rider' => 'Delivery Assignments',
      _ => 'General',
    };
  }

  Future<void> registerToken([String? token]) async {
    final tokenToRegister = token ?? _currentToken;
    if (tokenToRegister == null || _apiClient == null) return;
    try {
      final platform = Platform.isAndroid ? 'android' : 'ios';
      final deviceName = Platform.localHostname;

      final prefs = await SharedPreferences.getInstance();
      String? authToken;
      if (_appType == 'restaurant') {
        authToken = prefs.getString('vendor_auth_token');
      } else if (_appType == 'rider') {
        authToken = prefs.getString('rider_auth_token');
      } else {
        authToken = prefs.getString('auth_token');
      }

      if (authToken == null || authToken.isEmpty) {
        debugPrint('FCM: No auth token found in SharedPreferences, skipping registration');
        return;
      }

      _apiClient!.setToken(authToken);

      await _apiClient!.dio.post('/fcm/token', data: {
        'token': tokenToRegister,
        'platform': platform,
        'app_type': _appType,
        'device_name': deviceName,
      });
      debugPrint('FCM token registered successfully');
    } catch (e) {
      debugPrint('FCM token registration failed: $e');
    }
  }

  Future<void> unregisterToken() async {
    if (_currentToken == null || _apiClient == null) return;
    try {
      await _apiClient!.dio.delete('/fcm/token', data: {'token': _currentToken});
    } catch (e) {
      debugPrint('FCM token unregister failed: $e');
    }
  }

  String? get currentToken => _currentToken;
}
