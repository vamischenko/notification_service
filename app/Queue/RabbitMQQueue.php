<?php

declare(strict_types=1);

namespace App\Queue;

use Illuminate\Contracts\Queue\Job as JobContract;
use Illuminate\Contracts\Queue\Queue as QueueContract;
use Illuminate\Queue\Queue;
use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;
use PhpAmqpLib\Wire\AMQPTable;

class RabbitMQQueue extends Queue implements QueueContract
{
    private AMQPChannel $channel;

    private array $declaredQueues = [];

    private AMQPStreamConnection $amqpConnection;

    private array $rabbitConfig;

    public function __construct(AMQPStreamConnection $connection, array $config)
    {
        $this->amqpConnection = $connection;
        $this->rabbitConfig   = $config;
        $this->channel        = $connection->channel();
        $this->setupExchanges();
    }

    private function setupExchanges(): void
    {
        $this->channel->exchange_declare('notifications.exchange', 'topic', false, true, false);
        $this->channel->exchange_declare('notifications.dlx', 'direct', false, true, false);

        $this->channel->queue_declare('notifications.dead', false, true, false, false);
        $this->channel->queue_bind('notifications.dead', 'notifications.dlx', 'dead.#');
    }

    private function declareQueue(string $queueName): void
    {
        if (isset($this->declaredQueues[$queueName])) {
            return;
        }

        $args = new AMQPTable([
            'x-dead-letter-exchange'    => 'notifications.dlx',
            'x-dead-letter-routing-key' => 'dead.' . $queueName,
            'x-message-ttl'             => 86400000,
        ]);

        $this->channel->queue_declare($queueName, false, true, false, false, false, $args);

        $routingKey = str_replace('notifications.', 'notification.', $queueName);
        $this->channel->queue_bind($queueName, 'notifications.exchange', $routingKey . '.*');
        $this->channel->queue_bind($queueName, 'notifications.exchange', $routingKey);

        $this->declaredQueues[$queueName] = true;
    }

    public function size($queue = null): int
    {
        $queueName = $queue ?? $this->rabbitConfig['queue'];
        $this->declareQueue($queueName);

        [, $messageCount] = $this->channel->queue_declare($queueName, true);

        return (int) $messageCount;
    }

    public function pendingSize($queue = null): int
    {
        return $this->size($queue);
    }

    public function delayedSize($queue = null): int
    {
        return 0;
    }

    public function reservedSize($queue = null): int
    {
        return 0;
    }

    public function creationTimeOfOldestPendingJob($queue = null): ?int
    {
        return null;
    }

    public function push($job, $data = '', $queue = null): mixed
    {
        return $this->pushRaw($this->createPayload($job, $queue, $data), $queue);
    }

    public function pushRaw($payload, $queue = null, array $options = []): mixed
    {
        $queueName = $queue ?? $this->rabbitConfig['queue'];
        $this->declareQueue($queueName);

        $message = new AMQPMessage($payload, [
            'content_type'  => 'application/json',
            'delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT,
        ]);

        $this->channel->basic_publish($message, 'notifications.exchange', $queueName);

        return json_decode($payload, true)['uuid'] ?? null;
    }

    public function later($delay, $job, $data = '', $queue = null): mixed
    {
        return $this->pushRaw($this->createPayload($job, $queue, $data), $queue);
    }

    public function pop($queue = null): ?JobContract
    {
        $queueName = $queue ?? $this->rabbitConfig['queue'];
        $this->declareQueue($queueName);

        $message = $this->channel->basic_get($queueName);

        if ($message === null) {
            return null;
        }

        return new RabbitMQJob(
            $this->container,
            $this,
            $message,
            $this->connectionName,
            $queueName,
        );
    }

    public function getChannel(): AMQPChannel
    {
        return $this->channel;
    }

    public function __destruct()
    {
        try {
            $this->channel->close();
            $this->amqpConnection->close();
        } catch (\Throwable) {}
    }
}
