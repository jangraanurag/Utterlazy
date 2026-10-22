<?php

namespace App\Console\Commands;

use App\Models\Order;
use App\Services\EnableXService;
use App\Services\FcmService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class CallRestaurantReminder extends Command
{
    protected $signature = 'orders:call-restaurant-reminder';

    protected $description = 'Call restaurants that have not accepted orders within 2 min, retry every 30 sec up to 5 min';

    private const MAX_ATTEMPTS = 6;

    private const INITIAL_DELAY_MINUTES = 2;

    private const DEADLINE_MINUTES = 5;

    public function __construct(
        private EnableXService $enableX,
        private FcmService $fcm
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $cutoffStart = now()->subMinutes(self::INITIAL_DELAY_MINUTES);
        $cutoffDeadline = now()->subMinutes(self::DEADLINE_MINUTES);

        $orders = Order::where('status', 'pending')
            ->where('created_at', '<=', $cutoffStart)
            ->where('created_at', '>', $cutoffDeadline)
            ->with('restaurant')
            ->get();

        $count = 0;
        foreach ($orders as $order) {
            $restaurant = $order->restaurant;
            if (! $restaurant || ! $restaurant->phone) {
                continue;
            }

            $cacheKey = "restaurant_call:{$order->id}";
            $attempt = (int) Cache::get($cacheKey, 0);

            if ($attempt >= self::MAX_ATTEMPTS) {
                continue;
            }

            $result = $this->enableX->callDirect($restaurant->phone, [
                'name' => 'restaurant-order-reminder',
                'owner_ref' => $order->id,
                'tts_text' => 'Namaste! Aapke paas UtterLazy par ek naya order aaya hai. Kripya order accept karein.',
                'tts_language' => 'hi-IN',
                'tts_voice' => 'female',
                'custom_data' => [
                    'order_id' => $order->id,
                    'call_type' => 'restaurant_reminder',
                    'attempt' => $attempt + 1,
                ],
            ]);

            if ($result) {
                Cache::put($cacheKey, $attempt + 1, now()->addHours(1));

                $this->fcm->sendToRestaurant($restaurant, [
                    'type' => 'order_reminder_call',
                    'order_id' => $order->id,
                ], 'New Order Reminder', 'Please accept the pending order. A call has been placed.');
            }

            $count++;
        }

        if ($count > 0) {
            Log::info("CallRestaurantReminder: Called {$count} restaurants");
        }

        return Command::SUCCESS;
    }
}
