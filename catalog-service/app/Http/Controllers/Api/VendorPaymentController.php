<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\Restaurant;
use App\Models\VendorPayment;
use Illuminate\Http\Request;

class VendorPaymentController extends Controller
{
    public function generateWeeklyPayment(Request $request)
    {
        if ($request->filled('restaurant_id')) {
            $request->validate([
                'restaurant_id' => 'required|exists:restaurants,id',
                'week_start' => 'required|date',
                'week_end' => 'required|date|after_or_equal:week_start',
            ]);

            return response()->json($this->generateForRestaurant($request->restaurant_id, $request->week_start, $request->week_end));
        }

        // Generate for ALL active restaurants (admin bulk action)
        $restaurants = Restaurant::where('is_active', true)->get();
        $weekStart = $request->week_start ?? now()->startOfWeek()->toDateString();
        $weekEnd = $request->week_end ?? now()->endOfWeek()->toDateString();
        $results = [];
        foreach ($restaurants as $restaurant) {
            try {
                $results[] = $this->generateForRestaurant($restaurant->id, $weekStart, $weekEnd);
            } catch (\Exception $e) {
                $results[] = ['restaurant_id' => $restaurant->id, 'error' => $e->getMessage()];
            }
        }

        return response()->json(['payments' => $results, 'total' => count($results)]);
    }

    private function generateForRestaurant($restaurantId, $weekStart, $weekEnd)
    {
        $restaurant = Restaurant::findOrFail($restaurantId);
        $period = date('Y-m', strtotime($weekStart));

        $orders = Order::where('restaurant_id', $restaurantId)
            ->whereBetween('created_at', [$weekStart.' 00:00:00', $weekEnd.' 23:59:59'])
            ->whereIn('status', ['delivered', 'completed'])
            ->get();

        $invoices = Invoice::where('restaurant_id', $restaurantId)
            ->whereDate('period_start', $weekStart)
            ->whereDate('period_end', $weekEnd)
            ->get();

        $totalOrderValue = $orders->sum('subtotal');
        $totalDiscounts = $orders->sum('vendor_discount_amount') + $orders->sum('coupon_discount_amount');
        $customerGst = $orders->sum('tax_amount');

        $commInvoice = $invoices->where('type', 'commission')->first();
        $sponInvoice = $invoices->where('type', 'sponsorship')->first();

        $netOrderValue = $totalOrderValue - $totalDiscounts;

        $totalCommission = $commInvoice ? $commInvoice->amount : ($netOrderValue * 0.15);
        $commissionGst = $commInvoice ? $commInvoice->gst_amount : ($totalCommission * 0.18);
        $totalSponsorship = $sponInvoice ? $sponInvoice->amount : 0;
        $sponsorshipGst = $sponInvoice ? $sponInvoice->gst_amount : 0;

        $netPayable = ($netOrderValue + $customerGst) - ($totalCommission + $commissionGst) - ($totalSponsorship + $sponsorshipGst);

        $payment = VendorPayment::create([
            'restaurant_id' => $restaurantId,
            'payment_period' => $period,
            'week_start' => $weekStart,
            'week_end' => $weekEnd,
            'total_order_value' => $totalOrderValue,
            'total_discounts' => $totalDiscounts,
            'total_commission' => $totalCommission,
            'commission_gst' => $commissionGst,
            'total_sponsorship' => $totalSponsorship,
            'sponsorship_gst' => $sponsorshipGst,
            'total_refunds' => 0,
            'net_payable' => max($netPayable, 0),
            'status' => 'pending_approval',
        ]);

        return ['payment' => $payment, 'order_count' => $orders->count(), 'restaurant_id' => $restaurantId];
    }

    public function listPayments(Request $request)
    {
        $query = VendorPayment::with('restaurant');

        if ($request->restaurant_id) {
            $query->where('restaurant_id', $request->restaurant_id);
        }
        if ($request->status) {
            $query->where('status', $request->status);
        }
        if ($request->payment_period) {
            $query->where('payment_period', $request->payment_period);
        }

        return response()->json(['payments' => $query->orderBy('created_at', 'desc')->get()]);
    }

    public function approvePayment(Request $request, $id)
    {
        $payment = VendorPayment::findOrFail($id);
        $payment->update([
            'status' => 'approved',
            'approved_by' => $request->user()?->id,
            'approved_at' => now(),
            'admin_notes' => $request->admin_notes ?? $payment->admin_notes,
        ]);

        return response()->json(['payment' => $payment]);
    }

    public function completePayment(Request $request, $id)
    {
        $request->validate([
            'utr_number' => 'required|string',
            'admin_notes' => 'nullable|string',
        ]);

        $payment = VendorPayment::findOrFail($id);
        $payment->update([
            'status' => 'completed',
            'utr_number' => $request->utr_number,
            'admin_notes' => $request->admin_notes ?? $payment->admin_notes,
            'paid_at' => now(),
        ]);

        return response()->json(['payment' => $payment]);
    }
}
