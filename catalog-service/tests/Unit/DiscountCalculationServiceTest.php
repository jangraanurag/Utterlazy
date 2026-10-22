<?php

namespace Tests\Unit;

use App\Models\BogoDiscount;
use App\Models\Coupon;
use App\Models\MenuItem;
use App\Models\OptInCampaign;
use App\Services\DiscountCalculationService;
use Tests\TestCase;

class DiscountCalculationServiceTest extends TestCase
{
    private DiscountCalculationService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new DiscountCalculationService;
    }

    // ─── No Discount ───

    public function test_no_discount_when_nothing_applies()
    {
        $result = $this->service->calculateBestDiscount(
            100, collect(), collect(), collect([])
        );
        $this->assertNull($result['discount_type']);
        $this->assertEquals(0, $result['discount_amount']);
    }

    // ─── Coupon ───

    public function test_flat_coupon_discount()
    {
        $coupon = $this->makeCoupon(['type' => 'flat', 'value' => 50]);
        $result = $this->service->calculateBestDiscount(
            200, collect(), collect(), collect([]), $coupon
        );
        $this->assertEquals('coupon', $result['discount_type']);
        $this->assertEquals(50, $result['discount_amount']);
        $this->assertEquals($coupon->id, $result['coupon_id']);
    }

    public function test_percentage_coupon_with_cap()
    {
        $coupon = $this->makeCoupon(['type' => 'percentage', 'value' => 20, 'max_discount' => 100]);
        $result = $this->service->calculateBestDiscount(
            1000, collect(), collect(), collect([]), $coupon
        );
        $this->assertEquals('coupon', $result['discount_type']);
        $this->assertEquals(100, $result['discount_amount']);
    }

    public function test_coupon_min_order_not_met()
    {
        $coupon = $this->makeCoupon(['type' => 'flat', 'value' => 50, 'min_order' => 500]);
        $result = $this->service->calculateBestDiscount(
            200, collect(), collect(), collect([]), $coupon
        );
        $this->assertNull($result['discount_type']);
    }

    public function test_null_coupon_does_not_apply()
    {
        $result = $this->service->calculateBestDiscount(
            200, collect(), collect(), collect([]), null
        );
        $this->assertNull($result['discount_type']);
    }

    // ─── applyDiscount ───

    public function test_apply_discount_never_goes_below_zero()
    {
        $this->assertEquals(0, $this->service->applyDiscount(50, 100));
    }

    public function test_apply_discount()
    {
        $this->assertEquals(400, $this->service->applyDiscount(500, 100));
        $this->assertEquals(200, $this->service->applyDiscount(200, 0));
    }

    // ─── BOGO same-item ───

    public function test_bogo_same_item_discount()
    {
        $item = $this->makeMenuItem('menu-item-1', 100);
        $bogo = $this->makeBogo(['buy_quantity' => 1, 'get_quantity' => 1]);
        $bogo->setRelation('menuItems', collect([$item]));

        $cartItems = collect([
            ['menu_item_id' => 'menu-item-1', 'quantity' => 2, 'unit_price' => 100],
        ]);

        $result = $this->service->calculateBestDiscount(
            200, collect([$bogo]), collect(), $cartItems
        );

        $this->assertEquals('bogo', $result['discount_type']);
        $this->assertEquals(100, $result['discount_amount']);
        $this->assertCount(1, $result['bogo_items']);
        $this->assertEquals(1, $result['bogo_items'][0]['free_quantity']);
    }

    public function test_bogo_not_eligible_when_quantity_too_low()
    {
        $item = $this->makeMenuItem('menu-item-1', 100);
        $bogo = $this->makeBogo(['buy_quantity' => 2, 'get_quantity' => 1]);
        $bogo->setRelation('menuItems', collect([$item]));

        $cartItems = collect([
            ['menu_item_id' => 'menu-item-1', 'quantity' => 1, 'unit_price' => 100],
        ]);

        $result = $this->service->calculateBestDiscount(
            100, collect([$bogo]), collect(), $cartItems
        );
        $this->assertNull($result['discount_type']);
    }

    public function test_bogo_buy_3_get_2()
    {
        $item = $this->makeMenuItem('menu-item-1', 100);
        $bogo = $this->makeBogo(['buy_quantity' => 3, 'get_quantity' => 2]);
        $bogo->setRelation('menuItems', collect([$item]));

        $cartItems = collect([
            ['menu_item_id' => 'menu-item-1', 'quantity' => 5, 'unit_price' => 100],
        ]);

        $result = $this->service->calculateBestDiscount(
            500, collect([$bogo]), collect(), $cartItems
        );
        $this->assertEquals('bogo', $result['discount_type']);
        $this->assertEquals(200, $result['discount_amount']);
    }

    // ─── Opt-In Campaign ───

    public function test_opt_in_campaign_flat_discount()
    {
        $campaign = $this->makeCampaign([
            'name' => 'Diwali Sale',
            'discount_type' => 'flat',
            'discount_value' => 75,
        ]);

        // Mock isRestaurantOptedIn
        $campaignMock = \Mockery::mock($campaign)->makePartial();
        $campaignMock->shouldReceive('isRestaurantOptedIn')->with('rest-1')->andReturn(true);

        $result = $this->service->calculateBestDiscount(
            300, collect(), collect([$campaignMock]), collect([]), null, 'rest-1'
        );

        $this->assertEquals('campaign', $result['discount_type']);
        $this->assertEquals(75, $result['discount_amount']);
    }

    public function test_opt_in_not_opted_in_doesnt_apply()
    {
        $campaign = $this->makeCampaign([
            'name' => 'Diwali Sale',
            'discount_type' => 'flat',
            'discount_value' => 75,
        ]);

        $campaignMock = \Mockery::mock($campaign)->makePartial();
        $campaignMock->shouldReceive('isRestaurantOptedIn')->with('rest-1')->andReturn(false);

        $result = $this->service->calculateBestDiscount(
            300, collect(), collect([$campaignMock]), collect([]), null, 'rest-1'
        );
        $this->assertNull($result['discount_type']);
    }

    // ─── Best discount selection ───

    public function test_best_discount_wins()
    {
        $coupon = $this->makeCoupon(['type' => 'flat', 'value' => 30]);

        $campaign = $this->makeCampaign([
            'name' => 'Big Sale',
            'discount_type' => 'flat',
            'discount_value' => 200,
        ]);
        $campaignMock = \Mockery::mock($campaign)->makePartial();
        $campaignMock->shouldReceive('isRestaurantOptedIn')->with('rest-1')->andReturn(true);

        $result = $this->service->calculateBestDiscount(
            500, collect(), collect([$campaignMock]), collect([]), $coupon, 'rest-1'
        );

        // Campaign gives 200 > coupon gives 30
        $this->assertEquals('campaign', $result['discount_type']);
        $this->assertEquals(200, $result['discount_amount']);
    }

    // ─── Helpers ───

    private function makeCoupon(array $overrides = []): Coupon
    {
        return new Coupon(array_merge([
            'id' => 1,
            'code' => 'TEST',
            'title' => 'Test Coupon',
            'type' => 'flat',
            'value' => 50,
            'min_order' => 0,
            'max_discount' => null,
            'per_user_limit' => 10,
            'times_used' => 0,
            'is_active' => true,
            'valid_from' => now()->subDay(),
            'valid_until' => now()->addDay(),
        ], $overrides));
    }

    private function makeMenuItem(string $id, float $price): MenuItem
    {
        $item = new MenuItem(['name' => 'Test Item', 'price' => $price, 'tax_type' => 'NONE']);
        $item->id = $id;

        return $item;
    }

    private function makeBogo(array $overrides = []): BogoDiscount
    {
        $attrs = array_merge([
            'id' => 1,
            'restaurant_id' => 'rest-1',
            'title' => 'Buy 1 Get 1',
            'buy_quantity' => 1,
            'get_quantity' => 1,
            'user_type' => 'all',
            'all_day' => true,
            'start_date' => now()->subDay()->format('Y-m-d'),
            'end_date' => null,
            'is_active' => true,
        ], $overrides);

        $bogo = new BogoDiscount;
        foreach ($attrs as $key => $val) {
            $bogo->$key = $val;
        }
        // Convert date strings to Carbon
        $bogo->start_date = now()->subDay();

        return $bogo;
    }

    private function makeCampaign(array $overrides = []): OptInCampaign
    {
        $attrs = array_merge([
            'id' => 1,
            'name' => 'Campaign',
            'discount_type' => 'flat',
            'discount_value' => 100,
            'share_platform' => 50,
            'share_restaurant' => 50,
            'start_date' => now()->subDay()->format('Y-m-d'),
            'is_active' => true,
        ], $overrides);

        $campaign = new OptInCampaign;
        foreach ($attrs as $key => $val) {
            $campaign->$key = $val;
        }
        $campaign->start_date = now()->subDay();

        return $campaign;
    }
}
