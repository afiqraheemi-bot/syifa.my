<?php

declare(strict_types=1);

namespace App\Modules\SubscriptionBilling\Contracts\Renewal;

use InvalidArgumentException;

final readonly class RedirectAction
{
    public string $kind;

    public string $method;

    public function __construct(public string $destination)
    {
        $parts = parse_url($destination);
        if (! is_array($parts) || ($parts['scheme'] ?? null) !== 'https'
            || ! is_string($parts['host'] ?? null) || $parts['host'] === ''
            || isset($parts['user']) || isset($parts['pass'])) {
            throw new InvalidArgumentException('Redirect destination must be an absolute credential-free HTTPS URL.');
        }

        $this->kind = 'redirect';
        $this->method = 'GET';
    }
}
