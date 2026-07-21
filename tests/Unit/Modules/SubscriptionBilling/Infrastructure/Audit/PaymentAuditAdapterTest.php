<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\SubscriptionBilling\Infrastructure\Audit;

use App\Modules\PlatformAdministration\Application\AuditEntry\RecordAuditEntryService;
use App\Modules\PlatformAdministration\Contracts\AuditEntry\AuditEntryRepositoryInterface;
use App\Modules\PlatformAdministration\Domain\AuditEntry\AuditEntry;
use App\Modules\PlatformAdministration\Domain\AuditEntry\Exceptions\InvalidAuditEntryValueException;
use App\Modules\SubscriptionBilling\Domain\Aggregates\Payment\Payment;
use App\Modules\SubscriptionBilling\Domain\Aggregates\Payment\ValueObjects\IdempotencyKey;
use App\Modules\SubscriptionBilling\Domain\Aggregates\Payment\ValueObjects\PaymentAmount;
use App\Modules\SubscriptionBilling\Domain\Aggregates\Payment\ValueObjects\PaymentCurrency;
use App\Modules\SubscriptionBilling\Domain\Aggregates\Payment\ValueObjects\PaymentId;
use App\Modules\SubscriptionBilling\Domain\Aggregates\Payment\ValueObjects\PaymentReference;
use App\Modules\SubscriptionBilling\Domain\Aggregates\Payment\ValueObjects\PaymentStatus;
use App\Modules\SubscriptionBilling\Domain\Aggregates\Payment\ValueObjects\TenantId;
use App\Modules\SubscriptionBilling\Infrastructure\Audit\PaymentAuditAdapter;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class PaymentAuditAdapterTest extends TestCase
{
    private const string PLATFORM_IDENTITY_ID = '00000000-0000-4000-8000-000000000013';

    private const string CORRELATION_ID = '00000000-0000-4000-8000-000000000099';

    /**
     * @return iterable<string, array{0: string}>
     */
    public static function lifecycleActions(): iterable
    {
        return [
            'create' => ['payment.create'],
            'start' => ['payment.start'],
            'pending' => ['payment.pending'],
            'succeeded' => ['payment.succeeded'],
            'failed' => ['payment.failed'],
            'cancel' => ['payment.cancel'],
            'expire' => ['payment.expire'],
        ];
    }

    #[DataProvider('lifecycleActions')]
    public function test_every_payment_lifecycle_action_records_through_real_audit_entry_validation(string $action): void
    {
        [$adapter, $auditEntries] = $this->build();

        $adapter->record($action, $this->payment(PaymentStatus::Pending), $this->now(), self::CORRELATION_ID);

        self::assertCount(1, $auditEntries->entries);
    }

    public function test_audit_target_contains_the_payment_id(): void
    {
        [$adapter, $auditEntries] = $this->build();

        $adapter->record('payment.create', $this->payment(PaymentStatus::Draft), $this->now(), self::CORRELATION_ID);

        self::assertSame('payment', $auditEntries->entries[0]->targetType);
        self::assertSame('payment-1', $auditEntries->entries[0]->targetId);
    }

    public function test_audit_entry_carries_the_correlation_id(): void
    {
        [$adapter, $auditEntries] = $this->build();

        $adapter->record('payment.create', $this->payment(PaymentStatus::Draft), $this->now(), self::CORRELATION_ID);

        self::assertSame(self::CORRELATION_ID, $auditEntries->entries[0]->correlationId);
    }

    public function test_audit_entry_carries_platform_identity_actor_semantics(): void
    {
        [$adapter, $auditEntries] = $this->build();

        $adapter->record('payment.create', $this->payment(PaymentStatus::Draft), $this->now(), self::CORRELATION_ID);

        self::assertSame('platform_identity', $auditEntries->entries[0]->actorType->value);
        self::assertSame(self::PLATFORM_IDENTITY_ID, $auditEntries->entries[0]->actorIdentityId);
    }

    public function test_metadata_contains_only_allowlisted_keys(): void
    {
        [$adapter, $auditEntries] = $this->build();

        $adapter->record('payment.succeeded', $this->payment(PaymentStatus::Succeeded), $this->now(), self::CORRELATION_ID);

        $metadata = $auditEntries->entries[0]->safeMetadata;
        self::assertSame(['resource_type', 'resource_label', 'target_label'], array_keys($metadata));
        foreach (array_keys($metadata) as $key) {
            self::assertContains($key, [
                'actor_role', 'category_key', 'ip_address', 'permission_key',
                'resource_label', 'resource_type', 'target_label', 'tenant_label', 'user_agent',
            ]);
        }
    }

    public function test_metadata_contains_no_secret_or_credential_shaped_values(): void
    {
        [$adapter, $auditEntries] = $this->build();

        foreach (['payment.create', 'payment.start', 'payment.pending', 'payment.succeeded', 'payment.failed', 'payment.cancel', 'payment.expire'] as $action) {
            $adapter->record($action, $this->payment(PaymentStatus::Pending), $this->now(), self::CORRELATION_ID);
        }

        foreach ($auditEntries->entries as $entry) {
            $serialized = json_encode($entry->safeMetadata);
            self::assertIsString($serialized);
            foreach (['secret', 'credential', 'api_key', 'password', 'card', 'bank', 'sk_', 'whsec_', 'webhook_payload', 'raw_response'] as $forbidden) {
                self::assertStringNotContainsStringIgnoringCase($forbidden, $serialized);
            }
        }
    }

    public function test_metadata_preserves_status_and_commercial_offer_relationship(): void
    {
        [$adapter, $auditEntries] = $this->build();

        $adapter->record('payment.succeeded', $this->payment(PaymentStatus::Succeeded), $this->now(), self::CORRELATION_ID);

        $metadata = $auditEntries->entries[0]->safeMetadata;
        self::assertSame('succeeded', $metadata['resource_label']);
        self::assertStringContainsString('commercial_offer_id=offer-1', $metadata['target_label']);
        self::assertStringContainsString('currency=MYR', $metadata['target_label']);
        self::assertStringContainsString('amount_minor=2550', $metadata['target_label']);
    }

    public function test_outcome_encoding_actions_are_aliased_to_operation_shaped_audit_actions(): void
    {
        [$adapter, $auditEntries] = $this->build();

        $adapter->record('payment.succeeded', $this->payment(PaymentStatus::Succeeded), $this->now(), self::CORRELATION_ID);
        $adapter->record('payment.failed', $this->payment(PaymentStatus::Failed), $this->now(), self::CORRELATION_ID);

        self::assertSame('payment.mark_succeeded', $auditEntries->entries[0]->action);
        self::assertSame('payment.mark_failed', $auditEntries->entries[1]->action);
    }

    public function test_a_malformed_platform_identity_actor_is_rejected_by_real_audit_validation(): void
    {
        [$adapter] = $this->build();

        $this->expectException(InvalidAuditEntryValueException::class);
        $adapter->record(
            'payment.create',
            $this->payment(PaymentStatus::Draft, platformIdentityId: 'not-a-uuid'),
            $this->now(),
            self::CORRELATION_ID,
        );
    }

    /** @return array{0: PaymentAuditAdapter, 1: InMemoryAuditEntryRepository} */
    private function build(): array
    {
        $auditEntries = new InMemoryAuditEntryRepository;
        $adapter = new PaymentAuditAdapter(new RecordAuditEntryService($auditEntries));

        return [$adapter, $auditEntries];
    }

    private function payment(PaymentStatus $status, string $platformIdentityId = self::PLATFORM_IDENTITY_ID): Payment
    {
        return new Payment(
            id: new PaymentId('payment-1'),
            commercialOfferId: new PaymentReference('offer-1'),
            clinicRegistrationId: new PaymentReference('clinic-1'),
            platformIdentityId: new PaymentReference($platformIdentityId),
            tenantId: new TenantId('00000000-0000-4000-8000-000000000014'),
            amount: new PaymentAmount(2550),
            currency: new PaymentCurrency('MYR'),
            idempotencyKey: new IdempotencyKey('idem-1'),
            status: $status,
            providerReference: null,
            failureReasonCode: null,
            createdAt: new DateTimeImmutable('2026-07-21T00:00:00Z'),
            lastChangedAt: new DateTimeImmutable('2026-07-21T00:00:00Z'),
        );
    }

    private function now(): DateTimeImmutable
    {
        return new DateTimeImmutable('2026-07-21T00:00:00Z');
    }
}

final class InMemoryAuditEntryRepository implements AuditEntryRepositoryInterface
{
    /** @var list<AuditEntry> */
    public array $entries = [];

    public function append(AuditEntry $auditEntry): AuditEntry
    {
        $this->entries[] = $auditEntry;

        return $auditEntry;
    }
}
