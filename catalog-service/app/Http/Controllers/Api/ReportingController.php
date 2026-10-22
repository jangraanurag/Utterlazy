<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Restaurant;
use App\Services\BusinessReportService;
use App\Services\LiveTrackingService;
use App\Services\MetricAggregationService;
use App\Services\PiiAccessLogger;
use Carbon\Carbon;
use Illuminate\Http\Request;

class ReportingController extends Controller
{
    public function __construct(
        private LiveTrackingService $liveTracking,
        private BusinessReportService $businessReports,
        private MetricAggregationService $aggregator,
        private PiiAccessLogger $pii,
    ) {}

    // ─── Admin: Live Tracking ───────────────────────────────────────────────

    public function adminLiveTracking(Request $request)
    {
        $request->validate([
            'restaurant_id' => 'required|exists:restaurants,id',
            'date' => 'nullable|date',
        ]);

        $restaurant = Restaurant::findOrFail($request->restaurant_id);
        $date = $request->date ? Carbon::parse($request->date) : Carbon::yesterday();

        return response()->json([
            'data' => $this->liveTracking->getComparisonData($restaurant, $date),
            'restaurant' => ['id' => $restaurant->id, 'name' => $restaurant->name],
        ]);
    }

    // ─── Admin: Trend Table ──────────────────────────────────────────────────

    public function adminTrendTable(Request $request)
    {
        $request->validate([
            'restaurant_id' => 'required|exists:restaurants,id',
            'granularity' => 'nullable|in:daily,weekly,monthly',
            'start' => 'required|date',
            'end' => 'required|date|after_or_equal:start',
        ]);

        $restaurant = Restaurant::findOrFail($request->restaurant_id);

        return response()->json(
            $this->businessReports->getTrendTable(
                $restaurant,
                $request->granularity ?? 'daily',
                Carbon::parse($request->start),
                Carbon::parse($request->end),
            )
        );
    }

    // ─── Admin: Export Report ────────────────────────────────────────────────

    public function adminExportReport(Request $request)
    {
        $request->validate([
            'restaurant_id' => 'required|exists:restaurants,id',
            'type' => 'required|in:detailed,item_sales',
            'granularity' => 'nullable|in:daily,weekly,monthly',
            'start' => 'required|date',
            'end' => 'required|date|after_or_equal:start',
        ]);

        $restaurant = Restaurant::findOrFail($request->restaurant_id);
        $start = Carbon::parse($request->start);
        $end = Carbon::parse($request->end);

        $path = $request->type === 'item_sales'
            ? $this->businessReports->exportItemSalesReport($restaurant, $start, $end)
            : $this->businessReports->exportDetailedReport($restaurant, $request->granularity ?? 'daily', $start, $end);

        $this->pii->log(
            $request->user(),
            'export',
            'restaurant_report',
            $restaurant->id,
            $request->only(['type', 'granularity', 'start', 'end']),
            null,
            'admin_business_report_export',
        );

        return response()->download($path)->deleteFileAfterSend(true);
    }

    // ─── Admin: Trigger Aggregation ──────────────────────────────────────────

    public function adminTriggerAggregation(Request $request)
    {
        $request->validate([
            'date' => 'nullable|date',
            'restaurant_id' => 'nullable|exists:restaurants,id',
        ]);

        $date = $request->date ? Carbon::parse($request->date) : Carbon::yesterday();

        if ($request->restaurant_id) {
            $restaurant = Restaurant::findOrFail($request->restaurant_id);
            $this->aggregator->aggregateForRestaurant($restaurant, $date);
        } else {
            $this->aggregator->aggregateForAllRestaurants($date);
        }

        return response()->json(['message' => 'Aggregation complete']);
    }

    // ─── Vendor: Live Tracking ───────────────────────────────────────────────

    public function vendorLiveTracking(Request $request)
    {
        $restaurant = $request->user();
        $date = $request->date ? Carbon::parse($request->date) : Carbon::yesterday();

        return response()->json([
            'data' => $this->liveTracking->getComparisonData($restaurant, $date),
        ]);
    }

    // ─── Vendor: Trend Table ─────────────────────────────────────────────────

    public function vendorTrendTable(Request $request)
    {
        $request->validate([
            'granularity' => 'nullable|in:daily,weekly,monthly',
            'start' => 'required|date',
            'end' => 'required|date|after_or_equal:start',
        ]);

        $restaurant = $request->user();

        return response()->json(
            $this->businessReports->getTrendTable(
                $restaurant,
                $request->granularity ?? 'daily',
                Carbon::parse($request->start),
                Carbon::parse($request->end),
            )
        );
    }

    // ─── Vendor: Export Report ───────────────────────────────────────────────

    public function vendorExportReport(Request $request)
    {
        $request->validate([
            'type' => 'required|in:detailed,item_sales',
            'granularity' => 'nullable|in:daily,weekly,monthly',
            'start' => 'required|date',
            'end' => 'required|date|after_or_equal:start',
        ]);

        $restaurant = $request->user();
        $start = Carbon::parse($request->start);
        $end = Carbon::parse($request->end);

        $path = $request->type === 'item_sales'
            ? $this->businessReports->exportItemSalesReport($restaurant, $start, $end)
            : $this->businessReports->exportDetailedReport($restaurant, $request->granularity ?? 'daily', $start, $end);

        $this->pii->log(
            $request->user(),
            'export',
            'restaurant_report',
            $restaurant->id,
            $request->only(['type', 'granularity', 'start', 'end']),
            null,
            'admin_business_report_export',
        );

        return response()->download($path)->deleteFileAfterSend(true);
    }

    // ─── Admin: List Restaurants for dropdown ────────────────────────────────

    public function listReportingRestaurants()
    {
        $restaurants = Restaurant::where('is_active', true)
            ->get(['id', 'name', 'gst_number', 'address']);

        return response()->json(['restaurants' => $restaurants]);
    }
}
