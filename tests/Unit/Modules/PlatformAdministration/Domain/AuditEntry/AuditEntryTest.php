<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\PlatformAdministration\Domain\AuditEntry;

use App\Modules\PlatformAdministration\Domain\AuditEntry\AuditEntry;
use App\Modules\PlatformAdministration\Domain\AuditEntry\Exceptions\InvalidAuditEntryValueException;
use App\Modules\PlatformAdministration\Domain\AuditEntry\ValueObjects\AuditActorType;
use App\Modules\PlatformAdministration\Domain\AuditEntry\ValueObjects\AuditEntryId;
use App\Modules\PlatformAdministration\Domain\AuditEntry\ValueObjects\AuditOutcomeType;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

final class AuditEntryTest extends TestCase
{
    #[DataProvider('validActorMatrices')]
    public function test_it_accepts_valid_actor_matrices_and_scope_combinations(
        AuditActorType $actorType,
        ?string $actorIdentityId,
        ?string $tenantId,
    ): void {
        $entry = $this->entry(
            actorType: $actorType,
            actorIdentityId: $actorIdentityId,
            tenantId: $tenantId,
            outcome: AuditOutcomeType::Succeeded,
            reasonCode: null,
        );

        self::assertSame($actorType, $entry->actorType);
        self::assertSame($actorIdentityId, $entry->actorIdentityId);
        self::assertSame($tenantId, $entry->tenantId);
    }

    public function test_it_rejects_invalid_actor_identity_combinations(): void
    {
        foreach ([
            [AuditActorType::Anonymous, self::ACTOR_IDENTITY_ID, null],
            [AuditActorType::Anonymous, null, self::tenantId()],
            [AuditActorType::System, self::ACTOR_IDENTITY_ID, null],
            [AuditActorType::PlatformIdentity, null, null],
            [AuditActorType::ClinicOwner, null, self::tenantId()],
            [AuditActorType::ClinicOwner, self::ACTOR_IDENTITY_ID, null],
        ] as [$actorType, $actorIdentityId, $tenantId]) {
            try {
                $this->entry(
                    actorType: $actorType,
                    actorIdentityId: $actorIdentityId,
                    tenantId: $tenantId,
                );
                self::fail('Expected invalid actor combination to be rejected.');
            } catch (InvalidAuditEntryValueException) {
                self::addToAssertionCount(1);
            }
        }
    }

    public function test_it_accepts_valid_outcomes_and_reason_code_rules(): void
    {
        $succeeded = $this->entry(outcome: AuditOutcomeType::Succeeded, reasonCode: null);
        self::assertSame(AuditOutcomeType::Succeeded, $succeeded->outcome);
        self::assertNull($succeeded->reasonCode);

        $failed = $this->entry(
            outcome: AuditOutcomeType::Failed,
            reasonCode: 'validation_failed',
        );
        self::assertSame('validation_failed', $failed->reasonCode);

        $denied = $this->entry(
            outcome: AuditOutcomeType::Denied,
            reasonCode: 'authorization_denied',
        );
        self::assertSame('authorization_denied', $denied->reasonCode);
    }

    public function test_it_rejects_reason_codes_for_succeeded_outcomes(): void
    {
        $this->expectException(InvalidAuditEntryValueException::class);

        $this->entry(
            outcome: AuditOutcomeType::Succeeded,
            reasonCode: 'should_not_exist',
        );
    }

    public function test_it_rejects_invalid_identifiers_and_action_tokens(): void
    {
        foreach ([
            fn (): AuditEntry => $this->entry(id: 'not-a-uuid'),
            fn (): AuditEntry => $this->entry(correlationId: 'not-a-uuid'),
            fn (): AuditEntry => $this->entry(targetId: ' with-space '),
            fn (): AuditEntry => $this->entry(action: 'platform.authentication.login.succeeded'),
            fn (): AuditEntry => $this->entry(targetType: 'TargetType'),
        ] as $factory) {
            try {
                $factory();
                self::fail('Expected invalid identifier or token to be rejected.');
            } catch (InvalidAuditEntryValueException) {
                self::addToAssertionCount(1);
            }
        }
    }

    public function test_it_rejects_forbidden_safe_metadata_and_oversized_payloads(): void
    {
        foreach ([
            ['actor_role' => 'super_admin', 'resource_label' => 'safe'],
            ['resource_label' => 'password'],
            ['resource_label' => ['nested' => 'value']],
            ['resource_label' => new class {}],
            ['resource_label' => str_repeat('x', 5000)],
            ['forbidden_key' => 'safe'],
            ['resource_label' => INF],
        ] as $metadata) {
            try {
                $this->entry(safeMetadata: $metadata);

                if ($metadata === ['actor_role' => 'super_admin', 'resource_label' => 'safe']) {
                    self::addToAssertionCount(1);

                    continue;
                }

                self::fail('Expected invalid safe metadata to be rejected.');
            } catch (InvalidAuditEntryValueException) {
                if ($metadata === ['actor_role' => 'super_admin', 'resource_label' => 'safe']) {
                    self::fail('Approved metadata should be accepted.');
                }

                self::addToAssertionCount(1);
            }
        }
    }

    public function test_it_is_immutable_and_has_no_mutators(): void
    {
        $reflection = new ReflectionClass(AuditEntry::class);

        self::assertTrue($reflection->isReadOnly());
        self::assertSame([], array_filter(
            $reflection->getMethods(),
            static fn ($method): bool => str_starts_with($method->getName(), 'set'),
        ));
    }

    /**
     * @return array<int, array{0: AuditActorType, 1: ?string, 2: ?string}>
     */
    public static function validActorMatrices(): array
    {
        return [
            [AuditActorType::Anonymous, null, null],
            [AuditActorType::System, null, null],
            [AuditActorType::PlatformIdentity, self::ACTOR_IDENTITY_ID, null],
            [AuditActorType::PlatformIdentity, self::ACTOR_IDENTITY_ID, self::tenantId()],
            [AuditActorType::ClinicOwner, self::ACTOR_IDENTITY_ID, self::tenantId()],
        ];
    }

    /**
     * @param  array<string, scalar>  $safeMetadata
     */
    private function entry(
        AuditEntryId|string $id = self::AUDIT_ENTRY_ID,
        DateTimeImmutable|string $occurredAt = self::OCCURRED_AT,
        AuditActorType $actorType = AuditActorType::PlatformIdentity,
        ?string $actorIdentityId = self::ACTOR_IDENTITY_ID,
        ?string $tenantId = null,
        string $action = 'platform.authorization.evaluate',
        string $targetType = 'platform_permission',
        ?string $targetId = self::TARGET_ID,
        AuditOutcomeType $outcome = AuditOutcomeType::Failed,
        ?string $reasonCode = 'authorization_denied',
        string $correlationId = self::CORRELATION_ID,
        array $safeMetadata = ['actor_role' => 'super_admin', 'resource_label' => 'commercial catalogue'],
    ): AuditEntry {
        return AuditEntry::record(
            $id instanceof AuditEntryId ? $id : new AuditEntryId($id),
            $occurredAt instanceof DateTimeImmutable ? $occurredAt : new DateTimeImmutable($occurredAt),
            $actorType,
            $actorIdentityId,
            $tenantId,
            $action,
            $targetType,
            $targetId,
            $outcome,
            $reasonCode,
            $correlationId,
            $safeMetadata,
        );
    }

    private const string AUDIT_ENTRY_ID = '00000000-0000-4000-8000-000000000001';

    private const string CORRELATION_ID = '00000000-0000-4000-8000-000000000002';

    private const string ACTOR_IDENTITY_ID = '00000000-0000-4000-8000-000000000101';

    private const string TARGET_ID = 'commercial-catalogue.plan.annual';

    private const string OCCURRED_AT = '2026-07-20T03:30:00Z';

    private static function tenantId(): string
    {
        return '00000000-0000-4000-8000-000000000201';
    }
}
