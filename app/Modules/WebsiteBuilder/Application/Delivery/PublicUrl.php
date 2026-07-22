<?php

declare(strict_types=1);

namespace App\Modules\WebsiteBuilder\Application\Delivery;

use App\Modules\WebsiteBuilder\Application\Delivery\Exceptions\InvalidPublicDeliveryValueException;

final readonly class PublicUrl
{
    public function __construct(public string $value)
    {
        $parts = parse_url($value);
        $fragment = $parts['fragment'] ?? null;
        if (! is_array($parts) || ! in_array($parts['scheme'] ?? null, ['https', 'http'], true) || ! isset($parts['host']) || isset($parts['user']) || isset($parts['pass']) || ($fragment !== null && preg_match('/^[a-z0-9-]+$/', $fragment) !== 1)) {
            throw new InvalidPublicDeliveryValueException('Public URL is invalid.');
        }
    }
}
