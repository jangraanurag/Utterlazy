import 'dart:async';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:firebase_core/firebase_core.dart';
import 'package:ui/ui.dart';
import 'package:api/api.dart';
import 'package:core/core.dart';
import 'package:firebase_dynamic_links/firebase_dynamic_links.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'screens/home_screen.dart';
import 'screens/my_complaints_screen.dart';
import 'screens/my_reviews_screen.dart';
import 'screens/order_tracking_from_notification.dart';

final themeModeProvider = StateNotifierProvider<ThemeModeNotifier, ThemeMode>((ref) => ThemeModeNotifier());

class ThemeModeNotifier extends StateNotifier<ThemeMode> {
  ThemeModeNotifier() : super(ThemeMode.light);

  Future<void> load() async {
    final prefs = await SharedPreferences.getInstance();
    final mode = prefs.getString('theme_mode');
    state = mode == 'dark' ? ThemeMode.dark : mode == 'system' ? ThemeMode.system : ThemeMode.light;
  }

  Future<void> setMode(ThemeMode mode) async {
    state = mode;
    final prefs = await SharedPreferences.getInstance();
    await prefs.setString('theme_mode', mode == ThemeMode.dark ? 'dark' : mode == ThemeMode.system ? 'system' : 'light');
  }
}

void main() async {
  WidgetsFlutterBinding.ensureInitialized();
  await Firebase.initializeApp();

  final apiClient = ApiClient();
  await FcmService().initialize(
    appType: 'customer',
    apiClient: apiClient,
    onNotificationTap: (data) {
      final screen = data['screen'];
      final orderId = data['order_id'];
      if (screen == 'order_tracking' && orderId != null) {
        navigatorKey.currentState?.push(
          MaterialPageRoute(builder: (_) => OrderTrackingFromNotification(orderId: orderId)),
        );
      } else if (screen == 'complaint_detail') {
        Future.delayed(const Duration(milliseconds: 300), () {
          navigatorKey.currentState?.push(
            MaterialPageRoute(builder: (_) => const MyComplaintsScreen()),
          );
        });
      } else if (screen == 'review_detail') {
        Future.delayed(const Duration(milliseconds: 300), () {
          navigatorKey.currentState?.push(
            MaterialPageRoute(builder: (_) => const MyReviewsScreen()),
          );
        });
      }
    },
  );

  // ignore: deprecated_member_use
  final initialLink = await FirebaseDynamicLinks.instance.getInitialLink();
  if (initialLink != null) {
    _handleDeepLink(initialLink.link);
  }
  // ignore: deprecated_member_use
  FirebaseDynamicLinks.instance.onLink.listen((dynamicLink) {
    _handleDeepLink(dynamicLink.link);
  }).onError((error) {});

  final container = ProviderContainer();
  await container.read(themeModeProvider.notifier).load();

  runApp(
    UncontrolledProviderScope(
      container: container,
      child: const CustomerApp(),
    ),
  );
}

final GlobalKey<NavigatorState> navigatorKey = GlobalKey<NavigatorState>();

void _handleDeepLink(Uri uri) {
  final path = uri.pathSegments;
  if (path.length >= 2 && navigatorKey.currentContext != null) {
    if (path[0] == 'restaurant') {
    } else if (path[0] == 'order') {
    }
  }
}

class CustomerApp extends ConsumerWidget {
  const CustomerApp({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final authState = ref.watch(authProvider);
    final themeMode = ref.watch(themeModeProvider);

    return MaterialApp(
      title: 'UtterLazy',
      navigatorKey: navigatorKey,
      debugShowCheckedModeBanner: false,
      theme: WeloTheme.lightTheme,
      darkTheme: WeloTheme.darkTheme,
      themeMode: themeMode,
      home: authState.isLoading 
          ? const Scaffold(body: Center(child: CircularProgressIndicator(color: WeloColors.sovereignOrange)))
          : const HomeScreen(),
    );
  }
}

