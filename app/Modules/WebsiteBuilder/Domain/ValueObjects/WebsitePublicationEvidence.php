<?php

declare(strict_types=1);

namespace App\Modules\WebsiteBuilder\Domain\ValueObjects;

use App\Modules\WebsiteBuilder\Domain\Exceptions\InvalidWebsiteValueException;

final readonly class WebsitePublicationEvidence
{
    public function __construct(public bool $approvalGranted, public bool $entitlementActive)
    {
        if (! $approvalGranted || ! $entitlementActive) {
            throw new InvalidWebsiteValueException('Website publication requires approval and active entitlement evidence.');
        }
    }
}
