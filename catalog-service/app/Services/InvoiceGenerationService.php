<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\Order;
use App\Models\Recovery;
use App\Models\Restaurant;
use App\Models\SponsoredListing;
use Carbon\Carbon;
use Illuminate\Support\Str;
use ZipArchive;

class InvoiceGenerationService
{
    public function generateCommissionInvoice(Restaurant $restaurant, Carbon $start, Carbon $end): ?Invoice
    {
        $orders = Order::where('restaurant_id', $restaurant->id)
            ->whereBetween('created_at', [$start->startOfDay(), $end->endOfDay()])
            ->whereIn('status', ['delivered', 'completed'])
            ->get();

        if ($orders->isEmpty()) {
            return null;
        }

        $grossOrderValue = $orders->sum('subtotal');
        $totalDiscount    = $orders->sum('vendor_discount_amount') + $orders->sum('coupon_discount_amount');
        $totalTaxCollected = $orders->sum('tax_amount');
        $convenienceFee    = 0;

        $taxableValue = max(0, $grossOrderValue - $totalDiscount - $totalTaxCollected - $convenienceFee);
        $commissionRate = $restaurant->commission_rate ?? 15.00;
        $commission     = round($taxableValue * ($commissionRate / 100), 2);
        $cgstAmount     = round($commission * 0.09, 2);
        $sgstAmount     = round($commission * 0.09, 2);
        $totalAmount    = round($commission + $cgstAmount + $sgstAmount, 2);

        if ($commission <= 0) {
            return null;
        }

        $month = $start->format('Y-m');
        $shortId = substr($restaurant->id, 0, 6);
        $seq = Invoice::where('restaurant_id', $restaurant->id)
            ->where('type', 'commission')
            ->whereYear('period_start', $start->year)
            ->whereMonth('period_start', $start->month)
            ->count() + 1;

        $invoiceNumber = "UTL/COMM/{$month}/{$shortId}/".str_pad((string) $seq, 2, '0', STR_PAD_LEFT);

        return Invoice::updateOrCreate(
            [
                'restaurant_id' => $restaurant->id,
                'type' => 'commission',
                'period_start' => $start->toDateString(),
                'period_end' => $end->toDateString(),
            ],
            [
                'invoice_number' => $invoiceNumber,
                'amount' => $commission,
                'cgst_rate' => 9.00,
                'cgst_amount' => $cgstAmount,
                'sgst_rate' => 9.00,
                'sgst_amount' => $sgstAmount,
                'gst_rate' => 18.00,
                'gst_amount' => $cgstAmount + $sgstAmount,
                'total_amount' => $totalAmount,
                'convenience_fee' => 0,
                'commission_rate' => $commissionRate,
                'status' => 'pending',
            ]
        );
    }

    public function generateAdsInvoice(Restaurant $restaurant, Carbon $start, Carbon $end): ?Invoice
    {
        $ads = SponsoredListing::where('restaurant_id', $restaurant->id)
            ->whereIn('status', ['active', 'ended'])
            ->where(function ($q) use ($start, $end) {
                $q->where(function ($q2) use ($start, $end) {
                    $q2->whereBetween('start_date', [$start->toDateString(), $end->toDateString()]);
                })->orWhere(function ($q2) use ($start, $end) {
                    $q2->whereBetween('end_date', [$start->toDateString(), $end->toDateString()]);
                })->orWhere(function ($q2) use ($start, $end) {
                    $q2->where('start_date', '<=', $start->toDateString())
                       ->where('end_date', '>=', $end->toDateString());
                });
            })
            ->get();

        if ($ads->isEmpty()) {
            return null;
        }

        $adAmount = $ads->sum('current_spend');
        if ($adAmount <= 0) {
            return null;
        }

        $cgstAmount  = round($adAmount * 0.09, 2);
        $sgstAmount  = round($adAmount * 0.09, 2);
        $totalAmount = round($adAmount + $cgstAmount + $sgstAmount, 2);

        $month = $start->format('Y-m');
        $shortId = substr($restaurant->id, 0, 6);
        $seq = Invoice::where('restaurant_id', $restaurant->id)
            ->where('type', 'ads')
            ->whereYear('period_start', $start->year)
            ->whereMonth('period_start', $start->month)
            ->count() + 1;

        $invoiceNumber = "UTL/AD/{$month}/{$shortId}/".str_pad((string) $seq, 2, '0', STR_PAD_LEFT);

        return Invoice::updateOrCreate(
            [
                'restaurant_id' => $restaurant->id,
                'type' => 'ads',
                'period_start' => $start->toDateString(),
                'period_end' => $end->toDateString(),
            ],
            [
                'invoice_number' => $invoiceNumber,
                'amount' => $adAmount,
                'cgst_rate' => 9.00,
                'cgst_amount' => $cgstAmount,
                'sgst_rate' => 9.00,
                'sgst_amount' => $sgstAmount,
                'gst_rate' => 18.00,
                'gst_amount' => $cgstAmount + $sgstAmount,
                'total_amount' => $totalAmount,
                'convenience_fee' => 0,
                'status' => 'pending',
            ]
        );
    }

    public function generateRecoveryInvoice(Restaurant $restaurant, Carbon $start, Carbon $end): ?Invoice
    {
        $recoveries = Recovery::where('restaurant_id', $restaurant->id)
            ->where('status', 'pending')
            ->whereBetween('recovery_date', [$start->toDateString(), $end->toDateString()])
            ->get();

        if ($recoveries->isEmpty()) {
            return null;
        }

        $recoveryAmount = $recoveries->sum('amount');
        $cgstAmount     = round($recoveryAmount * 0.09, 2);
        $sgstAmount     = round($recoveryAmount * 0.09, 2);
        $totalAmount    = round($recoveryAmount + $cgstAmount + $sgstAmount, 2);

        $month = $start->format('Y-m');
        $shortId = substr($restaurant->id, 0, 6);
        $seq = Invoice::where('restaurant_id', $restaurant->id)
            ->where('type', 'recovery')
            ->whereYear('period_start', $start->year)
            ->whereMonth('period_start', $start->month)
            ->count() + 1;

        $invoiceNumber = "UTL/REC/{$month}/{$shortId}/".str_pad((string) $seq, 2, '0', STR_PAD_LEFT);

        $invoice = Invoice::updateOrCreate(
            [
                'restaurant_id' => $restaurant->id,
                'type' => 'recovery',
                'period_start' => $start->toDateString(),
                'period_end' => $end->toDateString(),
            ],
            [
                'invoice_number' => $invoiceNumber,
                'amount' => $recoveryAmount,
                'cgst_rate' => 9.00,
                'cgst_amount' => $cgstAmount,
                'sgst_rate' => 9.00,
                'sgst_amount' => $sgstAmount,
                'gst_rate' => 18.00,
                'gst_amount' => $cgstAmount + $sgstAmount,
                'total_amount' => $totalAmount,
                'convenience_fee' => 0,
                'status' => 'pending',
            ]
        );

        if ($invoice) {
            Recovery::where('restaurant_id', $restaurant->id)
                ->where('status', 'pending')
                ->whereBetween('recovery_date', [$start->toDateString(), $end->toDateString()])
                ->update(['status' => 'invoiced']);
        }

        return $invoice;
    }

    public function generateSponsorshipInvoice(Restaurant $restaurant, Carbon $start, Carbon $end, float $amount): ?Invoice
    {
        if ($amount <= 0) {
            return null;
        }

        $cgstAmount  = round($amount * 0.09, 2);
        $sgstAmount  = round($amount * 0.09, 2);
        $totalAmount = round($amount + $cgstAmount + $sgstAmount, 2);

        $month = $start->format('Y-m');
        $shortId = substr($restaurant->id, 0, 6);
        $seq = Invoice::where('restaurant_id', $restaurant->id)
            ->where('type', 'sponsorship')
            ->whereYear('period_start', $start->year)
            ->whereMonth('period_start', $start->month)
            ->count() + 1;

        $invoiceNumber = "UTL/SPON/{$month}/{$shortId}/".str_pad((string) $seq, 2, '0', STR_PAD_LEFT);

        return Invoice::updateOrCreate(
            [
                'restaurant_id' => $restaurant->id,
                'type' => 'sponsorship',
                'period_start' => $start->toDateString(),
                'period_end' => $end->toDateString(),
            ],
            [
                'invoice_number' => $invoiceNumber,
                'amount' => $amount,
                'cgst_rate' => 9.00,
                'cgst_amount' => $cgstAmount,
                'sgst_rate' => 9.00,
                'sgst_amount' => $sgstAmount,
                'gst_rate' => 18.00,
                'gst_amount' => $cgstAmount + $sgstAmount,
                'total_amount' => $totalAmount,
                'convenience_fee' => 0,
                'status' => 'pending',
            ]
        );
    }

    public function generateAllForWeek(): array
    {
        $start = now()->subWeek()->startOfWeek(Carbon::MONDAY);
        $end   = now()->subWeek()->endOfWeek(Carbon::SUNDAY);
        $results = ['commission' => 0, 'ads' => 0, 'recovery' => 0];

        Restaurant::where('is_active', true)->cursor()->each(function (Restaurant $restaurant) use ($start, $end, &$results) {
            if ($this->generateCommissionInvoice($restaurant, $start, $end)) {
                $results['commission']++;
            }
            if ($this->generateAdsInvoice($restaurant, $start, $end)) {
                $results['ads']++;
            }
            if ($this->generateRecoveryInvoice($restaurant, $start, $end)) {
                $results['recovery']++;
            }
        });

        return $results;
    }

    public function generateAllForMonth(Restaurant $restaurant, int $year, int $month): array
    {
        $start = Carbon::create($year, $month, 1)->startOfDay();
        $end   = $start->copy()->endOfMonth()->endOfDay();
        $results = ['commission' => false, 'ads' => false, 'recovery' => false];

        $results['commission'] = $this->generateCommissionInvoice($restaurant, $start, $end) !== null;
        $results['ads']        = $this->generateAdsInvoice($restaurant, $start, $end) !== null;
        $results['recovery']   = $this->generateRecoveryInvoice($restaurant, $start, $end) !== null;

        return $results;
    }

    public function bundlePdfs(array $invoiceIds): string
    {
        $invoices = Invoice::whereIn('id', $invoiceIds)->get();
        $zipPath = storage_path('app/invoices/zips');
        if (! is_dir($zipPath)) {
            mkdir($zipPath, 0755, true);
        }

        $filename = 'invoices_'.now()->format('Ymd_His').'.zip';
        $fullPath = $zipPath.'/'.$filename;

        $zip = new ZipArchive;
        if ($zip->open($fullPath, ZipArchive::CREATE) !== true) {
            throw new \RuntimeException('Cannot create ZIP file');
        }

        foreach ($invoices as $invoice) {
            if ($invoice->pdf_path && file_exists(storage_path('app/'.$invoice->pdf_path))) {
                $zip->addFile(
                    storage_path('app/'.$invoice->pdf_path),
                    str_replace('/', '-', $invoice->invoice_number).'.pdf'
                );
            } else {
                $pdfPath = $this->generatePdf($invoice);
                if ($pdfPath) {
                    $zip->addFile(
                        $pdfPath,
                        str_replace('/', '-', $invoice->invoice_number).'.pdf'
                    );
                }
            }
        }

        $zip->close();

        return $fullPath;
    }

    public function generatePdf(Invoice $invoice): ?string
    {
        $typeLabel = match ($invoice->type) {
            'commission' => 'Online Ordering Commission',
            'ads'        => 'Sponsored Advertisement Services',
            'recovery'   => 'Recovery Charges',
            'sponsorship' => 'Sponsorship',
            default      => 'Service Invoice',
        };

        $sac = match ($invoice->type) {
            'ads'  => '998366',
            default => '998599',
        };

        $restaurant = $invoice->restaurant;
        $aggGstin  = \App\Models\SystemConfig::getValue('aggregator_gstin', '29AABCU1234X1Z');

        $html = view('invoices.tax-invoice', compact(
            'invoice', 'restaurant', 'typeLabel', 'sac', 'aggGstin'
        ))->render();

        $pdfDir = storage_path('app/invoices/pdfs');
        if (! is_dir($pdfDir)) {
            mkdir($pdfDir, 0755, true);
        }

        $pdfFilename = str_replace('/', '-', $invoice->invoice_number).'.pdf';
        $fullPath = $pdfDir.'/'.$pdfFilename;

        try {
            $dompdf = new \Dompdf\Dompdf(['isRemoteEnabled' => true]);
            $dompdf->loadHtml($html);
            $dompdf->setPaper('A4');
            $dompdf->render();
            file_put_contents($fullPath, $dompdf->output());
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('PDF generation failed: '.$e->getMessage());
            return null;
        }

        $invoice->update(['pdf_path' => 'invoices/pdfs/'.$pdfFilename]);

        return $fullPath;
    }
}
