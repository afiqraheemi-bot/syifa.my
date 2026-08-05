<?php

declare(strict_types=1);

namespace App\Modules\WebsiteBuilder\Contracts\PublicAddress;

interface PublicWebsiteAddressAvailabilityInterface
{
    /**
     * @throws InvalidPublicWebsiteAddressException if the subdomain is malformed or reserved
     */
    public function available(string $subdomain, string $registrationOwner): bool;
}
