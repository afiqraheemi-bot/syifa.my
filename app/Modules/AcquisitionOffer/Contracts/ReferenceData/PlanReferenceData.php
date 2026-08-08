<?php

declare(strict_types=1);

namespace App\Modules\AcquisitionOffer\Contracts\ReferenceData;

final readonly class PlanReferenceData
{
    public function __construct(
        public string $planId,
        public string $code,
        public string $name,
        public string $status,
    ) {}
}
