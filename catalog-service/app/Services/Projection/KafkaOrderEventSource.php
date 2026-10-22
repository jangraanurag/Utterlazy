<?php

namespace App\Services\Projection;

use RdKafka\Conf;
use RdKafka\KafkaConsumer;
use RdKafka\Message;
use RdKafka\TopicPartition;
use RuntimeException;

/**
 * Consumes the Java order-command outbox from Kafka.
 *
 * Requires the php-rdkafka extension (installed in the catalog image).
 * At-least-once: offsets are committed only after the projection is written;
 * duplicates are absorbed by processed_events.
 */
class KafkaOrderEventSource implements OrderEventSource
{
    private KafkaConsumer $consumer;

    private string $topic;

    public function __construct(string $brokers, string $topic, string $groupId)
    {
        if (! class_exists(KafkaConsumer::class)) {
            throw new RuntimeException('php-rdkafka extension is not installed.');
        }

        $conf = new Conf;
        $conf->set('metadata.broker.list', $brokers);
        $conf->set('group.id', $groupId);
        $conf->set('enable.auto.commit', 'false');
        $conf->set('auto.offset.reset', 'earliest');

        $this->topic = $topic;
        $this->consumer = new KafkaConsumer($conf);
        $this->consumer->subscribe([$topic]);
    }

    public function poll(int $limit, int $timeoutMs): array
    {
        $records = [];
        $first = true;

        while (count($records) < $limit) {
            $message = $this->consumer->consume($first ? $timeoutMs : 0);
            $first = false;

            switch ($message->err) {
                case RD_KAFKA_RESP_ERR_NO_ERROR:
                    $envelope = json_decode((string) $message->payload, true);
                    $records[] = [
                        'id' => $message->topic_name.':'.$message->partition.':'.$message->offset,
                        'envelope' => is_array($envelope) ? $envelope : [],
                        'meta' => [
                            'topic' => $message->topic_name,
                            'partition' => $message->partition,
                            'offset' => $message->offset,
                        ],
                    ];
                    break;

                case RD_KAFKA_RESP_ERR__TIMED_OUT:
                case RD_KAFKA_RESP_ERR__PARTITION_EOF:
                    return $records;

                default:
                    throw new RuntimeException('Kafka consume error: '.$message->errstr());
            }
        }

        return $records;
    }

    public function ack(array $record): void
    {
        $meta = $record['meta'] ?? [];

        if (! isset($meta['topic'], $meta['partition'], $meta['offset'])) {
            return;
        }

        $this->consumer->commitSync([
            new TopicPartition($meta['topic'], (int) $meta['partition'], ((int) $meta['offset']) + 1),
        ]);
    }

    public function close(): void
    {
        $this->consumer->close();
    }
}
