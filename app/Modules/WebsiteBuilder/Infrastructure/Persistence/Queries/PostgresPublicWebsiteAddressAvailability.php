<?php

declare(strict_types=1);

namespace App\Modules\WebsiteBuilder\Infrastructure\Persistence\Queries;

use App\Modules\WebsiteBuilder\Application\WebsiteAddress\WebsiteSubdomainPolicy;
use App\Modules\WebsiteBuilder\Contracts\PublicAddress\InvalidPublicWebsiteAddressException;
use App\Modules\WebsiteBuilder\Contracts\PublicAddress\PublicWebsiteAddressAvailabilityInterface;
use App\Modules\WebsiteBuilder\Contracts\PublicAddress\WebsitePublicAddressRepositoryInterface;
use App\Modules\WebsiteBuilder\Domain\Exceptions\InvalidWebsiteValueException;
use Illuminate\Database\ConnectionInterface;

final readonly class PostgresPublicWebsiteAddressAvailability implements PublicWebsiteAddressAvailabilityInterface
{
    public function __construct(
        private ConnectionInterface $connection,
        private WebsitePublicAddressRepositoryInterface $addresses,
        private WebsiteSubdomainPolicy $subdomains,
    ) {}

    public function available(string $subdomain, string $registrationOwner): bool
    {
        try {
            $host = $this->subdomains->host($subdomain);
        } catch (InvalidWebsiteValueException $exception) {
            throw new InvalidPublicWebsiteAddressException($exception->getMessage(), previous: $exception);
        }

        return $this->addresses->isAvailable($host, '00000000-0000-4000-8000-000000000000')
            && ! $this->connection->table('clinic_registrations')
                ->where('preferred_subdomain', $subdomain)
                ->where('platform_identity_id', '!=', $registrationOwner)
                ->whereIn('status', ['draft', 'submitted', 'under_review', 'correction_requested', 'approved'])
                ->exists();
    }
}
