<?php

namespace App\Services;

use App\Models\Order;

class InvoiceService
{
    public static function generate(Order $order): array
    {
        $order->load(['items.menuItem', 'restaurant', 'user']);

        $items = $order->items->map(function ($item) {
            $gstAmount = $item->total_price * ($item->menuItem->gst_percent ?? 5) / 100;

            return [
                'name' => $item->menuItem->name,
                'hsn_code' => $item->menuItem->hsn_code ?? '9964',
                'quantity' => $item->quantity,
                'unit_price' => $item->unit_price,
                'total' => $item->total_price,
                'gst_percent' => $item->menuItem->gst_percent ?? 5,
                'gst_amount' => round($gstAmount, 2),
                'tax_type' => $item->menuItem->tax_type ?? 'inclusive',
            ];
        });

        $subtotal = $items->sum('total');
        $totalGst = $items->sum('gst_amount');
        $sgst = round($totalGst / 2, 2);
        $cgst = round($totalGst / 2, 2);

        return [
            'invoice_number' => 'KHA-'.strtoupper(substr($order->id, 0, 8)),
            'invoice_date' => $order->created_at->format('d/m/Y'),
            'order_id' => $order->id,

            'seller' => [
                'name' => $order->restaurant->name,
                'address' => $order->restaurant->address ?? 'N/A',
                'gstin' => $order->restaurant->gst_number ?? 'N/A',
                'state' => $order->restaurant->state ?? 'N/A',
            ],

            'buyer' => [
                'name' => $order->user->name,
                'address' => $order->user->addresses->first()?->street ?? 'N/A',
                'state' => $order->user->addresses->first()?->state ?? 'N/A',
                'is_business' => (bool) $order->is_business_order,
                'company_name' => $order->company_name,
                'gstin' => $order->company_gst,
            ],

            'items' => $items,
            'summary' => [
                'subtotal' => round($subtotal, 2),
                'sgst' => $sgst,
                'cgst' => $cgst,
                'total_gst' => round($totalGst, 2),
                'delivery_fee' => $order->delivery_fee ?? 0,
                'discount' => ($order->vendor_discount_amount ?? 0) + ($order->coupon_discount_amount ?? 0),
                'grand_total' => round($order->grand_total, 2),
            ],

            'payment' => [
                'mode' => strtoupper($order->payment_mode),
                'status' => $order->payment_status ?? 'pending',
            ],

            'terms' => 'This is a computer-generated invoice. Subject to jurisdiction.',
        ];
    }
}
