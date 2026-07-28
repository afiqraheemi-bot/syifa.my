<?php

declare(strict_types=1);

namespace App\Modules\WebsiteBuilder\Application\Provisioning;

use App\Modules\WebsiteBuilder\Application\WebsiteAddress\WebsiteSubdomainPolicy;
use App\Modules\WebsiteBuilder\Contracts\Provisioning\ReserveProvisionedWebsiteAddressInterface;
use App\Modules\WebsiteBuilder\Contracts\PublicAddress\WebsitePublicAddressData;
use App\Modules\WebsiteBuilder\Contracts\PublicAddress\WebsitePublicAddressRepositoryInterface;
use DateTimeImmutable;

final readonly class ReserveProvisionedWebsiteAddressService implements ReserveProvisionedWebsiteAddressInterface
{
    public function __construct(
        private WebsitePublicAddressRepositoryInterface $addresses,
        private WebsiteSubdomainPolicy $subdomains,
    ) {}

    public function execute(
        string $addressId,
        string $tenantId,
        string $websiteId,
        string $clinicName,
        DateTimeImmutable $occurredAt,
    ): WebsitePublicAddressData {
        $existing = $this->addresses->forWebsite($tenantId, $websiteId);
        if ($existing !== null) {
            return $existing;
        }

        $label = strtolower(trim((string) preg_replace('/[^a-z0-9]+/i', '-', $clinicName), '-'));
        $label = substr($label, 0, 50);
        if (strlen($label) < 3) {
            $label = 'clinic';
        }
        $host = $this->subdomains->host($label);
        if (! $this->addresses->isAvailable($host, $websiteId)) {
            $host = $this->subdomains->host(substr($label, 0, 50).'-'.substr(str_replace('-', '', $tenantId), 0, 8));
        }

        return $this->addresses->reservePrimary(
            $addressId,
            $tenantId,
            $websiteId,
            $host,
            $occurredAt,
        );
    }
}
