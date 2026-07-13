<?php

declare(strict_types=1);

namespace App\Modules\TenantManagement\Infrastructure\TenantRouting;

use App\Modules\TenantManagement\Contracts\Authentication\TrustedTenantSelectionData;
use App\Modules\TenantManagement\Contracts\Authentication\TrustedTenantSelectorInterface;
use App\Modules\TenantManagement\Contracts\TenantRouting\TenantAdminRoutingLookupInterface;
use App\Modules\TenantManagement\Domain\Aggregates\Tenant\Exceptions\InvalidTenantIdentityValueException;
use App\Modules\TenantManagement\Domain\Aggregates\Tenant\ValueObjects\TenantId;

final readonly class TenantAdminHostTrustedTenantSelector implements TrustedTenantSelectorInterface
{
    public function __construct(
        private AdminHostParser $hostParser,
        private TenantAdminRoutingLookupInterface $routingLookup,
    ) {}

    public function select(string $selectorReference): ?TrustedTenantSelectionData
    {
        $routingLabel = $this->hostParser->parse($selectorReference);

        if ($routingLabel === null) {
            return null;
        }

        $routingData = $this->routingLookup->resolve($routingLabel->value);

        if ($routingData === null || $routingData->routingLabel !== $routingLabel->value) {
            return null;
        }

        try {
            $tenantId = new TenantId($routingData->tenantId);
        } catch (InvalidTenantIdentityValueException) {
            return null;
        }

        return new TrustedTenantSelectionData($tenantId->value);
    }
}
