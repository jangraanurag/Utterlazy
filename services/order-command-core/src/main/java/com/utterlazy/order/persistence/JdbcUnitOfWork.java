package com.utterlazy.order.persistence;

import com.utterlazy.order.port.UnitOfWork;
import org.springframework.transaction.PlatformTransactionManager;
import org.springframework.transaction.support.TransactionTemplate;

/** Spring transaction adapter for the explicit unit-of-work port. */
public class JdbcUnitOfWork implements UnitOfWork {

    private final TransactionTemplate transactionTemplate;

    public JdbcUnitOfWork(PlatformTransactionManager transactionManager) {
        this.transactionTemplate = new TransactionTemplate(transactionManager);
    }

    @Override
    public <T> T inTransaction(Work<T> work) {
        return transactionTemplate.execute(status -> work.run());
    }
}
