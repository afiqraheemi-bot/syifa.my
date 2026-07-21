<?php

declare(strict_types=1);

namespace App\Modules\Commercial\Contracts\ReferenceData;

final readonly class BillingCycleReferenceData
{
    public function __construct(
        public string $billingCycleId,
        public string $code,
        public string $name,
        public string $availability,
        public string $recurrenceClassification,
    ) {}
}
