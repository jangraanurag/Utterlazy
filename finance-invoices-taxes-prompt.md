# Finance → Invoices & Taxes — Implementation Prompt

## Overview
Build a 4-tab Invoices & Taxes module. Add proper CGST/SGST split, PDF invoice generation, ZIP bundling, GSTR1 XLSX export, outlet filter, Tuesday auto-generation, and admin verification flow. Both Admin (Vue) and Restaurant (Flutter) panels.

---

## 1. DATABASE & MODELS

### 1.1 Add Columns to `invoices` Table
Create migration `add_cgst_sgst_to_invoices.php`:
```php
Schema::table('invoices', function (Blueprint $table) {
    $table->decimal('cgst_rate', 5, 2)->default(9.00)->after('amount');
    $table->decimal('cgst_amount', 12, 2)->default(0)->after('cgst_rate');
    $table->decimal('sgst_rate', 5, 2)->default(9.00)->after('cgst_amount');
    $table->decimal('sgst_amount', 12, 2)->default(0)->after('sgst_rate');
    $table->decimal('convenience_fee', 12, 2)->default(0)->after('sgst_amount');
    $table->unsignedBigInteger('entered_by')->nullable()->after('approved_at');
    $table->string('pdf_path')->nullable()->after('entered_by');
    $table->string('excel_path')->nullable()->after('pdf_path');
    // Ensure type supports: commission, sponsorship, recovery, ads
    $table->string('type')->change(); // already varchar, just note in migration comment
});
```

### 1.2 Create `sponsored_ads` Table Migration (if not exists)
Check if `sponsored_listings` table exists. If yes, map it. If no, create:
```php
Schema::create('sponsored_ads', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->foreignUuid('restaurant_id')->constrained('restaurants');
    $table->string('name');
    $table->text('description')->nullable();
    $table->decimal('amount', 12, 2);
    $table->date('start_date');
    $table->date('end_date');
    $table->enum('status', ['active', 'completed', 'cancelled'])->default('active');
    $table->timestamps();
});
```

### 1.3 Create `recoveries` Table Migration
```php
Schema::create('recoveries', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->foreignUuid('restaurant_id')->constrained('restaurants');
    $table->string('reference_type')->nullable(); // order, dispute, penalty, other
    $table->string('reference_id')->nullable();
    $table->decimal('amount', 12, 2);
    $table->text('notes')->nullable();
    $table->date('recovery_date');
    $table->enum('status', ['pending', 'invoiced', 'paid', 'waived'])->default('pending');
    $table->timestamps();
});
```

### 1.4 Create `Recovery` Model
```php
class Recovery extends Model {
    use HasUuids;
    protected $fillable = ['restaurant_id', 'reference_type', 'reference_id', 'amount', 'notes', 'recovery_date', 'status'];
    protected $casts = ['recovery_date' => 'date', 'amount' => 'decimal:2'];
    public function restaurant() { return $this->belongsTo(Restaurant::class); }
}
```

### 1.5 Update `Invoice` Model Fillable
Add to `$fillable`: `cgst_rate`, `cgst_amount`, `sgst_rate`, `sgst_amount`, `convenience_fee`, `entered_by`, `pdf_path`, `excel_path`

### 1.6 Create `SponsoredAd` Model (if table exists)
```php
class SponsoredAd extends Model {
    use HasUuids;
    protected $fillable = ['restaurant_id', 'name', 'description', 'amount', 'start_date', 'end_date', 'status'];
    protected $casts = ['amount' => 'decimal:2', 'start_date' => 'date', 'end_date' => 'date'];
    public function restaurant() { return $this->belongsTo(Restaurant::class); }
}
```

---

## 2. SERVICES (Backend)

### 2.1 `InvoiceGenerationService` (`app/Services/InvoiceGenerationService.php`)
**Purpose:** Generate individual PDF invoices, create invoice DB records, handle ZIP bundling.

```php
class InvoiceGenerationService
{
    // ─── Commission Invoice (Online Ordering) ───
    public function generateCommissionInvoice(Restaurant $restaurant, Carbon $start, Carbon $end): Invoice
    // Logic:
    //   1. Fetch all delivered orders for this restaurant in [start, end]
    //   2. Compute:
    //       grossOrderValue = sum(order.subtotal)
    //       totalDiscount = sum(order.vendor_discount_amount) + sum(order.coupon_discount_amount)
    //       totalTaxCollected = sum(order.tax_amount)
    //       convenienceFee = 0 (hardcoded)
    //       taxableValue = grossOrderValue - totalDiscount - totalTaxCollected - convenienceFee
    //       commission = taxableValue * 0.15 (15%, use restaurant->commission_rate if set)
    //       cgstAmount = commission * 0.09  (9% CGST)
    //       sgstAmount = commission * 0.09  (9% SGST)
    //       totalAmount = commission + cgstAmount + sgstAmount
    //   3. invoice_number = auto-generated: "UTL/COMM/{YYYY-MM}/{restaurant->id first 6 chars}/{sequence}"
    //       sequence = count of commission invoices for this restaurant in this month + 1
    //   4. Check duplicate: if Invoice already exists for same restaurant_id + type='commission' + period, skip
    //   5. Create Invoice record: type='commission', invoice_number, period_start=$start, period_end=$end,
    //       amount=$commission, cgst_rate=9, cgst_amount=$cgstAmount, sgst_rate=9, sgst_amount=$sgstAmount,
    //       commission_rate=15, convenience_fee=0, total_amount=$totalAmount, status='pending'

    // ─── Ads Invoice ───
    public function generateAdsInvoice(Restaurant $restaurant, Carbon $start, Carbon $end): ?Invoice
    // Logic:
    //   1. Fetch sponsored_ads for this restaurant where status='active' AND period overlaps [start, end]
    //      (ad start_date <= end AND ad end_date >= start)
    //   2. adAmount = sum(sponsored_ads.amount for date range)
    //   3. If adAmount == 0, return null (no invoice needed)
    //   4. cgstAmount = adAmount * 0.09, sgstAmount = adAmount * 0.09
    //   5. totalAmount = adAmount + cgstAmount + sgstAmount
    //   6. invoice_number = "UTL/AD/{YYYY-MM}/{restaurant->id first 6 chars}/{sequence}"
    //   7. Check duplicate, create Invoice record: type='ads', amount=adAmount

    // ─── Recovery Invoice ───
    public function generateRecoveryInvoice(Restaurant $restaurant, Carbon $start, Carbon $end): ?Invoice
    // Logic:
    //   1. Fetch recoveries for this restaurant where status='pending' AND recovery_date in [start, end]
    //   2. recoveryAmount = sum(recoveries.amount)
    //   3. If recoveryAmount == 0, return null
    //   4. cgstAmount = recoveryAmount * 0.09, sgstAmount = recoveryAmount * 0.09
    //   5. totalAmount = recoveryAmount + cgstAmount + sgstAmount
    //   6. invoice_number = "UTL/REC/{YYYY-MM}/{restaurant->id first 6 chars}/{sequence}"
    //   7. Check duplicate, create Invoice: type='recovery', amount=recoveryAmount
    //   8. AFTER creation: UPDATE recoveries SET status='invoiced' WHERE those recoveries

    // ─── PDF Generation ───
    public function generatePdf(Invoice $invoice): string  // returns file path
    // Use Barryvdh\DomPDF or PhpOffice\PhpSpreadsheet\Writer\Pdf\Dompdf
    // PDF Layout (GST-compliant tax invoice):
    //   Header: "TAX INVOICE" title, UtterLazy logo (from storage/logo.png), aggregator GSTIN
    //   Restaurant section: Name, Address, GSTIN (restaurant->gst_number)
    //   Invoice Details: Invoice#, Date, Type (Online Ordering Commission / Ad Services / Recovery)
    //   Period: start_date to end_date
    //   Particulars table:
    //     | Sl | Particulars | SAC/HSN | Taxable Value | CGST Rate | CGST Amt | SGST Rate | SGST Amt | Total |
    //   For commission: "Commission for usage of UtterLazy Online Ordering Platform"
    //     SAC: 998599 (E-commerce platform services), taxableValue = commission amount
    //   For ads: "Sponsored Advertisement Services"
    //     SAC: 998366 (Advertising services), taxableValue = ad amount
    //   For recovery: "Recovery Charges — {notes from recoveries}"
    //     SAC: 998599, taxableValue = recovery amount
    //   Bottom: Total in words, Bank Details (UtterLazy bank account, IFSC)
    //   Signature section: "For UtterLazy" with blank authorized signatory line
    //   Footer: "This is a computer-generated invoice."

    // ─── ZIP Bundling ───
    public function bundlePdfs(array $invoiceIds): string  // returns zip path
    // Use ZipArchive
    //   zipName = "invoices_{type}_{YYYY-MM}_{timestamp}.zip"
    //   For each invoiceId:
    //     - If pdf_path exists, add to zip
    //     - If not, generate PDF first, then add
    //   Save to storage/app/invoices/zips/
    //   Return zip file path

    // ─── GSTR1 XLSX Export ───
    // (Delegate to GstController's existing buildGstr1Sheet but break into proper GSTR1 portal tables)
    // See Section 2.2 below

    // ─── Tuesday Auto-Generate ───
    // Tuesday 8:00 AM scheduler (add to routes/console.php):
    //   Schedule::command('invoices:generate-weekly')->tuesdays()->at('08:00');
    public function generateAllForWeek(): void
    {
        $start = now()->subWeek()->startOfWeek(Carbon::MONDAY);
        $end   = now()->subWeek()->endOfWeek(Carbon::SUNDAY);
        // For each active restaurant:
        foreach (Restaurant::where('is_active', true)->cursor() as $restaurant) {
            $this->generateCommissionInvoice($restaurant, $start, $end);
            $this->generateAdsInvoice($restaurant, $start, $end);
            $this->generateRecoveryInvoice($restaurant, $start, $end);
        }
    }
}
```

### 2.2 `Gstr1ReportService` (`app/Services/Gstr1ReportService.php`)
**Purpose:** Generate GSTR1 XLSX with sheets matching GSTR1 portal tables.

```php
class Gstr1ReportService
{
    public function generateReport(Restaurant $restaurant, int $year, int $month): string
    // Generate multi-sheet XLSX matching GSTR1 portal format:
    //
    // Sheet 1: GSTR1 — B2B (Table 4) — if restaurant sells to other GST-registered entities
    //   | GSTIN of Recipient | Invoice No | Invoice Date | Invoice Value | Taxable Value | CESS |
    //   (Note: B2B is rare for food delivery to consumers. Include if any orders have company_gst filled.)
    //
    // Sheet 2: GSTR1 — B2CS (Table 7) — business to consumer (large, >₹2.5L)
    //   | Place of Supply | Invoice No | Invoice Date | Invoice Value | Taxable Value | CESS |
    //   (Group orders by state of delivery address)
    //
    // Sheet 3: GSTR1 — B2CS (Table 7A) — small invoices (<₹2.5L) — SUMMARY
    //   | GSTIN of Supplier | Month | Place of Supply | Rate | Taxable Value | CESS |
    //   Aggregate by GST rate (5%, 18%) and state
    //
    // Sheet 4: GSTR1 — Nil Rated / Exempt (Table 8)
    //   | Nil Rated | Exempted | Non-GST | Total |
    //   Orders where tax_type = 'NONE' or restaurant has no GSTIN
    //
    // Sheet 5: GSTR1 — HSN Summary (Table 12)
    //   | HSN Code | Description | UQC | Qty | Total Value | Taxable Value | Integrated Tax | Central Tax | State Tax | CESS |
    //   GROUP BY menu_item.hsn_code, aggregate quantities and values
    //
    // Sheet 6: GSTR1 — Tax Liability Summary
    //   | Description | Taxable Value | Central Tax | State Tax | Total Tax |
    //   Sections: "Commission on Online Ordering", "Advertising Services", "Recovery Charges"
    //   For each: taxableValue = sum(amount for that invoice type in the period)
    //              centralTax = taxableValue * 0.09 (CGST)
    //              stateTax   = taxableValue * 0.09 (SGST)
    //
    // Sheet 7: GSTR1 — At-a-glance
    //   | Restaurant Name | GSTIN | Period | Total Invoices | Total Taxable Value | Total CGST | Total SGST | Total Tax |
    //
    // Note: Place of Supply = restaurant state (from restaurant.address or restaurant.state field)
    //       If restaurant.state not found, default to aggregator's POS from SystemConfig('place_of_supply')
    //
    // Return file path
}
```

---

## 3. BACKEND API ENDPOINTS

### 3.1 Admin Routes (`routes/api.php` — add to admin prefix)

```php
// ─── Invoices & Taxes (new unified module) ───

// Generate single invoice
Route::post('/invoices/generate', [InvoiceController::class, 'generateCustom']);
// Auto-generate all invoices for current week (Tuesday cron trigger or manual)
Route::post('/invoices/auto-generate-weekly', [InvoiceController::class, 'autoGenerateWeekly']);
// List all invoices with advanced filters
Route::get('/invoices', [InvoiceController::class, 'listInvoices']);
// Get single invoice detail
Route::get('/invoices/{invoice}', [InvoiceController::class, 'showInvoice']);
// Update invoice (admin enters/updates invoice number)
Route::patch('/invoices/{invoice}', [InvoiceController::class, 'updateInvoice']);
// Verify/approve invoice
Route::patch('/invoices/{invoice}/approve', [InvoiceController::class, 'approveInvoice']);
// Mark invoice as paid
Route::patch('/invoices/{invoice}/mark-paid', [InvoiceController::class, 'markInvoicePaid']);
// Cancel invoice
Route::patch('/invoices/{invoice}/cancel', [InvoiceController::class, 'cancelInvoice']);
// Download invoice PDF
Route::get('/invoices/{invoice}/download-pdf', [InvoiceController::class, 'downloadPdf']);
// Download ZIP bundle for a month
Route::get('/invoices/download-bundle', [InvoiceController::class, 'downloadBundle']);
// Download GSTR1 report (XLSX)
Route::get('/gst/gstr1-report', [InvoiceController::class, 'gstr1Report']);
// List restaurants with outlet filter support (for dropdown)
Route::get('/gst/restaurants', [InvoiceController::class, 'listGstRestaurants']);

// Recovery management
Route::get('/recoveries', [InvoiceController::class, 'listRecoveries']);
Route::post('/recoveries', [InvoiceController::class, 'createRecovery']);
Route::patch('/recoveries/{recovery}', [InvoiceController::class, 'updateRecovery']);
```

### 3.2 Vendor Routes (`routes/api.php` — add to vendor prefix)

```php
// Vendor-facing invoice views
Route::get('/invoices', [InvoiceController::class, 'vendorInvoices']);
Route::get('/invoices/{invoice}/download', [InvoiceController::class, 'vendorDownloadInvoice']);
Route::post('/invoices/download-bundle', [InvoiceController::class, 'vendorDownloadBundle']);
Route::get('/gst/gstr1-report', [InvoiceController::class, 'vendorGstr1Report']);
```

### 3.3 Controller Method Specs (`InvoiceController.php` — extend existing)

```php
// ─── generateCustom() — Manual generation for any type
// POST /admin/invoices/generate
// Request: { restaurant_id, type: 'commission'|'ads'|'recovery', invoice_number?, period_start, period_end }
// Response: { message, invoice: {...} }
// Uses InvoiceGenerationService::generateCommissionInvoice / generateAdsInvoice / generateRecoveryInvoice
// If admin provides invoice_number, use it; otherwise auto-generate

// ─── autoGenerateWeekly() — Tuesday cron or manual trigger
// POST /admin/invoices/auto-generate-weekly
// Request: {} or { restaurant_id } (optional, for single restaurant)
// Response: { message, generated: { commission: count, ads: count, recovery: count } }
// Calls InvoiceGenerationService::generateAllForWeek()

// ─── listInvoices() — Advanced listing with filters
// GET /admin/invoices
// Query params: ?type=commission|ads|recovery&status=pending|approved|paid|cancelled
//              &restaurant_id=x&month=2026-07&year=2026&page=1&per_page=20&outlet_id=x
// Response: paginated { data: [...], current_page, last_page, total }
// Each invoice includes: restaurant.name, generated pdf_path existence

// ─── showInvoice() — Single invoice detail
// GET /admin/invoices/{invoice}
// Response: { invoice: {...full columns..., restaurant: {name, gst_number, address}, order_lines: [...] }

// ─── updateInvoice() — Admin updates invoice number / details before verifying
// PATCH /admin/invoices/{invoice}
// Request: { invoice_number?, admin_notes? }
// Response: { message, invoice }
// Only allowed when status = 'pending'

// ─── approveInvoice() — Admin verifies invoice → status becomes 'approved'
// PATCH /admin/invoices/{invoice}/approve
// Sets status='approved', approved_by=auth()->id(), approved_at=now()
// Then shows in restaurant panel

// ─── markInvoicePaid() — Admin marks paid
// PATCH /admin/invoices/{invoice}/mark-paid
// Request: { utr_number?, payment_date? }
// Sets status='paid', records payment info

// ─── downloadPdf() — Single invoice PDF download
// GET /admin/invoices/{invoice}/download-pdf
// Response: PDF file (Content-Type: application/pdf, Content-Disposition: attachment)
// Generates PDF via InvoiceGenerationService if not already cached

// ─── downloadBundle() — ZIP bundle download
// GET /admin/invoices/download-bundle?restaurant_id=x&type=commission&month=2026-07&year=2026
// Response: ZIP file
// Calls InvoiceGenerationService::bundlePdfs(ids)

// ─── gstr1Report() — GSTR1 XLSX download
// GET /admin/gst/gstr1-report?restaurant_id=x&year=2026&month=7
// Response: XLSX file
// Delegates to Gstr1ReportService::generateReport()

// ─── listGstRestaurants() — Restaurant list for dropdown (with outlet support)
// GET /admin/gst/restaurants
// Response: { restaurants: [{id, name, gst_number, address, outlets: [{id, name}]}] }
// Groups by restaurant and lists outlets if they exist

// ─── listRecoveries() / createRecovery() / updateRecovery() — Simple CRUD
// GET /admin/recoveries?restaurant_id=x&status=pending
// POST /admin/recoveries { restaurant_id, amount, recovery_date, notes, reference_type }
// PATCH /admin/recoveries/{recovery} { status }  — mark as waived or revert to pending

// ─── VENDOR ENDPOINTS ───
// vendorInvoices() — GET /vendor/invoices?type=commission&year=2026&month=7
//   Returns invoices for the authenticated restaurant, with status visible
// vendorDownloadInvoice() — GET /vendor/invoices/{invoice}/download
//   Downloads single invoice PDF
// vendorDownloadBundle() — POST /vendor/invoices/download-bundle { type, year, month }
//   Downloads ZIP of all invoices for that month+type
// vendorGstr1Report() — GET /vendor/gst/gstr1-report?year=2026&month=7
//   Downloads GSTR1 XLSX for the vendor's restaurant
```

---

## 4. ADMIN VUE PAGE — `InvoicesTaxes.vue`

**Route:** `/invoices-taxes`
**Sidebar:** Add under "Accounting" section: `Invoices & Taxes`

### 4.1 Layout
```
┌─────────────────────────────────────────────────────────────┐
│  Invoices & Taxes                    [Download GSTR1 XLSX]  │
├─────────────────────────────────────────────────────────────┤
│  Outlet: [All Outlets ▾]    Year: [▾] Level: [Month ▾]     │
├─────────────────────────────────────────────────────────────┤
│  [Online Ordering] [Ads] [Recovery] [GSTR1 Report]          │
├─────────────────────────────────────────────────────────────┤
│  Tab Content (varies per tab)                               │
└─────────────────────────────────────────────────────────────┘
```

### 4.2 State
```js
const activeTab = ref('commission')  // commission | ads | recovery | gstr1
const selectedOutlet = ref(null)     // null = all outlets
const selectedYear = ref(new Date().getFullYear())
const selectedMonth = ref(new Date().getMonth() + 1) // 1-12
const outletList = ref([])           // [{id, name}]
const loading = ref(false)
const error = ref(null)
```

### 4.3 Tab 1: Online Ordering (Commission)
```
┌─────────────────────────────────────────────────────────────┐
│ Year: [2026 ▾]    Month: [July ▾]                           │
│                                                             │
│ Summary Card:                                               │
│ ┌─────────────────┬──────────────┬──────────────┐           │
│ │ Total Orders    │ Gross Value  │ Taxable Value │           │
│ │ 156             │ ₹1,25,000    │ ₹1,06,250    │           │
│ ├─────────────────┼──────────────┼──────────────┤           │
│ │ Commission(15%) │ CGST @ 9%    │ SGST @ 9%    │           │
│ │ ₹15,937.50      │ ₹1,434.38    │ ₹1,434.38    │           │
│ ├─────────────────┴──────────────┴──────────────┤           │
│ │ Total Invoice Value: ₹18,806.25               │           │
│ └───────────────────────────────────────────────┘           │
│                                                             │
│ Invoice Table:                                              │
│ | Inv# | Restaurant | Period | Commission | CGST | SGST |  │
│ |      |            |        |            |      |      |  │
│ | UTL/ | Saffron    | Jul    | ₹15,937.50 | ₹1,4 | ₹1,4 |  │
│ | COMM | Palace     | 1-31   |            | 34   | 34   |  │
│ | ...                                                     │
│                                                             │
│ Buttons: [Download All as ZIP] [Generate Now] [Refresh]     │
└─────────────────────────────────────────────────────────────┘
```

**API calls:**
- `GET /admin/invoices?type=commission&restaurant_id={outlet}&year={year}&month={month}`
- `POST /admin/invoices/generate` → to generate a single invoice (from dropdown or bulk button)
- `POST /admin/invoices/auto-generate-weekly` → bulk generate all
- `GET /admin/invoices/download-bundle?type=commission&restaurant_id={outlet}&year={year}&month={month}`
- `GET /admin/gst/restaurants` → for outlet dropdown

**Summary card** calculates from fetched invoice data:
- totalOrders = sum of all orders counted in these invoices
- grossValue = sum of invoice amounts from order details
- taxableValue = sum(amount) from invoices
- commission = sum(amount)
- cgstTotal = sum(cgst_amount)
- sgstTotal = sum(sgst_amount)
- totalInvoiceValue = sum(total_amount)

### 4.4 Tab 2: Ads
Same layout as Tab 1 but:
- Data source: `type=ads`
- Summary shows: Total Ads, Ad Amount, CGST, SGST, Total
- Table columns: Inv#, Restaurant, Period, Ad Amount, CGST, SGST, Total, Status
- Amount comes from `sponsored_ads` table aggregated by month
- SAC: 998366

### 4.5 Tab 3: Recovery
Same layout as Tab 1 but:
- Data source: `type=recovery`
- Summary shows: Total Recoveries, Recovery Amount, CGST, SGST, Total
- Table columns: Inv#, Restaurant, Period, Recovery Amt, CGST, SGST, Total, Status
- Amount comes from `recoveries` table aggregated by month
- SAC: 998599
- Below the table, a "Recovery Entries" section lists individual recoveries with edit/waive options

### 4.6 Tab 4: GSTR1 Report
```
┌─────────────────────────────────────────────────────────────┐
│ Year: [2026 ▾]    Month: [July ▾]                           │
│                                                             │
│ GSTR1 Report Preview:                                       │
│ ┌───────────────────────────────────────────────────────┐   │
│ │ Restaurant: The Saffron Palace                       │   │
│ │ GSTIN: 27AABCG1234X2Z     Period: July 2026          │   │
│ ├───────────────────────────────────────────────────────┤   │
│ │ Sheet Summary:                                       │   │
│ │  Table 4  — B2B Invoices:           0 records       │   │
│ │  Table 7  — B2CS Large:            3 records       │   │
│ │  Table 7A — B2CS Small Aggregate:   2 records       │   │
│ │  Table 8  — Nil/Exempt:             1 record        │   │
│ │  Table 12 — HSN Summary:           12 records       │   │
│ │  Tax Liability Summary:   CGST ₹1,434 | SGST ₹1,434  │   │
│ └───────────────────────────────────────────────────────┘   │
│                                                             │
│ Buttons: [Download GSTR1 XLSX] [Preview in Table]           │
└─────────────────────────────────────────────────────────────┘
```

### 4.7 Common Modals
- **Generate Invoice Modal**: Select restaurant (outlet), type, invoice_number override, period
- **Invoice Detail Modal**: Full invoice view (same as PDF preview), actions: Approve | Mark Paid | Download PDF | Edit Number
- **Edit Invoice Number Modal**: Simple text field to update invoice_number before approval

---

## 5. RESTAURANT APP (FLUTTER) — `invoices_taxes_screen.dart`

### 5.1 Screen Layout
```
┌──────────────────────────────────────────────────────┐
│  Invoices & Taxes                                    │
├──────────────────────────────────────────────────────┤
│  Year: [2026 ▾]    Month: [July ▾]                   │
├──────────────────────────────────────────────────────┤
│  [Commission] [Ads] [Recovery] [GSTR1]              │
├──────────────────────────────────────────────────────┤
│                                                      │
│  ┌────────────────────────────────────────────┐      │
│  │ Online Ordering Commission                │      │
│  │ July 1 - July 31, 2026                   │      │
│  │                                          │      │
│  │ Commission (15%)       ₹15,937.50       │      │
│  │ CGST @ 9%              ₹ 1,434.38       │      │
│  │ SGST @ 9%              ₹ 1,434.38       │      │
│  │ ─────────────────────────────────        │      │
│  │ Total                   ₹18,806.25       │      │
│  │                                          │      │
│  │ Status: Approved ✓                      │      │
│  │ Invoice #: UTL/COMM/2026-07/saff/01      │      │
│  │                                          │      │
│  │ [Download Invoice PDF] [Download ZIP]    │      │
│  └────────────────────────────────────────────┘      │
│                                                      │
│  ┌────────────────────────────────────────────┐      │
│  │ (Repeat for each invoice)                  │      │
│  └────────────────────────────────────────────┘      │
└──────────────────────────────────────────────────────┘
```

### 5.2 Implementation
```dart
class InvoicesTaxesScreen extends ConsumerStatefulWidget {
  // State: selectedYear, selectedMonth, activeTab (commission/ads/recovery/gstr1)
  // Data: List<InvoiceCard> from API
  // API: VendorRepository.getInvoices(type, year, month) — extended to accept new params
  // Download: Use dio download with ResponseType.bytes, save to Downloads/UtterLazy/
  //          Open with open_filex package
  // GSTR1: Single download button → GET /vendor/gst/gstr1-report?year=2026&month=7
}
```

### 5.3 VendorRepository Methods to Add
```dart
// GET /vendor/invoices?type=commission&year=2026&month=7
Future<Map<String, dynamic>> getInvoices({String? type, int? year, int? month});
// GET /vendor/invoices/{invoice}/download → ResponseType.bytes
Future<List<int>> downloadInvoicePdf(String invoiceId);
// POST /vendor/invoices/download-bundle { type, year, month }
Future<List<int>> downloadInvoiceBundle(String type, int year, int month);
// GET /vendor/gst/gstr1-report?year=2026&month=7
Future<List<int>> downloadGstr1Report(int year, int month);
```

### 5.4 Tab Integration
Add "Invoices & Taxes" tab to VendorShell in main.dart:
- Index: Insert after "Payouts" tab (index 9), shift everything after
- Icon: `Icons.description_rounded`
- Label: `Invoices`
- Update badge/notification logic accordingly

---

## 6. COMMAND & SCHEDULER

### 6.1 `GenerateWeeklyInvoices` Command
```php
class GenerateWeeklyInvoices extends Command {
    protected $signature = 'invoices:generate-weekly {--restaurant=}';
    protected $description = 'Generate weekly commission, ads, and recovery invoices every Tuesday';
    
    public function handle(InvoiceGenerationService $service): void {
        if ($this->option('restaurant')) {
            $restaurant = Restaurant::findOrFail($this->option('restaurant'));
            $start = now()->subWeek()->startOfWeek(Carbon::MONDAY);
            $end   = now()->subWeek()->endOfWeek(Carbon::SUNDAY);
            $service->generateCommissionInvoice($restaurant, $start, $end);
            $service->generateAdsInvoice($restaurant, $start, $end);
            $service->generateRecoveryInvoice($restaurant, $start, $end);
        } else {
            $service->generateAllForWeek();
        }
        $this->info('Weekly invoices generated.');
    }
}
```

### 6.2 Scheduler Entry (`routes/console.php`)
```php
Schedule::command('invoices:generate-weekly')->tuesdays()->at('08:00');
```

---

## 7. OUTLET FILTER IMPLEMENTATION

Since outlets/branches don't exist yet:

**Option A (Minimal — same DB):** Treat each outlet as a separate Restaurant record linked by a `parent_restaurant_id` field. Add migration:
```php
Schema::table('restaurants', function (Blueprint $table) {
    $table->foreignUuid('parent_restaurant_id')->nullable()->constrained('restaurants')->nullOnDelete();
    $table->boolean('is_outlet')->default(false);
});
```
Frontend: The outlet dropdown filters restaurants where `is_outlet = true AND parent_restaurant_id = selected_parent`.

**Option B (Just filter by restaurant):** Skip outlet concept entirely for now. The "Outlet" dropdown becomes a "Restaurant" dropdown. The filter just passes `restaurant_id` to the API. This is the simpler approach that works with the existing schema.

**Recommendation:** Start with Option B (simple restaurant filter). Add Option A migration when outlets are truly needed.

---

## 8. SUMMARY OF FILES TO CREATE/MODIFY

### New Files
| File | Purpose |
|------|---------|
| `database/migrations/add_cgst_sgst_to_invoices.php` | Add CGST/SGST columns to invoices |
| `database/migrations/create_recoveries_table.php` | Recovery entries table |
| `database/migrations/create_sponsored_ads_table.php` | Sponsored ads table (if not exists) |
| `app/Models/Recovery.php` | Recovery model |
| `app/Models/SponsoredAd.php` | SponsoredAd model (if needed) |
| `app/Services/InvoiceGenerationService.php` | Commission/Ads/Recovery invoice + PDF + ZIP logic |
| `app/Services/Gstr1ReportService.php` | GSTR1 multi-sheet XLSX export |
| `app/Console/Commands/GenerateWeeklyInvoices.php` | Tuesday scheduler command |
| `web/admin-web/src/pages/InvoicesTaxes.vue` | 4-tab admin page |
| `mobile/apps/restaurant_app/lib/screens/invoices_taxes_screen.dart` | Restaurant Flutter screen |

### Modify Files
| File | Change |
|------|--------|
| `app/Models/Invoice.php` | Add `cgst_rate`, `cgst_amount`, `sgst_rate`, `sgst_amount`, `convenience_fee`, `entered_by`, `pdf_path`, `excel_path` to fillable |
| `app/Http/Controllers/Api/InvoiceController.php` | Add: `generateCustom`, `showInvoice`, `updateInvoice`, `downloadPdf`, `downloadBundle`, `gstr1Report`, `listGstRestaurants`, `listRecoveries`, `createRecovery`, `updateRecovery`, `vendorInvoices`, `vendorDownloadInvoice`, `vendorDownloadBundle`, `vendorGstr1Report` |
| `routes/api.php` | Add admin + vendor routes for invoices/taxes/recoveries |
| `routes/console.php` | Add `invoices:generate-weekly` schedule |
| `web/admin-web/src/api.ts` | Add invoice-related API functions |
| `web/admin-web/src/main.js` | Add InvoicesTaxes route import + entry |
| `web/admin-web/src/App.vue` | Add sidebar link under Accounting |
| `mobile/packages/api/lib/src/repositories/vendor_repository.dart` | Add `getInvoices`, `downloadInvoicePdf`, `downloadInvoiceBundle`, `downloadGstr1Report` |
| `mobile/apps/restaurant_app/lib/main.dart` | Add InvoicesTaxes tab to VendorShell |

---

## 9. KEY BUSINESS RULES

1. **Commission**: 15% of (gross_order_value - discounts - tax_collected - convenience_fee)
2. **Convenience Fee**: Always ₹0 for now
3. **CGST**: 9% on taxable amount (commission, ad amount, or recovery amount)
4. **SGST**: 9% on taxable amount (same as CGST)
5. **SAC Code**: 998599 for commission/recovery (e-commerce services), 998366 for ads (advertising)
6. **Duplicate Prevention**: Check unique combination of `restaurant_id + type + period_start + period_end` before creating
7. **Invoice Number Format**: `UTL/{TYPE}/{YYYY-MM}/{REST_ID_SHORT}/{SEQ_INCREMENTAL}`
8. **Tuesday 8 AM**: Auto-generate for all active restaurants for the PREVIOUS week (Mon-Sun)
9. **Admin Flow**: Generate → Enter Invoice# → Verify/Approve → Visible in Restaurant Panel
10. **GSTR1 Place of Supply**: Default to restaurant's state. If unknown, use `SystemConfig.get('place_of_supply')` defaulting to '29-Karnataka'
