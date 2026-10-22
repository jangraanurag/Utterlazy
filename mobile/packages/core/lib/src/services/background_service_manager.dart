import 'package:flutter/foundation.dart';
import 'dart:async';
import 'dart:convert';
import 'dart:io';
import 'dart:ui';
import 'package:flutter_background_service/flutter_background_service.dart';
import 'package:flutter_local_notifications/flutter_local_notifications.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'package:geolocator/geolocator.dart';
import 'package:api/api.dart';
import 'package:flutter_tts/flutter_tts.dart';
import 'package:audioplayers/audioplayers.dart';
import 'package:vibration/vibration.dart';

class BackgroundServiceManager {
  static const String vendorOrdersChannelId = 'vendor_orders_channel';
  static const String riderOrdersChannelId = 'rider_orders_channel';
  static const String foregroundChannelId = 'utterlazy_foreground_channel';

  static final FlutterLocalNotificationsPlugin _notificationsPlugin =
      FlutterLocalNotificationsPlugin();

  static bool _isInitialized = false;

  static Future<void> initialize([String? role]) async {
    if (_isInitialized) return;
    _isInitialized = true;

    final service = FlutterBackgroundService();

    const AndroidInitializationSettings initializationSettingsAndroid =
        AndroidInitializationSettings('launcher_icon');
    
    const InitializationSettings initializationSettings = InitializationSettings(
      android: initializationSettingsAndroid,
    );

    await _notificationsPlugin.initialize(initializationSettings);

    final flutterLocalNotificationsPlugin = FlutterLocalNotificationsPlugin();
    
    final androidNotificationImplementation = flutterLocalNotificationsPlugin
        .resolvePlatformSpecificImplementation<
            AndroidFlutterLocalNotificationsPlugin>();

    await androidNotificationImplementation?.createNotificationChannel(const AndroidNotificationChannel(
          vendorOrdersChannelId,
          'Vendor Order Alerts',
          description: 'Alerts you about new incoming orders',
          importance: Importance.max,
          playSound: true,
          enableVibration: true,
        ));

    await androidNotificationImplementation?.createNotificationChannel(const AndroidNotificationChannel(
          riderOrdersChannelId,
          'Rider Order Alerts',
          description: 'Alerts you about new delivery requests',
          importance: Importance.max,
          playSound: true,
          enableVibration: true,
        ));

    await androidNotificationImplementation?.createNotificationChannel(const AndroidNotificationChannel(
          foregroundChannelId,
          'UtterLazy Active Services',
          description: 'Keeps UtterLazy services running in the background',
          importance: Importance.low,
          playSound: false,
          enableVibration: false,
        ));

    final String initialTitle = role == 'rider' ? '🟢 UtterLazy Delivery — Online' : '🟢 UtterLazy Partner — Online';
    final String initialContent = role == 'rider' 
        ? 'Waiting for deliveries' 
        : 'Waiting for orders';

    final List<AndroidForegroundType> foregroundTypes = role == 'rider'
        ? [AndroidForegroundType.location, AndroidForegroundType.dataSync]
        : [AndroidForegroundType.dataSync];

    await service.configure(
      androidConfiguration: AndroidConfiguration(
        onStart: onStart,
        autoStart: true,
        autoStartOnBoot: true,
        isForegroundMode: true,
        notificationChannelId: foregroundChannelId,
        initialNotificationTitle: initialTitle,
        initialNotificationContent: initialContent,
        foregroundServiceNotificationId: 888,
        foregroundServiceTypes: foregroundTypes,
      ),
      iosConfiguration: IosConfiguration(
        autoStart: false,
        onForeground: onStart,
        onBackground: (service) => false,
      ),
    );
  }

  static Future<void> start(String role) async {
    final prefs = await SharedPreferences.getInstance();
    await prefs.setString('bg_service_role', role);
    await initialize(role);
    
    final service = FlutterBackgroundService();
    final isRunning = await service.isRunning();
    if (!isRunning) {
      await service.startService();
    }
  }

  static Future<void> stop() async {
    final service = FlutterBackgroundService();
    final isRunning = await service.isRunning();
    if (isRunning) {
      service.invoke('stopService');
    }
  }

  @pragma('vm:entry-point')
  static Future<void> onStart(ServiceInstance service) async {
    DartPluginRegistrant.ensureInitialized();

    service.on('stopService').listen((event) {
      service.stopSelf();
    });

    final prefs = await SharedPreferences.getInstance();
    await prefs.reload();
    final role = prefs.getString('bg_service_role') ?? 'vendor';

    if (service is AndroidServiceInstance) {
      if (role == 'rider') {
        await service.setForegroundNotificationInfo(
          title: "🟢 UtterLazy Delivery — Online",
          content: "Waiting for deliveries",
        );
      } else {
        await service.setForegroundNotificationInfo(
          title: "🟢 UtterLazy Partner — Online",
          content: "Waiting for orders",
        );
      }
    }
    
    final apiClient = ApiClient();
    final notificationsPlugin = FlutterLocalNotificationsPlugin();
    final Set<String> alertedOrderIds = {};

    // Initialize TTS
    final tts = FlutterTts();
    try {
      await tts.setLanguage('en-IN');
      await tts.setSpeechRate(0.45);
      await tts.setVolume(1.0);
      await tts.setPitch(1.1);
    } catch (_) {}

    // Initialize AudioPlayer
    final alarmPlayer = AudioPlayer();

    Future<void> playAlarmAndVibrate() async {
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
    }

    Future<void> speakOrderDetails(String text) async {
      try {
        await tts.speak(text);
      } catch (_) {}
    }

    Future<void> stopAlerts() async {
      try {
        await alarmPlayer.stop();
        Vibration.cancel();
        await tts.stop();
      } catch (_) {}
    }

    Future<void> showLoudNotification({
      required int id,
      required String title,
      required String body,
      required String channelId,
      String? payload,
    }) async {
      await notificationsPlugin.show(
        id,
        title,
        body,
        NotificationDetails(
          android: AndroidNotificationDetails(
            channelId,
            channelId == vendorOrdersChannelId
                ? 'Vendor Order Alerts'
                : 'Rider Order Alerts',
            channelDescription: channelId == vendorOrdersChannelId
                ? 'Alerts you about new incoming orders'
                : 'Alerts you about new delivery requests',
            importance: Importance.max,
            priority: Priority.high,
            playSound: true,
            enableVibration: true,
            fullScreenIntent: true,
            category: AndroidNotificationCategory.alarm,
          ),
        ),
        payload: payload,
      );
    }

    // Poll every 15 seconds (FCM is primary push, polling is fallback)
    Timer.periodic(const Duration(seconds: 15), (timer) async {
      final isRunning = await FlutterBackgroundService().isRunning();
      if (!isRunning) {
        timer.cancel();
        return;
      }

      if (role == 'vendor') {
        final token = prefs.getString('vendor_auth_token');
        if (token == null || token.isEmpty) return;

        apiClient.setToken(token);
        try {
          final response = await apiClient.dio.get('/vendor/orders/active');
          if (response.data != null && response.data['orders'] != null) {
            final List<dynamic> list = response.data['orders'];
            final List<Order> activeOrders =
                list.map((json) => Order.fromJson(json)).toList();
            
            final pendingOrders =
                activeOrders.where((o) => o.status == 'pending').toList();
            
            if (pendingOrders.isNotEmpty) {
              final newOrder = pendingOrders.first;
              if (!alertedOrderIds.contains(newOrder.id)) {
                alertedOrderIds.add(newOrder.id);
                
                final orderIdShort = newOrder.id.length > 8
                    ? newOrder.id.substring(0, 8).toUpperCase()
                    : newOrder.id.toUpperCase();

                await showLoudNotification(
                  id: newOrder.id.hashCode,
                  title: 'New Order Received!',
                  body: 'Order #$orderIdShort — ₹${newOrder.totalAmount.toInt()}. Tap to accept.',
                  channelId: vendorOrdersChannelId,
                  payload: 'order:${newOrder.id}',
                );

                await playAlarmAndVibrate();
                await speakOrderDetails(
                  'New order received! Order number $orderIdShort, amount ${newOrder.totalAmount.toInt()} rupees.',
                );

                final autoAccept = prefs.getBool('vendor_auto_accept') ?? false;
                if (autoAccept) {
                  await Future.delayed(const Duration(seconds: 2));
                  try {
                    await apiClient.dio.patch('/vendor/orders/${newOrder.id}/accept', data: {'extra_minutes': 0});
                    await stopAlerts();
                    await showLoudNotification(
                      id: newOrder.id.hashCode + 200000,
                      title: 'Order Auto-Accepted!',
                      body: 'Order #$orderIdShort has been accepted automatically.',
                      channelId: vendorOrdersChannelId,
                    );
                  } catch (e) {
                    debugPrint('Vendor auto-accept error: $e');
                  }
                } else {
                  Timer(const Duration(seconds: 8), stopAlerts);
                }
              }
            } else {
              alertedOrderIds.clear();
            }
          }
        } catch (e) {
          debugPrint('Vendor background sync error: $e');
        }
      } else if (role == 'rider') {
        final token = prefs.getString('rider_auth_token');
        if (token == null || token.isEmpty) return;

        apiClient.setToken(token);
        
        // 1. Get location and send to server
        try {
          final permission = await Geolocator.checkPermission();
          if (permission == LocationPermission.always ||
              permission == LocationPermission.whileInUse) {
            Position? position;
            try {
              position = await Geolocator.getCurrentPosition(
                desiredAccuracy: LocationAccuracy.high,
                timeLimit: const Duration(seconds: 4),
              );
            } catch (_) {
              position = await Geolocator.getLastKnownPosition();
            }

            if (position != null) {
              await apiClient.dio.patch('/rider/location', data: {
                'latitude': position.latitude,
                'longitude': position.longitude,
              });
            }
          }
        } catch (e) {
          debugPrint('Rider background location tracking error: $e');
        }

        // 2. Poll for pending assignment
        try {
          final response = await apiClient.dio.get('/rider/current-assignment');
          if (response.data != null && response.data['data'] != null) {
            final assignment = response.data['data'];
            final orderData = assignment['order'];
            if (orderData != null) {
              final orderId = orderData['id'].toString();
              
              if (!alertedOrderIds.contains(orderId)) {
                alertedOrderIds.add(orderId);
                
                final payout = orderData['delivery_fee'] ?? (45.0 + ((orderData['grand_total'] ?? 0) * 0.05));
                final address = orderData['address']?['street'] ?? orderData['delivery_address_text'] ?? 'Customer Address';
                final orderIdShort = orderId.length > 8
                    ? orderId.substring(0, 8).toUpperCase()
                    : orderId.toUpperCase();

                // Distance + ETA info from assignment distance_eta
                final eta = assignment['distance_eta'] ?? <String, dynamic>{};
                final riderDistKm = eta['rider_to_restaurant_distance_km'];
                final riderEta = eta['rider_to_restaurant_eta_min'];
                String distInfo = '';
                if (riderDistKm != null || riderEta != null) {
                  final distStr = riderDistKm != null
                      ? (double.tryParse(riderDistKm.toString()) ?? 0) < 1
                          ? '${((double.tryParse(riderDistKm.toString()) ?? 0) * 1000).round()}m'
                          : '${riderDistKm}km'
                      : '';
                  final etaStr = riderEta != null ? '~${riderEta}min' : '';
                  distInfo = '  •  $distStr $etaStr';
                }

                // Check for multi-order route info from assignment
                final totalStops = assignment['total_stops']?.toString();
                final routeInfoRaw = assignment['route_info'];
                String body;
                if (totalStops != null && int.tryParse(totalStops) != null && (int.tryParse(totalStops) ?? 0) > 1) {
                  body = 'Route: $totalStops stops — Payout ₹${(payout is num ? payout.toInt() : 45)}';
                  if (routeInfoRaw is String && routeInfoRaw.isNotEmpty) {
                    try {
                      final stops = jsonDecode(routeInfoRaw) as List;
                      final labels = stops.map((s) => s['label']?.toString() ?? '').where((l) => l.isNotEmpty).toList();
                      if (labels.isNotEmpty) {
                        body = labels.join(' → ');
                      }
                    } catch (_) {}
                  }
                } else {
                  body = 'Order #$orderIdShort$distInfo — Payout ₹${(payout is num ? payout.toInt() : 45)}. Deliver to $address.';
                }

                await showLoudNotification(
                  id: orderId.hashCode,
                  title: 'New Delivery Request!',
                  body: body,
                  channelId: riderOrdersChannelId,
                  payload: 'rider_order:$orderId',
                );

                await playAlarmAndVibrate();
                await speakOrderDetails(
                  'New delivery request! Order $orderIdShort, payout ${(payout is num ? payout.toInt() : 45)} rupees. Tap to accept.',
                );

                Timer(const Duration(seconds: 15), stopAlerts);
              }
            }
          } else {
            alertedOrderIds.clear();
          }
        } catch (e) {
          debugPrint('Rider background assignment polling error: $e');
        }
      }
    });

    // WebSocket listener for real-time events
    _startWebSocketListener(prefs, apiClient, notificationsPlugin, alertedOrderIds, service, role, playAlarmAndVibrate, speakOrderDetails, showLoudNotification);
  }

  static void _startWebSocketListener(
      SharedPreferences prefs,
      ApiClient apiClient,
      FlutterLocalNotificationsPlugin notificationsPlugin,
      Set<String> alertedOrderIds,
      ServiceInstance service,
      String role,
      Future<void> Function() playAlarmAndVibrate,
      Future<void> Function(String) speakOrderDetails,
      Future<void> Function({required int id, required String title, required String body, required String channelId, String? payload}) showLoudNotification) {
    
    String? token;
    String channelId;
    if (role == 'vendor') {
      token = prefs.getString('vendor_auth_token');
      channelId = vendorOrdersChannelId;
    } else {
      token = prefs.getString('rider_auth_token');
      channelId = riderOrdersChannelId;
    }

    if (token == null || token.isEmpty) return;

    String baseUrl = ApiConfig.baseUrl;
    String wsUrl = baseUrl.replaceAll('https://', 'wss://').replaceAll('http://', 'ws://');
    if (wsUrl.endsWith('/api')) {
      wsUrl = wsUrl.substring(0, wsUrl.length - 4);
    }
    wsUrl = '$wsUrl/app/utterlazy_reverb_key_2024';

    Timer? reconnectTimer;
    WebSocket? webSocket;

    void connect() {
      WebSocket.connect(wsUrl, headers: {
        'Authorization': 'Bearer $token',
      }).then((socket) {
        webSocket = socket;
        debugPrint('Background WebSocket: Connected ($role)');

        // Send subscribe event
        if (role == 'vendor') {
          final vendorId = prefs.getString('vendor_id');
          if (vendorId != null) {
            socket.add(jsonEncode({
              'event': 'pusher:subscribe',
              'data': {'channel': 'vendor.$vendorId'}
            }));
          }
        } else if (role == 'rider') {
          final riderId = prefs.getString('rider_id');
          if (riderId != null) {
            socket.add(jsonEncode({
              'event': 'pusher:subscribe',
              'data': {'channel': 'rider.$riderId'}
            }));
          }
        }

        socket.listen(
          (message) {
            try {
              final data = jsonDecode(message.toString());
              final event = data['event'] as String?;
              final eventData = data['data'];

              Map<String, dynamic> parsedData = {};
              if (eventData is String) {
                parsedData = jsonDecode(eventData) as Map<String, dynamic>;
              } else if (eventData is Map) {
                parsedData = Map<String, dynamic>.from(eventData);
              }

              if (event == 'order.new' ||
                  event == 'App\\Events\\NewOrderForVendor' ||
                  event == 'NewOrderForVendor' ||
                  event == '.order.new') {
                final orderId = parsedData['id']?.toString();
                if (orderId != null && !alertedOrderIds.contains(orderId)) {
                  alertedOrderIds.add(orderId);
                  final amount = parsedData['grand_total'] ?? parsedData['total_amount'] ?? 0.0;
                  final orderIdShort = orderId.length > 8
                      ? orderId.substring(0, 8).toUpperCase()
                      : orderId.toUpperCase();

                  showLoudNotification(
                    id: orderId.hashCode,
                    title: role == 'vendor' ? 'New Order Received!' : 'New Delivery Request!',
                    body: role == 'vendor'
                        ? 'Order #$orderIdShort — ₹${(amount as num).toInt()}. Tap to accept.'
                        : 'Order #$orderIdShort. Tap to accept.',
                    channelId: channelId,
                    payload: role == 'vendor' ? 'order:$orderId' : 'rider_order:$orderId',
                  );

                  playAlarmAndVibrate();
                  speakOrderDetails(
                    role == 'vendor'
                        ? 'New order received! Order number $orderIdShort, amount ${(amount as num).toInt()} rupees.'
                        : 'New delivery request! Order $orderIdShort. Tap to accept.',
                  );

                  Timer(const Duration(seconds: 8), () async {
                    try {
                      await notificationsPlugin.cancel(orderId.hashCode);
                    } catch (_) {}
                  });
                }
              }

              if (event == 'status.changed' ||
                  event == 'App\\Events\\OrderStatusChanged' ||
                  event == 'OrderStatusChanged' ||
                  event == '.status.changed') {
                final orderId = parsedData['order_id']?.toString() ?? parsedData['id']?.toString();
                final status = parsedData['status']?.toString();
                if (orderId != null && status != null) {
                  showLoudNotification(
                    id: orderId.hashCode + 100000,
                    title: 'Order Status Updated',
                    body: 'Order #${orderId.length > 8 ? orderId.substring(0, 8).toUpperCase() : orderId.toUpperCase()} is now $status',
                    channelId: channelId,
                  );
                }
              }

              if (event == 'rider.assigned' ||
                  event == 'App\\Events\\RiderAssigned' ||
                  event == 'RiderAssigned' ||
                  event == '.rider.assigned') {
                if (role == 'rider') {
                  final orderId = parsedData['order_id']?.toString();
                  if (orderId != null && !alertedOrderIds.contains(orderId)) {
                    alertedOrderIds.add(orderId);
                    final riderName = parsedData['rider_name'] ?? 'You';
                    final orderIdShort = orderId.length > 8
                        ? orderId.substring(0, 8).toUpperCase()
                        : orderId.toUpperCase();

                    showLoudNotification(
                      id: orderId.hashCode + 300000,
                      title: 'New Delivery Assignment!',
                      body: 'Order #$orderIdShort assigned to $riderName. Tap to view.',
                      channelId: channelId,
                      payload: 'rider_order:$orderId',
                    );

                    playAlarmAndVibrate();
                    speakOrderDetails(
                      'New delivery assigned! Order $orderIdShort. Tap to accept.',
                    );

                    Timer(const Duration(seconds: 15), () async {
                      try {
                        await notificationsPlugin.cancel(orderId.hashCode + 300000);
                      } catch (_) {}
                    });
                  }
                }
              }
            } catch (e) {
              debugPrint('Background WebSocket message parsing error: $e');
            }
          },
          onError: (err) {
            debugPrint('Background WebSocket error: $err');
            reconnectTimer = Timer(const Duration(seconds: 10), connect);
          },
          onDone: () {
            debugPrint('Background WebSocket closed');
            reconnectTimer = Timer(const Duration(seconds: 10), connect);
          },
        );
      }).catchError((err) {
        debugPrint('Background WebSocket connection failed: $err');
        reconnectTimer = Timer(const Duration(seconds: 10), connect);
      });
    }

    connect();

    service.on('stopService').listen((event) {
      reconnectTimer?.cancel();
      webSocket?.close();
    });
  }
}
