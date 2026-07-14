<?php

declare(strict_types=1);

namespace App\Modules\SubscriptionBilling\Domain\Aggregates\Subscription\ValueObjects;

use App\Modules\SubscriptionBilling\Domain\Aggregates\Subscription\Exceptions\InvalidSubscriptionValueException;

final readonly class Money
{
    public function __construct(public int $amountMinor, public string $currencyCode)
    {
        if ($amountMinor < 0) {
            throw new InvalidSubscriptionValueException('Money cannot have a negative minor-unit amount.');
        }

        if (preg_match('/^[A-Z]{3}$/', $currencyCode) !== 1) {
            throw new InvalidSubscriptionValueException('Currency code must use the three-letter uppercase format.');
        }
    }

    public function equals(self $other): bool
    {
        return $this->amountMinor === $other->amountMinor && $this->currencyCode === $other->currencyCode;
    }
}
