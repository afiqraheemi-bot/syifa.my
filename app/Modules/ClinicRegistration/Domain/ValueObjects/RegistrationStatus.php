<?php

declare(strict_types=1);

namespace App\Modules\ClinicRegistration\Domain\ValueObjects;

enum RegistrationStatus: string
{
    case Draft = 'draft';
    case Submitted = 'submitted';
    case UnderReview = 'under_review';
    case CorrectionRequested = 'correction_requested';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Provisioned = 'provisioned';
    case Cancelled = 'cancelled';
    case Expired = 'expired';

    public function isTerminal(): bool
    {
        return in_array($this, [self::Rejected, self::Provisioned, self::Cancelled, self::Expired], true);
    }
}
