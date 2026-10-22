<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;

class LocaleController extends Controller
{
    /**
     * Get supported languages
     */
    public function languages()
    {
        return response()->json([
            'languages' => [
                ['code' => 'en', 'name' => 'English', 'native' => 'English'],
            ],
            'default' => 'en',
        ]);
    }

    /**
     * Get translations for a specific language
     */
    public function translations($locale)
    {
        $translations = self::getTranslationSet($locale);

        return response()->json([
            'locale' => $locale,
            'translations' => $translations,
        ]);
    }

    /**
     * Get all supported translation keys
     */
    private static function getTranslationSet($locale): array
    {
        $sets = [
            'en' => [
                'home' => 'Home',
                'search' => 'Search',
                'cart' => 'Cart',
                'orders' => 'Orders',
                'profile' => 'Profile',
                'login' => 'Login',
                'register' => 'Register',
                'checkout' => 'Checkout',
                'add_to_cart' => 'Add to Cart',
                'view_cart' => 'View Cart',
                'place_order' => 'Place Order',
                'track_order' => 'Track Order',
                'order_placed' => 'Order Placed',
                'order_delivered' => 'Order Delivered',
                'delivery_fee' => 'Delivery Fee',
                'total' => 'Total',
                'free_delivery' => 'Free Delivery',
                'veg_only' => 'Veg Only',
                'non_veg' => 'Non Veg',
                'rating' => 'Rating',
                'reviews' => 'Reviews',
                'cuisines' => 'Cuisines',
                'open_now' => 'Open Now',
                'closed' => 'Closed',
                'offers' => 'Offers',
                'favorites' => 'Favorites',
                'addresses' => 'Addresses',
                'payment' => 'Payment',
                'cash_on_delivery' => 'Cash on Delivery',
                'pay_online' => 'Pay Online',
                'scheduled_order' => 'Schedule Order',
                'group_order' => 'Group Order',
                'share_invite' => 'Share Invite',
                'joining' => 'Joining...',
                'min_order' => 'Min Order',
                'prepare_time' => 'Prep Time',
                'mins' => 'mins',
            ],
            'hi' => [
                'home' => 'होम',
                'search' => 'खोजें',
                'cart' => 'कार्ट',
                'orders' => 'ऑर्डर',
                'profile' => 'प्रोफ़ाइल',
                'login' => 'लॉगिन',
                'register' => 'रजिस्टर',
                'checkout' => 'चेकआउट',
                'add_to_cart' => 'कार्ट में जोड़ें',
                'view_cart' => 'कार्ट देखें',
                'place_order' => 'ऑर्डर दें',
                'track_order' => 'ऑर्डर ट्रैक करें',
                'order_placed' => 'ऑर्डर दिया गया',
                'order_delivered' => 'ऑर्डर डिलीवर हो गया',
                'delivery_fee' => 'डिलीवरी शुल्क',
                'total' => 'कुल',
                'free_delivery' => 'मुफ्त डिलीवरी',
                'veg_only' => 'केवल शाकाहारी',
                'non_veg' => 'मांसाहारी',
                'rating' => 'रेटिंग',
                'reviews' => 'समीक्षाएँ',
                'cuisines' => 'व्यंजन',
                'open_now' => 'अभी खुला है',
                'closed' => 'बंद',
                'offers' => 'ऑफ़र',
                'favorites' => 'पसंदीदा',
                'addresses' => 'पते',
                'payment' => 'भुगतान',
                'cash_on_delivery' => 'कैश ऑन डिलीवरी',
                'pay_online' => 'ऑनलाइन भुगतान',
                'scheduled_order' => 'शेड्यूल ऑर्डर',
                'group_order' => 'ग्रुप ऑर्डर',
                'share_invite' => 'आमंत्रण साझा करें',
                'min_order' => 'न्यूनतम ऑर्डर',
                'prepare_time' => 'तैयारी का समय',
                'mins' => 'मिनट',
            ],
        ];

        return $sets[$locale] ?? $sets['en'];
    }
}
