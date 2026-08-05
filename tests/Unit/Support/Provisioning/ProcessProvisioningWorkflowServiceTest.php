<?php

declare(strict_types=1);

namespace Tests\Unit\Support\Provisioning;

use App\Modules\Booking\Contracts\Provisioning\ProvisionBookingFoundationInterface;
use App\Modules\ClinicRegistration\Contracts\Completion\TrustedClinicRegistrationCompletionInterface;
use App\Modules\ClinicRegistration\Contracts\Data\ClinicRegistrationData;
use App\Modules\ClinicRegistration\Contracts\Provisioning\ClinicRegistrationProvisioningReadInterface;
use App\Modules\Onboarding\Contracts\Provisioning\ProvisionOnboardingJobInterface;
use App\Modules\SubscriptionBilling\Contracts\Subscription\SubscriptionActivatedIntegrationEvent;
use App\Modules\TenantManagement\Contracts\Provisioning\ProvisionedTenantData;
use App\Modules\TenantManagement\Contracts\Provisioning\ProvisionTenantInterface;
use App\Modules\WebsiteBuilder\Contracts\Provisioning\ProvisionedWebsiteFoundationData;
use App\Modules\WebsiteBuilder\Contracts\Provisioning\ProvisionWebsiteFoundationInterface;
use App\Modules\WebsiteBuilder\Contracts\Provisioning\ReserveProvisionedWebsiteAddressInterface;
use App\Modules\WebsiteBuilder\Contracts\PublicAddress\WebsitePublicAddressData;
use App\Support\Provisioning\Application\ClaimedProvisioningWorkflow;
use App\Support\Provisioning\Application\ProcessProvisioningWorkflowService;
use App\Support\Provisioning\Application\ProvisioningWorkflowData;
use App\Support\Provisioning\Application\ProvisioningWorkflowRepositoryInterface;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class ProcessProvisioningWorkflowServiceTest extends TestCase
{
    public function test_it_completes_every_module_handoff_in_governed_order(): void
    {
        $workflow = new ProvisioningWorkflowData(
            $this->uuid(1),
            $this->uuid(2),
            $this->uuid(3),
            $this->uuid(4),
            $this->uuid(5),
            'pending',
            'tenant_provisioning',
            0,
            new DateTimeImmutable('2026-09-01T00:00:00Z'),
        );
        $workflows = new InMemoryProvisioningWorkflowRepository($workflow);
        $registration = new ClinicRegistrationData(
            $workflow->clinicRegistrationId,
            $this->uuid(6),
            'approved',
            'Klinik Syifa Baru',
            'owner@syifa.test',
            '+60123456789',
            'Kuala Lumpur',
            $this->uuid(7),
            $this->uuid(8),
            '1',
            'registration-correlation',
            $workflow->tenantId,
            null,
            '2026-09-01T00:00:00+00:00',
            null,
            null,
            null,
            2,
            [],
            [],
            'klinik-syifa-baru',
            'SYIFA_DENTAL',
        );

        $registrations = $this->createMock(ClinicRegistrationProvisioningReadInterface::class);
        $registrations->expects(self::exactly(6))->method('approved')->with($workflow->clinicRegistrationId)->willReturn($registration);
        $tenants = $this->createMock(ProvisionTenantInterface::class);
        $tenants->expects(self::once())->method('execute')->willReturn(new ProvisionedTenantData($workflow->tenantId, 'provisioning', 1, false));
        $websites = $this->createMock(ProvisionWebsiteFoundationInterface::class);
        $websites->expects(self::once())->method('execute')
            ->with(self::callback(static function ($command): bool {
                self::assertSame('SYIFA_DENTAL', $command->templateId);

                return true;
            }))
            ->willReturn(
                new ProvisionedWebsiteFoundationData($workflow->tenantId, $this->uuid(9), $this->uuid(10)),
            );
        $booking = $this->createMock(ProvisionBookingFoundationInterface::class);
        $booking->expects(self::once())->method('execute')->with($workflow->tenantId)->willReturn(1);
        $addresses = $this->createMock(ReserveProvisionedWebsiteAddressInterface::class);
        $addresses->expects(self::once())->method('execute')
            ->with(
                self::anything(),
                $workflow->tenantId,
                self::anything(),
                'Klinik Syifa Baru',
                $workflow->occurredAt,
                'klinik-syifa-baru',
            )
            ->willReturn(
                new WebsitePublicAddressData($this->uuid(10), $workflow->tenantId, 'klinik-syifa-baru.syifa.my', 'https://klinik-syifa-baru.syifa.my', false),
            );
        $onboarding = $this->createMock(ProvisionOnboardingJobInterface::class);
        $onboarding->expects(self::once())->method('execute')->willReturn($this->uuid(11));
        $completion = $this->createMock(TrustedClinicRegistrationCompletionInterface::class);
        $completion->expects(self::once())->method('execute')->willReturn($registration);

        $processor = new ProcessProvisioningWorkflowService(
            $workflows,
            $registrations,
            $tenants,
            $websites,
            $booking,
            $addresses,
            $onboarding,
            $completion,
        );

        for ($step = 0; $step < 6; $step++) {
            self::assertTrue($processor->processNext());
        }
        self::assertFalse($processor->processNext());
        self::assertSame('completed', $workflows->workflow->status);
        self::assertSame('completed', $workflows->workflow->currentStep);
    }

    public function test_it_dead_letters_a_workflow_that_exhausts_its_retry_budget(): void
    {
        $workflow = new ProvisioningWorkflowData(
            $this->uuid(1),
            $this->uuid(2),
            $this->uuid(3),
            $this->uuid(4),
            $this->uuid(5),
            'processing',
            'tenant_provisioning',
            10,
            new DateTimeImmutable('2026-09-01T00:00:00Z'),
        );
        $workflows = new InMemoryProvisioningWorkflowRepository($workflow);
        $registrations = $this->createMock(ClinicRegistrationProvisioningReadInterface::class);
        $registrations->method('approved')->willReturn(new ClinicRegistrationData(
            $workflow->clinicRegistrationId,
            $this->uuid(6),
            'approved',
            'Klinik Syifa Baru',
            'owner@syifa.test',
            '+60123456789',
            'Kuala Lumpur',
            $this->uuid(7),
            $this->uuid(8),
            '1',
            'registration-correlation',
            $workflow->tenantId,
            null,
            '2026-09-01T00:00:00+00:00',
            null,
            null,
            null,
            2,
            [],
            [],
            'klinik-syifa-baru',
            'SYIFA_DENTAL',
        ));
        $tenants = $this->createMock(ProvisionTenantInterface::class);
        $tenants->method('execute')->willThrowException(new \RuntimeException('Tenant provisioning is permanently broken.'));

        $processor = new ProcessProvisioningWorkflowService(
            $workflows,
            $registrations,
            $tenants,
            $this->createMock(ProvisionWebsiteFoundationInterface::class),
            $this->createMock(ProvisionBookingFoundationInterface::class),
            $this->createMock(ReserveProvisionedWebsiteAddressInterface::class),
            $this->createMock(ProvisionOnboardingJobInterface::class),
            $this->createMock(TrustedClinicRegistrationCompletionInterface::class),
        );

        self::assertTrue($processor->processNext());

        self::assertSame(1, $workflows->deadLetterCalls);
        self::assertSame('failed', $workflows->workflow->status);
        self::assertFalse($processor->processNext());
    }

    private function uuid(int $suffix): string
    {
        return sprintf('00000000-0000-4000-8000-%012d', $suffix);
    }
}

final class InMemoryProvisioningWorkflowRepository implements ProvisioningWorkflowRepositoryInterface
{
    public int $deadLetterCalls = 0;

    public function __construct(public ProvisioningWorkflowData $workflow) {}

    public function register(SubscriptionActivatedIntegrationEvent $event): ProvisioningWorkflowData
    {
        return $this->workflow;
    }

    public function findBySourceEvent(string $sourceEventId): ?ProvisioningWorkflowData
    {
        return $this->workflow->sourceEventId === $sourceEventId ? $this->workflow : null;
    }

    public function claimNext(DateTimeImmutable $now): ?ClaimedProvisioningWorkflow
    {
        if (in_array($this->workflow->status, ['completed', 'failed'], true)) {
            return null;
        }

        return new ClaimedProvisioningWorkflow($this->workflow, 'claim-token');
    }

    public function advance(string $workflowId, string $claimToken, string $nextStep, DateTimeImmutable $now): bool
    {
        $this->workflow = new ProvisioningWorkflowData(
            $this->workflow->id,
            $this->workflow->sourceEventId,
            $this->workflow->subscriptionId,
            $this->workflow->tenantId,
            $this->workflow->clinicRegistrationId,
            'pending',
            $nextStep,
            $this->workflow->attemptCount + 1,
            $this->workflow->occurredAt,
        );

        return true;
    }

    public function releaseForRetry(
        string $workflowId,
        string $claimToken,
        DateTimeImmutable $retryAt,
        string $safeFailureLabel,
        DateTimeImmutable $now,
    ): bool {
        $this->workflow = new ProvisioningWorkflowData(
            $this->workflow->id,
            $this->workflow->sourceEventId,
            $this->workflow->subscriptionId,
            $this->workflow->tenantId,
            $this->workflow->clinicRegistrationId,
            'retry_pending',
            $this->workflow->currentStep,
            $this->workflow->attemptCount + 1,
            $this->workflow->occurredAt,
        );

        return true;
    }

    public function deadLetter(
        string $workflowId,
        string $claimToken,
        string $safeFailureLabel,
        DateTimeImmutable $now,
    ): bool {
        $this->deadLetterCalls++;
        $this->workflow = new ProvisioningWorkflowData(
            $this->workflow->id,
            $this->workflow->sourceEventId,
            $this->workflow->subscriptionId,
            $this->workflow->tenantId,
            $this->workflow->clinicRegistrationId,
            'failed',
            $this->workflow->currentStep,
            $this->workflow->attemptCount,
            $this->workflow->occurredAt,
        );

        return true;
    }

    public function complete(string $workflowId, string $claimToken, DateTimeImmutable $now): bool
    {
        $this->workflow = new ProvisioningWorkflowData(
            $this->workflow->id,
            $this->workflow->sourceEventId,
            $this->workflow->subscriptionId,
            $this->workflow->tenantId,
            $this->workflow->clinicRegistrationId,
            'completed',
            'completed',
            $this->workflow->attemptCount + 1,
            $this->workflow->occurredAt,
        );

        return true;
    }
}
