<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\VendorPayment;
use Carbon\Carbon;
use Illuminate\Http\Request;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class AccountingReportController extends Controller
{
    public function downloadMonthly(Request $request, $year, $month)
    {
        $restaurant = $request->user();

        // Optional parameters: could also download weekly or custom range, but default to monthly.
        $startDate = Carbon::create($year, $month, 1)->startOfMonth();
        $endDate = $startDate->copy()->endOfMonth();

        $orders = Order::where('restaurant_id', $restaurant->id)
            ->where('status', 'delivered')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->with('items.menuItem')
            ->get();

        $invoices = Invoice::where('restaurant_id', $restaurant->id)
            ->whereBetween('period_start', [$startDate, $endDate])
            ->get();

        $payments = VendorPayment::where('restaurant_id', $restaurant->id)
            ->whereBetween('week_start', [$startDate, $endDate])
            ->get();

        $spreadsheet = new Spreadsheet;

        // 1. Summary Sheet
        $sheet1 = $spreadsheet->getActiveSheet();
        $sheet1->setTitle('1. Summary');
        $this->buildSummarySheet($sheet1, $orders, $invoices, $payments, $restaurant);

        // 2. Orders Sheet
        $sheet2 = $spreadsheet->createSheet();
        $sheet2->setTitle('2. Orders');
        $this->buildOrdersSheet($sheet2, $orders);

        // 3. GSTR1 Report
        $sheet3 = $spreadsheet->createSheet();
        $sheet3->setTitle('3. GSTR1');
        $this->buildGstr1Sheet($sheet3, $orders);

        // 4. GSTR3B Report
        $sheet4 = $spreadsheet->createSheet();
        $sheet4->setTitle('4. GSTR3B');
        $this->buildGstr3bSheet($sheet4, $orders, $invoices);

        // 5. Accounting Software Export (Reverse Charge)
        $sheet5 = $spreadsheet->createSheet();
        $sheet5->setTitle('5. Accounting Export');
        $this->buildAccountingSheet($sheet5, $orders, $restaurant);

        // 6. Invoices
        $sheet6 = $spreadsheet->createSheet();
        $sheet6->setTitle('6. Invoices');
        $this->buildInvoicesSheet($sheet6, $invoices);

        // 7. Payments/UTR
        $sheet7 = $spreadsheet->createSheet();
        $sheet7->setTitle('7. Payments & UTR');
        $this->buildPaymentsSheet($sheet7, $payments);

        // Set active sheet index to the first sheet
        $spreadsheet->setActiveSheetIndex(0);

        $filename = "Accounting_Report_{$restaurant->name}_{$year}_{$month}.xlsx";
        $tempFile = tempnam(sys_get_temp_dir(), 'report_');
        $writer = new Xlsx($spreadsheet);
        $writer->save($tempFile);

        return response()->download($tempFile, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend(true);
    }

    private function buildSummarySheet($sheet, $orders, $invoices, $payments, $restaurant)
    {
        $grossRevenue = $orders->sum('subtotal');
        $totalCommission = $invoices->where('type', 'commission')->sum('amount');
        $totalGstOnCommission = $invoices->where('type', 'commission')->sum('gst_amount');
        $totalTcs = round($grossRevenue * 0.01, 2); // Assuming 1% TCS
        $totalTds = round($totalCommission * 0.01, 2); // Assuming 1% TDS on commission
        $netPayable = $payments->sum('net_payable');

        $sheet->setCellValue('A1', 'ACCOUNTING SUMMARY');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);

        $data = [
            ['Total Orders Delivered', $orders->count()],
            ['Gross Sales Revenue', $grossRevenue],
            ['Total Platform Commission', $totalCommission],
            ['GST on Commission', $totalGstOnCommission],
            ['TCS Deducted (1%)', $totalTcs],
            ['TDS Deducted (1%)', $totalTds],
            ['Total Net Payable (Payments)', $netPayable],
        ];

        $row = 3;
        foreach ($data as $item) {
            $sheet->setCellValue("A$row", $item[0]);
            $sheet->setCellValue("B$row", $item[1]);
            $sheet->getStyle("A$row")->getFont()->setBold(true);
            $row++;
        }
        $sheet->getColumnDimension('A')->setAutoSize(true);
        $sheet->getColumnDimension('B')->setAutoSize(true);
    }

    private function buildOrdersSheet($sheet, $orders)
    {
        $headers = ['Order ID', 'Date', 'Item Name', 'Qty', 'Unit Price', 'Discount', 'HSN Code', 'GST %', 'GST Amount', 'Total Line Value'];
        $this->setHeaders($sheet, $headers);

        $row = 2;
        foreach ($orders as $order) {
            foreach ($order->items as $item) {
                $sheet->setCellValue("A$row", $order->id);
                $sheet->setCellValue("B$row", $order->created_at->format('Y-m-d H:i'));
                $sheet->setCellValue("C$row", $item->menuItem ? $item->menuItem->name : 'Unknown Item');
                $sheet->setCellValue("D$row", $item->quantity);
                $sheet->setCellValue("E$row", $item->price);
                $sheet->setCellValue("F$row", 0); // Discount logic
                $sheet->setCellValue("G$row", '9963'); // Default Restaurant Service HSN
                $sheet->setCellValue("H$row", 5); // Assuming 5% GST for food
                $sheet->setCellValue("I$row", round(($item->price * $item->quantity) * 0.05, 2));
                $sheet->setCellValue("J$row", ($item->price * $item->quantity));
                $row++;
            }
        }
    }

    private function buildGstr1Sheet($sheet, $orders)
    {
        // GSTR1 focuses on Outward Supplies
        $headers = ['GSTIN/UIN of Recipient', 'Receiver Name', 'Invoice Number', 'Invoice Date', 'Invoice Value', 'Place Of Supply', 'Reverse Charge', 'Invoice Type', 'E-Commerce GSTIN', 'Rate', 'Taxable Value', 'Cess Amount'];
        $this->setHeaders($sheet, $headers);

        $row = 2;
        foreach ($orders as $order) {
            $sheet->setCellValue("A$row", $order->company_gst ?? ''); // B2B if GST present, else B2C
            $sheet->setCellValue("B$row", $order->company_name ?? $order->customer_name);
            $sheet->setCellValue("C$row", $order->id);
            $sheet->setCellValue("D$row", $order->created_at->format('Y-m-d'));
            $sheet->setCellValue("E$row", $order->grand_total);
            $sheet->setCellValue("F$row", 'State Code Here'); // Implement based on state
            $sheet->setCellValue("G$row", 'N'); // Reverse charge handled in E-commerce specific returns usually, but marking N here for standard order
            $sheet->setCellValue("H$row", $order->company_gst ? 'Regular' : 'B2C');
            $sheet->setCellValue("I$row", env('PLATFORM_GSTIN', '')); // Platform GSTIN
            $sheet->setCellValue("J$row", 5); // Rate
            $sheet->setCellValue("K$row", $order->subtotal);
            $sheet->setCellValue("L$row", 0);
            $row++;
        }
    }

    private function buildGstr3bSheet($sheet, $orders, $invoices)
    {
        $headers = ['Nature of Supplies', 'Total Taxable Value', 'Integrated Tax', 'Central Tax', 'State/UT Tax', 'Cess'];
        $this->setHeaders($sheet, $headers);

        $totalSales = $orders->sum('subtotal');
        $gstSales = round($totalSales * 0.05, 2);
        $cgstSales = $gstSales / 2;
        $sgstSales = $gstSales / 2;

        $sheet->setCellValue('A2', '(a) Outward taxable supplies');
        $sheet->setCellValue('B2', $totalSales);
        $sheet->setCellValue('C2', 0); // Assuming intra-state for now
        $sheet->setCellValue('D2', $cgstSales);
        $sheet->setCellValue('E2', $sgstSales);
        $sheet->setCellValue('F2', 0);

        // Input Tax Credit (ITC) from Invoices (Commission)
        $totalCommGst = $invoices->sum('gst_amount');
        $cgstItc = $totalCommGst / 2;
        $sgstItc = $totalCommGst / 2;

        $sheet->setCellValue('A3', '(4) Eligible ITC');
        $sheet->setCellValue('B3', '');
        $sheet->setCellValue('C3', 0);
        $sheet->setCellValue('D3', $cgstItc);
        $sheet->setCellValue('E3', $sgstItc);
        $sheet->setCellValue('F3', 0);
    }

    private function buildAccountingSheet($sheet, $orders, $restaurant)
    {
        $headers = ['Date', 'Voucher Type', 'Voucher Number', 'Ledger Name (Debit)', 'Amount (Debit)', 'Ledger Name (Credit)', 'Amount (Credit)', 'Narration'];
        $this->setHeaders($sheet, $headers);

        $row = 2;
        // Group by Date for simple daily journal entries
        $grouped = $orders->groupBy(function ($o) {
            return $o->created_at->format('Y-m-d');
        });

        foreach ($grouped as $date => $dayOrders) {
            $totalSales = $dayOrders->sum('subtotal');
            $platformCommission = round($totalSales * ($restaurant->commission_rate / 100), 2);
            $netToRestaurant = $totalSales - $platformCommission;

            // Reverse Charge Entry concept:
            $sheet->setCellValue("A$row", $date);
            $sheet->setCellValue("B$row", 'Journal');
            $sheet->setCellValue("C$row", "JRN-$date");
            $sheet->setCellValue("D$row", 'Platform Receivable A/C');
            $sheet->setCellValue("E$row", $netToRestaurant);
            $sheet->setCellValue("F$row", 'Sales A/C');
            $sheet->setCellValue("G$row", $totalSales);
            $sheet->setCellValue("H$row", "Daily Sales via Platform (Net of Commission $platformCommission)");
            $row++;
        }
    }

    private function buildInvoicesSheet($sheet, $invoices)
    {
        $headers = ['Invoice ID', 'Invoice Number', 'Type', 'Period Start', 'Period End', 'Taxable Amount', 'GST Rate', 'GST Amount', 'Total Amount', 'Status', 'Generated Date'];
        $this->setHeaders($sheet, $headers);

        $row = 2;
        foreach ($invoices as $invoice) {
            $sheet->setCellValue("A$row", $invoice->id);
            $sheet->setCellValue("B$row", $invoice->invoice_number ?? 'PENDING');
            $sheet->setCellValue("C$row", $invoice->type);
            $sheet->setCellValue("D$row", $invoice->period_start ? $invoice->period_start->format('Y-m-d') : '');
            $sheet->setCellValue("E$row", $invoice->period_end ? $invoice->period_end->format('Y-m-d') : '');
            $sheet->setCellValue("F$row", $invoice->amount);
            $sheet->setCellValue("G$row", $invoice->gst_rate);
            $sheet->setCellValue("H$row", $invoice->gst_amount);
            $sheet->setCellValue("I$row", $invoice->total_amount);
            $sheet->setCellValue("J$row", $invoice->status);
            $sheet->setCellValue("K$row", $invoice->created_at->format('Y-m-d H:i'));
            $row++;
        }
    }

    private function buildPaymentsSheet($sheet, $payments)
    {
        $headers = ['Payment ID', 'Week Period', 'Total Order Value', 'Commission Deducted', 'Other Deductions', 'Net Payable', 'Status', 'UTR Number', 'Paid Date'];
        $this->setHeaders($sheet, $headers);

        $row = 2;
        foreach ($payments as $payment) {
            $sheet->setCellValue("A$row", $payment->id);
            $sheet->setCellValue("B$row", $payment->payment_period);
            $sheet->setCellValue("C$row", $payment->total_order_value);
            $sheet->setCellValue("D$row", $payment->total_commission + $payment->commission_gst);
            $sheet->setCellValue("E$row", $payment->total_refunds + $payment->total_sponsorship);
            $sheet->setCellValue("F$row", $payment->net_payable);
            $sheet->setCellValue("G$row", $payment->status);
            $sheet->setCellValue("H$row", $payment->utr_number ?? 'PENDING');
            $sheet->setCellValue("I$row", $payment->paid_at ? $payment->paid_at->format('Y-m-d') : '');
            $row++;
        }
    }

    private function setHeaders($sheet, array $headers)
    {
        $col = 'A';
        foreach ($headers as $header) {
            $sheet->setCellValue("{$col}1", $header);
            $sheet->getStyle("{$col}1")->getFont()->setBold(true);
            $sheet->getColumnDimension($col)->setAutoSize(true);
            $col++;
        }
    }
}
