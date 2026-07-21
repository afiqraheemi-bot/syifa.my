<?php

declare(strict_types=1);

namespace App\Modules\SubscriptionBilling\Domain\Aggregates\Payment\ValueObjects;

use App\Modules\SubscriptionBilling\Domain\Aggregates\Payment\Exceptions\InvalidPaymentValueException;

final readonly class ProviderReference
{
    public function __construct(
        public string $providerKey,
        public string $providerPaymentReference,
    ) {
        if (trim($providerKey) === '' || mb_strlen($providerKey) > 80) {
            throw new InvalidPaymentValueException('Provider key must be a non-empty opaque identifier.');
        }

        if (trim($providerPaymentReference) === '' || mb_strlen($providerPaymentReference) > 160) {
            throw new InvalidPaymentValueException('Provider payment reference must be a non-empty opaque identifier.');
        }
    }
}
