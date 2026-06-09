<?php

declare(strict_types=1);

namespace App\Queue;

use Illuminate\Container\Container;
use Illuminate\Queue\Jobs\Job;
use Illuminate\Contracts\Queue\Job as JobContract;
use PhpAmqpLib\Message\AMQPMessage;

class RabbitMQJob extends Job implements JobContract
{
    public function __construct(
        Container                     $container,
        private readonly RabbitMQQueue $rabbitQueue,
        private readonly AMQPMessage   $message,
        string                         $connectionName,
        string                         $queue,
    ) {
        $this->container      = $container;
        $this->connectionName = $connectionName;
        $this->queue          = $queue;
    }

    public function getJobId(): ?string
    {
        $payload = $this->payload();

        return $payload['uuid'] ?? null;
    }

    public function getRawBody(): string
    {
        return $this->message->getBody();
    }

    public function attempts(): int
    {
        $payload = $this->payload();

        return ($payload['attempts'] ?? 0) + 1;
    }

    public function delete(): void
    {
        parent::delete();
        $this->message->ack();
    }

    public function release($delay = 0): void
    {
        parent::release($delay);

        // Reject and requeue for immediate retry; for delayed use DLX TTL
        $this->message->nack(true);
    }

    public function fail($e = null): void
    {
        parent::fail($e);
        // Send to DLX (dead letter queue) by rejecting without requeue
        $this->message->nack(false);
    }
}
