<?php

declare(strict_types=1);

namespace App\Modules\WebsiteBuilder\Application\WebsiteAddress;

use App\Modules\WebsiteBuilder\Application\Exceptions\WebsiteOperationForbiddenException;
use App\Modules\WebsiteBuilder\Application\WebsiteAuthorization;
use App\Modules\WebsiteBuilder\Contracts\PublicAddress\WebsitePublicAddressData;
use App\Modules\WebsiteBuilder\Contracts\PublicAddress\WebsitePublicAddressRepositoryInterface;
use App\Modules\WebsiteBuilder\Contracts\Repositories\WebsiteRepositoryInterface;
use App\Modules\WebsiteBuilder\Domain\Exceptions\InvalidWebsiteValueException;
use App\Modules\WebsiteBuilder\Domain\ValueObjects\TenantId;
use App\Modules\WebsiteBuilder\Domain\ValueObjects\WebsiteId;
use App\Modules\WebsiteBuilder\Domain\ValueObjects\WebsiteLifecycle;
use DateTimeImmutable;
use RuntimeException;

final readonly class ReserveWebsiteSubdomainService
{
    public function __construct(
        private WebsiteRepositoryInterface $websites,
        private WebsitePublicAddressRepositoryInterface $addresses,
        private WebsiteAuthorization $authorization,
        private WebsiteSubdomainPolicy $subdomains,
    ) {}

    public function available(string $subdomain, string $websiteId): bool
    {
        return $this->addresses->isAvailable($this->subdomains->host($subdomain), $websiteId);
    }

    public function handle(ReserveWebsiteSubdomainCommand $command): WebsitePublicAddressData
    {
        if ($command->authorization->role !== 'website_designer') {
            throw new WebsiteOperationForbiddenException(
                'Only an assigned Website Designer may reserve a Website subdomain.',
            );
        }
        $tenantId = new TenantId($command->tenantId);
        $websiteId = new WebsiteId($command->websiteId);
        $this->authorization->assertCanUpdate($command->authorization, $tenantId);
        $website = $this->websites->findById($tenantId, $websiteId)
            ?? throw new RuntimeException('Website was not found in the authorized scope.');
        if ($website->lifecycle() === WebsiteLifecycle::Published) {
            throw new InvalidWebsiteValueException(
                'A published Website address requires the governed replacement workflow.',
            );
        }

        return $this->addresses->reservePrimary(
            $command->addressId,
            $tenantId->value,
            $websiteId->value,
            $this->subdomains->host($command->subdomain),
            new DateTimeImmutable,
        );
    }
}
