<?php

declare(strict_types=1);

namespace App\Modules\Commercial\Infrastructure\Events;

use App\Modules\Commercial\Contracts\Events\CommercialOfferEventPublisherInterface;
use Illuminate\Contracts\Events\Dispatcher;

final readonly class LaravelCommercialOfferEventPublisher implements CommercialOfferEventPublisherInterface
{
    public function __construct(private Dispatcher $events) {}

    public function publish(array $events): void
    {
        foreach ($events as $event) {
            $this->events->dispatch($event);
        }
    }
}
