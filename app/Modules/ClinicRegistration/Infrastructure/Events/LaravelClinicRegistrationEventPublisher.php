<?php

declare(strict_types=1);

namespace App\Modules\ClinicRegistration\Infrastructure\Events;

use App\Modules\ClinicRegistration\Contracts\Events\ClinicRegistrationEventPublisherInterface;
use Illuminate\Contracts\Events\Dispatcher;

final readonly class LaravelClinicRegistrationEventPublisher implements ClinicRegistrationEventPublisherInterface
{
    public function __construct(private Dispatcher $events) {}

    public function publish(array $events): void
    {
        foreach ($events as $event) {
            $this->events->dispatch($event);
        }
    }
}
