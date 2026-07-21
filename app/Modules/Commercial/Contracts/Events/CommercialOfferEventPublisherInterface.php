<?php

declare(strict_types=1);

namespace App\Modules\Commercial\Contracts\Events;

interface CommercialOfferEventPublisherInterface
{
    /**
     * @param  list<object>  $events
     */
    public function publish(array $events): void;
}
