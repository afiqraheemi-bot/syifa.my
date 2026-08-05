<?php

declare(strict_types=1);

namespace App\Support\Provisioning\Application;

use App\Modules\Booking\Contracts\Provisioning\ProvisionBookingFoundationInterface;
use App\Modules\ClinicRegistration\Contracts\Commands\CompleteClinicRegistrationFromTrustedHandoffCommand;
use App\Modules\ClinicRegistration\Contracts\Completion\TrustedClinicRegistrationCompletionInterface;
use App\Modules\ClinicRegistration\Contracts\Data\ClinicRegistrationData;
use App\Modules\ClinicRegistration\Contracts\Provisioning\ClinicRegistrationProvisioningReadInterface;
use App\Modules\Onboarding\Contracts\Provisioning\ProvisionOnboardingJobInterface;
use App\Modules\TenantManagement\Contracts\Provisioning\ProvisionTenantCommand;
use App\Modules\TenantManagement\Contracts\Provisioning\ProvisionTenantInterface;
use App\Modules\WebsiteBuilder\Contracts\Provisioning\ProvisionWebsiteFoundationCommand;
use App\Modules\WebsiteBuilder\Contracts\Provisioning\ProvisionWebsiteFoundationInterface;
use App\Modules\WebsiteBuilder\Contracts\Provisioning\ReserveProvisionedWebsiteAddressInterface;
use DateTimeImmutable;
use RuntimeException;
use Throwable;

final readonly class ProcessProvisioningWorkflowService
{
    /**
     * A workflow that still fails after this many claims (each claim already
     * represents one execution attempt) stops retrying and is dead-lettered
     * instead — an unrecoverable step (e.g. permanently invalid registration
     * data) must never retry forever.
     */
    private const int MAX_ATTEMPTS = 10;

    private const array STEPS = [
        'tenant_provisioning',
        'website_foundation',
        'booking_configuration',
        'website_address',
        'onboarding_job',
        'registration_completion',
    ];

    public function __construct(
        private ProvisioningWorkflowRepositoryInterface $workflows,
        private ClinicRegistrationProvisioningReadInterface $registrations,
        private ProvisionTenantInterface $tenants,
        private ProvisionWebsiteFoundationInterface $websites,
        private ProvisionBookingFoundationInterface $booking,
        private ReserveProvisionedWebsiteAddressInterface $addresses,
        private ProvisionOnboardingJobInterface $onboarding,
        private TrustedClinicRegistrationCompletionInterface $completion,
    ) {}

    public function processNext(): bool
    {
        $now = new DateTimeImmutable;
        $claim = $this->workflows->claimNext($now);
        if ($claim === null) {
            return false;
        }

        try {
            $this->executeStep($claim->workflow, $now);
        } catch (Throwable) {
            $safeFailureLabel = 'provisioning_'.$claim->workflow->currentStep.'_failed';

            $released = $claim->workflow->attemptCount >= self::MAX_ATTEMPTS
                ? $this->workflows->deadLetter($claim->workflow->id, $claim->claimToken, $safeFailureLabel, $now)
                : $this->workflows->releaseForRetry(
                    $claim->workflow->id,
                    $claim->claimToken,
                    $now->modify('+30 seconds'),
                    $safeFailureLabel,
                    $now,
                );

            if (! $released) {
                throw new RuntimeException('Provisioning workflow retry lease became stale.');
            }

            return true;
        }

        $next = $this->nextStep($claim->workflow->currentStep);
        $released = $next === null
            ? $this->workflows->complete($claim->workflow->id, $claim->claimToken, $now)
            : $this->workflows->advance($claim->workflow->id, $claim->claimToken, $next, $now);
        if (! $released) {
            throw new RuntimeException('Provisioning workflow completion lease became stale.');
        }

        return true;
    }

    private function executeStep(ProvisioningWorkflowData $workflow, DateTimeImmutable $now): void
    {
        $registration = $this->registration($workflow);
        $websiteId = $this->identifier($workflow->id, 'website');

        match ($workflow->currentStep) {
            'tenant_provisioning' => $this->tenants->execute(new ProvisionTenantCommand(
                $workflow->tenantId,
                $workflow->sourceEventId,
                $workflow->occurredAt,
            )),
            'website_foundation' => $this->websites->execute(new ProvisionWebsiteFoundationCommand(
                $workflow->tenantId,
                $this->identifier($workflow->id, 'clinic'),
                $websiteId,
                array_map(
                    fn (int $position): string => $this->identifier($workflow->id, 'section-'.$position),
                    range(1, 9),
                ),
                $this->required($registration->clinicName, 'clinic_name'),
                $this->required($registration->clinicEmail, 'clinic_email'),
                $this->required($registration->clinicPhone, 'clinic_phone'),
                $this->required($registration->clinicAddress, 'clinic_address'),
                $workflow->occurredAt,
                $registration->selectedWebsiteTemplate ?? 'SYIFA_ESSENTIAL',
            )),
            'booking_configuration' => $this->booking->execute($workflow->tenantId),
            'website_address' => $this->addresses->execute(
                $this->identifier($workflow->id, 'website-address'),
                $workflow->tenantId,
                $websiteId,
                $this->required($registration->clinicName, 'clinic_name'),
                $workflow->occurredAt,
                $registration->preferredSubdomain,
            ),
            'onboarding_job' => $this->onboarding->execute(
                $this->identifier($workflow->id, 'onboarding-job'),
                $workflow->tenantId,
                $websiteId,
                $registration->registrationCorrelationReference,
                $workflow->occurredAt,
            ),
            'registration_completion' => $this->completion->execute(
                new CompleteClinicRegistrationFromTrustedHandoffCommand(
                    $registration->registrationCorrelationReference,
                    $workflow->tenantId,
                    'provisioning_orchestrator',
                    $now,
                    $workflow->sourceEventId,
                ),
            ),
            default => throw new RuntimeException('Provisioning workflow step is unsupported.'),
        };
    }

    private function registration(ProvisioningWorkflowData $workflow): ClinicRegistrationData
    {
        $registration = $this->registrations->approved($workflow->clinicRegistrationId);
        if ($registration === null
            || $registration->reservedTenantId !== $workflow->tenantId
            || ($registration->provisionedTenantReference !== null
                && $registration->provisionedTenantReference !== $workflow->tenantId)) {
            throw new RuntimeException('Provisioning registration lineage is unavailable.');
        }

        return $registration;
    }

    private function nextStep(string $current): ?string
    {
        $position = array_search($current, self::STEPS, true);
        if (! is_int($position)) {
            throw new RuntimeException('Provisioning workflow step is unsupported.');
        }

        return self::STEPS[$position + 1] ?? null;
    }

    private function required(?string $value, string $field): string
    {
        if ($value === null || trim($value) === '') {
            throw new RuntimeException('Provisioning registration '.$field.' is unavailable.');
        }

        return $value;
    }

    private function identifier(string $workflowId, string $purpose): string
    {
        $hex = substr(hash('sha256', $workflowId.':'.$purpose), 0, 32);
        $hex[12] = '5';
        $hex[16] = dechex((hexdec($hex[16]) & 0x3) | 0x8);

        return sprintf('%s-%s-%s-%s-%s', substr($hex, 0, 8), substr($hex, 8, 4), substr($hex, 12, 4), substr($hex, 16, 4), substr($hex, 20));
    }
}
