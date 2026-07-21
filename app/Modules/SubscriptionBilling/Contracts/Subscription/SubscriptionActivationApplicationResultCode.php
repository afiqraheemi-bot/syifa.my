<?php

declare(strict_types=1);

namespace App\Modules\SubscriptionBilling\Contracts\Subscription;

enum SubscriptionActivationApplicationResultCode: string
{
    case Applied = 'applied';
    case AlreadyReflected = 'already_reflected';
    case Superseded = 'superseded';
    case ReconciliationRequired = 'reconciliation_required';
    case InvalidEvidence = 'invalid_evidence';
    case TenantMismatch = 'tenant_mismatch';
    case CommercialOfferMismatch = 'commercial_offer_mismatch';
    case ObligationMismatch = 'obligation_mismatch';
}
