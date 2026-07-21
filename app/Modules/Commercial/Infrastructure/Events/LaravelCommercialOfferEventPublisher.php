<?php

declare(strict_types=1);

namespace App\Modules\Commercial\Infrastructure\Events;

use App\Modules\Commercial\Contracts\Events\CommercialOfferEventPublisherInterface;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Database\Connection;

final readonly class LaravelCommercialOfferEventPublisher implements CommercialOfferEventPublisherInterface
{
    public function __construct(private Dispatcher $events, private Connection $connection) {}

    public function publish(array $events): void
    {
        if ($this->connection->transactionLevel() > 0) {
            $this->connection->afterCommit(fn (): mixed => $this->dispatch($events));

            return;
        }

        $this->dispatch($events);
    }

    /**
     * @param  list<object>  $events
     */
    private function dispatch(array $events): null
    {
        foreach ($events as $event) {
            $this->events->dispatch($event);
        }

        return null;
    }
}
