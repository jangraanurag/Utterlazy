package com.utterlazy.dispatch.web;

import com.utterlazy.dispatch.BatchPlanner;
import com.utterlazy.dispatch.DispatchPolicy;
import com.utterlazy.dispatch.OrderStop;
import com.utterlazy.dispatch.RiderCandidate;
import jakarta.validation.Valid;
import jakarta.validation.constraints.DecimalMax;
import jakarta.validation.constraints.DecimalMin;
import jakarta.validation.constraints.Max;
import jakarta.validation.constraints.Min;
import jakarta.validation.constraints.NotBlank;
import jakarta.validation.constraints.NotNull;
import org.springframework.http.HttpStatus;
import org.springframework.web.bind.annotation.GetMapping;
import org.springframework.web.bind.annotation.PostMapping;
import org.springframework.web.bind.annotation.RequestBody;
import org.springframework.web.bind.annotation.RequestMapping;
import org.springframework.web.bind.annotation.RestController;
import org.springframework.web.server.ResponseStatusException;

import java.util.List;
import java.util.Map;

@RestController
@RequestMapping("/api/dispatch")
public class DispatchController {

    private static final String POLICY_VERSION = "dispatch-policy-v1";

    private final DispatchPolicy policy = new DispatchPolicy();
    private final BatchPlanner planner = new BatchPlanner();

    @GetMapping("/health")
    public Map<String, String> health() {
        return Map.of("status", "ok", "service", "dispatch", "policy_version", POLICY_VERSION);
    }

    @PostMapping("/assign")
    public AssignResponse assign(@Valid @RequestBody AssignRequest request) {
        try {
            List<DispatchPolicy.Score> scores = policy.rank(
                    request.order().toStop(),
                    request.riders().stream().map(RiderDto::toCandidate).toList(),
                    request.excluded() == null ? java.util.Set.of() : java.util.Set.copyOf(request.excluded()),
                    request.assignmentSequence()
            );

            return new AssignResponse(
                    POLICY_VERSION,
                    scores.stream().map(s -> new ScoreDto(s.riderId(), s.score(), s.breakdown())).toList()
            );
        } catch (IllegalArgumentException e) {
            throw new ResponseStatusException(HttpStatus.UNPROCESSABLE_ENTITY, e.getMessage());
        }
    }

    @PostMapping("/batch")
    public BatchResponse batch(@Valid @RequestBody BatchRequest request) {
        try {
            List<BatchPlanner.Batch> batches = planner.plan(
                    request.orders().stream().map(OrderDto::toStop).toList(),
                    request.pickupRadiusKm(),
                    request.dropRadiusKm(),
                    request.maxPerBatch()
            );

            return new BatchResponse(batches.stream()
                    .map(b -> new BatchDto(b.orders().stream().map(OrderDto::from).toList()))
                    .toList());
        } catch (IllegalArgumentException e) {
            throw new ResponseStatusException(HttpStatus.UNPROCESSABLE_ENTITY, e.getMessage());
        }
    }

    public record RiderDto(
            @NotBlank String riderId,
            double lat,
            double lng,
            boolean available,
            boolean verified,
            @Min(0) int activeOrders,
            @DecimalMin("0.0") @DecimalMax("1.0") double acceptanceRate,
            @Min(0) int minutesSincePing,
            @Min(0) int totalTrips
    ) {
        RiderCandidate toCandidate() {
            return new RiderCandidate(riderId, lat, lng, available, verified, activeOrders,
                    acceptanceRate, minutesSincePing, totalTrips);
        }
    }

    public record OrderDto(
            @NotBlank String orderId,
            String restaurantId,
            double pickupLat,
            double pickupLng,
            double dropLat,
            double dropLng,
            @Min(0) int prepMinutes
    ) {
        OrderStop toStop() {
            return new OrderStop(orderId, restaurantId, pickupLat, pickupLng, dropLat, dropLng, prepMinutes);
        }

        static OrderDto from(OrderStop stop) {
            return new OrderDto(stop.orderId(), stop.restaurantId(), stop.pickupLat(), stop.pickupLng(),
                    stop.dropLat(), stop.dropLng(), stop.prepMinutes());
        }
    }

    public record AssignRequest(
            @NotNull @Valid OrderDto order,
            @NotNull List<@Valid RiderDto> riders,
            List<String> excluded,
            @Min(0) int assignmentSequence
    ) {
    }

    public record ScoreDto(String riderId, double score, Map<String, Double> breakdown) {
    }

    public record AssignResponse(String policyVersion, List<ScoreDto> candidates) {
    }

    public record BatchRequest(
            @NotNull List<@Valid OrderDto> orders,
            double pickupRadiusKm,
            double dropRadiusKm,
            @Min(1) @Max(3) int maxPerBatch
    ) {
    }

    public record BatchDto(List<OrderDto> orders) {
    }

    public record BatchResponse(List<BatchDto> batches) {
    }
}
