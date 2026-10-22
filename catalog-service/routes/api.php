<?php

use App\Http\Controllers\Api\AccountingReportController;
use App\Http\Controllers\Api\AccountManagerController;
use App\Http\Controllers\Api\AdController;
use App\Http\Controllers\Api\AddressController;
use App\Http\Controllers\Api\AdminController;
use App\Http\Controllers\Api\AdminStaffController;
use App\Http\Controllers\Api\AdminWhatsAppController;
use App\Http\Controllers\Api\AdminWorkforceController;
use App\Http\Controllers\Api\AdvancedSearchController;
use App\Http\Controllers\Api\AnalyticsController;
use App\Http\Controllers\Api\AssistantWebhookController;
use App\Http\Controllers\Api\CommandCenterController;
use App\Http\Controllers\Api\CutoverController;
use App\Http\Controllers\Api\IncidentController;
use App\Http\Controllers\Api\InternalOtpController;
use App\Http\Controllers\Api\InternalAdsController;
use App\Http\Controllers\Api\InternalRecoController;
use App\Http\Controllers\Api\PiiAuditController;
use App\Http\Controllers\Api\PricingParityController;
use App\Http\Controllers\Api\ProjectionController;
use App\Http\Controllers\Api\RefundOpsController;
use App\Http\Controllers\Api\WaCityFloorController;
use App\Http\Controllers\Api\WaPlacementController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BannerController;
use App\Http\Controllers\Api\BogoController;
use App\Http\Controllers\Api\CallController;
use App\Http\Controllers\Api\CampaignController;
use App\Http\Controllers\Api\CartController;
use App\Http\Controllers\Api\ComboOfferController;
use App\Http\Controllers\Api\ComplaintController;
use App\Http\Controllers\Api\CouponController;
use App\Http\Controllers\Api\FavoriteController;
use App\Http\Controllers\Api\FcmTokenController;
use App\Http\Controllers\Api\FinalFeaturesController;
use App\Http\Controllers\Api\FraudController;
use App\Http\Controllers\Api\GiftCardController;
use App\Http\Controllers\Api\GroupOrderController;
use App\Http\Controllers\Api\GstController;
use App\Http\Controllers\Api\HealthController;
use App\Http\Controllers\Api\InventoryController;
use App\Http\Controllers\Api\InvoiceController;
use App\Http\Controllers\Api\KOTController;
use App\Http\Controllers\Api\LocaleController;
use App\Http\Controllers\Api\MealPlanController;
use App\Http\Controllers\Api\MembershipController;
use App\Http\Controllers\Api\MenuItemController;
use App\Http\Controllers\Api\MenuPhotoController;
use App\Http\Controllers\Api\NavigationController;
use App\Http\Controllers\Api\NotificationPreferenceController;
use App\Http\Controllers\Api\OfferController;
use App\Http\Controllers\Api\OpenSearchController;
use App\Http\Controllers\Api\OndcController;
use App\Http\Controllers\Api\OnboardingController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\OrgApprovalController;
use App\Http\Controllers\Api\P1FeaturesController;
use App\Http\Controllers\Api\P2FeaturesController;
use App\Http\Controllers\Api\P3FeaturesController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\PayoutController;
use App\Http\Controllers\Api\POSController;
use App\Http\Controllers\Api\RankedRestaurantController;
use App\Http\Controllers\Api\RatingController;
use App\Http\Controllers\Api\RecommendationController;
use App\Http\Controllers\Api\ReferralController;
use App\Http\Controllers\Api\RemainingFeaturesController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\RestaurantController;
use App\Http\Controllers\Api\ReviewController;
use App\Http\Controllers\Api\RiderController;
use App\Http\Controllers\Api\RiderTrainingController;
use App\Http\Controllers\Api\ScheduledOrderController;
use App\Http\Controllers\Api\ShiftController;
use App\Http\Controllers\Api\SosController;
use App\Http\Controllers\Api\SearchController;
use App\Http\Controllers\Api\SearchHistoryController;
use App\Http\Controllers\Api\SponsoredListingController;
use App\Http\Controllers\Api\StaffController;
use App\Http\Controllers\Api\TrackingController;
use App\Http\Controllers\Api\UploadController;
use App\Http\Controllers\Api\VendorController;
use App\Http\Controllers\Api\VendorPaymentController;
use App\Http\Controllers\Api\VoiceSearchController;
use App\Http\Controllers\Api\WalletController;
use App\Http\Controllers\Api\ReportingController;
use App\Models\SystemConfig;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// ─── EnableX Webhook (no auth) ───
Route::post('/webhooks/enablex', [CallController::class, 'webhook']);

// ─── Razorpay Webhook (no auth, verified via signature) ───
Route::post('/webhooks/razorpay', [PaymentController::class, 'webhook']);

// ─── Voice Assistant Webhooks ───
Route::post('/webhooks/dialogflow', [AssistantWebhookController::class, 'dialogflow']);
Route::post('/webhooks/alexa', [AssistantWebhookController::class, 'alexa']);
Route::post('/assistant/chat', [AssistantWebhookController::class, 'chat']);

// ─── Public Auth Routes ───
Route::post('/auth/register', [AuthController::class, 'register'])->middleware('throttle:auth');
Route::post('/auth/login', [AuthController::class, 'login'])->middleware('throttle:auth');
Route::post('/auth/otp/request', [AuthController::class, 'requestOtp'])->middleware('throttle:otp');
Route::post('/auth/otp/verify', [AuthController::class, 'verifyOtp'])->middleware('throttle:otp');

// ─── Password Reset (via Email OTP) ───
Route::post('/auth/password-reset/request', [AuthController::class, 'requestPasswordReset'])->middleware('throttle:otp');
Route::post('/auth/password-reset/verify', [AuthController::class, 'verifyPasswordResetOtp'])->middleware('throttle:otp');
Route::post('/auth/password-reset/reset', [AuthController::class, 'resetPassword']);

// ─── Public Rider Auth ───
Route::post('/rider/register', [RiderController::class, 'register'])->middleware('throttle:auth');
Route::post('/rider/login', [RiderController::class, 'login'])->middleware('throttle:auth');

// ─── Public Vendor Auth ───
Route::post('/vendor/login', [VendorController::class, 'login'])->middleware('throttle:auth');

// ─── Ranked Restaurants (must be before apiResource to avoid {restaurant} param) ───
Route::get('/restaurants/ranked', [RankedRestaurantController::class, 'index']);
Route::get('/restaurants/ranked/{id}', [RankedRestaurantController::class, 'show']);

// Public city list (vendor onboarding + WhatsApp promotion targeting)
Route::get('/cities-public', [AdminController::class, 'getCities']);

// ─── Public Catalog Routes ───
Route::apiResource('restaurants', RestaurantController::class)->only(['index', 'show']);
Route::get('/global-categories', [RestaurantController::class, 'globalCategories']);
Route::apiResource('menu-items', MenuItemController::class)->only(['index', 'show']);
Route::get('/search', [SearchController::class, 'search']);
Route::get('/search/advanced', [AdvancedSearchController::class, 'search']);
Route::get('/search/autocomplete', [AdvancedSearchController::class, 'autocomplete']);
Route::get('/search/trending', [AdvancedSearchController::class, 'trending']);
Route::get('/search/fast', [OpenSearchController::class, 'search']);

// ─── Voice Search (NLP in any language) ───
Route::post('/search/voice', [VoiceSearchController::class, 'search']);

// ─── Recommendations (Public) ───
Route::get('/recommendations/trending', [RecommendationController::class, 'trending']);
Route::get('/recommendations/ranked-organic', [RecommendationController::class, 'rankedOrganic']);
Route::get('/recommendations/popular-dishes', [RecommendationController::class, 'popularDishes']);
Route::get('/restaurants/{restaurantId}/also-ordered', [RecommendationController::class, 'peopleAlsoOrdered']);

// ─── Multi-language ───
Route::get('/locales', [LocaleController::class, 'languages']);
Route::get('/locales/{locale}/translations', [LocaleController::class, 'translations']);

// ─── Banners (Public) ───
Route::get('/banners', [BannerController::class, 'index']);

// ─── ONDC (signed, disabled by default) ───
Route::prefix('ondc')->middleware('throttle:api')->group(function () {
    Route::post('/search', [OndcController::class, 'search']);
    Route::post('/select', [OndcController::class, 'select']);
    Route::post('/init', [OndcController::class, 'init']);
    Route::post('/confirm', [OndcController::class, 'confirm']);
});

// ─── Health & Monitoring ───
Route::get('/health', [HealthController::class, 'check']);
Route::get('/ready', [HealthController::class, 'ready']);

// ─── Restaurant Onboarding ───
Route::post('/onboarding/apply', [OnboardingController::class, 'apply']);
Route::get('/onboarding/status', [OnboardingController::class, 'status'])->middleware('auth:sanctum');

// ─── Authenticated Customer Routes ───
Route::middleware('auth:sanctum')->group(function () {
    // Auth
    Route::get('/auth/me', [AuthController::class, 'me']);
    Route::patch('/auth/profile', [AuthController::class, 'updateProfile']);
    Route::post('/auth/change-password', [AuthController::class, 'changePassword']);
    Route::post('/auth/logout', [AuthController::class, 'logout']);

    // Orders
    Route::get('/orders', [OrderController::class, 'index']);
    Route::post('/checkout', [OrderController::class, 'checkout'])->middleware(['idempotent', 'throttle:checkout']);
    Route::get('/orders/{order}', [OrderController::class, 'show']);
    Route::patch('/orders/{order}/accept', [OrderController::class, 'acceptOrder']);
    Route::patch('/orders/{order}/status', [OrderController::class, 'updateStatus']);
    Route::post('/orders/{order}/verify-otp', [OrderController::class, 'verifyDeliveryOtp']);
    Route::post('/orders/{order}/rider-arrived', [OrderController::class, 'riderArrived']);
    Route::post('/orders/{order}/customer-unreachable', [OrderController::class, 'customerUnreachable']);
    Route::post('/orders/{order}/pay-online', [OrderController::class, 'payOnline'])->middleware('throttle:10,1');
    Route::post('/orders/{order}/convert-to-cod', [OrderController::class, 'convertToCod'])->middleware('throttle:5,1');
    Route::patch('/orders/{order}/cancel', [OrderController::class, 'cancelOrder']);

    // Order Tracking
    Route::get('/orders/{order}/track', [TrackingController::class, 'track']);

    // Cart
    Route::get('/cart', [CartController::class, 'index']);
    Route::post('/cart/items', [CartController::class, 'addItem']);
    Route::patch('/cart/items/{cartItem}', [CartController::class, 'updateItem']);
    Route::delete('/cart/items/{cartItem}', [CartController::class, 'removeItem']);
    Route::delete('/cart', [CartController::class, 'clear']);

    // Addresses
    Route::get('/addresses', [AddressController::class, 'index']);
    Route::post('/addresses', [AddressController::class, 'store']);
    Route::put('/addresses/{address}', [AddressController::class, 'update']);
    Route::delete('/addresses/{address}', [AddressController::class, 'destroy']);
    Route::patch('/addresses/{address}/default', [AddressController::class, 'setDefault']);

    // Coupons
    Route::get('/coupons/available', [CouponController::class, 'available']);
    Route::post('/coupons/validate', [CouponController::class, 'validate']);

    // Sponsored Ads — customer-facing
    Route::get('/sponsored-results', [AdController::class, 'sponsoredResults']);
    Route::post('/sponsored-impression', [AdController::class, 'logImpression']);
    Route::post('/sponsored-click', [AdController::class, 'logClick']);
    Route::get('/restaurants/{restaurant}/campaigns', [CampaignController::class, 'activeForRestaurant']);
    Route::get('/scratch-card', [CouponController::class, 'getScratchCard']);

    // Ratings
    Route::post('/ratings', [RatingController::class, 'store']);
    Route::get('/restaurants/{restaurantId}/ratings', [RatingController::class, 'index']);
    Route::post('/ratings/rider', [RiderController::class, 'rateDelivery']);
    Route::get('/ratings/pending', [OrderController::class, 'pendingRatings']);

    // Favorites
    Route::get('/favorites', [FavoriteController::class, 'index']);
    Route::post('/favorites/toggle', [FavoriteController::class, 'toggle']);

    // Payments (rate limited to prevent abuse)
    Route::post('/orders/{order}/pay/create', [PaymentController::class, 'createOrder'])->middleware('throttle:payment');
    Route::post('/orders/{order}/pay/verify', [PaymentController::class, 'verify'])->middleware('throttle:payment');
    Route::post('/orders/{order}/pay/refund', [PaymentController::class, 'refund'])->middleware('throttle:payment');

    // File Uploads
    Route::post('/upload', [UploadController::class, 'store'])->middleware('throttle:upload');
    Route::delete('/upload', [UploadController::class, 'destroy'])->middleware('throttle:upload');

    // Personalized Recommendations
    Route::get('/recommendations', [RecommendationController::class, 'index']);
    Route::get('/recommendations/order-again', [RecommendationController::class, 'orderAgain']);

    // Reviews
    Route::get('/my-reviews', [ReviewController::class, 'myReviews']);
    Route::post('/reviews', [ReviewController::class, 'store']);
    Route::post('/reviews/{review}/helpful', [ReviewController::class, 'helpful']);
    Route::post('/reviews/{review}/report', [ReviewController::class, 'report']);

    // Complaints (Customer)
    Route::post('/complaints', [ComplaintController::class, 'store']);
    Route::get('/complaints/my', [ComplaintController::class, 'myComplaints']);

    // Scheduled Orders
    Route::get('/scheduled-orders', [ScheduledOrderController::class, 'index']);
    Route::post('/scheduled-orders', [ScheduledOrderController::class, 'store']);
    Route::patch('/scheduled-orders/{scheduledOrder}/cancel', [ScheduledOrderController::class, 'cancel']);

    // Group Orders
    Route::post('/group-orders', [GroupOrderController::class, 'store']);
    Route::get('/group-orders/{inviteCode}/join', [GroupOrderController::class, 'join']);
    Route::post('/group-orders/{groupOrder}/items', [GroupOrderController::class, 'addItem']);
    Route::get('/group-orders/{groupOrder}/items', [GroupOrderController::class, 'items']);
    Route::post('/group-orders/{groupOrder}/finalize', [GroupOrderController::class, 'finalize']);
    Route::patch('/group-orders/{groupOrder}/cancel', [GroupOrderController::class, 'cancel']);

    // Restaurant Reviews (public, but needs auth for creating)
    Route::get('/restaurants/{restaurantId}/reviews', [ReviewController::class, 'index']);

    // Search History
    Route::get('/search/recent', [SearchHistoryController::class, 'recent']);
    Route::post('/search/recent', [SearchHistoryController::class, 'store']);
    Route::delete('/search/recent', [SearchHistoryController::class, 'clear']);

    // Notification Preferences
    Route::get('/notification-preferences', [NotificationPreferenceController::class, 'index']);
    Route::patch('/notification-preferences', [NotificationPreferenceController::class, 'update']);

    // Invoice
    Route::get('/orders/{order}/invoice', [InvoiceController::class, 'generate']);

    // Vendor Staff
    Route::get('/vendor/staff', [StaffController::class, 'index']);
    Route::post('/vendor/staff', [StaffController::class, 'store']);
    Route::patch('/vendor/staff/{staff}', [StaffController::class, 'update']);
    Route::delete('/vendor/staff/{staff}', [StaffController::class, 'destroy']);

    // Inventory Management (Vendor)
    Route::get('/inventory', [InventoryController::class, 'getItems']);
    Route::post('/inventory', [InventoryController::class, 'createItem']);
    Route::patch('/inventory/{inventoryItem}', [InventoryController::class, 'updateItem']);
    Route::post('/inventory/{inventoryItem}/adjust', [InventoryController::class, 'adjustStock']);
    Route::get('/inventory/dashboard', [InventoryController::class, 'dashboard']);
    Route::post('/inventory/toggle', [InventoryController::class, 'toggleInventory']);

    // Recipe Management
    Route::get('/menu/items/{menuItem}/recipes', [InventoryController::class, 'getRecipes']);
    Route::post('/menu/items/{menuItem}/recipes', [InventoryController::class, 'saveRecipe']);

    // Combo Offers (Vendor)
    Route::get('/vendor/combos', [ComboOfferController::class, 'index']);
    Route::post('/vendor/combos', [ComboOfferController::class, 'store']);
    Route::patch('/vendor/combos/{combo}', [ComboOfferController::class, 'update']);
    Route::delete('/vendor/combos/{combo}', [ComboOfferController::class, 'destroy']);

    // Promotional Offers (Vendor)
    Route::get('/vendor/offers', [OfferController::class, 'index']);
    Route::post('/vendor/offers', [OfferController::class, 'store']);
    Route::patch('/vendor/offers/{offer}', [OfferController::class, 'update']);
    Route::delete('/vendor/offers/{offer}', [OfferController::class, 'destroy']);
    Route::patch('/vendor/offers/{offer}/toggle', [OfferController::class, 'toggleStatus']);
    Route::get('/vendor/offers/{offer}/performance', [OfferController::class, 'performance']);

    // BOGO Discounts
    Route::get('/vendor/bogo', [BogoController::class, 'index']);
    Route::post('/vendor/bogo', [BogoController::class, 'store']);
    Route::patch('/vendor/bogo/{bogoDiscount}', [BogoController::class, 'update']);
    Route::delete('/vendor/bogo/{bogoDiscount}', [BogoController::class, 'destroy']);
    Route::patch('/vendor/bogo/{bogoDiscount}/toggle', [BogoController::class, 'toggle']);

    // Opt-In Campaigns (Vendor)
    Route::get('/vendor/campaigns', [CampaignController::class, 'vendorAvailable']);
    Route::post('/vendor/campaigns/{campaign}/opt-in', [CampaignController::class, 'vendorOptIn']);
    Route::post('/vendor/campaigns/{campaign}/opt-out', [CampaignController::class, 'vendorOptOut']);

    // ─── Account Manager Routes (scoped to assigned restaurants) ───
    Route::prefix('account-manager')->middleware('auth:sanctum')->group(function () {
        Route::get('/restaurants', [AccountManagerController::class, 'myRestaurants']);
        Route::post('/ads', [AccountManagerController::class, 'createAd']);
        Route::post('/offers', [AccountManagerController::class, 'createOffer']);

        Route::get('/my-submissions', [AccountManagerController::class, 'mySubmissions']);

        // Restaurant Approval Flow
        Route::get('/pending', [AccountManagerController::class, 'pendingApprovals']);
        Route::get('/history', [AccountManagerController::class, 'approvalHistory']);
        Route::post('/send-otp', [AccountManagerController::class, 'sendApprovalOtp']);
        Route::post('/verify-otp', [AccountManagerController::class, 'verifyApprovalOtp']);
        Route::post('/reject', [AccountManagerController::class, 'rejectApproval']);
    });

    // Vendor Review Reply
    Route::patch('/vendor/reviews/{review}/reply', [ReviewController::class, 'reply']);

    // Sponsored Listings (Ads)
    Route::get('/vendor/ads', [SponsoredListingController::class, 'index']);
    Route::post('/vendor/ads', [SponsoredListingController::class, 'store']);
    Route::patch('/vendor/ads/{listing}', [SponsoredListingController::class, 'update']);
    Route::delete('/vendor/ads/{listing}', [SponsoredListingController::class, 'destroy']);
    Route::patch('/vendor/ads/{listing}/toggle', [SponsoredListingController::class, 'toggle']);
    Route::get('/vendor/ads/{listing}/performance', [SponsoredListingController::class, 'performance']);
    Route::get('/vendor/ads/recommendations', [SponsoredListingController::class, 'recommendations']);

    // WhatsApp Promotions (paid placements)
    Route::get('/wa-placements', [WaPlacementController::class, 'index']);
    Route::post('/wa-placements', [WaPlacementController::class, 'store']);
    Route::post('/wa-placements/{placement}/pause', [WaPlacementController::class, 'pause']);

    // Vendor Analytics
    Route::get('/vendor/reliability', [VendorController::class, 'reliability']);
    Route::get('/vendor/analytics', [AnalyticsController::class, 'dashboard']);
    Route::get('/restaurants/{restaurantId}/analytics', [AnalyticsController::class, 'trends']);

    // Membership
    Route::get('/membership', [MembershipController::class, 'status']);
    Route::post('/membership/subscribe', [MembershipController::class, 'subscribe']);
    Route::post('/membership/cancel', [MembershipController::class, 'cancel']);

    // Referrals
    Route::get('/referrals/my-code', [ReferralController::class, 'myCode']);
    Route::post('/referrals/apply', [ReferralController::class, 'apply']);

    // Share Tracking
    Route::post('/orders/{order}/share', [OrderController::class, 'shareTracking']);
    Route::get('/reorder-history', [OrderController::class, 'reorderHistory']);
    Route::post('/orders/{order}/reorder', [OrderController::class, 'reorder']);

    // Calls
    Route::post('/orders/{order}/call-rider', [CallController::class, 'callCustomerToRider']);
    Route::post('/orders/{order}/call-restaurant', [CallController::class, 'callCustomerToRestaurant']);

    // Gift Cards
    Route::post('/gift-cards/purchase', [GiftCardController::class, 'purchase']);
    Route::post('/gift-cards/redeem', [GiftCardController::class, 'redeem']);
    Route::get('/gift-cards/balance', [GiftCardController::class, 'balance']);

    // Wallet
    Route::get('/wallet/balance', [WalletController::class, 'index']);
    Route::post('/wallet/deposit/create', [WalletController::class, 'createDepositOrder'])->middleware('throttle:5,1');
    Route::post('/wallet/deposit/verify', [WalletController::class, 'verifyDeposit'])->middleware('throttle:10,1');
    Route::post('/wallet/transfer', [WalletController::class, 'transfer'])->middleware('throttle:5,1');

    // P1 Features: Sachet Pricing
    Route::post('/delivery-passes/purchase', [P1FeaturesController::class, 'purchasePass']);
    Route::get('/delivery-passes/active', [P1FeaturesController::class, 'activePass']);

    // P1 Features: Festivals
    Route::get('/festivals', [P1FeaturesController::class, 'festivals']);

    // P1 Features: Surge Pricing
    Route::get('/surge/current', [P1FeaturesController::class, 'currentSurge']);

    // Meal Plans / Tiffin
    Route::get('/meal-plans', [MealPlanController::class, 'index']);
    Route::get('/meal-plans/{mealPlan}', [MealPlanController::class, 'show']);
    Route::post('/meal-plans/subscribe', [MealPlanController::class, 'subscribe']);
    Route::get('/meal-plans/my/subscriptions', [MealPlanController::class, 'mySubscriptions']);
    Route::post('/meal-plans/subscriptions/{subscription}/pause', [MealPlanController::class, 'pause']);
    Route::post('/meal-plans/subscriptions/{subscription}/resume', [MealPlanController::class, 'resume']);
    Route::post('/meal-plans/subscriptions/{subscription}/cancel', [MealPlanController::class, 'cancel']);
    Route::post('/meal-plans/subscriptions/{subscription}/skip', [MealPlanController::class, 'skipDay']);

    // P2 Features
    Route::post('/catering/request', [P2FeaturesController::class, 'cateringRequest']);
    Route::post('/donate', [P2FeaturesController::class, 'donate']);
    Route::get('/feed', [P2FeaturesController::class, 'feed']);
    Route::post('/feed/post', [P2FeaturesController::class, 'createPost']);
    Route::post('/feed/{post}/like', [P2FeaturesController::class, 'toggleLike']);
    Route::get('/leaderboard', [P2FeaturesController::class, 'leaderboard']);
    Route::get('/referrals/stats', [P2FeaturesController::class, 'referralStats']);
    Route::post('/cart/suggestions', [P2FeaturesController::class, 'cartSuggestions']);

    // P3 Features (R&D / Future)
    Route::get('/ar-menu', [P3FeaturesController::class, 'arModels']);
    Route::get('/kitchen-streams/live', [P3FeaturesController::class, 'liveKitchens']);
    Route::get('/crypto/options', [P3FeaturesController::class, 'cryptoOptions']);
    Route::post('/crypto/payment', [P3FeaturesController::class, 'submitCryptoPayment']);
    Route::get('/nfts', [P3FeaturesController::class, 'myNfts']);
    Route::post('/nfts/mint', [P3FeaturesController::class, 'mintNft']);
    Route::get('/drone-delivery/{orderId}', [P3FeaturesController::class, 'droneStatus']);

    // Remaining Features
    Route::get('/menu/diet-filter', [RemainingFeaturesController::class, 'filterByDiet']);
    Route::get('/menu/{menuItem}/nutrition', [RemainingFeaturesController::class, 'nutritionInfo']);
    Route::post('/price-alerts', [RemainingFeaturesController::class, 'setPriceAlert']);
    Route::get('/price-alerts', [RemainingFeaturesController::class, 'myAlerts']);
    Route::delete('/price-alerts/{alert}', [RemainingFeaturesController::class, 'removeAlert']);
    Route::post('/spin', [RemainingFeaturesController::class, 'spin']);
    Route::post('/pickup', [RemainingFeaturesController::class, 'createPickup']);
    Route::get('/qr-tables/generate', [RemainingFeaturesController::class, 'generateQr']);

    // Final Features
    Route::post('/gallery/upload', [FinalFeaturesController::class, 'uploadPhoto'])->middleware('throttle:upload');
    Route::get('/gallery', [FinalFeaturesController::class, 'gallery']);
    Route::get('/blog', [FinalFeaturesController::class, 'blogPosts']);
    Route::get('/blog/{slug}', [FinalFeaturesController::class, 'blogPost']);
    Route::get('/recipes', [FinalFeaturesController::class, 'recipes']);
    Route::get('/recipes/{id}', [FinalFeaturesController::class, 'recipe']);

    // FCM Token Registration
    Route::post('/fcm/token', [FcmTokenController::class, 'store']);
    Route::delete('/fcm/token', [FcmTokenController::class, 'destroy']);

    // Voice Assistant Linking
    Route::post('/assistant/link', [AssistantWebhookController::class, 'link']);
    Route::delete('/assistant/link', [AssistantWebhookController::class, 'unlink']);
});

// ─── Authenticated Rider Routes ───
Route::prefix('rider')->middleware('auth:sanctum')->group(function () {
    Route::get('/auth/me', [RiderController::class, 'me']);
    Route::patch('/location', [RiderController::class, 'updateLocation']);
    Route::patch('/toggle-online', [RiderController::class, 'toggleOnline']);
    Route::get('/current-assignment', [RiderController::class, 'currentAssignment']);
    Route::post('/sos', [SosController::class, 'trigger']);
    Route::post('/assignment/{assignment}/accept', [RiderController::class, 'acceptAssignment']);
    Route::post('/assignment/{assignment}/reject', [RiderController::class, 'rejectAssignment']);
    Route::get('/earnings', [RiderController::class, 'earnings']);
    Route::get('/earnings/today', [RiderController::class, 'earningsToday']);
    Route::post('/rate-restaurant', [RiderController::class, 'rateRestaurant']);
    Route::get('/pending-restaurant-ratings', [RiderController::class, 'pendingRestaurantRatings']);
    Route::get('/incentives', [RiderController::class, 'incentives']);
    Route::get('/batch-orders', [RiderController::class, 'batchOrders']);

    // Safety Suite
    Route::post('/sos', [RiderController::class, 'sos']);
    Route::post('/sos/resolve', [RiderController::class, 'resolveSos']);
    Route::post('/emergency-contacts', [RiderController::class, 'saveEmergencyContacts']);
    Route::post('/share-ride', [RiderController::class, 'shareRide']);
    Route::post('/zones', [RiderController::class, 'saveZonePreference']);
    Route::get('/zones', [RiderController::class, 'getZonePreference']);
    Route::post('/upi', [RiderController::class, 'saveUpiId']);
    Route::post('/request-payout', [RiderController::class, 'requestInstantPayout']);
    Route::patch('/delivery-mode', [RiderController::class, 'updateDeliveryMode']);

    // P1 Features: EV Charging
    Route::get('/ev-stations', [P1FeaturesController::class, 'evStations']);
    Route::get('/ev-stations/nearby', [P1FeaturesController::class, 'nearestEvStations']);

    // Remaining Features (Rider)
    Route::get('/demand-heatmap', [RemainingFeaturesController::class, 'demandHeatMap']);
    Route::get('/earnings-estimate', [RemainingFeaturesController::class, 'earningsEstimate']);

    // P1 Features: Cash Reconciliation
    Route::get('/cash/reconciliation', [P1FeaturesController::class, 'cashReconciliation']);
    Route::post('/cash/submit', [P1FeaturesController::class, 'submitCash']);

    // Dashboard & History (§2, §3)
    Route::get('/dashboard', [RiderController::class, 'dashboard']);
    Route::get('/history', [RiderController::class, 'history']);
    Route::post('/calculate-pay', [RiderController::class, 'calculatePay']);

    // Payout Cycles (§5)
    Route::get('/payout-cycles', [RiderController::class, 'payoutCycles']);
    Route::get('/payout-cycles/{cycle}', [RiderController::class, 'payoutCycleDetail']);

    // Document Upload + Verification
    Route::post('/documents/upload', [RiderController::class, 'uploadDocument'])->middleware('throttle:upload');
    Route::get('/documents', [RiderController::class, 'documents']);
    Route::post('/documents/reupload', [RiderController::class, 'reuploadDocument'])->middleware('throttle:upload');

    // Training & Onboarding
    Route::get('/training/modules', [RiderTrainingController::class, 'modules']);
    Route::post('/training/modules/{moduleId}/lessons/{lessonId}/complete', [RiderTrainingController::class, 'markLessonComplete']);
    Route::post('/training/modules/{moduleId}/quiz', [RiderTrainingController::class, 'submitQuiz']);
    Route::get('/training/progress', [RiderTrainingController::class, 'progress']);

    // FCM Token
    Route::post('/fcm/token', [FcmTokenController::class, 'store']);
    Route::delete('/fcm/token', [FcmTokenController::class, 'destroy']);

    // Calls
    Route::post('/orders/{order}/call-customer', [CallController::class, 'callRiderToCustomer']);
    Route::post('/orders/{order}/call-restaurant', [CallController::class, 'callRiderToRestaurant']);

    // Proof of Delivery
    Route::post('/orders/{order}/delivery-photo', [OrderController::class, 'uploadDeliveryPhoto'])->middleware('throttle:upload');

    // Navigation
    Route::get('/orders/{order}/navigate/restaurant', [NavigationController::class, 'toRestaurant']);
    Route::get('/orders/{order}/navigate/customer', [NavigationController::class, 'toCustomer']);
});

// ─── Authenticated Vendor Routes ───
Route::prefix('vendor')->middleware('auth:sanctum')->group(function () {
    // Profile
    Route::get('/me', [VendorController::class, 'me']);
    Route::patch('/profile', [VendorController::class, 'updateProfile']);
    Route::patch('/toggle-online', [VendorController::class, 'toggleOnline']);
    Route::patch('/operating-hours', [VendorController::class, 'updateOperatingHours']);
    Route::post('/pause', [VendorController::class, 'pauseOrders']);
    Route::post('/resume', [VendorController::class, 'resumeOrders']);
    Route::post('/offline', [VendorController::class, 'goOffline']);

    // Orders
    Route::get('/orders', [VendorController::class, 'orders']);
    Route::get('/orders/active', [VendorController::class, 'activeOrders']);
    Route::patch('/orders/{order}/accept', [VendorController::class, 'acceptOrder']);
    Route::patch('/orders/{order}/reject', [VendorController::class, 'rejectOrder']);
    Route::patch('/orders/{order}/status', [VendorController::class, 'updateOrderStatus']);
    Route::post('/orders/{order}/help/kitchen-full', [VendorController::class, 'kitchenFull']);
    Route::post('/orders/{order}/help/out-of-stock', [VendorController::class, 'itemOutOfStock']);

    // Menu
    Route::get('/menu', [VendorController::class, 'menu']);
    Route::post('/menu/items', [VendorController::class, 'storeMenuItem']);
    Route::patch('/menu/items/{item}', [VendorController::class, 'updateMenuItem']);
    Route::delete('/menu/items/{item}', [VendorController::class, 'deleteMenuItem']);

    // Add-on groups
    Route::get('/add-on-groups', [VendorController::class, 'addOnGroups']);
    Route::post('/add-on-groups', [VendorController::class, 'storeAddOnGroup']);
    Route::patch('/add-on-groups/{addOnGroup}', [VendorController::class, 'updateAddOnGroup']);
    Route::delete('/add-on-groups/{addOnGroup}', [VendorController::class, 'deleteAddOnGroup']);
    Route::post('/add-on-groups/{addOnGroup}/items', [VendorController::class, 'storeAddOnItem']);
    Route::patch('/add-on-items/{addOnItem}', [VendorController::class, 'updateAddOnItem']);
    Route::delete('/add-on-items/{addOnItem}', [VendorController::class, 'deleteAddOnItem']);

    // Packaging settings
    Route::get('/packaging-settings', [VendorController::class, 'packagingSettings']);
    Route::put('/packaging-settings', [VendorController::class, 'upsertPackagingSettings']);

    Route::post('/menu/categories', [VendorController::class, 'storeCategory']);
    Route::patch('/menu/categories/{category}', [VendorController::class, 'updateCategory']);
    Route::delete('/menu/categories/{category}', [VendorController::class, 'deleteCategory']);

    // Earnings
    Route::get('/earnings', [VendorController::class, 'earnings']);
    Route::get('/earnings/statement', [VendorController::class, 'earningsStatement']);

    // Menu Photo → Digital Menu via AI
    Route::post('/menu/photo/analyze', [MenuPhotoController::class, 'analyze'])->middleware('throttle:upload');
    Route::post('/menu/photo/confirm', [MenuPhotoController::class, 'confirmSave']);

    // POS Integration
    Route::post('/pos/configure', [POSController::class, 'configure']);
    Route::get('/pos/status', [POSController::class, 'status']);
    Route::delete('/pos/disconnect', [POSController::class, 'disconnect']);
    Route::post('/pos/pull-menu', [POSController::class, 'pullMenu']);

    // GST Reports (Vendor) — restaurant determined from auth
    Route::get('/gst/monthly/{year}/{month}', [VendorController::class, 'monthlyGstReport']);
    Route::get('/accounting/report/{year}/{month}', [AccountingReportController::class, 'downloadMonthly']);
    Route::get('/invoices', [InvoiceController::class, 'vendorInvoices']);
    Route::get('/invoices/{invoice}/download', [InvoiceController::class, 'vendorDownloadInvoice']);
    Route::post('/invoices/download-bundle', [InvoiceController::class, 'vendorDownloadBundle']);
    Route::get('/gst/gstr1-report', [InvoiceController::class, 'vendorGstr1Report']);
    Route::get('/payments', [VendorController::class, 'payments']);
    Route::get('/payouts', [PayoutController::class, 'vendorIndex']);
    Route::get('/payouts/{cycle}', [PayoutController::class, 'vendorShow']);

    // Print KOT / Thermal Printer
    Route::get('/orders/{order}/kot', [KOTController::class, 'print']);

    // Complaints (Vendor)
    Route::get('/complaints', [ComplaintController::class, 'index']);
    Route::post('/complaints/{complaint}/respond', [ComplaintController::class, 'respond']);
    Route::post('/complaints/{complaint}/close', [ComplaintController::class, 'close']);

    // Vendor rates rider after pickup
    Route::post('/rate-rider', [VendorController::class, 'rateRider']);
    Route::get('/pending-rider-ratings', [VendorController::class, 'pendingRiderRatings']);

    // FCM Token
    Route::post('/fcm/token', [FcmTokenController::class, 'store']);
    Route::delete('/fcm/token', [FcmTokenController::class, 'destroy']);

    // Meal Plans / Tiffin (Vendor)
    Route::get('/meal-plans/manage', [MealPlanController::class, 'vendorPlans']);
    Route::post('/meal-plans', [MealPlanController::class, 'createPlan']);
    Route::patch('/meal-plans/{mealPlan}', [MealPlanController::class, 'updatePlan']);

    // P1 Features: Staff Shifts (Vendor)
    Route::get('/staff/shifts', [P1FeaturesController::class, 'staffShifts']);
    Route::post('/staff/shifts/{shift}/clock-in', [P1FeaturesController::class, 'clockIn']);
    Route::post('/staff/shifts/{shift}/clock-out', [P1FeaturesController::class, 'clockOut']);

    // P1 Features: Suppliers (Vendor)
    Route::get('/suppliers', [P1FeaturesController::class, 'suppliers']);
    Route::post('/suppliers', [P1FeaturesController::class, 'createSupplier']);

    // Video Menu / Reels
    Route::get('/media/reels', [VendorController::class, 'getReels']);
    Route::post('/media/reel', [VendorController::class, 'saveReel']);

    // P2 Features: Dark Kitchen Brands
    Route::get('/brands', [P2FeaturesController::class, 'brandList']);
    Route::post('/brands', [P2FeaturesController::class, 'createBrand']);

    // Remaining Features (Vendor)
    Route::get('/customers/insights', [RemainingFeaturesController::class, 'customerInsights']);
    Route::get('/competitor-prices', [RemainingFeaturesController::class, 'competitorPrices']);

    // Calls
    Route::post('/orders/{order}/call-customer', [CallController::class, 'callRestaurantToCustomer']);

    // ─── Reporting (Vendor) ───
    Route::get('/reporting/live-tracking', [ReportingController::class, 'vendorLiveTracking']);
    Route::get('/reporting/trend-table', [ReportingController::class, 'vendorTrendTable']);
    Route::post('/reporting/export', [ReportingController::class, 'vendorExportReport']);
});

// ─── Admin Auth (no middleware) ───
Route::post('/admin/auth/login', [AuthController::class, 'adminLogin'])->middleware('throttle:auth');

// ─── Internal service-to-service (shared SERVICE_TOKEN) ───
Route::prefix('internal')->middleware('service.auth')->group(function () {
    Route::get('/wa-reco', [InternalRecoController::class, 'waReco']);
    Route::post('/otp/request', [InternalOtpController::class, 'request']);
    Route::post('/otp/verify', [InternalOtpController::class, 'verify']);
    Route::post('/wa-impression', [InternalAdsController::class, 'impression']);
    Route::post('/wa-click', [InternalAdsController::class, 'click']);
});

// ─── Admin Routes (with auth + role middleware) ───
Route::prefix('admin')->middleware(['auth:sanctum', 'admin.auth', 'throttle:admin'])->group(function () {
    // Command Center (read-only ops view)
    Route::get('/command-center', [CommandCenterController::class, 'index'])
        ->middleware('admin.auth:command_center.view');
    Route::get('/cutover/readiness', [CutoverController::class, 'readiness'])
        ->middleware('admin.auth:command_center.view');
    Route::get('/projection/status', [ProjectionController::class, 'status'])
        ->middleware('admin.auth:orders.view');

    // Staff provisioning (super_admin only)
    Route::get('/staff', [AdminStaffController::class, 'index']);
    Route::post('/staff', [AdminStaffController::class, 'store']);
    Route::post('/staff/{user}/deactivate', [AdminStaffController::class, 'deactivate']);

    // WhatsApp placements + city floors
    Route::get('/wa-placements', [WaPlacementController::class, 'adminIndex'])
        ->middleware('admin.auth:placements.review');
    Route::post('/wa-placements/{placement}/reject', [WaPlacementController::class, 'adminReject'])
        ->middleware('admin.auth:placements.review');
    Route::get('/wa-city-floors', [WaCityFloorController::class, 'index'])
        ->middleware('admin.auth:floors.manage');
    Route::put('/wa-city-floors/{cityId}', [WaCityFloorController::class, 'update'])
        ->middleware('admin.auth:floors.manage');

    // WhatsApp Ops (conversation sessions + handoff assignment)
    Route::get('/whatsapp/sessions', [AdminWhatsAppController::class, 'sessions'])
        ->middleware('admin.auth:wa.sessions.view');
    Route::post('/whatsapp/sessions/{waId}/assign', [AdminWhatsAppController::class, 'assign'])
        ->middleware('admin.auth:wa.assign');

    // Workforce (agent shifts + queue caps)
    Route::get('/workforce', [AdminWorkforceController::class, 'index'])
        ->middleware('admin.auth:queue.assign');
    Route::post('/workforce', [AdminWorkforceController::class, 'store'])
        ->middleware('admin.auth:queue.assign');
    Route::patch('/workforce/{entry}', [AdminWorkforceController::class, 'update'])
        ->middleware('admin.auth:queue.assign');

    Route::get('/configs', [AdminController::class, 'getConfigs'])->middleware('admin.auth:restaurants.update');
    Route::post('/configs', [AdminController::class, 'updateConfigs'])->middleware('admin.auth:restaurants.update');
    Route::post('/configs/membership/toggle', function (Request $r) {
        $v = $r->validate(['enabled' => 'required|boolean']);
        SystemConfig::setValue('membership_enabled', $v['enabled'] ? 'true' : 'false');

        return response()->json(['message' => 'Membership '.($v['enabled'] ? 'enabled' : 'disabled'), 'enabled' => $v['enabled']]);
    })->middleware(['auth:sanctum', 'admin.auth', 'throttle:admin']);

    // Account Manager management
    Route::get('/account-managers', [AccountManagerController::class, 'adminIndex'])->middleware('admin.auth:restaurants.view');
    Route::post('/account-managers', [AccountManagerController::class, 'adminCreate'])->middleware('admin.auth:restaurants.update');
    Route::patch('/account-managers/{user}', [AccountManagerController::class, 'adminUpdate'])->middleware('admin.auth:restaurants.update');
    Route::post('/account-managers/assign', [AccountManagerController::class, 'adminAssignRestaurant'])->middleware('admin.auth:restaurants.update');
    Route::delete('/account-managers/assignments/{assignment}', [AccountManagerController::class, 'adminUnassignRestaurant'])->middleware('admin.auth:restaurants.update');
    Route::get('/account-managers/assignments', [AccountManagerController::class, 'adminAssignments'])->middleware('admin.auth:restaurants.view');
    Route::get('/account-managers/available-restaurants', [AccountManagerController::class, 'adminAvailableRestaurants'])->middleware('admin.auth:restaurants.view');

    Route::get('/rescue-orders', [AdminController::class, 'getRescueOrders'])->middleware('admin.auth:rescue.view');
    Route::post('/riders/{rider}/deactivate', [AdminController::class, 'deactivateRider'])
        ->middleware('admin.auth:riders.documents');
    Route::post('/orders/{order}/food-waste-claim', [AdminController::class, 'createFoodWasteClaim'])
        ->middleware('admin.auth:invoices.manage');
    Route::get('/refunds/pending', [RefundOpsController::class, 'index'])
        ->middleware('admin.auth:payments.manage');
    Route::get('/pricing-parity', [PricingParityController::class, 'summary'])
        ->middleware('admin.auth:payments.view');
    Route::get('/pii-audit', [PiiAuditController::class, 'index'])
        ->middleware('admin.auth:audit.view');
    Route::get('/pii-audit/retention', [PiiAuditController::class, 'retentionRuns'])
        ->middleware('admin.auth:audit.view');
    Route::get('/sos', [SosController::class, 'index'])
        ->middleware('admin.auth:sos.view');
    Route::post('/sos/{sos}/ack', [SosController::class, 'acknowledge'])
        ->middleware('admin.auth:sos.manage');
    Route::post('/sos/{sos}/resolve', [SosController::class, 'resolve'])
        ->middleware('admin.auth:sos.manage');

    Route::get('/shifts', [ShiftController::class, 'index'])
        ->middleware('admin.auth:shifts.view');
    Route::get('/shifts/coverage', [ShiftController::class, 'coverage'])
        ->middleware('admin.auth:shifts.view');
    Route::post('/shifts/rotate', [ShiftController::class, 'rotate'])
        ->middleware('admin.auth:shifts.manage');
    Route::post('/shifts', [ShiftController::class, 'store'])
        ->middleware('admin.auth:shifts.manage');
    Route::post('/shifts/{shift}/activate', [ShiftController::class, 'activate'])
        ->middleware('admin.auth:shifts.manage');
    Route::post('/shifts/{shift}/cancel', [ShiftController::class, 'cancel'])
        ->middleware('admin.auth:shifts.manage');

    Route::get('/incidents', [IncidentController::class, 'index'])
        ->middleware('admin.auth:incidents.view');
    Route::post('/incidents', [IncidentController::class, 'store'])
        ->middleware('admin.auth:incidents.manage');
    Route::post('/incidents/{incident}/ack', [IncidentController::class, 'acknowledge'])
        ->middleware('admin.auth:incidents.manage');
    Route::post('/incidents/{incident}/resolve', [IncidentController::class, 'resolve'])
        ->middleware('admin.auth:incidents.manage');
    Route::post('/incidents/{incident}/assign', [IncidentController::class, 'assign'])
        ->middleware('admin.auth:incidents.manage');
    Route::post('/refunds/{order}/retry', [RefundOpsController::class, 'retry'])
        ->middleware('admin.auth:payments.manage');
    Route::get('/stats', [AdminController::class, 'getStats']);
    Route::get('/live-stats', [AdminController::class, 'liveStats']);
    Route::post('/broadcast-stats', [AdminController::class, 'broadcastStats']);
    Route::get('/order-map', [AdminController::class, 'orderMap']);
    Route::get('/orders', [AdminController::class, 'getAllOrders'])->middleware('admin.auth:orders.view');
    Route::patch('/orders/{order}/status', [AdminController::class, 'adminUpdateStatus'])->middleware('admin.auth:orders.update');
    Route::get('/coupons', [AdminController::class, 'getCoupons'])->middleware('admin.auth:restaurants.update');
    Route::post('/coupons', [AdminController::class, 'storeCoupon'])->middleware('admin.auth:restaurants.update');
    Route::put('/coupons/{coupon}', [AdminController::class, 'updateCoupon'])->middleware('admin.auth:restaurants.update');
    Route::delete('/coupons/{coupon}', [AdminController::class, 'deleteCoupon'])->middleware('admin.auth:restaurants.update');

    // Rider Management
    Route::get('/riders', [AdminController::class, 'getRiders'])->middleware('admin.auth:riders.view');
    Route::post('/riders', [AdminController::class, 'createRider'])->middleware('admin.auth:riders.view');
    Route::patch('/riders/{rider}/verify', [AdminController::class, 'verifyRider'])->middleware('admin.auth:riders.verify');
    Route::get('/riders/{rider}/documents', [AdminController::class, 'getRiderDocuments'])->middleware('admin.auth:riders.documents');
    Route::patch('/riders/{rider}/documents/{document}/approve', [AdminController::class, 'approveRiderDocument'])->middleware('admin.auth:riders.documents');
    Route::patch('/riders/{rider}/documents/{document}/reject', [AdminController::class, 'rejectRiderDocument'])->middleware('admin.auth:riders.documents');
    Route::patch('/riders/{rider}/verify-documents', [AdminController::class, 'verifyRiderDocuments'])->middleware('admin.auth:riders.verify');

    // Rider Payouts
    Route::get('/rider-payouts', [AdminController::class, 'getRiderPayouts'])->middleware('admin.auth:rider-payouts.view');
    Route::post('/rider-payouts/settle', [AdminController::class, 'settlePayouts'])->middleware('admin.auth:rider-payouts.manage');
    Route::get('/rider-payout-cycles', [AdminController::class, 'getRiderPayoutCycles'])->middleware('admin.auth:rider-payouts.view');
    Route::post('/rider-payout-cycles/{cycle}/pay', [AdminController::class, 'payRiderPayoutCycle'])->middleware('admin.auth:rider-payouts.manage');
    Route::get('/assignment-logs', [AdminController::class, 'getAssignmentLogs'])->middleware('admin.auth:riders.view');

    // ─── Org Hierarchy + Approval Workflow Engine (§3–§6, §8) ───
    Route::get('/org-units', [OrgApprovalController::class, 'orgTree'])->middleware('admin.auth:org.view');
    Route::post('/org-units', [OrgApprovalController::class, 'storeOrgUnit'])->middleware('admin.auth:org.manage');
    Route::patch('/org-units/{unit}', [OrgApprovalController::class, 'updateOrgUnit'])->middleware('admin.auth:org.manage');
    Route::delete('/org-units/{unit}', [OrgApprovalController::class, 'deleteOrgUnit'])->middleware('admin.auth:org.manage');
    Route::post('/org-units/{unit}/roles', [OrgApprovalController::class, 'storeOrgRole'])->middleware('admin.auth:org.manage');
    Route::patch('/org-roles/{role}', [OrgApprovalController::class, 'updateOrgRole'])->middleware('admin.auth:org.manage');
    Route::delete('/org-roles/{role}', [OrgApprovalController::class, 'deleteOrgRole'])->middleware('admin.auth:org.manage');
    Route::get('/admin-users', [OrgApprovalController::class, 'adminUsers'])->middleware('admin.auth:org.view');

    Route::get('/approval-thresholds', [OrgApprovalController::class, 'thresholdIndex'])->middleware('admin.auth:org.view');
    Route::get('/approval-thresholds/audit', [OrgApprovalController::class, 'thresholdAudit'])->middleware('admin.auth:org.view');
    Route::post('/approval-thresholds', [OrgApprovalController::class, 'storeThreshold'])->middleware('admin.auth:org.manage');
    Route::patch('/approval-thresholds/{config}', [OrgApprovalController::class, 'updateThreshold'])->middleware('admin.auth:org.manage');
    Route::delete('/approval-thresholds/{config}', [OrgApprovalController::class, 'deleteThreshold'])->middleware('admin.auth:org.manage');

    Route::get('/approvals/my-queue', [OrgApprovalController::class, 'myQueue'])->middleware('admin.auth:approvals.view');
    Route::get('/approvals/sla-dashboard', [OrgApprovalController::class, 'slaDashboard'])->middleware('admin.auth:approvals.view');
    Route::get('/approvals/{workflow}', [OrgApprovalController::class, 'showWorkflow'])->middleware('admin.auth:approvals.view');
    Route::get('/approvals/{workflow}/chain-options', [OrgApprovalController::class, 'chainOptions'])->middleware('admin.auth:approvals.view');
    Route::post('/approvals/{workflow}/approve', [OrgApprovalController::class, 'approve'])->middleware('admin.auth:approvals.act');
    Route::post('/approvals/{workflow}/escalate', [OrgApprovalController::class, 'escalate'])->middleware('admin.auth:approvals.act');
    Route::post('/approvals/{workflow}/send-back', [OrgApprovalController::class, 'sendBack'])->middleware('admin.auth:approvals.act');
    Route::post('/approvals/{workflow}/resubmit', [OrgApprovalController::class, 'resubmit'])->middleware('admin.auth:approvals.act');
    Route::post('/approvals/{workflow}/reject', [OrgApprovalController::class, 'reject'])->middleware('admin.auth:approvals.act');

    Route::get('/finance/vendor-payouts/pending', [OrgApprovalController::class, 'pendingVendorPayouts'])->middleware('admin.auth:rider-payouts.view');
    Route::post('/finance/vendor-payouts/{payment}/initiate-approval', [OrgApprovalController::class, 'originateVendorPayout'])->middleware('admin.auth:approvals.act');
    Route::get('/rider-payout-cycles/pending-approval', [OrgApprovalController::class, 'pendingRiderPayoutCycles'])->middleware('admin.auth:rider-payouts.view');
    Route::post('/rider-payout-cycles/{cycle}/initiate-approval', [OrgApprovalController::class, 'originateRiderPayout'])->middleware('admin.auth:approvals.act');

    Route::get('/rider-kyc/pending', [OrgApprovalController::class, 'pendingKycVerifications'])->middleware('admin.auth:riders.verify');
    Route::post('/rider-kyc/{rider}/initiate-approval', [OrgApprovalController::class, 'originateKycVerification'])->middleware('admin.auth:approvals.act');

    Route::get('/account-management/sponsored-listings/pending', [OrgApprovalController::class, 'pendingSponsoredListings'])->middleware('admin.auth:approvals.act');
    Route::post('/account-management/sponsored-listings/{listing}/initiate-approval', [OrgApprovalController::class, 'originateSponsoredListing'])->middleware('admin.auth:approvals.act');
    Route::get('/account-management/offers/pending', [OrgApprovalController::class, 'pendingOffers'])->middleware('admin.auth:approvals.act');
    Route::post('/account-management/offers/{offer}/initiate-approval', [OrgApprovalController::class, 'originateOffer'])->middleware('admin.auth:approvals.act');

    Route::get('/account-management/assignments/pending', [OrgApprovalController::class, 'pendingRestaurantAssignments'])->middleware('admin.auth:account-manager.view');
    Route::post('/account-management/assignments/initiate-approval', [OrgApprovalController::class, 'originateRestaurantAssignment'])->middleware('admin.auth:approvals.act');

    Route::get('/support/complaint-refunds/pending', [OrgApprovalController::class, 'pendingRefundComplaints'])->middleware('admin.auth:approvals.act');
    Route::post('/support/complaint-refunds/{complaint}/initiate-approval', [OrgApprovalController::class, 'originateComplaintRefund'])->middleware('admin.auth:approvals.act');

    Route::get('/finance/invoices/pending', [OrgApprovalController::class, 'pendingInvoices'])->middleware('admin.auth:invoices.view');
    Route::post('/finance/invoices/{invoice}/initiate-approval', [OrgApprovalController::class, 'originateInvoice'])->middleware('admin.auth:approvals.act');
    Route::get('/finance/recoveries/pending', [OrgApprovalController::class, 'pendingRecoveries'])->middleware('admin.auth:invoices.view');
    Route::post('/finance/recoveries/{recovery}/initiate-approval', [OrgApprovalController::class, 'originateRecovery'])->middleware('admin.auth:approvals.act');

    Route::get('/finance/gst-filings', [OrgApprovalController::class, 'gstFilings'])->middleware('admin.auth:gst.view');
    Route::post('/finance/gst-filings/compute', [OrgApprovalController::class, 'computeGstLiability'])->middleware('admin.auth:gst.view');
    Route::post('/finance/gst-filings/{filing}/initiate-approval', [OrgApprovalController::class, 'originateGstFiling'])->middleware('admin.auth:approvals.act');

    // Onboarding
    Route::get('/onboarding/applications', [OnboardingController::class, 'listApplications'])->middleware('admin.auth:onboarding.view');
    Route::patch('/onboarding/applications/{application}/approve', [OnboardingController::class, 'approve'])->middleware('admin.auth:onboarding.approve');
    Route::patch('/onboarding/applications/{application}/reject', [OnboardingController::class, 'reject'])->middleware('admin.auth:onboarding.reject');

    // Fraud Detection
    Route::get('/fraud/logs', [FraudController::class, 'index'])->middleware('admin.auth:orders.view');
    Route::post('/fraud/logs', [FraudController::class, 'store'])->middleware('admin.auth:orders.view');
    Route::patch('/fraud/logs/{log}/review', [FraudController::class, 'review'])->middleware('admin.auth:orders.view');

    // Global Category Management
    Route::get('/global-categories', [AdminController::class, 'getGlobalCategories'])->middleware('admin.auth:categories.view');
    Route::post('/global-categories', [AdminController::class, 'storeGlobalCategory'])->middleware('admin.auth:categories.manage');
    Route::put('/global-categories/{globalCategory}', [AdminController::class, 'updateGlobalCategory'])->middleware('admin.auth:categories.manage');
    Route::delete('/global-categories/{globalCategory}', [AdminController::class, 'deleteGlobalCategory'])->middleware('admin.auth:categories.manage');

    // Admin Menu Item Mapping
    Route::get('/menu-items', [AdminController::class, 'getMenuItems'])->middleware('admin.auth:menu-items.view');
    Route::put('/menu-items/{menuItem}', [AdminController::class, 'updateMenuItem'])->middleware('admin.auth:menu-items.update');

    // Campaign management
    Route::get('/campaigns', [CampaignController::class, 'adminIndex'])->middleware('admin.auth:restaurants.view');
    Route::post('/campaigns', [CampaignController::class, 'adminStore'])->middleware('admin.auth:restaurants.update');
    Route::put('/campaigns/{campaign}', [CampaignController::class, 'adminUpdate'])->middleware('admin.auth:restaurants.update');

    // GST & Invoice Management (Admin)
    Route::post('/invoices/generate', [InvoiceController::class, 'generateCustom'])->middleware('admin.auth:invoices.manage');
    Route::post('/invoices/auto-generate-weekly', [InvoiceController::class, 'autoGenerateWeekly'])->middleware('admin.auth:invoices.manage');
    Route::get('/invoices/download-bundle', [InvoiceController::class, 'downloadBundle'])->middleware('admin.auth:invoices.view');
    Route::get('/invoices/gstr1-report', [InvoiceController::class, 'gstr1Report'])->middleware('admin.auth:invoices.view');
    Route::get('/invoices', [InvoiceController::class, 'listInvoices'])->middleware('admin.auth:invoices.view');
    Route::get('/invoices/{invoice}', [InvoiceController::class, 'showInvoice'])->middleware('admin.auth:invoices.view');
    Route::patch('/invoices/{invoice}', [InvoiceController::class, 'updateInvoice'])->middleware('admin.auth:invoices.manage');
    Route::patch('/invoices/{invoice}/approve', [InvoiceController::class, 'approveInvoice'])->middleware('admin.auth:invoices.manage');
    Route::patch('/invoices/{invoice}/mark-paid', [InvoiceController::class, 'markInvoicePaid'])->middleware('admin.auth:invoices.manage');
    Route::patch('/invoices/{invoice}/cancel', [InvoiceController::class, 'cancelInvoice'])->middleware('admin.auth:invoices.manage');
    Route::get('/invoices/{invoice}/download-pdf', [InvoiceController::class, 'downloadPdf'])->middleware('admin.auth:invoices.view');

    // Recovery Management (Admin)
    Route::get('/recoveries', [InvoiceController::class, 'listRecoveries'])->middleware('admin.auth:invoices.view');
    Route::post('/recoveries', [InvoiceController::class, 'createRecovery'])->middleware('admin.auth:invoices.manage');
    Route::patch('/recoveries/{recovery}', [InvoiceController::class, 'updateRecovery'])->middleware('admin.auth:invoices.manage');

    // Payment Management (Admin)
    Route::post('/payments/generate-weekly', [VendorPaymentController::class, 'generateWeeklyPayment'])->middleware('admin.auth:payments.manage');
    Route::get('/payments', [VendorPaymentController::class, 'listPayments'])->middleware('admin.auth:payments.view');
    Route::patch('/payments/{id}/approve', [VendorPaymentController::class, 'approvePayment'])->middleware('admin.auth:payments.manage');
    Route::post('/payments/{id}/complete', [VendorPaymentController::class, 'completePayment'])->middleware('admin.auth:payments.manage');

    // ─── Finance: Payout Cycles ───
    Route::get('/payouts/search', [PayoutController::class, 'search'])->middleware('admin.auth:payments.view');
    Route::get('/payouts', [PayoutController::class, 'index'])->middleware('admin.auth:payments.view');
    Route::get('/payouts/{cycle}', [PayoutController::class, 'show'])->middleware('admin.auth:payments.view');
    Route::post('/payouts/generate', [PayoutController::class, 'generate'])->middleware('admin.auth:payments.manage');
    Route::post('/payouts/{cycle}/mark-paid', [PayoutController::class, 'markPaid'])->middleware('admin.auth:payments.manage');
    Route::post('/payouts/{cycle}/deductions', [PayoutController::class, 'addDeduction'])->middleware('admin.auth:payments.manage');
    Route::post('/payouts/{cycle}/additions', [PayoutController::class, 'addAddition'])->middleware('admin.auth:payments.manage');

    // Monthly GST Reports (Admin)
    Route::get('/gst/restaurants', [GstController::class, 'listRestaurantsForGst'])->middleware('admin.auth:gst.view');
    Route::get('/gst/report/{restaurantId}/{year}/{month}', [GstController::class, 'monthlyReport'])->middleware('admin.auth:gst.view');

    // Restaurant Management
    Route::get('/restaurants', [AdminController::class, 'getRestaurants'])->middleware('admin.auth:restaurants.view');
    Route::get('/restaurants/{restaurant}', [AdminController::class, 'getRestaurantDetail'])->middleware('admin.auth:restaurants.view');
    Route::patch('/restaurants/{restaurant}', [AdminController::class, 'updateRestaurant'])->middleware('admin.auth:restaurants.update');

    // Review Moderation
    Route::get('/reviews', [AdminController::class, 'getReviews'])->middleware('admin.auth:reviews.view');

    // Complaint Management
    Route::get('/complaints', [AdminController::class, 'getComplaints'])->middleware('admin.auth:complaints.view');

    // Customer Management
    Route::get('/customers', [AdminController::class, 'getCustomers'])->middleware('admin.auth:customers.view');

    // City Management
    Route::get('/cities', [AdminController::class, 'getCities'])->middleware('admin.auth:cities.view');
    Route::post('/cities', [AdminController::class, 'storeCity'])->middleware('admin.auth:cities.manage');
    Route::put('/cities/{city}', [AdminController::class, 'updateCity'])->middleware('admin.auth:cities.manage');
    Route::delete('/cities/{city}', [AdminController::class, 'deleteCity'])->middleware('admin.auth:cities.manage');

    // Banner Management
    Route::get('/banners', [AdminController::class, 'getBanners'])->middleware('admin.auth:banners.view');
    Route::post('/banners', [AdminController::class, 'storeBanner'])->middleware('admin.auth:banners.manage');
    Route::put('/banners/{banner}', [AdminController::class, 'updateBanner'])->middleware('admin.auth:banners.manage');
    Route::delete('/banners/{banner}', [AdminController::class, 'deleteBanner'])->middleware('admin.auth:banners.manage');

    // Ranking Monitor & Config
    Route::get('/ranking/monitor', [AdminController::class, 'rankingMonitor'])->middleware('admin.auth:ranking.view');
    Route::get('/ranking/config', [AdminController::class, 'getRankingConfig'])->middleware('admin.auth:ranking.view');
    Route::post('/ranking/config', [AdminController::class, 'saveRankingConfig'])->middleware('admin.auth:ranking.manage');
    Route::get('/ads', [SponsoredListingController::class, 'adminIndex'])->middleware('admin.auth:restaurants.view');
    Route::post('/ads/{listing}/approve', [SponsoredListingController::class, 'adminApprove'])->middleware('admin.auth:restaurants.update');
    Route::patch('/ads/{listing}', [SponsoredListingController::class, 'adminUpdate'])->middleware('admin.auth:restaurants.update');

    // Multi-City Expansion Toolkit
    Route::post('/restaurants/clone', [AdminController::class, 'cloneRestaurantConfig'])->middleware('admin.auth:restaurants.update');
    Route::get('/cities/{city}/overview', [AdminController::class, 'cityOverview'])->middleware('admin.auth:cities.view');

    // Reports & Exports (Admin)
    Route::get('/reports/orders', [ReportController::class, 'exportOrders'])->middleware('admin.auth:orders.view');
    Route::get('/reports/revenue', [ReportController::class, 'exportRevenue'])->middleware('admin.auth:earnings.view');
    Route::get('/reports/riders', [ReportController::class, 'exportRiders'])->middleware('admin.auth:riders.view');

    // ─── Reporting Module (Admin) ───
    Route::get('/reporting/live-tracking', [ReportingController::class, 'adminLiveTracking'])->middleware('admin.auth:restaurants.view');
    Route::get('/reporting/trend-table', [ReportingController::class, 'adminTrendTable'])->middleware('admin.auth:restaurants.view');
    Route::get('/reporting/export', [ReportingController::class, 'adminExportReport'])->middleware('admin.auth:restaurants.view');
    Route::post('/reporting/trigger-aggregation', [ReportingController::class, 'adminTriggerAggregation'])->middleware('admin.auth:restaurants.manage');
    Route::get('/reporting/restaurants', [ReportingController::class, 'listReportingRestaurants'])->middleware('admin.auth:restaurants.view');
});
