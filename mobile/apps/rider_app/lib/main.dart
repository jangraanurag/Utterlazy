import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:firebase_core/firebase_core.dart';
import 'package:ui/ui.dart';
import 'package:api/api.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'screens/rider_home_screen.dart';
import 'screens/rider_profile_screen.dart';
import 'screens/rider_dashboard_screen.dart';
import 'screens/rider_history_screen.dart';
import 'screens/rider_payout_screen.dart';
import 'screens/login_screen.dart';
import 'screens/incoming_delivery_screen.dart';

import 'package:core/core.dart';

final GlobalKey<NavigatorState> navigatorKey = GlobalKey<NavigatorState>();

void main() async {
  WidgetsFlutterBinding.ensureInitialized();
  await Firebase.initializeApp();

  final apiClient = ApiClient();

  // Fire-and-forget: initialize FCM + background service AFTER runApp
  // to avoid blocking the UI thread (fixes white screen on cold start)
  runApp(const ProviderScope(child: RiderApp()));

  // Non-blocking initialization below — never await before runApp
  FcmService().initialize(
    appType: 'rider',
    apiClient: apiClient,
    onNotificationTap: (data) {
      final screen = data['screen'];
      final orderId = data['order_id']?.toString() ?? data['id']?.toString();

      if ((screen == 'assignment_detail' || screen == 'new_assignment') && orderId != null) {
        navigatorKey.currentState?.pushAndRemoveUntil(
          MaterialPageRoute(builder: (_) => IncomingDeliveryScreen(orderId: orderId)),
          (route) => false,
        );
      } else {
        navigatorKey.currentState?.pushAndRemoveUntil(
          MaterialPageRoute(builder: (_) => const RiderShell(initialIndex: 0)),
          (route) => false,
        );
      }
    },
  );

  // Check if rider was previously online and restart background service.
  // This handles auto-restart by WatchdogService when app was killed.
  SharedPreferences.getInstance().then((prefs) {
    final wasOnline = prefs.getBool('rider_is_online') ?? false;
    if (wasOnline) {
      BackgroundServiceManager.start('rider');
    }
  });
}

class RiderApp extends ConsumerWidget {
  const RiderApp({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final authState = ref.watch(riderAuthProvider);

    return MaterialApp(
      title: 'UtterLazy Rider',
      debugShowCheckedModeBanner: false,
      navigatorKey: navigatorKey,
      theme: WeloTheme.lightTheme,
      home: authState.isLoading
          ? const Scaffold(body: Center(child: CircularProgressIndicator(color: WeloColors.sovereignOrange)))
          : (authState.isAuthenticated ? const RiderShell() : const LoginScreen()),
    );
  }
}

class RiderShell extends StatefulWidget {
  final int initialIndex;
  const RiderShell({super.key, this.initialIndex = 0});

  @override
  State<RiderShell> createState() => _RiderShellState();
}

class _RiderShellState extends State<RiderShell> {
  late int _currentIndex = widget.initialIndex;

  final _screens = const [
    RiderDashboardScreen(),
    RiderHistoryScreen(),
    RiderHomeScreen(),
    RiderPayoutScreen(),
    RiderProfileScreen(),
  ];

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      body: _screens[_currentIndex],
      bottomNavigationBar: WeloBottomNav(
        currentIndex: _currentIndex,
        onTap: (i) => setState(() => _currentIndex = i),
        items: const [
          WeloBottomNavItem(icon: Icons.dashboard_rounded, label: 'Dashboard'),
          WeloBottomNavItem(icon: Icons.history_rounded, label: 'History'),
          WeloBottomNavItem(icon: Icons.map_rounded, label: 'Live'),
          WeloBottomNavItem(icon: Icons.account_balance_wallet_rounded, label: 'Payout'),
          WeloBottomNavItem(icon: Icons.person_rounded, label: 'Profile'),
        ],
      ),
    );
  }
}
