<?php

declare(strict_types=1);

namespace App\Modules\WebsiteBuilder\Domain\CustomDomain;

enum CustomDomainStatus: string
{
    case VerificationPending = 'verification_pending';
    case Verified = 'verified';
    case Active = 'active';
    case Failing = 'failing';
    case Detached = 'detached';
    case Quarantined = 'quarantined';
}
