<?php

declare(strict_types=1);

namespace App\Modules\WebsiteBuilder\Application\CustomDomain;

final readonly class CustomDomainData
{
    public function __construct(
        public string $id,
        public string $hostname,
        public string $status,
        public int $version,
        public string $verificationName,
        public ?string $verificationValue = null,
    ) {}
}
