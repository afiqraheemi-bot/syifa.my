<?php

declare(strict_types=1);

namespace App\Support\Dashboard\Application\SuperAdmin\Commercial;

final readonly class CreateSubscriptionPackageResult
{
    public function __construct(
        public string $planId,
        public string $offeringId,
    ) {}
}
