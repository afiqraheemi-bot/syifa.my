<?php

declare(strict_types=1);

namespace App\Modules\WebsiteBuilder\Contracts\CustomDomain;

interface DomainControlVerifierInterface
{
    public function hasTxtProof(string $hostname, string $proof): bool;

    public function isRoutedToPlatform(string $hostname): bool;
}
