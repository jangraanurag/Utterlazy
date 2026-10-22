<?php

namespace App\Providers;

use App\Events\NewOrderForVendor;
use App\Events\OrderStatusChanged;
use App\Events\RiderAssigned;
use App\Listeners\BroadcastAdminStats;
use App\Listeners\SendCustomerOrderStatusNotification;
use App\Listeners\SendRiderAssignmentNotification;
use App\Listeners\SendVendorNewOrderNotification;
use App\Models\Order;
use App\Observers\OrderObserver;
use App\Services\Analytics\AnalyticsSink;
use App\Services\Analytics\CityAnalyticsReader;
use App\Services\Analytics\ClickHouseCityAnalytics;
use App\Services\Analytics\ClickHouseQueryClient;
use App\Services\Analytics\FallbackCityAnalytics;
use App\Services\Analytics\MySqlCityAnalytics;
use App\Services\Analytics\ClickHouseHttpSink;
use App\Services\FcmService;
use App\Services\Refunds\RazorpayRefundGateway;
use App\Services\Otp\OtpSender;
use App\Services\Otp\SmsOtpSender;
use App\Services\Sos\SosPartnerNotifier;
use App\Services\Sos\WebhookSosPartnerNotifier;
use App\Services\Refunds\RefundGateway;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(FcmService::class);
        $this->app->bind(RefundGateway::class, RazorpayRefundGateway::class);
        $this->app->bind(AnalyticsSink::class, ClickHouseHttpSink::class);
        $this->app->bind(SosPartnerNotifier::class, WebhookSosPartnerNotifier::class);
        $this->app->bind(OtpSender::class, SmsOtpSender::class);

        $this->app->bind(CityAnalyticsReader::class, function () {
            $mysql = new MySqlCityAnalytics();

            if (! config('services.analytics.enabled')
                || config('services.analytics.command_center_source', 'mysql') !== 'clickhouse') {
                return $mysql;
            }

            return new FallbackCityAnalytics(
                new ClickHouseCityAnalytics(new ClickHouseQueryClient()),
                $mysql,
            );
        });
    }

    public function boot(): void
    {
        Order::observe(OrderObserver::class);

        $this->registerRateLimiters();

        Event::listen(
            NewOrderForVendor::class,
            BroadcastAdminStats::class
        );
        Event::listen(
            OrderStatusChanged::class,
            BroadcastAdminStats::class
        );
        Event::listen(
            RiderAssigned::class,
            BroadcastAdminStats::class
        );
        Event::listen(
            NewOrderForVendor::class,
            SendVendorNewOrderNotification::class
        );
        Event::listen(
            OrderStatusChanged::class,
            SendCustomerOrderStatusNotification::class
        );
        Event::listen(
            RiderAssigned::class,
            SendRiderAssignmentNotification::class
        );

        $this->registerRateLimiters();
    }

    protected function registerRateLimiters(): void
    {
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(120)->by(
                $request->user()?->id ?: $request->ip()
            );
        });

        RateLimiter::for('auth', function (Request $request) {
            return Limit::perMinute(10)->by($request->ip());
        });

        RateLimiter::for('otp', function (Request $request) {
            return Limit::perMinute(3)->by($request->ip());
        });

        RateLimiter::for('payment', function (Request $request) {
            return Limit::perMinute(10)->by(
                $request->user()?->id ?: $request->ip()
            );
        });

        RateLimiter::for('checkout', function (Request $request) {
            return Limit::perMinute(5)->by(
                $request->user()?->id ?: $request->ip()
            );
        });

        RateLimiter::for('admin', function (Request $request) {
            return Limit::perMinute(300)->by(
                $request->user()?->id ?: $request->ip()
            );
        });

        RateLimiter::for('upload', function (Request $request) {
            return Limit::perMinute(30)->by(
                $request->user()?->id ?: $request->ip()
            );
        });
    }
}
