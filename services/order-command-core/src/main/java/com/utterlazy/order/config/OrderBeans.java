package com.utterlazy.order.config;

import com.fasterxml.jackson.databind.ObjectMapper;
import com.utterlazy.order.application.OrderCommandService;
import com.utterlazy.order.application.PlaceOrderHandler;
import com.utterlazy.order.persistence.JdbcOrderRepository;
import com.utterlazy.order.persistence.JdbcOutboxWriter;
import com.utterlazy.order.persistence.JdbcUnitOfWork;
import com.utterlazy.order.port.OrderRepository;
import com.utterlazy.order.port.OutboxWriter;
import com.utterlazy.order.port.UnitOfWork;
import org.springframework.context.annotation.Bean;
import org.springframework.context.annotation.Configuration;
import org.springframework.jdbc.core.JdbcTemplate;
import org.springframework.transaction.PlatformTransactionManager;

@Configuration
public class OrderBeans {

    @Bean
    public OrderRepository orderRepository(JdbcTemplate jdbc) {
        return new JdbcOrderRepository(jdbc);
    }

    @Bean
    public OutboxWriter outboxWriter(JdbcTemplate jdbc, ObjectMapper objectMapper) {
        return new JdbcOutboxWriter(jdbc, objectMapper);
    }

    @Bean
    public UnitOfWork unitOfWork(PlatformTransactionManager transactionManager) {
        return new JdbcUnitOfWork(transactionManager);
    }

    @Bean
    public PlaceOrderHandler placeOrderHandler(OrderRepository orders, OutboxWriter outbox, UnitOfWork uow) {
        return new PlaceOrderHandler(orders, outbox, uow);
    }

    @Bean
    public OrderCommandService orderCommandService(OrderRepository orders, OutboxWriter outbox, UnitOfWork uow) {
        return new OrderCommandService(orders, outbox, uow);
    }
}
