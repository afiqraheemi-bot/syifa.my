<?php

declare(strict_types=1);

namespace App\Modules\Commercial\Domain\ValueObjects;

enum CommercialOfferStatus: string
{
    case Prepared = 'prepared';
    case Consumed = 'consumed';
    case Cancelled = 'cancelled';
    case Expired = 'expired';

    public function isTerminal(): bool
    {
        return $this !== self::Prepared;
    }
}
