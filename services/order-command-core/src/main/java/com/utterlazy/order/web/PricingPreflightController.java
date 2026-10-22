package com.utterlazy.order.web;

import com.utterlazy.order.application.PricingCalculator;
import com.utterlazy.order.web.dto.PricingPreflightRequest;
import com.utterlazy.order.web.dto.PricingPreflightResponse;
import jakarta.validation.Valid;
import org.springframework.web.bind.annotation.PostMapping;
import org.springframework.web.bind.annotation.RequestBody;
import org.springframework.web.bind.annotation.RequestMapping;
import org.springframework.web.bind.annotation.RestController;

import java.math.BigDecimal;

/**
 * Pricing preflight for cutover certification. Computes the same breakdown as
 * the pricing contract and writes nothing.
 */
@RestController
@RequestMapping("/api/pricing")
public class PricingPreflightController {

    private final PricingCalculator calculator = new PricingCalculator();

    @PostMapping("/preflight")
    public PricingPreflightResponse preflight(@Valid @RequestBody PricingPreflightRequest body) {
        BigDecimal subtotal = body.lines().stream()
                .map(line -> line.unitPrice().multiply(BigDecimal.valueOf(line.quantity())))
                .reduce(BigDecimal.ZERO, BigDecimal::add);

        PricingCalculator.Result result = calculator.calculate(new PricingCalculator.Input(
                subtotal,
                body.vendorDiscountRate(),
                body.taxRate(),
                body.deliveryMin(),
                body.deliveryMax(),
                body.deliveryEstimate(),
                body.deliveryFreeAbove(),
                body.platformFee(),
                body.coupon(),
                body.tip(),
                body.commissionRate(),
                body.commissionGstRate()
        ));

        return PricingPreflightResponse.from(result.subtotal(), result);
    }
}
