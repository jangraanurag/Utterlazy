<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\Recovery;
use App\Models\Restaurant;
use App\Services\Gstr1ReportService;
use App\Services\InvoiceGenerationService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class InvoiceController extends Controller
{
    public function __construct(
        private InvoiceGenerationService $invoiceService,
        private Gstr1ReportService $gstr1Service
    ) {}

    // ─── Admin: Generate single invoice ─────────────────────────────────

    public function generateCustom(Request $request)
    {
        $request->validate([
            'restaurant_id' => 'required|exists:restaurants,id',
            'type'          => 'required|in:commission,ads,recovery,sponsorship',
            'invoice_number' => 'nullable|string',
            'amount'        => 'required_if:type,sponsorship|nullable|numeric|min:0.01',
            'period_start'  => 'required|date',
            'period_end'    => 'required|date|after_or_equal:period_start',
        ]);

        $restaurant = Restaurant::findOrFail($request->restaurant_id);
        $start = Carbon::parse($request->period_start);
        $end   = Carbon::parse($request->period_end);

        $invoice = match ($request->type) {
            'commission' => $this->invoiceService->generateCommissionInvoice($restaurant, $start, $end),
            'ads'        => $this->invoiceService->generateAdsInvoice($restaurant, $start, $end),
            'recovery'   => $this->invoiceService->generateRecoveryInvoice($restaurant, $start, $end),
            'sponsorship' => $this->invoiceService->generateSponsorshipInvoice(
                $restaurant,
                $start,
                $end,
                (float) $request->input('amount', 0)
            ),
        };

        if (! $invoice) {
            return response()->json(['message' => 'No data found for this period to generate an invoice.'], 422);
        }

        if ($request->filled('invoice_number')) {
            $invoice->update(['invoice_number' => $request->invoice_number]);
        }

        return response()->json(['message' => 'Invoice generated', 'invoice' => $invoice->load('restaurant')]);
    }

    // ─── Auto-generate weekly invoices (Tuesday cron) ───────────────────

    public function autoGenerateWeekly(Request $request)
    {
        if ($request->filled('restaurant_id')) {
            $restaurant = Restaurant::findOrFail($request->restaurant_id);
            $start = now()->subWeek()->startOfWeek(Carbon::MONDAY);
            $end   = now()->subWeek()->endOfWeek(Carbon::SUNDAY);

            $commission = $this->invoiceService->generateCommissionInvoice($restaurant, $start, $end);
            $ads        = $this->invoiceService->generateAdsInvoice($restaurant, $start, $end);
            $recovery   = $this->invoiceService->generateRecoveryInvoice($restaurant, $start, $end);

            return response()->json([
                'message' => 'Invoices generated for this week',
                'generated' => [
                    'commission' => $commission !== null,
                    'ads'        => $ads !== null,
                    'recovery'   => $recovery !== null,
                ],
            ]);
        }

        $results = $this->invoiceService->generateAllForWeek();

        return response()->json(['message' => 'Weekly invoices generated for all restaurants', 'generated' => $results]);
    }

    // ─── Admin: List invoices with filters ───────────────────────────────

    public function listInvoices(Request $request)
    {
        $query = Invoice::with('restaurant')
            ->orderBy('created_at', 'desc');

        if ($request->filled('restaurant_id')) {
            $query->where('restaurant_id', $request->restaurant_id);
        }
        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('month') && $request->filled('year')) {
            $start = Carbon::create($request->year, $request->month, 1)->startOfDay();
            $end   = $start->copy()->endOfMonth()->endOfDay();
            $query->whereBetween('period_start', [$start, $end]);
        }

        $perPage = min((int) ($request->per_page ?? 20), 100);

        return response()->json($query->paginate($perPage));
    }

    // ─── Admin: Show single invoice ──────────────────────────────────────

    public function showInvoice(Request $request, $id)
    {
        $invoice = Invoice::with('restaurant')->findOrFail($id);

        return response()->json(['invoice' => $invoice]);
    }

    // ─── Admin: Update invoice number ────────────────────────────────────

    public function updateInvoice(Request $request, $id)
    {
        $request->validate([
            'invoice_number' => 'required|string|max:100',
        ]);

        $invoice = Invoice::findOrFail($id);
        if ($invoice->status !== 'pending') {
            return response()->json(['error' => 'Can only update pending invoices'], 422);
        }

        $invoice->update([
            'invoice_number' => $request->invoice_number,
            'entered_by'     => $request->user()?->id,
        ]);

        return response()->json(['message' => 'Invoice updated', 'invoice' => $invoice->load('restaurant')]);
    }

    // ─── Admin: Approve ──────────────────────────────────────────────────

    public function approveInvoice(Request $request, $id)
    {
        $invoice = Invoice::findOrFail($id);
        $invoice->update([
            'status'       => 'approved',
            'approved_by'  => $request->user()?->id,
            'approved_at'  => now(),
            'admin_notes'  => $request->admin_notes ?? $invoice->admin_notes,
        ]);

        return response()->json(['invoice' => $invoice]);
    }

    // ─── Admin: Mark paid ────────────────────────────────────────────────

    public function markInvoicePaid(Request $request, $id)
    {
        $invoice = Invoice::findOrFail($id);
        $invoice->update([
            'status'       => 'paid',
            'admin_notes'  => $request->admin_notes ?? $invoice->admin_notes,
        ]);

        return response()->json(['invoice' => $invoice]);
    }

    // ─── Admin: Cancel ───────────────────────────────────────────────────

    public function cancelInvoice($id)
    {
        $invoice = Invoice::findOrFail($id);
        $invoice->update(['status' => 'cancelled']);

        return response()->json(['invoice' => $invoice]);
    }

    // ─── Admin: Download single invoice PDF ──────────────────────────────

    public function downloadPdf($id)
    {
        $invoice = Invoice::findOrFail($id);

        if ($invoice->pdf_path) {
            $path = storage_path('app/'.$invoice->pdf_path);
            if (file_exists($path)) {
                return response()->download($path);
            }
        }

        $path = $this->invoiceService->generatePdf($invoice);
        if (! $path || ! file_exists($path)) {
            return response()->json(['error' => 'Failed to generate PDF'], 500);
        }

        return response()->download($path);
    }

    // ─── Admin: Download ZIP bundle ──────────────────────────────────────

    public function downloadBundle(Request $request)
    {
        $request->validate([
            'restaurant_id' => 'required|exists:restaurants,id',
            'type'          => 'required|in:commission,ads,recovery',
            'year'          => 'required|integer|min:2020|max:2099',
            'month'         => 'required|integer|min:1|max:12',
        ]);

        $start = Carbon::create($request->year, $request->month, 1)->startOfDay();
        $end   = $start->copy()->endOfMonth()->endOfDay();

        $invoiceIds = Invoice::where('restaurant_id', $request->restaurant_id)
            ->where('type', $request->type)
            ->whereBetween('period_start', [$start, $end])
            ->pluck('id')
            ->toArray();

        if (empty($invoiceIds)) {
            return response()->json(['error' => 'No invoices found for this period'], 404);
        }

        $zipPath = $this->invoiceService->bundlePdfs($invoiceIds);

        return response()->download($zipPath)->deleteFileAfterSend(true);
    }

    // ─── GSTR1 Report ────────────────────────────────────────────────────

    public function gstr1Report(Request $request)
    {
        $request->validate([
            'restaurant_id' => 'required|exists:restaurants,id',
            'year'          => 'required|integer|min:2020|max:2099',
            'month'         => 'required|integer|min:1|max:12',
        ]);

        $restaurant = Restaurant::findOrFail($request->restaurant_id);
        $path = $this->gstr1Service->generateReport($restaurant, $request->year, $request->month);

        return response()->download($path)->deleteFileAfterSend(true);
    }

    // ─── List restaurants for GST dropdown ───────────────────────────────

    public function listGstRestaurants()
    {
        $restaurants = Restaurant::where('is_active', true)
            ->get(['id', 'name', 'gst_number', 'address'])
            ->map(fn ($r) => [
                'id'         => $r->id,
                'name'       => $r->name,
                'gst_number' => $r->gst_number,
                'address'    => $r->address,
            ]);

        return response()->json(['restaurants' => $restaurants]);
    }

    // ─── Recovery Management ──────────────────────────────────────────────

    public function listRecoveries(Request $request)
    {
        $query = Recovery::with('restaurant:id,name')->orderBy('created_at', 'desc');

        if ($request->filled('restaurant_id')) {
            $query->where('restaurant_id', $request->restaurant_id);
        }
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        return response()->json(['recoveries' => $query->paginate(20)]);
    }

    public function createRecovery(Request $request)
    {
        $request->validate([
            'restaurant_id'  => 'required|exists:restaurants,id',
            'amount'         => 'required|numeric|min:0.01',
            'recovery_date'  => 'required|date',
            'reference_type' => 'nullable|string',
            'notes'          => 'nullable|string',
        ]);

        $recovery = Recovery::create($request->only([
            'restaurant_id', 'amount', 'recovery_date', 'reference_type', 'notes',
        ]));

        return response()->json(['recovery' => $recovery->load('restaurant')]);
    }

    public function updateRecovery(Request $request, Recovery $recovery)
    {
        $request->validate([
            'status' => 'nullable|in:pending,invoiced,paid,waived',
            'amount' => 'nullable|numeric|min:0.01',
            'notes'  => 'nullable|string',
        ]);

        $recovery->update($request->only(['status', 'amount', 'notes']));

        return response()->json(['recovery' => $recovery]);
    }

    // ─── VENDOR ENDPOINTS ────────────────────────────────────────────────

    public function vendorInvoices(Request $request)
    {
        $restaurant = $request->user();

        $query = Invoice::where('restaurant_id', $restaurant->id)
            ->orderBy('period_start', 'desc');

        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }
        if ($request->filled('month') && $request->filled('year')) {
            $start = Carbon::create($request->year, $request->month, 1)->startOfDay();
            $end   = $start->copy()->endOfMonth()->endOfDay();
            $query->whereBetween('period_start', [$start, $end]);
        }

        return response()->json(['invoices' => $query->get()]);
    }

    public function vendorDownloadInvoice(Request $request, $id)
    {
        $restaurant = $request->user();
        $invoice = Invoice::where('restaurant_id', $restaurant->id)->findOrFail($id);

        if ($invoice->pdf_path && file_exists(storage_path('app/'.$invoice->pdf_path))) {
            return response()->download(storage_path('app/'.$invoice->pdf_path));
        }

        $path = $this->invoiceService->generatePdf($invoice);
        if (! $path || ! file_exists($path)) {
            return response()->json(['error' => 'Failed to generate PDF'], 500);
        }

        return response()->download($path);
    }

    public function vendorDownloadBundle(Request $request)
    {
        $restaurant = $request->user();
        $request->validate([
            'type'  => 'required|in:commission,ads,recovery',
            'year'  => 'required|integer|min:2020',
            'month' => 'required|integer|min:1|max:12',
        ]);

        $start = Carbon::create($request->year, $request->month, 1)->startOfDay();
        $end   = $start->copy()->endOfMonth()->endOfDay();

        $invoiceIds = Invoice::where('restaurant_id', $restaurant->id)
            ->where('type', $request->type)
            ->whereBetween('period_start', [$start, $end])
            ->pluck('id')
            ->toArray();

        if (empty($invoiceIds)) {
            return response()->json(['error' => 'No invoices found'], 404);
        }

        $zipPath = $this->invoiceService->bundlePdfs($invoiceIds);

        return response()->download($zipPath)->deleteFileAfterSend(true);
    }

    public function vendorGstr1Report(Request $request)
    {
        $restaurant = $request->user();
        $request->validate([
            'year'  => 'required|integer|min:2020',
            'month' => 'required|integer|min:1|max:12',
        ]);

        $path = $this->gstr1Service->generateReport($restaurant, $request->year, $request->month);

        return response()->download($path)->deleteFileAfterSend(true);
    }
}
