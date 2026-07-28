<?php

declare(strict_types=1);

namespace App\Modules\ClinicRegistration\Contracts\Checkout;

final readonly class PublicInitialAcquisitionCheckoutResult
{
    private function __construct(
        public string $status,
        public ?string $redirectDestination,
    ) {}

    public static function ready(string $redirectDestination): self
    {
        return new self('ready', $redirectDestination);
    }

    public static function providerNotReady(): self
    {
        return new self('provider_not_ready', null);
    }

    public static function unavailable(): self
    {
        return new self('unavailable', null);
    }
}
