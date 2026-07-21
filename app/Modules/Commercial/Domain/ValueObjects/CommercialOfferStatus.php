<?php

declare(strict_types=1);

namespace App\Modules\Commercial\Domain\ValueObjects;

enum CommercialOfferStatus: string
{
    case Prepared = 'prepared';
    case Claimed = 'claimed';
    case Cancelled = 'cancelled';
    case Expired = 'expired';

    public function isTerminal(): bool
    {
        return $this !== self::Prepared;
    }
}
