<?php

declare(strict_types=1);

namespace App\Modules\WebsiteBuilder\Application\Delivery;

use App\Modules\WebsiteBuilder\Application\Delivery\Exceptions\InvalidPublicDeliveryValueException;

/**
 * The governed `brand-*` semantic token family required by the accepted
 * Design Token Taxonomy. Every value is always a normalized `#RRGGBB`
 * string — either derived from the tenant's own published Branding colour
 * or the platform fallback for that role. Never partially populated.
 */
final readonly class BrandTokens
{
    private const string HEX_PATTERN = '/^#[0-9A-F]{6}$/';

    public function __construct(
        public string $primary,
        public string $primaryHover,
        public string $primaryActive,
        public string $onPrimary,
        public string $secondary,
        public string $onSecondary,
    ) {
        foreach ([$primary, $primaryHover, $primaryActive, $onPrimary, $secondary, $onSecondary] as $value) {
            if (preg_match(self::HEX_PATTERN, $value) !== 1) {
                throw new InvalidPublicDeliveryValueException('Resolved brand token is not a normalized colour.');
            }
        }
    }
}
