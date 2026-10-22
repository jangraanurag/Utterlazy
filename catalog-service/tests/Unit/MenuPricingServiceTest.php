<?php

namespace Tests\Unit;

use App\Models\AddOnItem;
use App\Models\MenuItem;
use App\Models\RestaurantPackagingSetting;
use App\Models\Variant;
use App\Services\MenuPricingService;
use Illuminate\Support\Collection;
use Tests\TestCase;

class MenuPricingServiceTest extends TestCase
{
    private MenuPricingService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new MenuPricingService;
    }

    // ─── Item Price Calculation ───

    public function test_calculates_price_without_variant()
    {
        $item = new MenuItem(['price' => 100, 'tax_type' => 'NONE', 'tax_included' => false]);
        $result = $this->service->calculateItemPrice($item);

        $this->assertEquals(100, $result['item_price']);
        $this->assertEquals(0, $result['variant_impact']);
        $this->assertEquals(0, $result['tax_amount']);
        $this->assertEquals(100, $result['final_price']);
    }

    public function test_calculates_price_with_variant_impact()
    {
        $item = new MenuItem(['price' => 100, 'tax_type' => 'NONE', 'tax_included' => false]);
        $variant = new Variant(['price_impact' => 50]);

        $result = $this->service->calculateItemPrice($item, $variant);

        $this->assertEquals(150, $result['item_price']);
        $this->assertEquals(50, $result['variant_impact']);
        $this->assertEquals(150, $result['final_price']);
    }

    public function test_calculates_price_with_negative_variant_impact()
    {
        $item = new MenuItem(['price' => 100, 'tax_type' => 'NONE', 'tax_included' => false]);
        $variant = new Variant(['price_impact' => -30]);

        $result = $this->service->calculateItemPrice($item, $variant);

        $this->assertEquals(70, $result['item_price']);
        $this->assertEquals(-30, $result['variant_impact']);
    }

    // ─── Tax: Excluded (added on top) ───

    public function test_handles_gst5_tax_excluded()
    {
        $item = new MenuItem(['price' => 200, 'tax_type' => 'GST_5', 'tax_included' => false]);
        $result = $this->service->calculateItemPrice($item);

        $this->assertEquals(200, $result['item_price']);
        $this->assertEquals(10, $result['tax_amount']);   // 200 * 0.05
        $this->assertFalse($result['tax_included']);
        $this->assertEquals(210, $result['final_price']); // 200 + 10
    }

    public function test_handles_gst18_tax_excluded()
    {
        $item = new MenuItem(['price' => 500, 'tax_type' => 'GST_18', 'tax_included' => false]);
        $result = $this->service->calculateItemPrice($item);

        $this->assertEquals(500, $result['item_price']);
        $this->assertEquals(90, $result['tax_amount']);   // 500 * 0.18
        $this->assertEquals(590, $result['final_price']);
    }

    public function test_handles_none_tax()
    {
        $item = new MenuItem(['price' => 300, 'tax_type' => 'NONE', 'tax_included' => false]);
        $result = $this->service->calculateItemPrice($item);

        $this->assertEquals(0, $result['tax_amount']);
        $this->assertEquals(300, $result['final_price']);
    }

    // ─── Tax: Included (back-calculate) ───

    public function test_handles_gst5_tax_included()
    {
        $item = new MenuItem(['price' => 210, 'tax_type' => 'GST_5', 'tax_included' => true]);
        $result = $this->service->calculateItemPrice($item);

        $this->assertEquals(210, $result['item_price']);
        $this->assertTrue($result['tax_included']);
        // Tax component = 210 * 0.05 / 1.05 = 10
        $this->assertEquals(10.0, $result['tax_amount']);
        $this->assertEquals(210, $result['final_price']);
    }

    public function test_handles_gst18_tax_included()
    {
        $item = new MenuItem(['price' => 590, 'tax_type' => 'GST_18', 'tax_included' => true]);
        $result = $this->service->calculateItemPrice($item);

        $this->assertTrue($result['tax_included']);
        // Tax component = 590 * 0.18 / 1.18 = 90
        $this->assertEquals(90.0, $result['tax_amount']);
        $this->assertEquals(590, $result['final_price']);
    }

    // ─── Add-On Calculations ───

    public function test_calculates_add_on_total()
    {
        $addOns = collect([
            new AddOnItem(['id' => '1', 'name' => 'Extra Cheese', 'price' => 30, 'tax_type' => 'GST_5']),
            new AddOnItem(['id' => '2', 'name' => 'Extra Sauce', 'price' => 20, 'tax_type' => 'NONE']),
        ]);

        $result = $this->service->calculateAddOnTotal($addOns);

        $this->assertEquals(50, $result['subtotal']);          // 30 + 20
        $this->assertEquals(1.5, $result['total_tax']);        // 30*0.05 = 1.5
        $this->assertEquals(51.5, $result['grand_total']);     // 50 + 1.5
        $this->assertCount(2, $result['items']);
    }

    public function test_calculates_empty_add_on_total()
    {
        $result = $this->service->calculateAddOnTotal(new Collection);
        $this->assertEquals(0, $result['subtotal']);
        $this->assertEquals(0, $result['total_tax']);
        $this->assertEquals(0, $result['grand_total']);
    }

    // ─── Packaging Charge ───

    public function test_item_level_packaging_overrides_order_level()
    {
        $item = new MenuItem([
            'packaging_charge_level' => 'item_level',
            'packaging_charge_value' => 10,
            'packaging_tax_type' => 'GST_5',
        ]);

        $result = $this->service->resolvePackagingCharge($item);

        $this->assertEquals('item_level', $result['level']);
        $this->assertEquals(10, $result['computed_charge']);
        $this->assertEquals(0.5, $result['tax_amount']);       // 10 * 0.05
        $this->assertEquals(10.5, $result['total']);
    }

    public function test_order_level_fixed_packaging()
    {
        $item = new MenuItem(['packaging_charge_level' => null]);
        $orderPackaging = new RestaurantPackagingSetting([
            'charge_type' => 'fixed',
            'charge_value' => 20,
            'tax_type' => 'GST_5',
            'is_active' => true,
        ]);

        $result = $this->service->resolvePackagingCharge($item, $orderPackaging);

        $this->assertEquals('order_level', $result['level']);
        $this->assertEquals(20, $result['computed_charge']);
        $this->assertEquals(1.0, $result['tax_amount']);
        $this->assertEquals(21, $result['total']);
    }

    public function test_order_level_percentage_packaging()
    {
        $item = new MenuItem(['packaging_charge_level' => null]);
        $orderPackaging = new RestaurantPackagingSetting([
            'charge_type' => 'percentage',
            'charge_value' => 5,
            'tax_type' => 'NONE',
            'is_active' => true,
        ]);

        $result = $this->service->resolvePackagingCharge($item, $orderPackaging, 1000);

        $this->assertEquals('order_level', $result['level']);
        $this->assertEquals(50, $result['computed_charge']);   // 1000 * 5%
        $this->assertEquals(0, $result['tax_amount']);
        $this->assertEquals(50, $result['total']);
    }

    public function test_no_packaging_configured()
    {
        $item = new MenuItem(['packaging_charge_level' => null]);
        $result = $this->service->resolvePackagingCharge($item, null);

        $this->assertNull($result['level']);
        $this->assertEquals(0, $result['computed_charge']);
        $this->assertEquals(0, $result['total']);
    }

    // ─── Line Item Calculation ───

    public function test_calculates_line_item_with_variant_addons_and_packaging()
    {
        $item = new MenuItem([
            'price' => 200,
            'tax_type' => 'GST_5',
            'tax_included' => false,
            'packaging_charge_level' => 'item_level',
            'packaging_charge_value' => 15,
            'packaging_tax_type' => 'GST_5',
        ]);
        $variant = new Variant(['price_impact' => 50]);
        $addOns = collect([
            new AddOnItem(['id' => 'a1', 'name' => 'Cheese', 'price' => 40, 'tax_type' => 'GST_5']),
        ]);

        $result = $this->service->calculateLineItem($item, 2, $variant, $addOns);

        // Item price: 200 + 50 = 250, tax: 250*0.05 = 12.5, final: 262.5
        // For qty 2: subtotal = 500, tax = 25, final = 525
        $this->assertEquals(500, $result['item_subtotal']);
        $this->assertEquals(25, $result['item_tax_total']);
        $this->assertEquals(525, $result['item_final_total']);

        // Add-on: 40 + 40*0.05 = 42
        $this->assertEquals(40, $result['addon_subtotal']);
        $this->assertEquals(2, $result['addon_tax_total']);
        $this->assertEquals(42, $result['addon_grand_total']);

        // Packaging: 15 + 15*0.05 = 15.75
        $this->assertEquals(15, $result['packaging']['computed_charge']);
        $this->assertEquals(0.75, $result['packaging']['tax_amount']);

        // Line total: 525 + 42 + 15.75 = 582.75
        $this->assertEquals(582.75, $result['line_total']);
    }

    public function test_calculates_line_item_without_variant_addons_or_packaging()
    {
        $item = new MenuItem([
            'price' => 100,
            'tax_type' => 'NONE',
            'tax_included' => false,
            'packaging_charge_level' => null,
        ]);

        $result = $this->service->calculateLineItem($item, 3, null, new Collection);

        $this->assertEquals(300, $result['item_subtotal']);
        $this->assertEquals(0, $result['item_tax_total']);
        $this->assertEquals(300, $result['item_final_total']);
        $this->assertEquals(0, $result['addon_grand_total']);
        $this->assertEquals(0, $result['packaging_total']);
        $this->assertEquals(300, $result['line_total']);
    }

    // ─── Order Total ───

    public function test_calculates_order_total_from_multiple_lines()
    {
        $line1 = [
            'item_subtotal' => 200,
            'addon_subtotal' => 0,
            'item_tax_total' => 10,
            'addon_tax_total' => 0,
            'packaging' => ['computed_charge' => 5, 'tax_amount' => 0.25],
        ];
        $line2 = [
            'item_subtotal' => 300,
            'addon_subtotal' => 40,
            'item_tax_total' => 15,
            'addon_tax_total' => 2,
            'packaging' => ['computed_charge' => 0, 'tax_amount' => 0],
        ];

        $result = $this->service->calculateOrderTotal([$line1, $line2]);

        // subtotal = 200 + 300 + 40 = 540
        $this->assertEquals(540, $result['subtotal']);
        // total_tax = 10 + 15 + 2 + 0.25 = 27.25
        $this->assertEquals(27.25, $result['total_tax']);
        // total_packaging = 5 + 0 = 5
        $this->assertEquals(5, $result['total_packaging']);
        // grand_total = 540 + 27.25 + 5 = 572.25
        $this->assertEquals(572.25, $result['grand_total']);
    }
}
