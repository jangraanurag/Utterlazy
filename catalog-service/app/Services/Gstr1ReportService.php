<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Restaurant;
use App\Models\SystemConfig;
use Carbon\Carbon;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class Gstr1ReportService
{
    public function generateReport(Restaurant $restaurant, int $year, int $month): string
    {
        $start  = Carbon::create($year, $month, 1)->startOfDay();
        $end    = $start->copy()->endOfMonth()->endOfDay();
        $period = $start->format('F Y');
        $aggGstin = SystemConfig::getValue('aggregator_gstin', '29AABCU1234X1Z');
        $restGstin = $restaurant->gst_number ?? 'UNREGISTERED';
        $pos = $this->placeOfSupply($restaurant);

        $orders = Order::where('restaurant_id', $restaurant->id)
            ->whereBetween('created_at', [$start, $end])
            ->whereIn('status', ['delivered', 'completed'])
            ->with('items.menuItem')
            ->get();

        $spreadsheet = new Spreadsheet;
        $spreadsheet->getProperties()->setTitle("GSTR1 — {$restaurant->name} — {$period}");

        // Sheet 1: B2CS (Table 7) — Individual invoices
        $this->buildB2csSheet($spreadsheet, $orders, $restaurant, $restGstin, $pos);

        // Sheet 2: B2CS Summary (Table 7A) — Aggregate by rate + state
        $this->buildB2csSummarySheet($spreadsheet, $orders, $restaurant, $restGstin, $pos);

        // Sheet 3: Nil Rated / Exempt (Table 8)
        $this->buildNilExemptSheet($spreadsheet, $orders, $restaurant, $restGstin);

        // Sheet 4: HSN Summary (Table 12)
        $this->buildHsnSheet($spreadsheet, $orders, $restaurant, $restGstin);

        // Sheet 5: Tax Liability Summary
        $this->buildTaxLiabilitySheet($spreadsheet, $restaurant, $start, $end, $restGstin);

        // Sheet 6: At-a-glance
        $this->buildAtGlanceSheet($spreadsheet, $restaurant, $orders, $period, $restGstin, $aggGstin);

        // Remove default sheet
        $spreadsheet->removeSheetByIndex(0);

        $dir = storage_path('app/gst/reports');
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $filename = "GSTR1_{$restaurant->name}_{$year}_{$month}.xlsx";
        $filename = str_replace([' ', '/', '\\'], '_', $filename);
        $path = $dir.'/'.$filename;

        $writer = new Xlsx($spreadsheet);
        $writer->save($path);

        return $path;
    }

    // ─── Sheet Builders ───

    private function buildB2csSheet(Spreadsheet $spreadsheet, $orders, Restaurant $restaurant, string $gstin, string $pos): void
    {
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('B2CS (Table 7)');

        $sheet->setCellValue('A1', "GSTR-1 B2CS (Outward Supplies — Consumer)");
        $sheet->setCellValue('A2', "Restaurant: {$restaurant->name}");
        $sheet->setCellValue('A3', "GSTIN: {$gstin}");
        $sheet->mergeCells('A1:I1');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(12);

        $headers = ['Place of Supply', 'Invoice No', 'Invoice Date', 'Invoice Value', 'Taxable Value', 'CGST', 'SGST', 'IGST', 'Rate'];
        $col = 'A';
        foreach ($headers as $h) {
            $sheet->setCellValue($col.'5', $h);
            $sheet->getStyle($col.'5')->getFont()->setBold(true);
            $col++;
        }

        $row = 6;
        foreach ($orders as $i => $order) {
            $taxableValue = max(0, $order->subtotal - $order->vendor_discount_amount - ($order->coupon_discount_amount ?? 0));
            $gstDetails = json_decode($order->gst_details ?? '{}', true);
            $cgst = $gstDetails['cgst'] ?? ($order->tax_amount / 2);
            $sgst = $gstDetails['sgst'] ?? ($order->tax_amount / 2);
            $rate = $order->tax_amount > 0 && $taxableValue > 0 ? round(($order->tax_amount / $taxableValue) * 100, 0) : 5;

            $sheet->setCellValue("A{$row}", $pos);
            $sheet->setCellValue("B{$row}", 'INV-'.str_pad((string) ($i + 1), 5, '0', STR_PAD_LEFT));
            $sheet->setCellValue("C{$row}", $order->created_at->format('d-m-Y'));
            $sheet->setCellValue("D{$row}", round($order->grand_total, 2));
            $sheet->setCellValue("E{$row}", round($taxableValue, 2));
            $sheet->setCellValue("F{$row}", round($cgst, 2));
            $sheet->setCellValue("G{$row}", round($sgst, 2));
            $sheet->setCellValue("H{$row}", 0);
            $sheet->setCellValue("I{$row}", $rate.'%');
            $row++;
        }

        foreach (range('A', 'I') as $c) {
            $sheet->getColumnDimension($c)->setAutoSize(true);
        }
    }

    private function buildB2csSummarySheet(Spreadsheet $spreadsheet, $orders, Restaurant $restaurant, string $gstin, string $pos): void
    {
        $sheet = $spreadsheet->createSheet();
        $sheet->setTitle('B2CS Summary (Table 7A)');

        $sheet->setCellValue('A1', "GSTR-1 B2CS Summary (< ₹2.5L per invoice)");
        $sheet->setCellValue('A2', "Restaurant: {$restaurant->name}");
        $sheet->mergeCells('A1:G1');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(12);

        $headers = ['GSTIN of Supplier', 'Place of Supply', 'Rate', 'Taxable Value', 'CESS', 'CGST', 'SGST'];
        $col = 'A';
        foreach ($headers as $h) {
            $sheet->setCellValue($col.'4', $h);
            $sheet->getStyle($col.'4')->getFont()->setBold(true);
            $col++;
        }

        $breakdown = [];
        foreach ($orders as $order) {
            if ($order->grand_total > 250000) {
                continue;
            }
            $taxableValue = max(0, $order->subtotal - $order->vendor_discount_amount - ($order->coupon_discount_amount ?? 0));
            $rate = $order->tax_amount > 0 && $taxableValue > 0 ? round(($order->tax_amount / $taxableValue) * 100, 0) : 5;
            $key = "{$pos}|{$rate}";
            if (! isset($breakdown[$key])) {
                $breakdown[$key] = ['taxable' => 0, 'cgst' => 0, 'sgst' => 0];
            }
            $breakdown[$key]['taxable'] += $taxableValue;
            $breakdown[$key]['cgst']   += $order->tax_amount / 2;
            $breakdown[$key]['sgst']   += $order->tax_amount / 2;
        }

        $row = 5;
        foreach ($breakdown as $key => $vals) {
            [$state, $rate] = explode('|', $key);
            $sheet->setCellValue("A{$row}", $gstin);
            $sheet->setCellValue("B{$row}", $state);
            $sheet->setCellValue("C{$row}", $rate.'%');
            $sheet->setCellValue("D{$row}", round($vals['taxable'], 2));
            $sheet->setCellValue("E{$row}", 0);
            $sheet->setCellValue("F{$row}", round($vals['cgst'], 2));
            $sheet->setCellValue("G{$row}", round($vals['sgst'], 2));
            $row++;
        }

        foreach (range('A', 'G') as $c) {
            $sheet->getColumnDimension($c)->setAutoSize(true);
        }
    }

    private function buildNilExemptSheet(Spreadsheet $spreadsheet, $orders, Restaurant $restaurant, string $gstin): void
    {
        $sheet = $spreadsheet->createSheet();
        $sheet->setTitle('Nil/Exempt (Table 8)');

        $sheet->setCellValue('A1', "GSTR-1 Nil Rated, Exempt, Non-GST Supplies");
        $sheet->setCellValue('A2', "Restaurant: {$restaurant->name}");
        $sheet->mergeCells('A1:E1');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(12);

        $headers = ['Description', 'Nil Rated', 'Exempted', 'Non-GST', 'Total'];
        $col = 'A';
        foreach ($headers as $h) {
            $sheet->setCellValue($col.'4', $h);
            $sheet->getStyle($col.'4')->getFont()->setBold(true);
            $col++;
        }

        $nilCount   = $orders->filter(fn ($o) => $o->tax_amount <= 0)->count();
        $nilValue   = $orders->filter(fn ($o) => $o->tax_amount <= 0)->sum('grand_total');
        $exemptCount = 0;
        $exemptValue = 0;
        $nonGstCount = $gstin === 'UNREGISTERED' ? $orders->count() : 0;
        $nonGstValue = $gstin === 'UNREGISTERED' ? $orders->sum('grand_total') : 0;

        $totals = [
            ['Nil Rated Supplies', $nilCount, $nilValue],
            ['Exempt Supplies', $exemptCount, $exemptValue],
            ['Non-GST Supplies', $nonGstCount, $nonGstValue],
        ];

        $row = 5;
        foreach ($totals as $t) {
            $sheet->setCellValue("A{$row}", $t[0]);
            $sheet->setCellValue("B{$row}", $t[1]);
            $sheet->setCellValue("C{$row}", $t[2]);
            $sheet->setCellValue("D{$row}", $t[1] + $t[2]);
            $sheet->setCellValue("E{$row}", round($t[2], 2));
            $row++;
        }

        foreach (range('A', 'E') as $c) {
            $sheet->getColumnDimension($c)->setAutoSize(true);
        }
    }

    private function buildHsnSheet(Spreadsheet $spreadsheet, $orders, Restaurant $restaurant, string $gstin): void
    {
        $sheet = $spreadsheet->createSheet();
        $sheet->setTitle('HSN Summary (Table 12)');

        $sheet->setCellValue('A1', "GSTR-1 HSN-wise Summary");
        $sheet->setCellValue('A2', "Restaurant: {$restaurant->name}");
        $sheet->mergeCells('A1:J1');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(12);

        $headers = ['HSN Code', 'Description', 'UQC', 'Qty', 'Total Value', 'Taxable Value', 'IGST', 'CGST', 'SGST', 'CESS'];
        $col = 'A';
        foreach ($headers as $h) {
            $sheet->setCellValue($col.'4', $h);
            $sheet->getStyle($col.'4')->getFont()->setBold(true);
            $col++;
        }

        $hsnGroups = [];
        foreach ($orders as $order) {
            foreach ($order->items as $item) {
                $hsn = $item->menuItem->hsn_code ?? '210690';
                $desc = $item->menuItem->name ?? $item->item_name;
                if (! isset($hsnGroups[$hsn])) {
                    $hsnGroups[$hsn] = ['desc' => $desc, 'qty' => 0, 'value' => 0, 'taxable' => 0, 'cgst' => 0, 'sgst' => 0];
                }
                $hsnGroups[$hsn]['qty']     += $item->quantity;
                $hsnGroups[$hsn]['value']   += $item->total_price;
                $hsnGroups[$hsn]['taxable'] += $item->total_price;
                $gstPct = $item->menuItem->gst_percent ?? 5;
                $hsnGroups[$hsn]['cgst'] += round($item->total_price * ($gstPct / 2 / 100), 2);
                $hsnGroups[$hsn]['sgst'] += round($item->total_price * ($gstPct / 2 / 100), 2);
            }
        }

        $row = 5;
        foreach ($hsnGroups as $hsn => $data) {
            $sheet->setCellValue("A{$row}", $hsn);
            $sheet->setCellValue("B{$row}", $data['desc']);
            $sheet->setCellValue("C{$row}", 'KGS');
            $sheet->setCellValue("D{$row}", $data['qty']);
            $sheet->setCellValue("E{$row}", round($data['value'], 2));
            $sheet->setCellValue("F{$row}", round($data['taxable'], 2));
            $sheet->setCellValue("G{$row}", 0);
            $sheet->setCellValue("H{$row}", round($data['cgst'], 2));
            $sheet->setCellValue("I{$row}", round($data['sgst'], 2));
            $sheet->setCellValue("J{$row}", 0);
            $row++;
        }

        foreach (range('A', 'J') as $c) {
            $sheet->getColumnDimension($c)->setAutoSize(true);
        }
    }

    private function buildTaxLiabilitySheet(Spreadsheet $spreadsheet, Restaurant $restaurant, Carbon $start, Carbon $end, string $gstin): void
    {
        $sheet = $spreadsheet->createSheet();
        $sheet->setTitle('Tax Liability Summary');

        $sheet->setCellValue('A1', "Tax Liability Summary — {$restaurant->name}");
        $sheet->setCellValue('A2', "Period: {$start->format('F Y')} | GSTIN: {$gstin}");
        $sheet->mergeCells('A1:F1');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(12);

        $headers = ['Description', 'SAC', 'Taxable Value', 'CGST @ 9%', 'SGST @ 9%', 'Total'];
        $col = 'A';
        foreach ($headers as $h) {
            $sheet->setCellValue($col.'4', $h);
            $sheet->getStyle($col.'4')->getFont()->setBold(true);
            $col++;
        }

        $invTypes = [
            'commission' => ['desc' => 'Commission — Online Ordering Platform', 'sac' => '998599'],
            'ads'        => ['desc' => 'Sponsored Advertisement Services', 'sac' => '998366'],
            'recovery'   => ['desc' => 'Recovery Charges', 'sac' => '998599'],
        ];

        $row = 5;
        $grandTaxable = 0;
        $grandCgst = 0;
        $grandSgst = 0;

        foreach ($invTypes as $type => $info) {
            $invoices = \App\Models\Invoice::where('restaurant_id', $restaurant->id)
                ->where('type', $type)
                ->whereBetween('period_start', [$start->toDateString(), $end->toDateString()])
                ->get();

            $taxable = $invoices->sum('amount');
            $cgst    = $invoices->sum('cgst_amount');
            $sgst    = $invoices->sum('sgst_amount');
            $total   = $taxable + $cgst + $sgst;

            if ($taxable <= 0) {
                continue;
            }

            $sheet->setCellValue("A{$row}", $info['desc']);
            $sheet->setCellValue("B{$row}", $info['sac']);
            $sheet->setCellValue("C{$row}", round($taxable, 2));
            $sheet->setCellValue("D{$row}", round($cgst, 2));
            $sheet->setCellValue("E{$row}", round($sgst, 2));
            $sheet->setCellValue("F{$row}", round($total, 2));
            $row++;

            $grandTaxable += $taxable;
            $grandCgst    += $cgst;
            $grandSgst    += $sgst;
        }

        $row++;
        $sheet->setCellValue("A{$row}", 'TOTAL');
        $sheet->getStyle("A{$row}:F{$row}")->getFont()->setBold(true);
        $sheet->setCellValue("C{$row}", round($grandTaxable, 2));
        $sheet->setCellValue("D{$row}", round($grandCgst, 2));
        $sheet->setCellValue("E{$row}", round($grandSgst, 2));
        $sheet->setCellValue("F{$row}", round($grandTaxable + $grandCgst + $grandSgst, 2));

        foreach (range('A', 'F') as $c) {
            $sheet->getColumnDimension($c)->setAutoSize(true);
        }
    }

    private function buildAtGlanceSheet(Spreadsheet $spreadsheet, Restaurant $restaurant, $orders, string $period, string $restGstin, string $aggGstin): void
    {
        $sheet = $spreadsheet->createSheet();
        $sheet->setTitle('At-a-glance');

        $sheet->setCellValue('A1', "GSTR-1 At-a-glance");
        $sheet->setCellValue('A2', "Aggregator: UtterLazy ({$aggGstin})");
        $sheet->setCellValue('A3', "Restaurant: {$restaurant->name} ({$restGstin})");
        $sheet->setCellValue('A4', "Period: {$period}");
        $sheet->mergeCells('A1:C1');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(12);

        $data = [
            'Total Orders' => $orders->count(),
            'Total Order Value' => '₹'.number_format($orders->sum('grand_total'), 2),
            'Total Discounts' => '₹'.number_format($orders->sum('vendor_discount_amount') + $orders->sum('coupon_discount_amount'), 2),
            'Total Tax Collected' => '₹'.number_format($orders->sum('tax_amount'), 2),
            'Net Payable to Restaurant' => '₹'.number_format($orders->sum('net_payout'), 2),
        ];

        $row = 6;
        foreach ($data as $label => $value) {
            $sheet->setCellValue("A{$row}", $label);
            $sheet->setCellValue("B{$row}", $value);
            $sheet->getStyle("A{$row}")->getFont()->setBold(true);
            $row++;
        }

        $sheet->getColumnDimension('A')->setWidth(30);
        $sheet->getColumnDimension('B')->setWidth(25);
    }

    private function placeOfSupply(Restaurant $restaurant): string
    {
        return SystemConfig::getValue('place_of_supply', '29-Karnataka');
    }
}
