<?php

namespace Tests\Feature;

use App\Models\Invoice;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\Restaurant;
use App\Models\SystemConfig;
use App\Models\User;
use App\Models\VendorPayment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;
use Tests\TestCase;

class WeeklyPaymentsAndInvoicesTest extends TestCase
{
    use RefreshDatabase;

    public function test_gst_calculations_and_commission_payouts()
    {
        // 1. Setup system config
        SystemConfig::create(['config_key' => 'platform_tax_rate', 'config_value' => '5', 'type' => 'float']);
        SystemConfig::create(['config_key' => 'commission_gst_rate', 'config_value' => '18', 'type' => 'float']);

        // 2. Setup user and restaurants
        $user = User::factory()->create();

        $restaurantWithGst = Restaurant::create([
            'id' => Str::uuid(),
            'name' => 'Gst Restaurant',
            'gst_number' => '27AAAAA1111A1Z1',
            'commission_rate' => 15,
            'is_active' => true,
            'is_accepting_orders' => true,
        ]);

        $restaurantWithoutGst = Restaurant::create([
            'id' => Str::uuid(),
            'name' => 'No Gst Restaurant',
            'gst_number' => null,
            'commission_rate' => 15,
            'is_active' => true,
            'is_accepting_orders' => true,
        ]);

        $item1 = MenuItem::create([
            'id' => Str::uuid(),
            'restaurant_id' => $restaurantWithGst->id,
            'name' => 'Pizza',
            'price' => 100,
        ]);

        $item2 = MenuItem::create([
            'id' => Str::uuid(),
            'restaurant_id' => $restaurantWithoutGst->id,
            'name' => 'Burger',
            'price' => 100,
        ]);

        // 3. Place order at GST restaurant
        $this->actingAs($user);

        $responseGst = $this->postJson('/api/checkout', [
            'restaurant_id' => $restaurantWithGst->id,
            'items' => [
                ['menu_item_id' => $item1->id, 'quantity' => 10],
            ],
            'payment_mode' => 'prepaid',
        ]);

        $responseGst->assertStatus(201);
        $orderGst = Order::find($responseGst->json('order.id'));
        // Subtotal = 1000. Vendor Discount = 50 (5%). Amount after discount = 950. Tax (5% of 950) = 47.5.
        $this->assertEquals(1000, $orderGst->subtotal);
        $this->assertEquals(50, $orderGst->vendor_discount_amount);
        $this->assertEquals(47.5, $orderGst->tax_amount);

        // 4. Place order at NO GST restaurant
        $responseNoGst = $this->postJson('/api/checkout', [
            'restaurant_id' => $restaurantWithoutGst->id,
            'items' => [
                ['menu_item_id' => $item2->id, 'quantity' => 10],
            ],
            'payment_mode' => 'prepaid',
        ]);
        $responseNoGst->assertStatus(201);
        $orderNoGst = Order::find($responseNoGst->json('order.id'));
        // Subtotal = 1000. Vendor Discount = 50. Amount after discount = 950. Tax = 0.
        $this->assertEquals(1000, $orderNoGst->subtotal);
        $this->assertEquals(0, $orderNoGst->tax_amount);

        // Deliver orders to include in invoicing/payouts
        $orderGst->update(['status' => 'delivered']);
        $orderNoGst->update(['status' => 'delivered']);

        // 5. Generate custom invoice for GST restaurant
        $admin = User::factory()->create(['is_admin' => true]);
        $this->actingAs($admin);

        $weekStart = now()->subWeek()->startOfWeek()->toDateString();
        $weekEnd = now()->subWeek()->endOfWeek()->toDateString();

        // Backdate orders to the previous week to trigger payout/invoicing
        $orderGst->created_at = now()->subWeek()->startOfWeek()->addDays(2);
        $orderGst->save();
        $orderNoGst->created_at = now()->subWeek()->startOfWeek()->addDays(2);
        $orderNoGst->save();

        $invoiceResponse = $this->postJson('/api/admin/invoices/generate', [
            'restaurant_id' => $restaurantWithGst->id,
            'type' => 'commission',
            'invoice_number' => 'INV-TEST-001',
            'period_start' => $weekStart,
            'period_end' => $weekEnd,
        ]);
        $invoiceResponse->assertStatus(200);
        $invoice = Invoice::where('invoice_number', 'INV-TEST-001')->first();
        $this->assertNotNull($invoice);
        $this->assertEquals('pending', $invoice->status);
        // Commission = 15% of taxable value (1000 - 50 discount - 47.5 tax) = 15% of 902.5 = 135.38
        $this->assertEquals(135.38, $invoice->amount);
        // GST is CGST 9% (12.18) + SGST 9% (12.18) = 24.36
        $this->assertEquals(24.36, $invoice->gst_amount);
        $this->assertEquals(159.74, $invoice->total_amount);

        // 6. Generate sponsorship invoice
        $sponResponse = $this->postJson('/api/admin/invoices/generate', [
            'restaurant_id' => $restaurantWithGst->id,
            'type' => 'sponsorship',
            'invoice_number' => 'INV-TEST-002',
            'period_start' => $weekStart,
            'period_end' => $weekEnd,
            'amount' => 200,
        ]);
        $sponResponse->assertStatus(200);
        $sponInvoice = Invoice::where('invoice_number', 'INV-TEST-002')->first();
        $this->assertEquals(200, $sponInvoice->amount);
        // GST is 18% of 200 = 36
        $this->assertEquals(36, $sponInvoice->gst_amount);
        $this->assertEquals(236, $sponInvoice->total_amount);

        // 7. Run Wednesday Payout generator command
        $exitCode = Artisan::call('payouts:generate-weekly', [
            '--week-start' => $weekStart,
            '--week-end' => $weekEnd,
        ]);
        $this->assertEquals(0, $exitCode);

        // 8. Verify Payout amount for GST restaurant
        $payout = VendorPayment::where('restaurant_id', $restaurantWithGst->id)
            ->whereDate('week_start', $weekStart)
            ->first();
        $this->assertNotNull($payout);

        // Payout Calculation:
        // Net order value = 950
        // Customer GST = 47.5
        // Total Commission Invoice = 159.74 (135.38 + 12.18 CGST + 12.18 SGST)
        // Total Sponsorship Invoice = 236 (200 + 36 GST)
        // Net Payable = (950 + 47.5) - 159.74 - 236 = 997.5 - 395.74 = 601.76
        $this->assertEquals(601.76, $payout->net_payable);
    }

    public function test_monthly_gst_report_download()
    {
        $restaurant = Restaurant::create([
            'id' => Str::uuid(),
            'name' => 'Monthly Gst Restaurant',
            'gst_number' => '27AAAAA1111A1Z1',
            'commission_rate' => 15,
            'is_active' => true,
            'is_accepting_orders' => true,
        ]);

        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin);

        $responseAdmin = $this->get("/api/admin/gst/report/{$restaurant->id}/2026/06");
        $responseAdmin->assertStatus(200);
        $responseAdmin->assertHeader('Content-Disposition');

        $this->actingAs($restaurant);
        $responseVendor = $this->get('/api/vendor/gst/monthly/2026/06');
        $responseVendor->assertStatus(200);
        $responseVendor->assertHeader('Content-Disposition');
    }
}
