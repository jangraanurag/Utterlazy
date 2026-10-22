<?php

namespace Tests\Unit;

use App\Services\PaymentService;
use Tests\TestCase;

class PaymentServiceTest extends TestCase
{
    public function test_create_razorpay_order_returns_order_data()
    {
        $result = PaymentService::createRazorpayOrder('test-order-123', 500);

        $this->assertArrayHasKey('id', $result);
        $this->assertArrayHasKey('amount', $result);
        $this->assertEquals(50000, $result['amount']); // 500 * 100 paise
        $this->assertEquals('created', $result['status']);
    }

    public function test_verify_payment_returns_verified()
    {
        $result = PaymentService::verifyPayment('order_123', 'pay_123', 'sig_123');

        $this->assertTrue($result['verified']);
        $this->assertEquals('captured', $result['status']);
    }

    public function test_create_refund_returns_refund_data()
    {
        $result = PaymentService::createRefund('pay_123', 500);

        $this->assertArrayHasKey('id', $result);
        $this->assertEquals(50000, $result['amount']);
        $this->assertEquals('processed', $result['status']);
    }
}
