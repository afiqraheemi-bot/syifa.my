<?php

declare(strict_types=1);

namespace App\Modules\AcquisitionOffer\Domain\ValueObjects;

use App\Modules\AcquisitionOffer\Domain\Exceptions\InvalidCommercialOfferValueException;
use DateTimeImmutable;

final readonly class OfferExpiry
{
    public function __construct(public DateTimeImmutable $expiresAt)
    {
        //
    }

    public static function fromPreparedAt(DateTimeImmutable $preparedAt, int $ttlMinutes): self
    {
        if ($ttlMinutes < 1) {
            throw new InvalidCommercialOfferValueException('Commercial Offer TTL must be at least one minute.');
        }

        return new self($preparedAt->modify(sprintf('+%d minutes', $ttlMinutes)));
    }

    public function isExpiredAt(DateTimeImmutable $occurredAt): bool
    {
        return $occurredAt >= $this->expiresAt;
    }
}
