import 'package:flutter/material.dart';
import 'package:quick_actions/quick_actions.dart';

class AppShortcutsService {
  static final QuickActions quickActions = QuickActions();

  static void initialize() {
    quickActions.initialize((shortcutType) {
      final navigatorKey = globalNavigatorKey;
      if (navigatorKey.currentContext == null) return;

      switch (shortcutType) {
        case 'search':
          navigatorKey.currentState?.pushNamed('/search');
          break;
        case 'reorder':
          navigatorKey.currentState?.pushNamed('/orders');
          break;
        case 'favorites':
          navigatorKey.currentState?.pushNamed('/favorites');
          break;
        case 'cart':
          navigatorKey.currentState?.pushNamed('/checkout');
          break;
      }
    });
  }

  static void setShortcuts(bool isLoggedIn) {
    if (isLoggedIn) {
      quickActions.setShortcutItems([
        const ShortcutItem(type: 'search', localizedTitle: 'Search Food', icon: 'search'),
        const ShortcutItem(type: 'reorder', localizedTitle: 'Order Again', icon: 'refresh'),
        const ShortcutItem(type: 'favorites', localizedTitle: 'Favorites', icon: 'heart'),
        const ShortcutItem(type: 'cart', localizedTitle: 'Cart', icon: 'shopping_cart'),
      ]);
    } else {
      quickActions.setShortcutItems([
        const ShortcutItem(type: 'search', localizedTitle: 'Search Food', icon: 'search'),
      ]);
    }
  }
}

final GlobalKey<NavigatorState> globalNavigatorKey = GlobalKey<NavigatorState>();
