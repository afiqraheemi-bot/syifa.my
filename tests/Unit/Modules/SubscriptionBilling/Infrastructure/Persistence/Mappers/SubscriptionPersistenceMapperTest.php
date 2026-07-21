<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\SubscriptionBilling\Infrastructure\Persistence\Mappers;

use App\Modules\SubscriptionBilling\Domain\Aggregates\Subscription\Subscription;
use App\Modules\SubscriptionBilling\Domain\Aggregates\Subscription\ValueObjects\BillingCycleId;
use App\Modules\SubscriptionBilling\Domain\Aggregates\Subscription\ValueObjects\BillingPeriod;
use App\Modules\SubscriptionBilling\Domain\Aggregates\Subscription\ValueObjects\CapabilityKey;
use App\Modules\SubscriptionBilling\Domain\Aggregates\Subscription\ValueObjects\ClinicRegistrationId;
use App\Modules\SubscriptionBilling\Domain\Aggregates\Subscription\ValueObjects\CommercialOfferId;
use App\Modules\SubscriptionBilling\Domain\Aggregates\Subscription\ValueObjects\Entitlement;
use App\Modules\SubscriptionBilling\Domain\Aggregates\Subscription\ValueObjects\EntitlementStatus;
use App\Modules\SubscriptionBilling\Domain\Aggregates\Subscription\ValueObjects\Money;
use App\Modules\SubscriptionBilling\Domain\Aggregates\Subscription\ValueObjects\PaymentId;
use App\Modules\SubscriptionBilling\Domain\Aggregates\Subscription\ValueObjects\PlanId;
use App\Modules\SubscriptionBilling\Domain\Aggregates\Subscription\ValueObjects\SubscriptionId;
use App\Modules\SubscriptionBilling\Domain\Aggregates\Subscription\ValueObjects\SubscriptionStatus;
use App\Modules\SubscriptionBilling\Domain\Aggregates\Subscription\ValueObjects\TenantId;
use App\Modules\SubscriptionBilling\Infrastructure\Persistence\Mappers\SubscriptionPersistenceMapper;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class SubscriptionPersistenceMapperTest extends TestCase
{
    public function test_maps_every_owned_value_and_reconstitutes_without_domain_events(): void
    {
        $plan = new PlanId($this->uuid(6));
        $cycle = new BillingCycleId($this->uuid(7));
        $subscription = Subscription::reconstitute(
            new SubscriptionId($this->uuid(1)),
            new TenantId($this->uuid(2)),
            new ClinicRegistrationId($this->uuid(3)),
            new PaymentId($this->uuid(4)),
            new CommercialOfferId($this->uuid(5)),
            $plan,
            $cycle,
            new Money(12500, 'MYR'),
            new BillingPeriod('2026-07-22', '2027-07-21'),
            new Entitlement($plan, $cycle, 'catalogue-v1', EntitlementStatus::Effective, [new CapabilityKey('appointments.manage')]),
            SubscriptionStatus::Active,
            new DateTimeImmutable('2026-07-22T00:00:00Z'),
            new DateTimeImmutable('2026-07-22T00:01:00Z'),
            1,
        );

        $mapper = new SubscriptionPersistenceMapper;
        $record = $mapper->toRecord($subscription);
        $restored = $mapper->toDomain($record);

        self::assertSame($this->uuid(3), $record->clinicRegistrationId);
        self::assertSame($this->uuid(4), $record->paymentId);
        self::assertSame($this->uuid(5), $record->commercialOfferId);
        self::assertSame(['appointments.manage'], $record->entitlementCapabilities);
        self::assertSame(SubscriptionStatus::Active, $restored->status());
        self::assertSame(EntitlementStatus::Effective, $restored->entitlement()->status);
        self::assertSame(1, $restored->version());
        self::assertSame([], $restored->releaseDomainEvents());
    }

    private function uuid(int $suffix): string
    {
        return sprintf('00000000-0000-4000-8000-%012d', $suffix);
    }
}
