package com.utterlazy.dispatch.web;

import com.fasterxml.jackson.databind.ObjectMapper;
import org.junit.jupiter.api.Test;
import org.springframework.beans.factory.annotation.Autowired;
import org.springframework.boot.test.autoconfigure.web.servlet.AutoConfigureMockMvc;
import org.springframework.boot.test.context.SpringBootTest;
import org.springframework.http.MediaType;
import org.springframework.test.web.servlet.MockMvc;

import java.util.List;
import java.util.Map;

import static org.springframework.test.web.servlet.request.MockMvcRequestBuilders.get;
import static org.springframework.test.web.servlet.request.MockMvcRequestBuilders.post;
import static org.springframework.test.web.servlet.result.MockMvcResultMatchers.jsonPath;
import static org.springframework.test.web.servlet.result.MockMvcResultMatchers.status;

@SpringBootTest
@AutoConfigureMockMvc
class DispatchApiTest {

    @Autowired
    private MockMvc mvc;

    @Autowired
    private ObjectMapper objectMapper;

    private Map<String, Object> rider(String id, double lat, double lng, int trips) {
        return Map.of(
                "riderId", id, "lat", lat, "lng", lng,
                "available", true, "verified", true, "activeOrders", 0,
                "acceptanceRate", 0.9, "minutesSincePing", 2, "totalTrips", trips
        );
    }

    private Map<String, Object> order() {
        return Map.of(
                "orderId", "o-1", "restaurantId", "r-1",
                "pickupLat", 22.70, "pickupLng", 75.80,
                "dropLat", 22.75, "dropLng", 75.85, "prepMinutes", 15
        );
    }

    @Test
    void health() throws Exception {
        mvc.perform(get("/api/dispatch/health"))
                .andExpect(status().isOk())
                .andExpect(jsonPath("$.policy_version").value("dispatch-policy-v1"));
    }

    @Test
    void assignPrefersNearest() throws Exception {
        Map<String, Object> body = Map.of(
                "order", order(),
                "riders", List.of(rider("far", 22.74, 75.83, 100), rider("near", 22.705, 75.805, 100)),
                "excluded", List.of(),
                "assignmentSequence", 1
        );

        mvc.perform(post("/api/dispatch/assign")
                        .contentType(MediaType.APPLICATION_JSON)
                        .content(objectMapper.writeValueAsString(body)))
                .andExpect(status().isOk())
                .andExpect(jsonPath("$.policyVersion").value("dispatch-policy-v1"))
                .andExpect(jsonPath("$.candidates[0].riderId").value("near"))
                .andExpect(jsonPath("$.candidates[0].breakdown.distance").isNumber());
    }

    @Test
    void assignRejectsInvalidAcceptance() throws Exception {
        Map<String, Object> badRider = Map.of(
                "riderId", "bad", "lat", 22.7, "lng", 75.8,
                "available", true, "verified", true, "activeOrders", 0,
                "acceptanceRate", 1.5, "minutesSincePing", 2, "totalTrips", 10
        );

        mvc.perform(post("/api/dispatch/assign")
                        .contentType(MediaType.APPLICATION_JSON)
                        .content(objectMapper.writeValueAsString(Map.of(
                                "order", order(), "riders", List.of(badRider), "assignmentSequence", 1))))
                .andExpect(status().isBadRequest());
    }

    @Test
    void batchGroupsCloseOrders() throws Exception {
        Map<String, Object> second = Map.of(
                "orderId", "o-2", "restaurantId", "r-1",
                "pickupLat", 22.701, "pickupLng", 75.801,
                "dropLat", 22.751, "dropLng", 75.851, "prepMinutes", 15
        );

        mvc.perform(post("/api/dispatch/batch")
                        .contentType(MediaType.APPLICATION_JSON)
                        .content(objectMapper.writeValueAsString(Map.of(
                                "orders", List.of(order(), second),
                                "pickupRadiusKm", 1.0,
                                "dropRadiusKm", 1.0,
                                "maxPerBatch", 2))))
                .andExpect(status().isOk())
                .andExpect(jsonPath("$.batches[0].orders.length()").value(2));
    }
}
