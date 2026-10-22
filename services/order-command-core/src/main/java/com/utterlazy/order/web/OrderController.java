package com.utterlazy.order.web;

import com.utterlazy.order.application.OrderCommandService;
import com.utterlazy.order.application.PlaceOrderCommand;
import com.utterlazy.order.application.PlaceOrderHandler;
import com.utterlazy.order.application.PlaceOrderResult;
import com.utterlazy.order.domain.InvalidTransitionException;
import com.utterlazy.order.domain.Order;
import com.utterlazy.order.domain.OrderChannel;
import com.utterlazy.order.domain.OrderLine;
import com.utterlazy.order.domain.PricingBreakdown;
import com.utterlazy.order.port.OrderRepository;
import com.utterlazy.order.web.dto.OrderResponse;
import com.utterlazy.order.web.dto.PlaceOrderRequest;
import com.utterlazy.order.web.dto.StatusChangeRequest;
import jakarta.validation.Valid;
import org.springframework.http.HttpStatus;
import org.springframework.http.ResponseEntity;
import org.springframework.web.bind.annotation.GetMapping;
import org.springframework.web.bind.annotation.PatchMapping;
import org.springframework.web.bind.annotation.PathVariable;
import org.springframework.web.bind.annotation.PostMapping;
import org.springframework.web.bind.annotation.RequestBody;
import org.springframework.web.bind.annotation.RequestHeader;
import org.springframework.web.bind.annotation.RequestMapping;
import org.springframework.web.bind.annotation.RestController;
import org.springframework.web.server.ResponseStatusException;

import java.util.List;
import java.util.Map;
import java.util.UUID;

@RestController
@RequestMapping("/api/orders")
public class OrderController {

    private final PlaceOrderHandler placeOrder;
    private final OrderCommandService commands;
    private final OrderRepository orders;

    public OrderController(PlaceOrderHandler placeOrder, OrderCommandService commands, OrderRepository orders) {
        this.placeOrder = placeOrder;
        this.commands = commands;
        this.orders = orders;
    }

    @PostMapping
    public ResponseEntity<OrderResponse> place(
            @RequestHeader(value = "Idempotency-Key", required = false) String idempotencyKey,
            @Valid @RequestBody PlaceOrderRequest body
    ) {
        List<OrderLine> lines = body.lines().stream()
                .map(line -> new OrderLine(line.menuItemId(), line.variantId(),
                        line.quantity(), line.unitPrice()))
                .toList();

        PricingBreakdown pricing = body.pricing() == null ? null : new PricingBreakdown(
                body.pricing().grandTotal(),
                body.pricing().tax(),
                body.pricing().vendorDiscount(),
                body.pricing().deliveryFee(),
                body.pricing().platformFee(),
                body.pricing().couponDiscount(),
                body.pricing().commission(),
                body.pricing().commissionGst(),
                body.pricing().netPayout()
        );

        PlaceOrderCommand command = PlaceOrderCommand.of(
                body.userId(), body.restaurantId(), body.cityId(),
                body.channel(), idempotencyKey, lines, pricing);

        PlaceOrderResult result = placeOrder.handle(command);

        HttpStatus status = result.replayed() ? HttpStatus.OK : HttpStatus.CREATED;
        return ResponseEntity.status(status).body(OrderResponse.from(result.order()));
    }

    @PatchMapping("/{id}/status")
    public OrderResponse changeStatus(@PathVariable("id") String id,
                                      @Valid @RequestBody StatusChangeRequest body) {
        UUID orderId = parseId(id);
        String target = body.status().trim().toUpperCase();

        Order order = switch (target) {
            case "CONFIRMED" -> commands.accept(orderId);
            case "PREPARING" -> commands.startPreparing(orderId);
            case "READY_FOR_PICKUP" -> commands.markReady(orderId);
            case "OUT_FOR_DELIVERY" -> commands.pickup(orderId);
            case "DELIVERED" -> commands.deliver(orderId);
            case "CANCELLED" -> commands.cancel(orderId, requireReason(body.reason(), "cancel"));
            case "ABANDONED" -> commands.abandon(orderId, requireReason(body.reason(), "abandon"));
            case "RESCUED" -> commands.rescue(orderId);
            default -> throw new IllegalArgumentException("Unsupported status: " + body.status());
        };

        return OrderResponse.from(order);
    }

    @GetMapping("/{id}")
    public OrderResponse get(@PathVariable("id") String id) {
        return orders.findById(parseId(id))
                .map(OrderResponse::from)
                .orElseThrow(() -> new ResponseStatusException(HttpStatus.NOT_FOUND, "Order not found"));
    }

    @GetMapping("/by-idempotency")
    public OrderResponse byIdempotency(
            @org.springframework.web.bind.annotation.RequestParam("userId") UUID userId,
            @org.springframework.web.bind.annotation.RequestParam("key") String key) {
        return orders.findByIdempotencyKey(userId, key)
                .map(OrderResponse::from)
                .orElseThrow(() -> new ResponseStatusException(HttpStatus.NOT_FOUND, "Shadow order not found"));
    }

    @GetMapping("/health")
    public Map<String, String> health() {
        return Map.of("status", "ok", "service", "order-command");
    }

    private UUID parseId(String raw) {
        try {
            return UUID.fromString(raw);
        } catch (IllegalArgumentException e) {
            throw new IllegalArgumentException("Invalid order id: " + raw);
        }
    }

    private String requireReason(String reason, String action) {
        if (reason == null || reason.isBlank()) {
            throw new IllegalArgumentException("reason is required to " + action + " an order");
        }
        return reason;
    }
}
