<?php

declare(strict_types=1);

namespace App\Modules\SubscriptionBilling\Contracts\Authorization;

/**
 * The sole authorization boundary for Payment Provider administration
 * (assess/enable/disable/make-default). Presentation must never resolve a
 * Platform Principal or compare a role itself — it asks this boundary for a
 * decision and acts on the result only.
 *
 * No approved Super-Admin category/permission exists for Payment Provider
 * administration in 21_PERMISSION_MATRIX.md, ADR-005, ADR-008, or ADR-009 —
 * only ADR-009's own text ("Provider activation is platform-global
 * operational configuration controlled by Super Admin") governs this
 * surface, and it does not call for category-scoped grant evaluation the way
 * 28_COMMERCIAL_CATALOGUE_SPECIFICATION.md explicitly did for Commercial
 * Catalogue. This boundary therefore enforces exactly what ADR-009 approved
 * — authenticated Platform Identity holding the Super Admin role — and no
 * more. Extending this to the category-scoped PlatformAuthorizationInterface
 * model (as ADR-005 generally prefers for privileged operations) requires
 * its own governance decision naming and provisioning that category; this
 * interface does not invent one.
 */
interface PaymentProviderAdministrationAuthorizationInterface
{
    public function authorize(): PaymentProviderAdministrationDecision;
}
