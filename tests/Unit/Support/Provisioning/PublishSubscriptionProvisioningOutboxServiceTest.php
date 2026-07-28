<?php

declare(strict_types=1);

namespace Tests\Unit\Support\Provisioning;

use App\Modules\SubscriptionBilling\Contracts\Subscription\SubscriptionActivatedIntegrationEvent;
use App\Modules\SubscriptionBilling\Contracts\Subscription\SubscriptionIntegrationOutboxClaim;
use App\Modules\SubscriptionBilling\Contracts\Subscription\SubscriptionIntegrationOutboxRepositoryInterface;
use App\Support\Provisioning\Application\ClaimedProvisioningWorkflow;
use App\Support\Provisioning\Application\ProvisioningWorkflowData;
use App\Support\Provisioning\Application\ProvisioningWorkflowRepositoryInterface;
use App\Support\Provisioning\Application\RegisterProvisioningWorkflowService;
use App\Support\Provisioning\Infrastructure\PublishSubscriptionProvisioningOutboxService;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class PublishSubscriptionProvisioningOutboxServiceTest extends TestCase
{
    public function test_it_registers_the_durable_workflow_before_completing_the_outbox_claim(): void
    {
        $outbox = new ProvisioningOutboxStub($this->event());
        $workflows = new ProvisioningWorkflowRepositoryStub;
        $publisher = new PublishSubscriptionProvisioningOutboxService(
            $outbox,
            new RegisterProvisioningWorkflowService($workflows),
        );

        self::assertTrue($publisher->publishNext());
        self::assertSame(1, $workflows->registerCalls);
        self::assertSame(1, $outbox->completeCalls);
        self::assertSame(0, $outbox->releaseCalls);
        self::assertFalse($publisher->publishNext());
    }

    public function test_registration_failure_releases_the_claim_without_exposing_failure_details(): void
    {
        $outbox = new ProvisioningOutboxStub($this->event());
        $workflows = new ProvisioningWorkflowRepositoryStub(true);
        $publisher = new PublishSubscriptionProvisioningOutboxService(
            $outbox,
            new RegisterProvisioningWorkflowService($workflows),
        );

        self::assertTrue($publisher->publishNext());
        self::assertSame(0, $outbox->completeCalls);
        self::assertSame(1, $outbox->releaseCalls);
        self::assertSame('provisioning_workflow_registration_failed', $outbox->failureLabel);
    }

    private function event(): SubscriptionActivatedIntegrationEvent
    {
        return new SubscriptionActivatedIntegrationEvent(
            '10000000-0000-4000-8000-000000000001',
            '20000000-0000-4000-8000-000000000001',
            '30000000-0000-4000-8000-000000000001',
            '40000000-0000-4000-8000-000000000001',
            '50000000-0000-4000-8000-000000000001',
            '60000000-0000-4000-8000-000000000001',
            '70000000-0000-4000-8000-000000000001',
            '80000000-0000-4000-8000-000000000001',
            '2026-09-01',
            '2027-08-31',
            new DateTimeImmutable('2026-09-01T00:00:00Z'),
        );
    }
}

final class ProvisioningOutboxStub implements SubscriptionIntegrationOutboxRepositoryInterface
{
    public int $completeCalls = 0;

    public int $releaseCalls = 0;

    public ?string $failureLabel = null;

    private bool $claimed = false;

    public function __construct(private readonly SubscriptionActivatedIntegrationEvent $event) {}

    public function add(SubscriptionActivatedIntegrationEvent $event): void {}

    public function pending(DateTimeImmutable $availableAt, int $limit = 100): array
    {
        return [];
    }

    public function claimNext(DateTimeImmutable $now, int $leaseSeconds = 120): ?SubscriptionIntegrationOutboxClaim
    {
        if ($this->claimed) {
            return null;
        }
        $this->claimed = true;

        return new SubscriptionIntegrationOutboxClaim(
            $this->event,
            '90000000-0000-4000-8000-000000000001',
            $now->modify('+2 minutes'),
            1,
        );
    }

    public function completeDispatch(string $eventId, string $leaseToken, DateTimeImmutable $dispatchedAt): bool
    {
        $this->completeCalls++;

        return true;
    }

    public function releaseForRetry(string $eventId, string $leaseToken, DateTimeImmutable $nextRetryAt, string $safeFailureLabel, DateTimeImmutable $now): bool
    {
        $this->releaseCalls++;
        $this->failureLabel = $safeFailureLabel;

        return true;
    }
}

final class ProvisioningWorkflowRepositoryStub implements ProvisioningWorkflowRepositoryInterface
{
    public int $registerCalls = 0;

    public function __construct(private readonly bool $fail = false) {}

    public function register(SubscriptionActivatedIntegrationEvent $event): ProvisioningWorkflowData
    {
        $this->registerCalls++;
        if ($this->fail) {
            throw new \RuntimeException('Sensitive infrastructure detail.');
        }

        return new ProvisioningWorkflowData(
            $event->eventId,
            $event->eventId,
            $event->subscriptionId,
            $event->tenantId,
            $event->clinicRegistrationId,
            'pending',
            'tenant_provisioning',
            0,
            $event->occurredAt,
        );
    }

    public function findBySourceEvent(string $sourceEventId): ?ProvisioningWorkflowData
    {
        return null;
    }

    public function claimNext(DateTimeImmutable $now): ?ClaimedProvisioningWorkflow
    {
        return null;
    }

    public function advance(string $workflowId, string $claimToken, string $nextStep, DateTimeImmutable $now): bool
    {
        return false;
    }

    public function releaseForRetry(
        string $workflowId,
        string $claimToken,
        DateTimeImmutable $retryAt,
        string $safeFailureLabel,
        DateTimeImmutable $now,
    ): bool {
        return false;
    }

    public function complete(string $workflowId, string $claimToken, DateTimeImmutable $now): bool
    {
        return false;
    }
}
