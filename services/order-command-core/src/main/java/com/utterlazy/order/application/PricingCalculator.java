package com.utterlazy.order.application;

import java.math.BigDecimal;
import java.math.MathContext;
import java.math.RoundingMode;

/**
 * Pure pricing mirror of the Laravel checkout formula.
 *
 * Intermediates use DECIMAL128; only persisted money fields are rounded to
 * 2 dp (HALF_UP). Coupon selection and wallet checks are adapter concerns,
 * not part of this calculator.
 */
public final class PricingCalculator {

    private static final MathContext MC = MathContext.DECIMAL128;
    private static final BigDecimal HUNDRED = BigDecimal.valueOf(100);
    private static final int SCALE = 2;

    public record Input(
            BigDecimal subtotal,
            BigDecimal vendorDiscountRatePct,
            BigDecimal taxRatePct,
            BigDecimal deliveryMin,
            BigDecimal deliveryMax,
            BigDecimal deliveryEstimate,
            BigDecimal deliveryFreeAbove,
            BigDecimal platformFee,
            BigDecimal coupon,
            BigDecimal tip,
            BigDecimal commissionRatePct,
            BigDecimal commissionGstRatePct
    ) {
    }

    public record Result(
            BigDecimal subtotal,
            BigDecimal vendorDiscount,
            BigDecimal tax,
            BigDecimal deliveryFee,
            BigDecimal platformFee,
            BigDecimal couponDiscount,
            BigDecimal tip,
            BigDecimal grandTotal,
            BigDecimal netOrderValue,
            BigDecimal commission,
            BigDecimal commissionGst,
            BigDecimal netPayout
    ) {
    }

    public Result calculate(Input in) {
        BigDecimal vendorDiscount = in.subtotal()
                .multiply(in.vendorDiscountRatePct(), MC)
                .divide(HUNDRED, MC);

        BigDecimal afterDiscount = in.subtotal().subtract(vendorDiscount, MC);
        BigDecimal tax = afterDiscount.multiply(in.taxRatePct(), MC).divide(HUNDRED, MC);

        BigDecimal deliveryFee = in.deliveryEstimate()
                .max(in.deliveryMin())
                .min(in.deliveryMax())
                .setScale(SCALE, RoundingMode.HALF_UP);

        if (in.subtotal().compareTo(in.deliveryFreeAbove()) >= 0) {
            deliveryFee = BigDecimal.ZERO.setScale(SCALE);
        }

        BigDecimal grandTotal = afterDiscount
                .add(tax, MC)
                .add(deliveryFee, MC)
                .add(in.platformFee(), MC);

        BigDecimal couponDiscount = in.coupon().min(grandTotal).max(BigDecimal.ZERO);
        grandTotal = grandTotal.subtract(couponDiscount, MC).add(in.tip(), MC);

        BigDecimal netOrderValue = in.subtotal()
                .subtract(vendorDiscount, MC)
                .subtract(couponDiscount, MC);

        BigDecimal commission = netOrderValue
                .multiply(in.commissionRatePct(), MC)
                .divide(HUNDRED, MC)
                .max(BigDecimal.ZERO);

        BigDecimal commissionGst = commission
                .multiply(in.commissionGstRatePct(), MC)
                .divide(HUNDRED, MC);

        BigDecimal netPayout = netOrderValue
                .add(tax, MC)
                .subtract(commission, MC)
                .subtract(commissionGst, MC);

        return new Result(
                money(in.subtotal()),
                money(vendorDiscount),
                money(tax),
                money(deliveryFee),
                money(in.platformFee()),
                money(couponDiscount),
                money(in.tip()),
                money(grandTotal),
                money(netOrderValue),
                money(commission),
                money(commissionGst),
                money(netPayout)
        );
    }

    private BigDecimal money(BigDecimal value) {
        return value.setScale(SCALE, RoundingMode.HALF_UP);
    }
}
