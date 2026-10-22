import 'package:home_widget/home_widget.dart';

class HomeWidgetService {
  static const String widgetName = 'UtterlazyOrderStatus';

  static Future<void> initialize() async {
    HomeWidget.widgetClicked.listen(_onWidgetClicked);
  }

  static void _onWidgetClicked(Uri? uri) {
    if (uri?.path == '/order') {
    }
  }

  static Future<void> updateOrderStatus({
    required String orderId,
    required String status,
    required String restaurantName,
    required String eta,
  }) async {
    await HomeWidget.saveWidgetData('${widgetName}_order_id', orderId);
    await HomeWidget.saveWidgetData('${widgetName}_status', status);
    await HomeWidget.saveWidgetData('${widgetName}_restaurant', restaurantName);
    await HomeWidget.saveWidgetData('${widgetName}_eta', eta);
    await HomeWidget.updateWidget(name: widgetName);
  }

  static Future<void> clearOrderStatus() async {
    await HomeWidget.saveWidgetData('${widgetName}_order_id', null);
    await HomeWidget.saveWidgetData('${widgetName}_status', null);
    await HomeWidget.saveWidgetData('${widgetName}_restaurant', null);
    await HomeWidget.saveWidgetData('${widgetName}_eta', null);
    await HomeWidget.updateWidget(name: widgetName);
  }
}
