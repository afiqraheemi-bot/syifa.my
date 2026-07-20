<?php

declare(strict_types=1);

namespace App\Modules\ClinicRegistration\Contracts\Events;

interface ClinicRegistrationEventPublisherInterface
{
    /** @param list<object> $events */
    public function publish(array $events): void;
}
