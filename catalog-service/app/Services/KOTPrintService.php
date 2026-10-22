<?php

namespace App\Services;

use App\Models\Order;

class KOTPrintService
{
    public function generateKOT(Order $order): array
    {
        $order->load(['items.menuItem', 'restaurant', 'user']);

        $lines = [];
        $lines[] = str_repeat('=', 42);
        $lines[] = '          UTTERLAZY KITCHEN';
        $lines[] = '     Kitchen Order Ticket';
        $lines[] = str_repeat('=', 42);
        $lines[] = '';
        $lines[] = "Order #: {$order->id}";
        $lines[] = 'Date:   '.now()->format('d M Y, h:i A');
        $lines[] = 'Table:  DELIVERY';
        $lines[] = '';
        $lines[] = str_repeat('-', 42);
        $lines[] = ' QTY  ITEM                    AMOUNT';
        $lines[] = str_repeat('-', 42);

        $totalQty = 0;
        foreach ($order->items as $item) {
            $name = $item->menuItem?->name ?? 'Unknown';
            $name = substr($name, 0, 20);
            $qty = $item->quantity;
            $price = number_format($item->unit_price, 2);
            $totalQty += $qty;
            $lines[] = " {$qty}   ".str_pad($name, 20)." ₹{$price}";
            if ($item->special_instructions) {
                $lines[] = "      → {$item->special_instructions}";
            }
        }

        $lines[] = str_repeat('-', 42);
        $lines[] = " Total Items: {$totalQty}";
        $lines[] = ' Total:       ₹'.number_format($order->grand_total, 2);
        $lines[] = ' Payment:     '.strtoupper($order->payment_mode ?? 'PENDING');
        $lines[] = str_repeat('-', 42);
        $lines[] = '';
        $lines[] = " Customer: {$order->user?->name}";
        $lines[] = " Phone:    {$order->user?->phone}";
        if ($order->address) {
            $addr = $order->address->flat_building.', '.$order->address->street;
            $lines[] = " Address:  {$addr}";
        }
        $lines[] = '';
        $lines[] = str_repeat('=', 42);
        $lines[] = '     Thank you for choosing UtterLazy!';
        $lines[] = str_repeat('=', 42);

        return [
            'raw' => implode("\n", $lines),
            'html' => $this->toHtml($lines),
            'total_items' => $totalQty,
            'order_id' => $order->id,
        ];
    }

    protected function toHtml(array $lines): string
    {
        $html = '<div style="font-family: monospace; font-size: 12px; line-height: 1.4; max-width: 300px;">';
        foreach ($lines as $line) {
            $html .= '<div>'.htmlspecialchars($line).'</div>';
        }
        $html .= '</div>';

        return $html;
    }
}
