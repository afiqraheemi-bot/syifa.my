<?php

declare(strict_types=1);

namespace App\Modules\SubscriptionBilling\Domain\Aggregates\Subscription\ValueObjects;

enum EntitlementStatus: string
{
    case Pending = 'pending';
    case Effective = 'effective';
    case Changed = 'changed';
    case GraceRestricted = 'grace_restricted';
    case Suspended = 'suspended';
    case Expired = 'expired';
    case Superseded = 'superseded';
}
