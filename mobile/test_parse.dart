import 'dart:convert';
import 'package:api/api.dart';

void main() {
  final jsonStr = '''
  {
    "id": "019eef02-afed-7283-97ec-4e4005e780c4",
    "user_id": 1,
    "restaurant_id": "019eeab0-7e98-73de-99d4-ab866115d09c",
    "address_id": 1,
    "delivery_address_text": "gali no 2 ghar no 1, Chowk, Bichla Bazar, Bound Kalan, Krishna Colony, Bhiwani, Haryana, 127021, India, , Bhiwani, 127021",
    "rider_id": null,
    "status": "pending",
    "delivery_otp": null,
    "payment_mode": "cod",
    "payment_status": "pending",
    "paid_at": null,
    "subtotal": "249.00",
    "tax_amount": "11.83",
    "platform_fee": "0.00",
    "net_payout": "0.00",
    "gst_details": null,
    "vendor_discount_amount": "12.45",
    "platform_commission": "37.35",
    "delivery_fee": "27.00",
    "delivery_fee_customer": "16.20",
    "delivery_fee_restaurant": "6.75",
    "delivery_fee_platform": "4.05",
    "grand_total": "264.58",
    "rescue_discount_code": null,
    "coupon_id": null,
    "coupon_discount_amount": "0.00",
    "is_business_order": false,
    "company_name": null,
    "company_gst": null,
    "rider_assigned_at": null,
    "accepted_at": null,
    "prep_time_minutes": 0,
    "prep_deadline_at": null,
    "ready_marked_at": null,
    "rider_arrived_at": null,
    "handover_delay_minutes": null,
    "prep_delay_minutes": null,
    "rider_picked_up_at": null,
    "delivered_at": null,
    "delivery_distance_km": "0.19",
    "delivery_address_lat": "28.7929000",
    "delivery_address_lng": "76.1372000",
    "customer_tip": "0.00",
    "created_at": "2026-06-22T11:06:31.000000Z",
    "updated_at": "2026-06-22T11:06:31.000000Z",
    "rider_assigned": false,
    "rider_name": null,
    "rider_phone": null,
    "rider_distance_to_restaurant": null,
    "rider_arrived": false,
    "handover_deadline_at": null,
    "items": [
      {
        "id": "019eef02-aff5-710c-a706-316ee39e4607",
        "order_id": "019eef02-afed-7283-97ec-4e4005e780c4",
        "menu_item_id": "019eeab0-7eac-729f-9aa7-2ec6f15e6e9b",
        "quantity": 1,
        "unit_price": "249.00",
        "is_mrp": 0,
        "total_price": "249.00",
        "special_instructions": null,
        "created_at": "2026-06-22T11:06:31.000000Z",
        "updated_at": "2026-06-22T11:06:31.000000Z",
        "menu_item": {
          "id": "019eeab0-7eac-729f-9aa7-2ec6f15e6e9b",
          "restaurant_id": "019eeab0-7e98-73de-99d4-ab866115d09c",
          "category_id": null,
          "global_category_id": null,
          "name": "Royal Awadhi Dum Biryani",
          "description": "Aged basmati rice and tender mutton slow-cooked in a sealed copper handi with saffron, mace, and rose water.",
          "price": "249.00",
          "gst_percent": "5.00",
          "hsn_code": null,
          "tax_type": "inclusive",
          "is_mrp": false,
          "image_url": "http://localhost:8001/images/dum_biryani.png",
          "is_available": true,
          "is_bestseller": 0,
          "is_new": 0,
          "preparation_time_minutes": null,
          "spice_level": 0,
          "variants": null,
          "add_ons": null,
          "modifiers": null,
          "created_at": "2026-06-21T14:58:15.000000Z",
          "updated_at": "2026-06-21T14:58:15.000000Z"
        }
      }
    ],
    "user": {
      "id": 1,
      "name": "Anurag",
      "email": "anurag@welo.com",
      "phone": "9999988888",
      "wallet_balance": "5000.00",
      "email_verified_at": null,
      "khaalo_coins": 1240,
      "created_at": "2026-04-29T03:54:44.000000Z",
      "updated_at": "2026-06-21T14:58:16.000000Z"
    },
    "rider": null,
    "restaurant": {
      "id": "019eeab0-7e98-73de-99d4-ab866115d09c",
      "name": "The Saffron Palace",
      "email": "vendor@utterlazy.com",
      "phone": "9896012345",
      "description": "Masters of slow-cooked Dum Biryani and delicate Galouti Kebabs. Authentic Awadhi royal dining.",
      "logo_url": "http://localhost:8001/images/saffron_palace_logo.png",
      "is_active": true,
      "minimum_order_amount": "0.00",
      "delivery_radius_km": "10.00",
      "photos": null,
      "cuisine_type": null,
      "price_for_two": null,
      "is_accepting_orders": true,
      "avg_prep_time_minutes": 25,
      "total_orders_completed": 0,
      "total_revenue": "0.00",
      "commission_rate": 15,
      "delivery_fee_share": 25,
      "last_login_at": "2026-06-22T11:01:54.000000Z",
      "latitude": 28.7942,
      "longitude": 76.1384,
      "address": "Circular Road, near Nehru Park, Bhiwani, Haryana 127021",
      "fssai_number": "12345678901234",
      "gst_number": "06BBBBB2222B2Z2",
      "pan_number": "BBBBB2222B",
      "bank_account_number": null,
      "bank_ifsc": null,
      "bank_name": null,
      "bank_holder_name": null,
      "operating_hours": {
        "open": "12:00",
        "close": "23:30"
      },
      "created_at": "2026-06-21T14:58:15.000000Z",
      "updated_at": "2026-06-22T11:01:54.000000Z"
    }
  }
  ''';

  try {
    final parsed = jsonDecode(jsonStr);
    final order = Order.fromJson(parsed);
    print("SUCCESS: parsed order ID = ${order.id}, totalAmount = ${order.totalAmount}, subtotal = ${order.subtotal}");
    print("itemSummary = ${order.itemSummary}");
  } catch (e, stack) {
    print("ERROR parsing order: \$e");
    print(stack);
  }
}
