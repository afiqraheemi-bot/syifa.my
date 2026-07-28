<?php

declare(strict_types=1);

namespace App\Modules\WebsiteBuilder\Contracts\PublicAddress;

use DateTimeImmutable;

interface WebsitePublicAddressRepositoryInterface extends WebsitePublicAddressReadInterface
{
    public function isAvailable(string $normalizedHost, string $websiteId): bool;

    public function reservePrimary(
        string $addressId,
        string $trustedTenantId,
        string $websiteId,
        string $normalizedHost,
        DateTimeImmutable $at,
    ): WebsitePublicAddressData;

    public function activatePrimary(
        string $trustedTenantId,
        string $websiteId,
        DateTimeImmutable $at,
    ): WebsitePublicAddressData;
}
