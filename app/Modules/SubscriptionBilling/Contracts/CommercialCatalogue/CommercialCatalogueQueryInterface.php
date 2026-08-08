<?php

declare(strict_types=1);

namespace App\Modules\SubscriptionBilling\Contracts\CommercialCatalogue;

/**
 * Not the same thing as `App\Modules\AcquisitionOffer` (renamed from
 * `App\Modules\Commercial` on 2026-08-08) — that is the smaller "Commercial
 * Offer" bounded context (checkout snapshot: prepare, claim, cancel, expire)
 * used during public Clinic Registration. This `CommercialCatalogue`
 * namespace is the pricing/plans system of record (Plan, PlanOffering,
 * BillingOption, CapabilityDefinition). `App\Modules\AcquisitionOffer`
 * consumes this interface read-only through its own `Contracts\ReferenceData`
 * adapters; it never writes here. The two modules previously shared the
 * "Commercial" prefix by historical naming coincidence; the smaller module
 * was renamed to remove the collision — see
 * `docs/37_MASTER_ARCHITECTURE_PROGRESS.md` ("Next governed decisions").
 */
interface CommercialCatalogueQueryInterface
{
    public function findPlan(string $planId): ?PlanData;

    public function findBillingOption(string $billingOptionId): ?BillingOptionData;

    public function findPlanOffering(string $planOfferingId): ?PlanOfferingData;

    public function findCapability(string $capabilityId): ?CapabilityDefinitionData;
}
