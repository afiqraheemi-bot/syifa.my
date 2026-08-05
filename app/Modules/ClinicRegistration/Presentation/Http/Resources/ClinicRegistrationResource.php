<?php

declare(strict_types=1);

namespace App\Modules\ClinicRegistration\Presentation\Http\Resources;

use App\Modules\ClinicRegistration\Contracts\Data\ClinicRegistrationData;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin ClinicRegistrationData */
final class ClinicRegistrationResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        /** @var ClinicRegistrationData $registration */
        $registration = $this->resource;

        return [
            'id' => $registration->id,
            'status' => $registration->status,
            'clinic' => [
                'name' => $registration->clinicName,
                'email' => $registration->clinicEmail,
                'phone' => $registration->clinicPhone,
                'address' => $registration->clinicAddress,
            ],
            'website_preferences' => [
                'subdomain' => $registration->preferredSubdomain,
                'host' => $registration->preferredSubdomain === null
                    ? null
                    : $registration->preferredSubdomain.'.'.config('public_website_delivery.base_domain'),
                'template' => $registration->selectedWebsiteTemplate,
            ],
            'commercial_selection' => [
                'plan_offering_reference' => $registration->selectedPlanOfferingReference,
                'billing_option_reference' => $registration->selectedBillingOptionReference,
                'snapshot_version' => $registration->commercialSnapshotVersion,
            ],
            'registration_correlation_reference' => $registration->registrationCorrelationReference,
            'provisioned_tenant_reference' => $registration->provisionedTenantReference,
            'submitted_at' => $registration->submittedAt,
            'provisioned_at' => $registration->provisionedAt,
            'cancelled_at' => $registration->cancelledAt,
            'expired_at' => $registration->expiredAt,
            'version' => $registration->version,
            'declarations' => array_map(
                static fn ($declaration): array => [
                    'key' => $declaration->key,
                    'version' => $declaration->version,
                    'accepted_at' => $declaration->acceptedAt,
                ],
                $registration->declarations,
            ),
            'decisions' => array_map(
                static fn ($decision): array => [
                    'id' => $decision->id,
                    'outcome' => $decision->outcome,
                    'reasonCategory' => $decision->reasonCategory,
                    'correctionInstructions' => $decision->correctionInstructions,
                    'decidedAt' => $decision->decidedAt,
                    'supersededAt' => $decision->supersededAt,
                ],
                $registration->decisions,
            ),
        ];
    }
}
