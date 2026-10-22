<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\Restaurant;
use App\Models\SystemConfig;
use App\Models\VendorPayment;
use Illuminate\Http\Request;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class GstController extends Controller
{
    private function getConfig(string $key, string $default = ''): string
    {
        $config = SystemConfig::where('config_key', $key)->first();

        return $config ? $config->config_value : $default;
    }

    private function commissionGstRate(): float
    {
        return (float) $this->getConfig('commission_gst_rate', '18');
    }

    private function aggregatorGstin(): string
    {
        return $this->getConfig('aggregator_gstin', '');
    }

    private function placeOfSupply(): string
    {
        return $this->getConfig('place_of_supply', '');
    }

    public function listRestaurantsForGst()
    {
        $restaurants = Restaurant::select('id', 'name', 'gst_number', 'pan_number', 'email', 'phone')
            ->orderBy('name')
            ->get();

        return response()->json(['restaurants' => $restaurants]);
    }

    public function monthlyReport(Request $request, $restaurantId, $year, $month)
    {
        $restaurant = Restaurant::findOrFail($restaurantId);
        $period = "$year-$month";
        $startDate = "$year-$month-01";
        $endDate = date('Y-m-t', strtotime($startDate));

        $orders = Order::where('restaurant_id', $restaurantId)
            ->whereBetween('created_at', [$startDate, $endDate.' 23:59:59'])
            ->whereIn('status', ['delivered', 'completed'])
            ->get();

        if ($request->expectsJson()) {
            $totalTaxable = 0;
            $totalGst = 0;
            foreach ($orders as $o) {
                $taxable = $o->subtotal - $o->vendor_discount_amount - ($o->coupon_discount_amount ?? 0);
                $totalTaxable += $taxable;
                $totalGst += $o->tax_amount;
            }

            return response()->json([
                'data' => [
                    'restaurant' => [
                        'name' => $restaurant->name,
                        'gstin' => $restaurant->gst_number,
                    ],
                    'orders' => $orders->map(fn ($o) => [
                        'order_number' => $o->id,
                        'created_at' => $o->created_at,
                        'subtotal' => $o->subtotal,
                        'cgst' => $o->tax_amount / 2,
                        'sgst' => $o->tax_amount / 2,
                        'grand_total' => $o->grand_total,
                    ]),
                    'summary' => [
                        'total_orders' => $orders->count(),
                        'total_taxable' => round($totalTaxable, 2),
                        'total_gst' => round($totalGst, 2),
                        'total_invoice_value' => round($orders->sum('grand_total'), 2),
                    ],
                ],
            ]);
        }

        $invoices = Invoice::where('restaurant_id', $restaurantId)
            ->whereDate('period_start', '>=', $startDate)
            ->whereDate('period_start', '<=', $endDate)
            ->get();

        $payments = VendorPayment::where('restaurant_id', $restaurantId)
            ->where('payment_period', $period)
            ->get();

        $spreadsheet = new Spreadsheet;

        $this->buildSummarySheet($spreadsheet, $restaurant, $orders, $invoices, $payments, $period);
        $this->buildOrdersSheet($spreadsheet, $orders, $restaurant);
        $this->buildGstr1Sheet($spreadsheet, $orders, $restaurant, $period);
        $this->buildGstr3bSheet($spreadsheet, $orders, $invoices, $period);
        $this->buildSalesSheet($spreadsheet, $orders, $restaurant, $period);
        $this->buildInvoicesSheet($spreadsheet, $invoices);
        $this->buildPaymentsSheet($spreadsheet, $payments, $period);

        $filename = "GST_Report_{$restaurant->name}_{$period}.xlsx";
        $tempFile = tempnam(sys_get_temp_dir(), 'gst_');
        $writer = new Xlsx($spreadsheet);
        $writer->save($tempFile);

        return response()->download($tempFile, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend(true);
    }

    private function buildSummarySheet(Spreadsheet $spreadsheet, Restaurant $restaurant, $orders, $invoices, $payments, $period): void
    {
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Summary');

        $sheet->setCellValue('A1', 'KHAALO - GST Summary Report');
        $sheet->setCellValue('A2', "Period: $period");
        $sheet->mergeCells('A1:F1');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);

        $commRate = $restaurant->commission_rate ?? 15;
        $gstRate = $this->commissionGstRate();

        $row = 4;
        $labels = [
            'Restaurant Name' => $restaurant->name,
            'GST Number' => $restaurant->gst_number ?? 'N/A',
            'PAN Number' => $restaurant->pan_number ?? 'N/A',
            'Total Orders' => $orders->count(),
            'Total Order Value (after discounts)' => number_format($orders->sum('grand_total'), 2),
            'Total Discounts Given' => number_format($orders->sum('vendor_discount_amount') + $orders->sum('coupon_discount_amount'), 2),
            "Total Commission ({$commRate}% + GST {$gstRate}%)" => number_format($invoices->where('type', 'commission')->sum('total_amount'), 2),
            'Total Sponsorship (incl GST)' => number_format($invoices->where('type', 'sponsorship')->sum('total_amount'), 2),
        ];

        foreach ($labels as $label => $value) {
            $sheet->setCellValue("A$row", $label);
            $sheet->setCellValue("B$row", $value);
            $sheet->getStyle("A$row")->getFont()->setBold(true);
            $row++;
        }

        $sheet->setCellValue("A$row", 'Net Payable to Restaurant');
        $sheet->setCellValue("B$row", number_format($payments->sum('net_payable'), 2));
        $sheet->getStyle("A$row")->getFont()->setBold(true);

        $sheet->getColumnDimension('A')->setWidth(35);
        $sheet->getColumnDimension('B')->setWidth(25);
    }

    private function buildOrdersSheet(Spreadsheet $spreadsheet, $orders, Restaurant $restaurant): void
    {
        $sheet = $spreadsheet->createSheet();
        $sheet->setTitle('Orders with HSN & GST');

        $headers = ['Order ID', 'Date', 'Time', 'Customer', 'Item', 'HSN Code', 'Qty', 'Unit Price', 'GST %', 'CGST', 'SGST', 'Total', 'Commission', 'Net Payout'];
        $col = 'A';
        foreach ($headers as $header) {
            $sheet->setCellValue($col.'1', $header);
            $sheet->getStyle($col.'1')->getFont()->setBold(true);
            $col++;
        }

        $row = 2;
        foreach ($orders as $order) {
            $gstDetails = $order->gst_details ? json_decode($order->gst_details, true) : [];
            foreach ($order->items as $item) {
                $hsn = $item->menuItem->hsn_code ?? 'N/A';
                $gstPct = $item->menuItem->gst_percent ?? 5.00;
                $cgst = $item->total_price * ($gstPct / 2 / 100);
                $sgst = $cgst;

                $sheet->setCellValue("A$row", $order->id);
                $sheet->setCellValue("B$row", $order->created_at->format('Y-m-d'));
                $sheet->setCellValue("C$row", $order->created_at->format('H:i'));
                $sheet->setCellValue("D$row", $order->user->name ?? 'N/A');
                $sheet->setCellValue("E$row", $item->menuItem->name ?? $item->item_name);
                $sheet->setCellValue("F$row", $hsn);
                $sheet->setCellValue("G$row", $item->quantity);
                $sheet->setCellValue("H$row", $item->unit_price);
                $sheet->setCellValue("I$row", $gstPct);
                $sheet->setCellValue("J$row", round($cgst, 2));
                $sheet->setCellValue("K$row", round($sgst, 2));
                $sheet->setCellValue("L$row", $item->total_price);
                $sheet->setCellValue("M$row", $order->platform_commission);
                $sheet->setCellValue("N$row", $order->net_payout);
                $row++;
            }
        }

        foreach (range('A', 'N') as $c) {
            $sheet->getColumnDimension($c)->setAutoSize(true);
        }
    }

    private function buildGstr1Sheet(Spreadsheet $spreadsheet, $orders, Restaurant $restaurant, string $period): void
    {
        $sheet = $spreadsheet->createSheet();
        $sheet->setTitle('GSTR-1 Report');

        $aggGstin = $this->aggregatorGstin();
        $pos = $this->placeOfSupply();

        $sheet->setCellValue('A1', 'GSTR-1 Report for Aggregator (UtterLazy)');
        $sheet->setCellValue('A2', "Period: $period");
        $sheet->setCellValue('A3', "Aggregator GSTIN: $aggGstin");
        $sheet->setCellValue('A4', 'Restaurant GSTIN: '.($restaurant->gst_number ?? 'N/A'));
        $sheet->mergeCells('A1:I1');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(12);

        $headers = ['Invoice No', 'Invoice Date', 'GSTIN of Restaurant', 'Invoice Value', 'Taxable Value', 'CGST', 'SGST', 'IGST', 'Place of Supply'];
        $col = 'A';
        foreach ($headers as $i => $header) {
            $sheet->setCellValue($col.'6', $header);
            $sheet->getStyle($col.'6')->getFont()->setBold(true);
            $col++;
        }

        $row = 7;
        foreach ($orders as $i => $order) {
            $taxableValue = $order->subtotal - $order->vendor_discount_amount - ($order->coupon_discount_amount ?? 0);
            $gstDetails = $order->gst_details ? json_decode($order->gst_details, true) : [];
            $cgst = $gstDetails['cgst'] ?? ($order->tax_amount / 2);
            $sgst = $gstDetails['sgst'] ?? ($order->tax_amount / 2);

            $sheet->setCellValue("A$row", 'INV-'.str_pad((string) ($i + 1), 5, '0', STR_PAD_LEFT));
            $sheet->setCellValue("B$row", $order->created_at->format('d-m-Y'));
            $sheet->setCellValue("C$row", $restaurant->gst_number ?? 'N/A');
            $sheet->setCellValue("D$row", $order->grand_total);
            $sheet->setCellValue("E$row", round($taxableValue, 2));
            $sheet->setCellValue("F$row", round($cgst, 2));
            $sheet->setCellValue("G$row", round($sgst, 2));
            $sheet->setCellValue("H$row", 0);
            $sheet->setCellValue("I$row", $pos);
            $row++;
        }

        foreach (range('A', 'I') as $c) {
            $sheet->getColumnDimension($c)->setAutoSize(true);
        }
    }

    private function buildGstr3bSheet(Spreadsheet $spreadsheet, $orders, $invoices, string $period): void
    {
        $sheet = $spreadsheet->createSheet();
        $sheet->setTitle('GSTR-3B Report');

        $sheet->setCellValue('A1', 'GSTR-3B Monthly Return Summary');
        $sheet->setCellValue('A2', "Period: $period");
        $sheet->mergeCells('A1:D1');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(12);

        $totalTaxableValue = 0;
        $totalCgst = 0;
        $totalSgst = 0;
        foreach ($orders as $order) {
            $taxableValue = $order->subtotal - $order->vendor_discount_amount - ($order->coupon_discount_amount ?? 0);
            $totalTaxableValue += $taxableValue;
            $gstDetails = $order->gst_details ? json_decode($order->gst_details, true) : [];
            $totalCgst += $gstDetails['cgst'] ?? ($order->tax_amount / 2);
            $totalSgst += $gstDetails['sgst'] ?? ($order->tax_amount / 2);
        }

        $commGst = $invoices->where('type', 'commission')->sum('gst_amount');
        $sponGst = $invoices->where('type', 'sponsorship')->sum('gst_amount');

        $row = 4;
        $items = [
            ['3(a) - Outward Supplies (Taxable)', number_format($totalTaxableValue, 2), ''],
            ['3(b) - Outward Supplies (Nil Rated)', '0.00', ''],
            ['4 - ITC Available', '', ''],
            ['   Commission Input GST', '', number_format($commGst, 2)],
            ['   Sponsorship Input GST', '', number_format($sponGst, 2)],
            ['5 - Net ITC', '', number_format($commGst + $sponGst, 2)],
            ['6 - Gross Tax Payable', '', ''],
            ['   CGST', number_format($totalCgst, 2), ''],
            ['   SGST', number_format($totalSgst, 2), ''],
            ['   IGST', '0.00', ''],
            ['7 - Net Tax Payable', number_format($totalCgst + $totalSgst, 2), ''],
        ];

        $sheet->setCellValue('A3', 'Description');
        $sheet->setCellValue('B3', 'Amount');
        $sheet->setCellValue('C3', 'Notes');
        $sheet->getStyle('A3:C3')->getFont()->setBold(true);

        foreach ($items as $i => $item) {
            $r = $row + $i;
            $sheet->setCellValue("A$r", $item[0]);
            $sheet->setCellValue("B$r", $item[1]);
            $sheet->setCellValue("C$r", $item[2] ?? '');
            $sheet->getStyle("A$r")->getFont()->setBold(str_starts_with($item[0], '3') || str_starts_with($item[0], '5') || str_starts_with($item[0], '6') || str_starts_with($item[0], '7'));
        }

        $sheet->getColumnDimension('A')->setWidth(35);
        $sheet->getColumnDimension('B')->setWidth(20);
        $sheet->getColumnDimension('C')->setWidth(20);
    }

    private function buildSalesSheet(Spreadsheet $spreadsheet, $orders, Restaurant $restaurant, string $period): void
    {
        $sheet = $spreadsheet->createSheet();
        $sheet->setTitle('Sales Report (Reverse Charge)');

        $commRate = $restaurant->commission_rate ?? 15;
        $gstRate = $this->commissionGstRate();

        $sheet->setCellValue('A1', 'Sales Report - Reverse Charge (GST by Aggregator)');
        $sheet->setCellValue('A2', "Period: $period");
        $sheet->setCellValue('A3', "Restaurant: {$restaurant->name}");
        $sheet->mergeCells('A1:G1');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(12);

        $headers = ['Date', 'Order ID', 'Order Value', 'Discounts', "Commission ({$commRate}%)", "Commission GST ({$gstRate}%)", 'Net Settlement'];
        $col = 'A';
        foreach ($headers as $header) {
            $sheet->setCellValue($col.'5', $header);
            $sheet->getStyle($col.'5')->getFont()->setBold(true);
            $col++;
        }

        $row = 6;
        foreach ($orders as $order) {
            $commission = $order->platform_commission;
            $commGst = $commission * ($gstRate / 100);
            $discounts = $order->vendor_discount_amount + ($order->coupon_discount_amount ?? 0);
            $netSettlement = ($order->subtotal - $discounts + $order->tax_amount) - $commission - $commGst;

            $sheet->setCellValue("A$row", $order->created_at->format('Y-m-d'));
            $sheet->setCellValue("B$row", $order->id);
            $sheet->setCellValue("C$row", $order->subtotal);
            $sheet->setCellValue("D$row", $discounts);
            $sheet->setCellValue("E$row", $commission);
            $sheet->setCellValue("F$row", round($commGst, 2));
            $sheet->setCellValue("G$row", round($netSettlement, 2));
            $row++;
        }

        foreach (range('A', 'G') as $c) {
            $sheet->getColumnDimension($c)->setAutoSize(true);
        }
    }

    private function buildInvoicesSheet(Spreadsheet $spreadsheet, $invoices): void
    {
        $sheet = $spreadsheet->createSheet();
        $sheet->setTitle('Invoices');

        $headers = ['Invoice #', 'Type', 'Period Start', 'Period End', 'Amount', 'GST Rate', 'GST Amount', 'Total', 'Status'];
        $col = 'A';
        foreach ($headers as $header) {
            $sheet->setCellValue($col.'1', $header);
            $sheet->getStyle($col.'1')->getFont()->setBold(true);
            $col++;
        }

        $row = 2;
        foreach ($invoices as $inv) {
            $sheet->setCellValue("A$row", $inv->invoice_number);
            $sheet->setCellValue("B$row", ucfirst($inv->type));
            $sheet->setCellValue("C$row", $inv->period_start->format('Y-m-d'));
            $sheet->setCellValue("D$row", $inv->period_end->format('Y-m-d'));
            $sheet->setCellValue("E$row", $inv->amount);
            $sheet->setCellValue("F$row", $inv->gst_rate.'%');
            $sheet->setCellValue("G$row", $inv->gst_amount);
            $sheet->setCellValue("H$row", $inv->total_amount);
            $sheet->setCellValue("I$row", ucfirst($inv->status));
            $row++;
        }

        foreach (range('A', 'I') as $c) {
            $sheet->getColumnDimension($c)->setAutoSize(true);
        }
    }

    private function buildPaymentsSheet(Spreadsheet $spreadsheet, $payments, string $period): void
    {
        $sheet = $spreadsheet->createSheet();
        $sheet->setTitle('Payments');

        $headers = ['Week', 'Gross Order Value', 'Discounts', 'Commission (incl GST)', 'Sponsorship (incl GST)', 'Net Payable', 'UTR', 'Status', 'Paid At'];
        $col = 'A';
        foreach ($headers as $header) {
            $sheet->setCellValue($col.'1', $header);
            $sheet->getStyle($col.'1')->getFont()->setBold(true);
            $col++;
        }

        $row = 2;
        foreach ($payments as $pmt) {
            $sheet->setCellValue("A$row", $pmt->week_start->format('d/m').' - '.$pmt->week_end->format('d/m/y'));
            $sheet->setCellValue("B$row", $pmt->total_order_value);
            $sheet->setCellValue("C$row", $pmt->total_discounts);
            $sheet->setCellValue("D$row", $pmt->total_commission + $pmt->commission_gst);
            $sheet->setCellValue("E$row", $pmt->total_sponsorship + $pmt->sponsorship_gst);
            $sheet->setCellValue("F$row", $pmt->net_payable);
            $sheet->setCellValue("G$row", $pmt->utr_number ?? 'Pending');
            $sheet->setCellValue("H$row", ucfirst(str_replace('_', ' ', $pmt->status)));
            $sheet->setCellValue("I$row", $pmt->paid_at?->format('Y-m-d') ?? '-');
            $row++;
        }

        foreach (range('A', 'I') as $c) {
            $sheet->getColumnDimension($c)->setAutoSize(true);
        }
    }
}
