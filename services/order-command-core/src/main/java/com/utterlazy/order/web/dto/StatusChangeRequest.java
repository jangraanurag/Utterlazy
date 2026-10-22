package com.utterlazy.order.web.dto;

import jakarta.validation.constraints.NotBlank;
import jakarta.validation.constraints.Size;

public record StatusChangeRequest(
        @NotBlank String status,
        @Size(max = 40) String reason
) {
}
