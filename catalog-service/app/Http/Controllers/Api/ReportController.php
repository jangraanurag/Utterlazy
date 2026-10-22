<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Rider;
use App\Services\AuditLogService;
use App\Services\PiiAccessLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class ReportController extends Controller
{
    public function __construct(private readonly PiiAccessLogger $pii)
    {
    }

    public function exportOrders(Request $request)
    {
        $query = Order::with(['restaurant:id,name', 'user:id,name,phone', 'rider:id,name'])
            ->whereBetween('created_at', [$request->start_date ?? now()->startOfMonth(), $request->end_date ?? now()]);

        if ($request->status) {
            $query->where('status', $request->status);
        }
        if ($request->restaurant_id) {
            $query->where('restaurant_id', $request->restaurant_id);
        }

        $orders = $query->get();

        $this->pii->log(
            $request->user(),
            'export',
            'orders',
            null,
            $request->only(['start_date', 'end_date', 'status', 'restaurant_id']),
            $orders->count(),
            'admin_orders_export',
        );

        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Orders');

        $headers = ['Order ID', 'Date', 'Restaurant', 'Customer', 'Phone', 'Rider', 'Status', 'Items', 'Subtotal', 'Delivery Fee', 'Commission', 'Total', 'Payment', 'Created At'];
        $sheet->fromArray([$headers], null, 'A1');
        $sheet->getStyle('A1:'.chr(64 + count($headers)).'1')->getFont()->setBold(true);

        $row = 2;
        foreach ($orders as $order) {
            $sheet->setCellValue("A{$row}", $order->id);
            $sheet->setCellValue("B{$row}", $order->created_at->format('Y-m-d'));
            $sheet->setCellValue("C{$row}", $order->restaurant?->name);
            $sheet->setCellValue("D{$row}", $order->user?->name);
            $sheet->setCellValue("E{$row}", $order->user?->phone);
            $sheet->setCellValue("F{$row}", $order->rider?->name);
            $sheet->setCellValue("G{$row}", $order->status);
            $sheet->setCellValue("H{$row}", $order->items->sum('quantity'));
            $sheet->setCellValue("I{$row}", $order->subtotal);
            $sheet->setCellValue("J{$row}", $order->delivery_fee_customer);
            $sheet->setCellValue("K{$row}", $order->platform_commission);
            $sheet->setCellValue("L{$row}", $order->grand_total);
            $sheet->setCellValue("M{$row}", $order->payment_mode);
            $sheet->setCellValue("N{$row}", $order->created_at->toDateTimeString());
            $row++;
        }

        $filename = 'orders_export_'.now()->format('Ymd_His').'.xlsx';

        return $this->downloadSpreadsheet($spreadsheet, $filename);
    }

    public function exportRevenue(Request $request)
    {
        $startDate = $request->start_date ?? now()->startOfMonth();
        $endDate = $request->end_date ?? now();

        $dailyRevenue = Order::where('status', 'delivered')
            ->whereBetween('updated_at', [$startDate, $endDate])
            ->select(
                DB::raw('DATE(updated_at) as date'),
                DB::raw('COUNT(*) as orders'),
                DB::raw('SUM(grand_total) as revenue'),
                DB::raw('SUM(platform_commission) as commission'),
                DB::raw('SUM(delivery_fee_platform) as delivery_fee_earned')
            )
            ->groupBy(DB::raw('DATE(updated_at)'))
            ->orderBy('date')
            ->get();

        $this->pii->log(
            $request->user(),
            'export',
            'revenue',
            null,
            ['start_date' => (string) $startDate, 'end_date' => (string) $endDate],
            $dailyRevenue->count(),
            'admin_revenue_export',
        );

        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Revenue');

        $headers = ['Date', 'Orders', 'Gross Revenue', 'Commission', 'Delivery Fee Earned', 'Net Revenue'];
        $sheet->fromArray([$headers], null, 'A1');
        $sheet->getStyle('A1:F1')->getFont()->setBold(true);

        $row = 2;
        foreach ($dailyRevenue as $day) {
            $sheet->setCellValue("A{$row}", $day->date);
            $sheet->setCellValue("B{$row}", $day->orders);
            $sheet->setCellValue("C{$row}", $day->revenue);
            $sheet->setCellValue("D{$row}", $day->commission);
            $sheet->setCellValue("E{$row}", $day->delivery_fee_earned);
            $sheet->setCellValue("F{$row}", $day->revenue - $day->commission - $day->delivery_fee_earned);
            $row++;
        }

        $filename = 'revenue_export_'.now()->format('Ymd_His').'.xlsx';
        AuditLogService::log('export_revenue', 'reports', 'export', null, null, ['start' => $startDate, 'end' => $endDate]);

        return $this->downloadSpreadsheet($spreadsheet, $filename);
    }

    public function exportRiders(Request $request)
    {
        $riders = Rider::withCount(['payouts', 'assignments as accepted_count' => fn ($q) => $q->where('status', 'accepted')])
            ->get()
            ->map(fn ($r) => [
                'id' => $r->id,
                'name' => $r->name,
                'phone' => $r->phone,
                'email' => $r->email,
                'vehicle' => $r->vehicle_type,
                'delivery_mode' => $r->delivery_mode ?? 'scooter',
                'is_verified' => $r->is_verified ? 'Yes' : 'No',
                'is_online' => $r->is_online ? 'Yes' : 'No',
                'total_deliveries' => $r->total_deliveries,
                'total_earnings' => $r->total_earnings,
                'avg_rating' => $r->avg_rating,
                'acceptance_rate' => $r->acceptance_rate.'%',
                'tier' => $r->rider_tier ?? 'bronze',
                'joined_at' => $r->created_at->format('Y-m-d'),
            ]);

        $this->pii->log(
            $request->user(),
            'export',
            'riders',
            null,
            [],
            $riders->count(),
            'admin_riders_export',
        );

        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Riders');

        $headers = array_keys($riders->first() ?? ['No data']);
        $sheet->fromArray([$headers], null, 'A1');
        $sheet->getStyle('A1:'.chr(64 + count($headers)).'1')->getFont()->setBold(true);

        $sheet->fromArray($riders->toArray(), null, 'A2');

        $filename = 'riders_export_'.now()->format('Ymd_His').'.xlsx';
        AuditLogService::log('export_riders', 'reports', 'export');

        return $this->downloadSpreadsheet($spreadsheet, $filename);
    }

    protected function downloadSpreadsheet(Spreadsheet $spreadsheet, string $filename)
    {
        $writer = new Xlsx($spreadsheet);
        ob_start();
        $writer->save('php://output');
        $content = ob_get_clean();

        return response($content, 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            'Content-Length' => strlen($content),
        ]);
    }
}
