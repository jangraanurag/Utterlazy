<?php

namespace App\Services\Outbox;

use RuntimeException;

class KafkaTransport implements OutboxTransport
{
    private string $topicPrefix;

    private string $dlqSuffix;

    private ?\RdKafka\Producer $producer = null;

    public function __construct()
    {
        if (! class_exists(\RdKafka\Producer::class)) {
            throw new RuntimeException('php-rdkafka extension is not installed. Use OUTBOX_TRANSPORT=redis in dev.');
        }

        $conf = new \RdKafka\Conf();
        $conf->set('metadata.broker.list', config('services.outbox.kafka.brokers', 'localhost:9092'));
        $conf->set('message.timeout.ms', '5000');

        $this->topicPrefix = config('services.outbox.kafka.topic_prefix', 'utterlazy.');
        $this->dlqSuffix = config('services.outbox.kafka.dlq_suffix', '.dlq');
        $this->producer = new \RdKafka\Producer($conf);
    }

    public function publish(string $topic, ?string $partitionKey, array $envelope): void
    {
        if (! $this->producer) {
            throw new RuntimeException('Kafka producer is not initialized.');
        }

        $fullTopic = $this->topicPrefix.$topic;
        $payload = json_encode($envelope, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        $kafkaTopic = $this->producer->newTopic($fullTopic);

        if ($partitionKey !== null && $partitionKey !== '') {
            $kafkaTopic->produce(RD_KAFKA_PARTITION_UA, 0, $payload, $partitionKey);
        } else {
            $kafkaTopic->produce(RD_KAFKA_PARTITION_UA, 0, $payload);
        }

        $result = $this->producer->flush(5000);
        if ($result !== 0) {
            throw new RuntimeException("Kafka flush failed with {$result} pending messages for topic {$fullTopic}.");
        }
    }

    public function publishToDlq(string $topic, array $envelope): void
    {
        $this->publish($topic.$this->dlqSuffix, null, $envelope);
    }
}
