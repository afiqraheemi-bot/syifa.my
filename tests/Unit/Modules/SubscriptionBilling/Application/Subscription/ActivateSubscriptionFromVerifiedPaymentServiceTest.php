<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\SubscriptionBilling\Application\Subscription;

use App\Modules\SubscriptionBilling\Application\Subscription\ActivateSubscriptionFromVerifiedPaymentService;
use App\Modules\SubscriptionBilling\Application\Subscription\AnnualTermCalculator;
use App\Modules\SubscriptionBilling\Application\Subscription\SubscriptionActivationRetryPolicy;
use App\Modules\SubscriptionBilling\Contracts\Entitlements\ComputedSubscriptionEntitlementData;
use App\Modules\SubscriptionBilling\Contracts\Entitlements\SubscriptionEntitlementComputationInterface;
use App\Modules\SubscriptionBilling\Contracts\Repositories\SubscriptionRepositoryInterface;
use App\Modules\SubscriptionBilling\Contracts\Subscription\SubscriptionActivationApplication;
use App\Modules\SubscriptionBilling\Contracts\Subscription\SubscriptionActivationApplicationRepositoryInterface;
use App\Modules\SubscriptionBilling\Contracts\Subscription\SubscriptionActivationApplicationResultCode;
use App\Modules\SubscriptionBilling\Contracts\Subscription\SubscriptionActivationApplicationStatus;
use App\Modules\SubscriptionBilling\Contracts\Subscription\SubscriptionActivationAuditInterface;
use App\Modules\SubscriptionBilling\Contracts\Subscription\SubscriptionActivationEvidence;
use App\Modules\SubscriptionBilling\Contracts\Subscription\SubscriptionActivationEvidenceRepositoryInterface;
use App\Modules\SubscriptionBilling\Contracts\Subscription\SubscriptionActivationReconciliationCaseRepositoryInterface;
use App\Modules\SubscriptionBilling\Contracts\Subscription\SubscriptionActivationTransactionInterface;
use App\Modules\SubscriptionBilling\Domain\Aggregates\Subscription\Subscription;
use DateTimeImmutable;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class ActivateSubscriptionFromVerifiedPaymentServiceTest extends TestCase
{
    private SubscriptionActivationApplicationRepositoryInterface&MockObject $applications;

    private SubscriptionActivationEvidenceRepositoryInterface&MockObject $evidence;

    private SubscriptionRepositoryInterface&MockObject $subscriptions;

    private SubscriptionActivationAuditInterface&MockObject $audit;

    protected function setUp(): void
    {
        $this->applications = $this->createMock(SubscriptionActivationApplicationRepositoryInterface::class);
        $this->evidence = $this->createMock(SubscriptionActivationEvidenceRepositoryInterface::class);
        $this->subscriptions = $this->createMock(SubscriptionRepositoryInterface::class);
        $this->audit = $this->createMock(SubscriptionActivationAuditInterface::class);
    }

    public function test_success_creates_and_activates_subscription_then_completes_and_audits(): void
    {
        $this->claim();
        $this->evidence->method('loadForUpdate')->willReturn($this->evidence());
        $this->subscriptions->method('findByPaymentId')->willReturn(null);
        $this->subscriptions->method('findByTenantId')->willReturn(null);
        $this->subscriptions->expects(self::once())->method('save')->with(self::callback(
            static fn (Subscription $subscription): bool => $subscription->status()->value === 'active'
                && $subscription->billingPeriod()->startsOn === '2026-07-25'
                && $subscription->billingPeriod()->endsOn === '2027-07-24',
        ));
        $this->applications->expects(self::once())->method('complete')->with(
            $this->uuid(1), $this->uuid(9), SubscriptionActivationApplicationStatus::Applied,
            SubscriptionActivationApplicationResultCode::Applied,
        )->willReturn(true);
        $this->audit->expects(self::once())->method('record')->with('subscription.activation.applied');

        $this->service()->execute($this->uuid(1), $this->time());
    }

    public function test_non_authoritative_evidence_is_quarantined_fail_closed(): void
    {
        $this->claim();
        $this->evidence->method('loadForUpdate')->willReturn($this->evidence(authoritative: false));
        $this->subscriptions->expects(self::never())->method('save');
        $this->applications->expects(self::once())->method('complete')->with(
            $this->uuid(1), $this->uuid(9), SubscriptionActivationApplicationStatus::Quarantined,
            SubscriptionActivationApplicationResultCode::InvalidEvidence,
        )->willReturn(true);
        $this->audit->expects(self::once())->method('record')->with('subscription.activation.quarantined');

        $this->service()->execute($this->uuid(1), $this->time());
    }

    public function test_terminal_or_concurrently_claimed_application_is_idempotent_no_op(): void
    {
        $this->applications->expects(self::once())->method('claim')->willReturn(null);
        $this->evidence->expects(self::never())->method('loadForUpdate');
        $this->subscriptions->expects(self::never())->method('save');
        $this->audit->expects(self::never())->method('record');

        $this->service()->execute($this->uuid(1), $this->time());
    }

    private function service(): ActivateSubscriptionFromVerifiedPaymentService
    {
        $entitlements = $this->createMock(SubscriptionEntitlementComputationInterface::class);
        $entitlements->method('compute')->willReturn(new ComputedSubscriptionEntitlementData(
            $this->uuid(6), $this->uuid(7), 'capability-v1', ['appointments.manage'],
        ));
        $reconciliations = $this->createMock(SubscriptionActivationReconciliationCaseRepositoryInterface::class);
        $transactions = $this->createMock(SubscriptionActivationTransactionInterface::class);
        $transactions->method('run')->willReturnCallback(static fn (callable $operation): mixed => $operation());

        return new ActivateSubscriptionFromVerifiedPaymentService(
            $this->applications, $this->evidence, $this->subscriptions, $entitlements,
            $reconciliations, $this->audit, $transactions, new AnnualTermCalculator,
            new SubscriptionActivationRetryPolicy,
        );
    }

    private function claim(): void
    {
        $this->applications->method('claim')->willReturn(new SubscriptionActivationApplication(
            $this->uuid(1), $this->uuid(2), $this->uuid(4), $this->uuid(8), $this->uuid(3),
            SubscriptionActivationApplicationStatus::Processing, 1, $this->uuid(9), $this->time('+2 minutes'), null, null,
        ));
    }

    private function evidence(bool $authoritative = true): SubscriptionActivationEvidence
    {
        return new SubscriptionActivationEvidence(
            $authoritative, $this->uuid(4), 'succeeded', $this->uuid(3), $this->uuid(5), $this->uuid(10), 12500, 'MYR',
            $this->uuid(5), $this->uuid(3), $this->uuid(10), $this->uuid(4), $this->uuid(11), $this->uuid(6), $this->uuid(7),
            '2026-07-01', '2027-06-30', 12500, 'MYR', 'offer-v1', 'capability-v1', $this->uuid(10), $this->uuid(3),
        );
    }

    private function time(string $modifier = ''): DateTimeImmutable
    {
        $time = new DateTimeImmutable('2026-07-25T00:00:00Z');

        return $modifier === '' ? $time : $time->modify($modifier);
    }

    private function uuid(int $suffix): string
    {
        return sprintf('00000000-0000-4000-8000-%012d', $suffix);
    }
}
