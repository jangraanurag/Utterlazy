import 'dart:async';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:firebase_core/firebase_core.dart';
import 'package:ui/ui.dart';
import 'package:api/api.dart';
import 'package:core/core.dart';
import 'screens/login_screen.dart';
import 'screens/dashboard_screen.dart';
import 'screens/orders_screen.dart';
import 'screens/menu_screen.dart';

import 'screens/settings_screen.dart';
import 'screens/inventory_screen.dart';
import 'screens/offers_screen.dart';
import 'screens/combo_offers_screen.dart';
import 'screens/analytics_screen.dart';
import 'screens/staff_management_screen.dart';
import 'screens/online_toggle_screen.dart';
import 'screens/incoming_order_screen.dart';
import 'screens/battery_optimization_guide.dart';
import 'screens/sponsored_listings_screen.dart';
import 'screens/complaints_screen.dart';
import 'screens/reviews_screen.dart';
import 'screens/gst_screen.dart';
import 'screens/approvals_screen.dart';
import 'screens/payouts_screen.dart';
import 'screens/invoices_taxes_screen.dart';
import 'screens/reporting_screen.dart';
import 'providers.dart';

final GlobalKey<NavigatorState> navigatorKey = GlobalKey<NavigatorState>();
final vendorTabProvider = StateProvider<int>((ref) => 0);

void main() async {
  WidgetsFlutterBinding.ensureInitialized();
  await Firebase.initializeApp();

  final apiClient = ApiClient();

  // Fire-and-forget: initialize FCM + background service AFTER runApp
  // to avoid blocking the UI thread (fixes white screen on cold start)
  runApp(const ProviderScope(child: RestaurantApp()));

  // Non-blocking initialization below — never await before runApp
  FcmService().initialize(
    appType: 'restaurant',
    apiClient: apiClient,
    onNotificationTap: (data) {
      final screen = data['screen'];
      final orderId = data['order_id']?.toString() ?? data['id']?.toString();

      final type = data['type'] ?? data['event'] ?? '';

      if (type == 'approval_pending' || screen == 'approvals') {
        navigatorKey.currentState?.pushAndRemoveUntil(
          MaterialPageRoute(builder: (_) => const VendorShell(initialIndex: 16)),
          (route) => false,
        );
      } else if ((screen == 'order_detail' || screen == 'incoming_order') && orderId != null) {
        navigatorKey.currentState?.pushAndRemoveUntil(
          MaterialPageRoute(
            builder: (_) => IncomingOrderScreen(orderId: orderId),
          ),
          (route) => false,
        );
      } else if (screen == 'complaint_detail') {
        navigatorKey.currentState?.pushAndRemoveUntil(
          MaterialPageRoute(builder: (_) => const VendorShell(initialIndex: 1)),
          (route) => false,
        );
        Future.delayed(const Duration(milliseconds: 300), () {
          navigatorKey.currentState?.push(
            MaterialPageRoute(builder: (_) => const ComplaintsScreen()),
          );
        });
      } else if (screen == 'review_detail') {
        navigatorKey.currentState?.pushAndRemoveUntil(
          MaterialPageRoute(builder: (_) => const VendorShell(initialIndex: 1)),
          (route) => false,
        );
        Future.delayed(const Duration(milliseconds: 300), () {
          navigatorKey.currentState?.push(
            MaterialPageRoute(builder: (_) => ReviewsScreen(restaurantId: '')),
          );
        });
      } else {
        navigatorKey.currentState?.pushAndRemoveUntil(
          MaterialPageRoute(builder: (_) => const VendorShell(initialIndex: 1)),
          (route) => false,
        );
      }
    },
  );

  BackgroundServiceManager.start('vendor').catchError((e) {
    debugPrint('BackgroundServiceManager start failed: $e');
  });
}

class RestaurantApp extends ConsumerWidget {
  const RestaurantApp({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final authState = ref.watch(vendorAuthProvider);

    return MaterialApp(
      title: 'UtterLazy Vendor',
      debugShowCheckedModeBanner: false,
      navigatorKey: navigatorKey,
      theme: WeloTheme.lightTheme.copyWith(
        scaffoldBackgroundColor: WeloColors.backgroundLight,
      ),
      home: authState.isLoading
          ? const Scaffold(body: Center(child: CircularProgressIndicator(color: WeloColors.sovereignOrange)))
          : (authState.isAuthenticated ? const VendorShell() : const LoginScreen()),
    );
  }
}

class VendorShell extends ConsumerStatefulWidget {
  final int initialIndex;
  const VendorShell({super.key, this.initialIndex = 0});

  @override
  ConsumerState<VendorShell> createState() => _VendorShellState();
}

class _VendorShellState extends ConsumerState<VendorShell> with WidgetsBindingObserver {
  final _screens = const [
    DashboardScreen(),
    OrdersScreen(),
    MenuScreen(),
    InventoryScreen(),
    OffersScreen(),
    ComboOffersScreen(),
    SponsoredListingsScreen(),
    AnalyticsScreen(),
    StaffManagementScreen(),
    OnlineToggleScreen(),
    PayoutsScreen(),
    InvoicesTaxesScreen(),
    ReportingScreen(),
    GstScreen(),
    SettingsScreen(),
    ApprovalsScreen(),
  ];

  StreamSubscription? _approvalSubscription;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addObserver(this);
    WidgetsBinding.instance.addPostFrameCallback((_) {
      final authState = ref.read(vendorAuthProvider);
      if (authState.token != null) {
        final apiClient = ref.read(apiClientProvider);
        ref.read(webSocketServiceProvider).connect(apiClient.dio.options.baseUrl, authState.token!);
      }
    });
    WidgetsBinding.instance.addPostFrameCallback((_) {
      showBatteryOptimizationGuide(context);
    });
    ref.read(vendorTabProvider.notifier).state = widget.initialIndex;
    _approvalSubscription = ref.read(webSocketServiceProvider).approvalStream.listen((event) {
      final count = ref.read(approvalBadgeProvider);
      ref.read(approvalBadgeProvider.notifier).state = count + 1;
    });
  }

  @override
  void didChangeAppLifecycleState(AppLifecycleState state) {
  }

  @override
  void dispose() {
    WidgetsBinding.instance.removeObserver(this);
    _approvalSubscription?.cancel();
    ref.read(webSocketServiceProvider).disconnect();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final currentIndex = ref.watch(vendorTabProvider);
    return Scaffold(
      body: _screens[currentIndex],
      bottomNavigationBar: Container(
        decoration: const BoxDecoration(
          color: Colors.white,
          boxShadow: [BoxShadow(color: Colors.black12, blurRadius: 10, offset: Offset(0, -2))],
        ),
        child: SafeArea(
          child: SizedBox(
            height: 70,
            child: ListView(
              scrollDirection: Axis.horizontal,
              padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 8),
              children: [
                _buildNavItem(0, Icons.dashboard_rounded, 'Dashboard'),
                _buildNavItem(1, Icons.receipt_long_rounded, 'Orders'),
                _buildNavItem(2, Icons.menu_book_rounded, 'Menu'),
                _buildNavItem(3, Icons.inventory_2_rounded, 'Inventory'),
                _buildNavItem(4, Icons.local_offer_rounded, 'Offers'),
                _buildNavItem(5, Icons.shopping_basket_rounded, 'Combos'),
                _buildNavItem(6, Icons.campaign_rounded, 'Ads'),
                _buildNavItem(7, Icons.analytics_rounded, 'Analytics'),
                _buildNavItem(8, Icons.people_rounded, 'Staff'),
                _buildNavItem(9, Icons.wifi_tethering_rounded, 'Status'),
                _buildNavItem(10, Icons.payments_rounded, 'Payouts'),
                _buildNavItem(11, Icons.description_rounded, 'Invoices'),
                _buildNavItem(12, Icons.bar_chart_rounded, 'Reports'),
                _buildNavItem(13, Icons.description_rounded, 'GST'),
                _buildNavItem(14, Icons.settings_rounded, 'Settings'),
                _buildNavItem(15, Icons.checklist_rounded, 'Approvals'),
              ],
            ),
          ),
        ),
      ),
    );
  }

  Widget _buildNavItem(int index, IconData icon, String label) {
    final currentIndex = ref.watch(vendorTabProvider);
    final isActive = currentIndex == index;
    final badgeCount = index == 15 ? ref.watch(approvalBadgeProvider) : 0;
    return GestureDetector(
      onTap: () {
        if (index == 15) ref.read(approvalBadgeProvider.notifier).state = 0;
        ref.read(vendorTabProvider.notifier).state = index;
      },
      behavior: HitTestBehavior.opaque,
      child: AnimatedContainer(
        duration: const Duration(milliseconds: 200),
        padding: EdgeInsets.symmetric(horizontal: isActive ? 12 : 10, vertical: 6),
        decoration: BoxDecoration(
          color: isActive ? WeloColors.sovereignOrange.withValues(alpha: 0.098) : Colors.transparent,
          borderRadius: BorderRadius.circular(10),
        ),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Stack(
              clipBehavior: Clip.none,
              children: [
                Icon(icon, size: 20, color: isActive ? WeloColors.sovereignOrange : WeloColors.textLight),
                if (badgeCount > 0)
                  Positioned(
                    right: -8,
                    top: -4,
                    child: Container(
                      padding: const EdgeInsets.all(4),
                      decoration: const BoxDecoration(
                        color: WeloColors.error,
                        shape: BoxShape.circle,
                      ),
                      child: Text(
                        badgeCount > 9 ? '9+' : '$badgeCount',
                        style: const TextStyle(color: Colors.white, fontSize: 8, fontWeight: FontWeight.bold),
                      ),
                    ),
                  ),
              ],
            ),
            const SizedBox(height: 2),
            Text(label, style: TextStyle(fontSize: 9, fontWeight: isActive ? FontWeight.bold : FontWeight.normal, color: isActive ? WeloColors.sovereignOrange : WeloColors.textLight)),
          ],
        ),
      ),
    );
  }
}
