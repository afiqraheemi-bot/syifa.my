<?php

declare(strict_types=1);

namespace App\Modules\ClinicRegistration\Contracts\Checkout;

final readonly class PublicInitialAcquisitionStatusData
{
    public function __construct(
        public string $paymentStatus,
        public int $amountMinor,
        public string $currency,
        public string $lastChangedAt,
    ) {}
}
