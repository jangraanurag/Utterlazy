<?php

namespace App\Services;

use App\Models\Order;
use App\Models\PayoutAddition;
use App\Models\PayoutCycle;
use App\Models\PayoutDeduction;
use App\Models\PayoutOrderLine;
use App\Models\Restaurant;
use App\Models\RestaurantPayoutBalance;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PayoutCalculationService
{
    public function generateWeeklyCycle(Restaurant $restaurant, Carbon $cycleStart, Carbon $cycleEnd): PayoutCycle
    {
        Log::info('Generating payout cycle', [
            'restaurant_id' => $restaurant->id,
            'cycle_start' => $cycleStart->toDateString(),
            'cycle_end' => $cycleEnd->toDateString(),
        ]);

        return DB::transaction(function () use ($restaurant, $cycleStart, $cycleEnd) {
            $orders = Order::where('restaurant_id', $restaurant->id)
                ->whereBetween('created_at', [$cycleStart, $cycleEnd->endOfDay()])
                ->where('status', 'delivered')
                ->get();

            $balance = RestaurantPayoutBalance::firstOrCreate(
                ['restaurant_id' => $restaurant->id],
                ['running_balance' => 0, 'total_paid_to_date' => 0, 'total_earnings_to_date' => 0, 'last_updated_at' => now()]
            );

            $previousBalance = $balance->running_balance;

            $cycle = PayoutCycle::create([
                'restaurant_id' => $restaurant->id,
                'cycle_start' => $cycleStart,
                'cycle_end' => $cycleEnd,
                'generated_at' => now(),
                'status' => 'generated',
                'gross_order_value' => 0,
                'total_deductions' => 0,
                'total_additions' => 0,
                'net_payable' => 0,
                'previous_balance_carried' => $previousBalance,
            ]);

            $grossOrderValue = 0;
            $totalDeductions = 0;

            foreach ($orders as $order) {
                $subtotalAfterDiscount = $order->subtotal - $order->vendor_discount_amount;
                $commission = $this->calculateCommission($order);
                $tax = $this->calculateTax($order);

                $convenienceFee = $order->platform_fee ?? 0;
                $deliveryChargeCollected = $order->delivery_fee_customer ?? 0;
                $netSettlement = $subtotalAfterDiscount - $commission - $tax - $convenienceFee;

                $orderLine = PayoutOrderLine::create([
                    'cycle_id' => $cycle->id,
                    'order_id' => $order->id,
                    'order_value' => $subtotalAfterDiscount,
                    'discount' => $order->vendor_discount_amount,
                    'tax' => $tax,
                    'commission' => $commission,
                    'convenience_fee' => $convenienceFee,
                    'delivery_charge_collected' => $deliveryChargeCollected,
                    'net_settlement' => $netSettlement,
                    'order_status' => $order->status,
                ]);

                $grossOrderValue += $subtotalAfterDiscount;

                PayoutDeduction::create([
                    'cycle_id' => $cycle->id,
                    'type' => 'commission',
                    'amount' => $commission,
                    'reference_type' => 'order',
                    'reference_id' => $order->id,
                    'notes' => "Commission on order #{$order->id}",
                ]);

                $totalDeductions += $commission;

                if ($tax > 0) {
                    PayoutDeduction::create([
                        'cycle_id' => $cycle->id,
                        'type' => 'tax',
                        'amount' => $tax,
                        'reference_type' => 'order',
                        'reference_id' => $order->id,
                        'notes' => "Tax on order #{$order->id}",
                    ]);

                    $totalDeductions += $tax;
                }

                if ($convenienceFee > 0) {
                    PayoutDeduction::create([
                        'cycle_id' => $cycle->id,
                        'type' => 'convenience_fee',
                        'amount' => $convenienceFee,
                        'reference_type' => 'order',
                        'reference_id' => $order->id,
                        'notes' => "Convenience fee on order #{$order->id}",
                    ]);

                    $totalDeductions += $convenienceFee;
                }

                Log::info('Processed order line for payout', [
                    'cycle_id' => $cycle->id,
                    'order_id' => $order->id,
                    'net_settlement' => $netSettlement,
                ]);
            }

            $totalAdditions = PayoutAddition::where('cycle_id', $cycle->id)->sum('amount');

            $netPayable = $this->getNetPayable($cycle);

            $cycle->update([
                'gross_order_value' => $grossOrderValue,
                'total_deductions' => $totalDeductions,
                'total_additions' => $totalAdditions,
                'net_payable' => $netPayable,
            ]);

            Log::info('Payout cycle generated', [
                'cycle_id' => $cycle->id,
                'gross_order_value' => $grossOrderValue,
                'total_deductions' => $totalDeductions,
                'net_payable' => $netPayable,
            ]);

            return $cycle->fresh();
        });
    }

    public function calculateCommission(Order $order): float
    {
        $commissionRate = $order->restaurant->commission_rate ?? 15;
        $subtotalAfterDiscount = $order->subtotal - $order->vendor_discount_amount;

        return round($subtotalAfterDiscount * $commissionRate / 100, 2);
    }

    public function calculateTax(Order $order): float
    {
        $restaurant = $order->restaurant;

        if (empty($restaurant->gst_number)) {
            return 0;
        }

        return round($order->tax_amount, 2);
    }

    public function getNetPayable(PayoutCycle $cycle): float
    {
        $gross = $cycle->gross_order_value ?? 0;
        $deductions = $cycle->total_deductions ?? 0;
        $additions = $cycle->total_additions ?? 0;
        $previous = $cycle->previous_balance_carried ?? 0;

        return round($gross - $deductions + $additions + $previous, 2);
    }

    public function markAsPaid(PayoutCycle $cycle, string $utr, string $paymentMode, float $amount, ?string $notes, User $enteredBy): PayoutCycle
    {
        return DB::transaction(function () use ($cycle, $utr, $paymentMode, $amount, $notes, $enteredBy) {
            $cycle->update([
                'utr_number' => $utr,
                'payment_date' => now(),
                'payment_mode' => $paymentMode,
                'amount_paid' => $amount,
                'payment_notes' => $notes,
                'entered_by' => $enteredBy->id,
                'status' => 'paid',
            ]);

            $this->updateRunningBalance($cycle->restaurant);

            Log::info('Payout cycle marked as paid', [
                'cycle_id' => $cycle->id,
                'utr' => $utr,
                'amount' => $amount,
                'entered_by' => $enteredBy->id,
            ]);

            return $cycle->fresh();
        });
    }

    public function updateRunningBalance(Restaurant $restaurant): void
    {
        $totalEarnings = PayoutCycle::where('restaurant_id', $restaurant->id)
            ->sum('gross_order_value');

        $totalPaid = PayoutCycle::where('restaurant_id', $restaurant->id)
            ->sum('amount_paid');

        $totalAdditions = PayoutCycle::where('restaurant_id', $restaurant->id)
            ->sum('total_additions');

        $totalDeductions = PayoutCycle::where('restaurant_id', $restaurant->id)
            ->sum('total_deductions');

        $runningBalance = ($totalEarnings + $totalAdditions) - $totalDeductions - $totalPaid;

        RestaurantPayoutBalance::updateOrCreate(
            ['restaurant_id' => $restaurant->id],
            [
                'running_balance' => round($runningBalance, 2),
                'total_paid_to_date' => round($totalPaid, 2),
                'total_earnings_to_date' => round($totalEarnings + $totalAdditions, 2),
                'last_updated_at' => now(),
            ]
        );

        Log::info('Running balance updated', [
            'restaurant_id' => $restaurant->id,
            'running_balance' => round($runningBalance, 2),
            'total_paid' => round($totalPaid, 2),
        ]);
    }
}
