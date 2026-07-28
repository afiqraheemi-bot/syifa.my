<?php

declare(strict_types=1);

namespace App\Modules\WebsiteBuilder\Application\WebsiteAddress;

use App\Modules\WebsiteBuilder\Domain\Exceptions\InvalidWebsiteValueException;

final readonly class WebsiteSubdomainPolicy
{
    public function __construct(private string $baseDomain)
    {
        if (preg_match('/^(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\\.)+[a-z]{2,63}$/', $baseDomain) !== 1) {
            throw new InvalidWebsiteValueException('Public Website base domain is invalid.');
        }
    }

    public function host(string $label): string
    {
        $label = strtolower(trim($label));
        if (preg_match('/^[a-z0-9](?:[a-z0-9-]{1,61}[a-z0-9])?$/', $label) !== 1) {
            throw new InvalidWebsiteValueException(
                'Subdomain must contain 3 to 63 lowercase letters, numbers, or internal hyphens.',
            );
        }

        return $label.'.'.$this->baseDomain;
    }

    public function baseDomain(): string
    {
        return $this->baseDomain;
    }
}
