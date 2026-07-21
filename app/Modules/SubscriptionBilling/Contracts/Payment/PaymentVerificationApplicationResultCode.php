<?php

declare(strict_types=1);

namespace App\Modules\SubscriptionBilling\Contracts\Payment;

enum PaymentVerificationApplicationResultCode: string
{
    case Applied = 'applied';
    case AlreadyReflected = 'already_reflected';
    case Superseded = 'superseded';
    case Regressive = 'regressive';
    case ReconciliationRequired = 'reconciliation_required';
    case InvalidEvidence = 'invalid_evidence';
    case PaymentMissing = 'payment_missing';
    case AttemptMismatch = 'attempt_mismatch';
}
