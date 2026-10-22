import 'dart:async';
import 'dart:convert';
import 'dart:io';
import 'package:flutter/foundation.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

class WebSocketService {
  static final WebSocketService _instance = WebSocketService._internal();
  factory WebSocketService() => _instance;
  WebSocketService._internal();

  final _orderStatusController = StreamController<Map<String, dynamic>>.broadcast();
  final _riderLocationController = StreamController<Map<String, dynamic>>.broadcast();
  final _newOrderController = StreamController<Map<String, dynamic>>.broadcast();
  final _approvalController = StreamController<Map<String, dynamic>>.broadcast();

  Stream<Map<String, dynamic>> get orderStatusStream => _orderStatusController.stream;
  Stream<Map<String, dynamic>> get riderLocationStream => _riderLocationController.stream;
  Stream<Map<String, dynamic>> get newOrderStream => _newOrderController.stream;
  Stream<Map<String, dynamic>> get approvalStream => _approvalController.stream;

  WebSocket? _socket;
  bool _isConnected = false;

  bool get isConnected => _isConnected;

  void connect(String baseUrl, String token) {
    if (_isConnected) return;

    // Convert http(s) to ws(s)
    String wsUrl = baseUrl.replaceAll('https://', 'wss://').replaceAll('http://', 'ws://');
    
    // For Laravel Reverb websocket server
    if (wsUrl.endsWith('/api')) {
      wsUrl = wsUrl.substring(0, wsUrl.length - 4); // Remove '/api' suffix
    }
    wsUrl = '$wsUrl/app/utterlazy_reverb_key_2024'; // Reverb endpoint with app key

    debugPrint('WebSocket: Connecting to $wsUrl');

    try {
      WebSocket.connect(wsUrl, headers: {
        'Authorization': 'Bearer $token',
      }).then((socket) {
        _socket = socket;
        _isConnected = true;
        debugPrint('WebSocket: Connected successfully');

        socket.listen(
          (message) {
            debugPrint('WebSocket received: $message');
            _handleIncomingMessage(message);
          },
          onError: (err) {
            debugPrint('WebSocket error: $err');
            _isConnected = false;
          },
          onDone: () {
            debugPrint('WebSocket closed');
            _isConnected = false;
          },
        );
      }).catchError((err) {
        debugPrint('WebSocket connection failed: $err');
        _isConnected = false;
      });
    } catch (e) {
      debugPrint('WebSocket connection exception: $e');
      _isConnected = false;
    }
  }

  void _handleIncomingMessage(dynamic message) {
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

      if (event == 'order.new' || event == 'App\\Events\\NewOrderForVendor' || event == 'NewOrderForVendor' || event == '.order.new') {
        _newOrderController.add(parsedData);
      } else if (event == 'status.changed' || event == 'App\\Events\\OrderStatusChanged' || event == 'OrderStatusChanged' || event == '.status.changed') {
        _orderStatusController.add(parsedData);
      } else if (event == 'rider.assigned' || event == 'App\\Events\\RiderAssigned' || event == 'RiderAssigned' || event == '.rider.assigned') {
        _orderStatusController.add(parsedData);
      } else if (event == 'location.updated') {
        _riderLocationController.add(parsedData);
      } else if ((event?.contains('approval.pending') ?? false) || event == 'RequiresRestaurantApproval') {
        _approvalController.add(parsedData);
      }
    } catch (e) {
      debugPrint('WebSocket: Error parsing message: $e');
    }
  }

  void subscribeToOrder(String orderId) {
    debugPrint('WebSocket: Subscribed to order.$orderId');
    if (_socket != null && _isConnected) {
      final frame = jsonEncode({
        'event': 'pusher:subscribe',
        'data': {
          'channel': 'order.$orderId'
        }
      });
      _socket!.add(frame);
    }
  }

  void subscribeToRider(String riderId) {
    debugPrint('WebSocket: Subscribed to rider.$riderId');
    if (_socket != null && _isConnected) {
      final frame = jsonEncode({
        'event': 'pusher:subscribe',
        'data': {
          'channel': 'rider.$riderId'
        }
      });
      _socket!.add(frame);
    }
  }

  void subscribeToRestaurantApprovals(String restaurantId) {
    debugPrint('WebSocket: Subscribed to restaurant.$restaurantId.approvals');
    if (_socket != null && _isConnected) {
      final frame = jsonEncode({
        'event': 'pusher:subscribe',
        'data': {
          'channel': 'restaurant.$restaurantId.approvals'
        }
      });
      _socket!.add(frame);
    }
  }

  void subscribeToVendor(String vendorId) {
    debugPrint('WebSocket: Subscribed to vendor.$vendorId');
    if (_socket != null && _isConnected) {
      final frame = jsonEncode({
        'event': 'pusher:subscribe',
        'data': {
          'channel': 'vendor.$vendorId'
        }
      });
      _socket!.add(frame);
    }
  }

  void unsubscribeFromOrder(String orderId) {
    debugPrint('WebSocket: Unsubscribed from order.$orderId');
    if (_socket != null && _isConnected) {
      final frame = jsonEncode({
        'event': 'pusher:unsubscribe',
        'data': {
          'channel': 'order.$orderId'
        }
      });
      _socket!.add(frame);
    }
  }

  void disconnect() {
    _socket?.close();
    _socket = null;
    _isConnected = false;
    debugPrint('WebSocket: Disconnected');
  }

  void dispose() {
    disconnect();
    _orderStatusController.close();
    _riderLocationController.close();
    _newOrderController.close();
    _approvalController.close();
  }

  // Debug simulations for local/offline testing
  void simulateNewOrder(Map<String, dynamic> order) {
    _newOrderController.add(order);
  }

  void simulateOrderStatusUpdate(Map<String, dynamic> status) {
    _orderStatusController.add(status);
  }
}

final webSocketServiceProvider = Provider<WebSocketService>((ref) {
  final service = WebSocketService();
  ref.onDispose(() {
    service.dispose();
  });
  return service;
});
