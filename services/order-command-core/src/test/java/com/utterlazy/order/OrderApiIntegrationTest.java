package com.utterlazy.order;

import com.fasterxml.jackson.databind.ObjectMapper;
import org.junit.jupiter.api.BeforeEach;
import org.junit.jupiter.api.Test;
import org.springframework.beans.factory.annotation.Autowired;
import org.springframework.boot.test.autoconfigure.web.servlet.AutoConfigureMockMvc;
import org.springframework.boot.test.context.SpringBootTest;
import org.springframework.http.MediaType;
import org.springframework.jdbc.core.JdbcTemplate;
import org.springframework.test.context.ActiveProfiles;
import org.springframework.test.web.servlet.MockMvc;

import java.util.List;
import java.util.Map;
import java.util.UUID;

import static org.assertj.core.api.Assertions.assertThat;
import static org.springframework.test.web.servlet.request.MockMvcRequestBuilders.get;
import static org.springframework.test.web.servlet.request.MockMvcRequestBuilders.patch;
import static org.springframework.test.web.servlet.request.MockMvcRequestBuilders.post;
import static org.springframework.test.web.servlet.result.MockMvcResultMatchers.jsonPath;
import static org.springframework.test.web.servlet.result.MockMvcResultMatchers.status;

@SpringBootTest
@AutoConfigureMockMvc
@ActiveProfiles("test")
class OrderApiIntegrationTest {

    @Autowired
    private MockMvc mvc;

    @Autowired
    private ObjectMapper objectMapper;

    @Autowired
    private JdbcTemplate jdbc;

    private final String userId = UUID.randomUUID().toString();
    private final String restaurantId = UUID.randomUUID().toString();
    private final String cityId = UUID.randomUUID().toString();

    @BeforeEach
    void clean() {
        jdbc.update("DELETE FROM order_lines_v2");
        jdbc.update("DELETE FROM outbox_v2");
        jdbc.update("DELETE FROM orders_v2");
    }

    @Test
    void placesOrderAndWritesOneOutboxEvent() throws Exception {
        String id = place("app", null, 2, "100.00", 201);

        mvc.perform(get("/api/orders/" + id))
                .andExpect(status().isOk())
                .andExpect(jsonPath("$.status").value("PENDING"))
                .andExpect(jsonPath("$.grandTotal").value(200.00))
                .andExpect(jsonPath("$.lines[0].quantity").value(2));

        Integer outbox = jdbc.queryForObject("SELECT COUNT(*) FROM outbox_v2", Integer.class);
        assertThat(outbox).isEqualTo(1);
    }

    @Test
    void idempotentReplayReturnsSameOrder() throws Exception {
        String key = "wa-key-12345678";
        String first = place("whatsapp", key, 1, "50.00", 201);
        String second = place("whatsapp", key, 1, "50.00", 200);

        assertThat(second).isEqualTo(first);

        Integer orders = jdbc.queryForObject("SELECT COUNT(*) FROM orders_v2", Integer.class);
        Integer outbox = jdbc.queryForObject("SELECT COUNT(*) FROM outbox_v2", Integer.class);
        assertThat(orders).isEqualTo(1);
        assertThat(outbox).isEqualTo(1);
    }

    @Test
    void whatsappWithoutKeyIsRejected() throws Exception {
        place("whatsapp", null, 1, "50.00", 422);
    }

    @Test
    void quantityAboveTwentyIsRejected() throws Exception {
        place("app", null, 21, "50.00", 422);
    }

    @Test
    void illegalTransitionIsConflict() throws Exception {
        String id = place("app", null, 1, "100.00", 201);

        mvc.perform(patch("/api/orders/{id}/status", id)
                        .contentType(MediaType.APPLICATION_JSON)
                        .content(objectMapper.writeValueAsString(Map.of("status", "confirmed"))))
                .andExpect(status().isOk())
                .andExpect(jsonPath("$.status").value("CONFIRMED"));

        mvc.perform(patch("/api/orders/{id}/status", id)
                        .contentType(MediaType.APPLICATION_JSON)
                        .content(objectMapper.writeValueAsString(Map.of("status", "delivered"))))
                .andExpect(status().isConflict());
    }

    @Test
    void cancelRequiresReason() throws Exception {
        String id = place("app", null, 1, "100.00", 201);

        mvc.perform(patch("/api/orders/{id}/status", id)
                        .contentType(MediaType.APPLICATION_JSON)
                        .content(objectMapper.writeValueAsString(Map.of("status", "cancelled"))))
                .andExpect(status().isUnprocessableEntity());
    }

    @Test
    void unknownOrderIsNotFound() throws Exception {
        mvc.perform(get("/api/orders/" + UUID.randomUUID()))
                .andExpect(status().isNotFound());
    }

    @Test
    void pricingBreakdownIsPersistedAndEchoed() throws Exception {
        Map<String, Object> body = Map.of(
                "userId", userId,
                "restaurantId", restaurantId,
                "cityId", cityId,
                "channel", "app",
                "lines", List.of(Map.of(
                        "menuItemId", "item-1",
                        "quantity", 1,
                        "unitPrice", "199.00"
                )),
                "pricing", Map.of(
                        "grandTotal", "230.50",
                        "tax", "9.45",
                        "vendorDiscount", "9.95",
                        "deliveryFee", "27.00",
                        "platformFee", "5.00",
                        "couponDiscount", "0.00",
                        "commission", "28.36",
                        "commissionGst", "5.10",
                        "netPayout", "165.04"
                )
        );

        String response = mvc.perform(post("/api/orders")
                        .contentType(MediaType.APPLICATION_JSON)
                        .content(objectMapper.writeValueAsString(body)))
                .andExpect(status().isCreated())
                .andExpect(jsonPath("$.grandTotal").value(230.50))
                .andExpect(jsonPath("$.tax").value(9.45))
                .andExpect(jsonPath("$.deliveryFee").value(27.00))
                .andExpect(jsonPath("$.netPayout").value(165.04))
                .andReturn().getResponse().getContentAsString();

        String id = objectMapper.readTree(response).get("id").asText();

        mvc.perform(get("/api/orders/" + id))
                .andExpect(status().isOk())
                .andExpect(jsonPath("$.grandTotal").value(230.50))
                .andExpect(jsonPath("$.commission").value(28.36));
    }

    @Test
    void pricingPreflightMatchesGoldenVector() throws Exception {
        Map<String, Object> body = new java.util.LinkedHashMap<>();
        body.put("lines", List.of(Map.of(
                "menuItemId", "item-1",
                "quantity", 1,
                "unitPrice", "199.00"
        )));
        body.put("vendorDiscountRate", "5.00");
        body.put("taxRate", "5.00");
        body.put("deliveryMin", "10.00");
        body.put("deliveryMax", "50.00");
        body.put("deliveryEstimate", "27.00");
        body.put("deliveryFreeAbove", "500.00");
        body.put("platformFee", "5.00");
        body.put("coupon", "0.00");
        body.put("tip", "0.00");
        body.put("commissionRate", "15.00");
        body.put("commissionGstRate", "18.00");

        mvc.perform(post("/api/pricing/preflight")
                        .contentType(MediaType.APPLICATION_JSON)
                        .content(objectMapper.writeValueAsString(body)))
                .andExpect(status().isOk())
                .andExpect(jsonPath("$.grandTotal").value(230.50))
                .andExpect(jsonPath("$.tax").value(9.45))
                .andExpect(jsonPath("$.vendorDiscount").value(9.95))
                .andExpect(jsonPath("$.deliveryFee").value(27.00))
                .andExpect(jsonPath("$.commission").value(28.36))
                .andExpect(jsonPath("$.commissionGst").value(5.10))
                .andExpect(jsonPath("$.netPayout").value(165.04));
    }

    @Test
    void looksUpByShadowIdempotencyKey() throws Exception {
        String key = "shadow-" + UUID.randomUUID();
        String id = place("whatsapp", key, 1, "75.00", 201);

        mvc.perform(get("/api/orders/by-idempotency")
                        .param("userId", userId)
                        .param("key", key))
                .andExpect(status().isOk())
                .andExpect(jsonPath("$.id").value(id));

        mvc.perform(get("/api/orders/by-idempotency")
                        .param("userId", userId)
                        .param("key", "shadow-missing"))
                .andExpect(status().isNotFound());
    }

    @Test
    void healthIsPublic() throws Exception {
        mvc.perform(get("/api/orders/health"))
                .andExpect(status().isOk())
                .andExpect(jsonPath("$.service").value("order-command"));
    }

    private String place(String channel, String idempotencyKey, int qty, String price,
                         int expectedStatus) throws Exception {
        Map<String, Object> body = Map.of(
                "userId", userId,
                "restaurantId", restaurantId,
                "cityId", cityId,
                "channel", channel,
                "lines", List.of(Map.of(
                        "menuItemId", "item-1",
                        "quantity", qty,
                        "unitPrice", price
                ))
        );

        var request = post("/api/orders")
                .contentType(MediaType.APPLICATION_JSON)
                .content(objectMapper.writeValueAsString(body));

        if (idempotencyKey != null) {
            request = request.header("Idempotency-Key", idempotencyKey);
        }

        String response = mvc.perform(request)
                .andExpect(status().is(expectedStatus))
                .andReturn().getResponse().getContentAsString();

        return expectedStatus < 400 ? objectMapper.readTree(response).get("id").asText() : null;
    }
}
