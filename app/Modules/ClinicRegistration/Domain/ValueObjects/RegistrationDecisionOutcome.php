<?php

declare(strict_types=1);

namespace App\Modules\ClinicRegistration\Domain\ValueObjects;

enum RegistrationDecisionOutcome: string
{
    case Approved = 'approved';
    case Rejected = 'rejected';
    case CorrectionRequested = 'correction_requested';
}
