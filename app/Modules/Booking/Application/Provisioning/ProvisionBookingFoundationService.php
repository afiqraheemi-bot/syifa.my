<?php

declare(strict_types=1);

namespace App\Modules\Booking\Application\Provisioning;

use App\Modules\Booking\Application\Configuration\ManageBookingFormConfigurationService;
use App\Modules\Booking\Contracts\Provisioning\ProvisionBookingFoundationInterface;
use RuntimeException;

final readonly class ProvisionBookingFoundationService implements ProvisionBookingFoundationInterface
{
    public function __construct(private ManageBookingFormConfigurationService $configurations) {}

    public function execute(string $tenantId): int
    {
        $configuration = $this->configurations->read($tenantId)->toArray();
        $version = $configuration['version'] ?? null;
        if (! is_int($version)) {
            throw new RuntimeException('Provisioned Booking configuration version is unavailable.');
        }

        return $version;
    }
}
