package com.utterlazy.order;

import org.springframework.boot.SpringApplication;
import org.springframework.boot.autoconfigure.SpringBootApplication;
import org.springframework.scheduling.annotation.EnableScheduling;

@SpringBootApplication
@EnableScheduling
public class OrderCommandApplication {
    public static void main(String[] args) {
        SpringApplication.run(OrderCommandApplication.class, args);
    }
}
