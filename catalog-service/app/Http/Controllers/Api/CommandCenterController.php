<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\City;
use App\Models\OpsIncident;
use App\Models\OpsShift;
use App\Models\SosAlert;
use App\Models\Order;
use App\Models\PricingParityCheck;
use App\Models\WaCityFloor;
use App\Models\WaPlacement;
use App\Services\Analytics\CityAnalyticsReader;
use App\Services\Cutover\CutoverGate;
use App\Services\Projection\OrderProjectionService;
use App\Services\OutboxPublisher;

class CommandCenterController extends Controller
{
    /**
     * Read-only city operations overview.
     *
     * Per-city ORDER metrics become available in Wave 2 when orders carry
     * `city_id`. Until then order exceptions are global; WhatsApp placement
     * health and event-bus lag are per city where the data exists.
     */
    public function index(OutboxPublisher $publisher, CityAnalyticsReader $analytics, CutoverGate $cutover, OrderProjectionService $projection): \Illuminate\Http\JsonResponse
    {
        $unacceptedOver3Min = Order::where('status', 'pending')
            ->where('created_at', '<', now()->subMinutes(3))
            ->count();

        $readyNoRiderOver8Min = Order::where('status', 'ready_for_pickup')
            ->whereNull('rider_id')
            ->where('ready_marked_at', '<', now()->subMinutes(8))
            ->count();

        $paymentStuck = Order::where('payment_mode', 'prepaid')
            ->where('payment_status', 'pending')
            ->where('created_at', '<', now()->subMinutes(30))
            ->count();

        $shiftsNow = OpsShift::whereIn('status', ['scheduled', 'active'])
            ->where('starts_at', '<=', now())
            ->where('ends_at', '>', now());
        $onShiftAgents = (clone $shiftsNow)->distinct('user_id')->count('user_id');
        $agentsByCity = (clone $shiftsNow)
            ->whereNotNull('city_id')
            ->selectRaw('city_id, COUNT(DISTINCT user_id) AS agents')
            ->groupBy('city_id')
            ->pluck('agents', 'city_id');

        $openSos = SosAlert::whereIn('status', ['open', 'ack'])->count();

        $incidentsOpen = OpsIncident::whereIn('status', ['open', 'ack'])->count();
        $incidentsSev1 = OpsIncident::whereIn('status', ['open', 'ack'])->where('severity', 'sev1')->count();
        $incidentBreaches = OpsIncident::whereIn('status', ['open', 'ack'])->whereNotNull('sla_breached_at')->count();
        $incidentsByCity = OpsIncident::whereIn('status', ['open', 'ack'])
            ->whereNotNull('city_id')
            ->selectRaw('city_id, COUNT(*) AS c')
            ->groupBy('city_id')
            ->pluck('c', 'city_id');

        $parityTotal = PricingParityCheck::where('created_at', '>=', now()->subDay())->count();
        $parityMismatches = PricingParityCheck::where('created_at', '>=', now()->subDay())
            ->where('matched', false)
            ->count();

        $refundPending = Order::where('payment_status', \App\Services\OrderReversalService::STATUS_REFUND_PENDING)->count();
        $oldestRefund = Order::where('payment_status', \App\Services\OrderReversalService::STATUS_REFUND_PENDING)
            ->whereNotNull('refund_retried_at')
            ->min('refund_retried_at');
        $oldestRefundMinutes = $oldestRefund
            ? (int) abs(now()->diffInMinutes(\Carbon\Carbon::parse($oldestRefund)))
            : 0;

        $codOutstanding = (float) Order::where('payment_mode', 'cod')
            ->where('status', 'delivered')
            ->where('payment_status', '!=', 'paid')
            ->sum('grand_total');

        $cityMetrics = Order::whereNotNull('city_id')
            ->where('created_at', '>=', now()->subDay())
            ->selectRaw("
                city_id,
                SUM(CASE WHEN status = 'pending' AND created_at < ? THEN 1 ELSE 0 END) AS unaccepted_over_3min,
                SUM(CASE WHEN status = 'ready_for_pickup' AND rider_id IS NULL AND ready_marked_at < ? THEN 1 ELSE 0 END) AS ready_no_rider_over_8min,
                COALESCE(SUM(CASE WHEN payment_mode = 'cod' AND status = 'delivered' AND payment_status <> 'paid' THEN grand_total ELSE 0 END), 0) AS cod_outstanding,
                SUM(CASE WHEN cancel_reason IN ('restaurant_timeout','restaurant_reject','no_rider') THEN 1 ELSE 0 END) AS platform_cancels
            ", [now()->subMinutes(3), now()->subMinutes(8)])
            ->groupBy('city_id')
            ->get()
            ->keyBy('city_id');

        $activeCities = City::where('is_active', true)->orderBy('name')->get();
        $daily = $analytics->daily($activeCities->pluck('id')->all());

        $cities = $activeCities->map(function ($city) use ($cityMetrics, $daily, $incidentsByCity, $agentsByCity) {
            $floor = WaCityFloor::find($city->id);
            $m = $cityMetrics->get($city->id);

            $day = $daily[$city->id] ?? [
                'orders' => 0, 'delivered' => 0, 'cancelled' => 0, 'no_rider' => 0, 'gmv' => 0.0,
            ];

            return [
                'city_id' => $city->id,
                'name' => $city->name,
                'open_incidents' => (int) ($incidentsByCity[$city->id] ?? 0),
                'agents_on_shift' => (int) ($agentsByCity[$city->id] ?? 0),
                'today_orders' => $day['orders'],
                'today_delivered' => $day['delivered'],
                'today_cancelled' => $day['cancelled'],
                'today_no_rider' => $day['no_rider'],
                'today_gmv' => $day['gmv'],
                'orders_unaccepted_over_3min' => (int) ($m->unaccepted_over_3min ?? 0),
                'orders_ready_no_rider_over_8min' => (int) ($m->ready_no_rider_over_8min ?? 0),
                'orders_cod_outstanding' => (float) ($m->cod_outstanding ?? 0),
                'orders_platform_cancels_24h' => (int) ($m->platform_cancels ?? 0),
                'wa_active_placements' => WaPlacement::where('city_id', $city->id)
                    ->where('status', WaPlacement::STATUS_ACTIVE)
                    ->count(),
                'wa_exhausted_today' => WaPlacement::where('city_id', $city->id)
                    ->where('status', WaPlacement::STATUS_EXHAUSTED)
                    ->count(),
                'wa_floor_cpc' => $floor?->floor_cpc ?? 0,
                'wa_max_slots' => $floor?->max_slots ?? 5,
            ];
        });

        return response()->json([
            'global' => [
                'unaccepted_over_3min' => $unacceptedOver3Min,
                'ready_no_rider_over_8min' => $readyNoRiderOver8Min,
                'prepaid_payment_stuck_over_30min' => $paymentStuck,
                'cod_outstanding_amount' => round($codOutstanding, 2),
                'refund_pending' => $refundPending,
                'open_incidents' => $incidentsOpen,
                'on_shift_agents' => $onShiftAgents,
                'open_sos' => $openSos,
                'sev1_incidents' => $incidentsSev1,
                'incident_sla_breaches' => $incidentBreaches,
                'pricing_parity_checks_24h' => $parityTotal,
                'pricing_parity_mismatches_24h' => $parityMismatches,
                'pricing_parity_mismatch_rate' => $parityTotal > 0 ? round(($parityMismatches / $parityTotal) * 100, 3) : 0.0,
                'oldest_refund_pending_minutes' => $oldestRefundMinutes,
            ],
            'event_bus' => [
                'outbox_pending' => \Illuminate\Support\Facades\DB::table('outbox')
                    ->whereNull('published_at')->count(),
                'oldest_pending_age_seconds' => $publisher->oldestPendingAgeSeconds(),
            ],
            'metrics_source' => $analytics->source(),
            'cutover' => $cutover->cached(),
            'projection' => $projection->status(),
            'cities' => $cities,
        ]);
    }
}
