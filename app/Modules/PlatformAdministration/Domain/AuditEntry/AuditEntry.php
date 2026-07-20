<?php

declare(strict_types=1);

namespace App\Modules\PlatformAdministration\Domain\AuditEntry;

use App\Modules\PlatformAdministration\Domain\AuditEntry\Exceptions\InvalidAuditEntryValueException;
use App\Modules\PlatformAdministration\Domain\AuditEntry\ValueObjects\AuditActorType;
use App\Modules\PlatformAdministration\Domain\AuditEntry\ValueObjects\AuditEntryId;
use App\Modules\PlatformAdministration\Domain\AuditEntry\ValueObjects\AuditOutcomeType;
use DateTimeImmutable;
use DateTimeZone;

final readonly class AuditEntry
{
    private const array SAFE_METADATA_KEYS = [
        'actor_role',
        'category_key',
        'ip_address',
        'permission_key',
        'resource_label',
        'resource_type',
        'target_label',
        'tenant_label',
        'user_agent',
    ];

    private const array FORBIDDEN_ACTION_TERMINALS = [
        'activated',
        'created',
        'deleted',
        'denied',
        'failed',
        'grandfathered',
        'retired',
        'succeeded',
        'updated',
        'unavailable',
    ];

    private const string UUID_PATTERN = '/^[0-9a-f]{8}-[0-9a-f]{4}-[1-8][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i';

    private const string SEMANTIC_TOKEN_PATTERN = '/^[a-z][a-z0-9]*(?:[._][a-z0-9]+)*$/';

    private const string OPAQUE_IDENTIFIER_PATTERN = '/^[^\x00-\x1F\x7F]{1,255}$/u';

    private const string REASON_CODE_PATTERN = '/^[a-z][a-z0-9]*(?:[._][a-z0-9]+)*$/';

    /**
     * @param  array<string, scalar>  $safeMetadata
     */
    private function __construct(
        public readonly AuditEntryId $id,
        public readonly DateTimeImmutable $occurredAt,
        public readonly AuditActorType $actorType,
        public readonly ?string $actorIdentityId,
        public readonly ?string $tenantId,
        public readonly string $action,
        public readonly string $targetType,
        public readonly ?string $targetId,
        public readonly AuditOutcomeType $outcome,
        public readonly ?string $reasonCode,
        public readonly string $correlationId,
        public readonly array $safeMetadata,
    ) {}

    /**
     * @param  array<string, scalar>  $safeMetadata
     */
    public static function record(
        AuditEntryId $id,
        DateTimeImmutable $occurredAt,
        AuditActorType $actorType,
        ?string $actorIdentityId,
        ?string $tenantId,
        string $action,
        string $targetType,
        ?string $targetId,
        AuditOutcomeType $outcome,
        ?string $reasonCode,
        string $correlationId,
        array $safeMetadata = [],
    ): self {
        return self::reconstitute(
            $id,
            $occurredAt,
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

    /**
     * @param  array<string, scalar>  $safeMetadata
     */
    public static function reconstitute(
        AuditEntryId $id,
        DateTimeImmutable $occurredAt,
        AuditActorType $actorType,
        ?string $actorIdentityId,
        ?string $tenantId,
        string $action,
        string $targetType,
        ?string $targetId,
        AuditOutcomeType $outcome,
        ?string $reasonCode,
        string $correlationId,
        array $safeMetadata = [],
    ): self {
        self::assertUtcInstant($occurredAt);
        self::assertActor($actorType, $actorIdentityId, $tenantId);
        self::assertSemanticToken($action, 'action');
        self::assertActionDoesNotEncodeOutcome($action);
        self::assertSemanticToken($targetType, 'target type');
        self::assertTargetIdentifier($targetId);
        self::assertOutcome($outcome, $reasonCode);
        self::assertUuid($correlationId, 'Correlation ID');
        $safeMetadata = self::validateSafeMetadata($safeMetadata);

        return new self(
            $id,
            self::utc($occurredAt),
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

    private static function assertUtcInstant(DateTimeImmutable $occurredAt): void
    {
        if ($occurredAt->getOffset() !== 0) {
            throw new InvalidAuditEntryValueException('Occurred at must be a UTC instant.');
        }
    }

    private static function utc(DateTimeImmutable $occurredAt): DateTimeImmutable
    {
        return $occurredAt->setTimezone(new DateTimeZone('UTC'));
    }

    private static function assertActor(
        AuditActorType $actorType,
        ?string $actorIdentityId,
        ?string $tenantId,
    ): void {
        $identityRequired = in_array($actorType, [
            AuditActorType::PlatformIdentity,
            AuditActorType::ClinicOwner,
        ], true);

        if ($identityRequired) {
            self::assertUuid($actorIdentityId, 'Actor identity ID');
        } elseif ($actorIdentityId !== null) {
            throw new InvalidAuditEntryValueException('Anonymous and system actors must not carry an identity ID.');
        }

        if ($tenantId !== null) {
            self::assertUuid($tenantId, 'Tenant ID');
        }

        if ($actorType === AuditActorType::Anonymous && $tenantId !== null) {
            throw new InvalidAuditEntryValueException('Anonymous actors must not carry tenant scope.');
        }

        if ($actorType === AuditActorType::System && $tenantId !== null) {
            throw new InvalidAuditEntryValueException('System actors must not carry tenant scope in this foundation slice.');
        }

        if ($actorType === AuditActorType::ClinicOwner && $tenantId === null) {
            throw new InvalidAuditEntryValueException('Clinic Owner actors require tenant scope.');
        }
    }

    private static function assertOutcome(AuditOutcomeType $outcome, ?string $reasonCode): void
    {
        if ($outcome === AuditOutcomeType::Succeeded) {
            if ($reasonCode !== null) {
                throw new InvalidAuditEntryValueException('Succeeded audit outcomes must not carry a reason code.');
            }

            return;
        }

        if ($reasonCode === null || trim($reasonCode) === '') {
            throw new InvalidAuditEntryValueException('Failed and denied audit outcomes require a reason code.');
        }

        self::assertSemanticToken($reasonCode, 'reason code', self::REASON_CODE_PATTERN);
    }

    private static function assertActionDoesNotEncodeOutcome(string $action): void
    {
        $position = strrpos($action, '.');
        $terminal = $position === false ? $action : substr($action, $position + 1);

        if (in_array($terminal, self::FORBIDDEN_ACTION_TERMINALS, true)) {
            throw new InvalidAuditEntryValueException('Action must encode the semantic operation, not the outcome.');
        }
    }

    private static function assertTargetIdentifier(?string $targetId): void
    {
        if ($targetId === null) {
            return;
        }

        if ($targetId !== trim($targetId)) {
            throw new InvalidAuditEntryValueException('Target identifier must not contain leading or trailing whitespace.');
        }

        if ($targetId === '' || strlen($targetId) > 255 || preg_match(self::OPAQUE_IDENTIFIER_PATTERN, $targetId) !== 1) {
            throw new InvalidAuditEntryValueException('Target identifier must be a validated opaque identifier.');
        }
    }

    private static function assertSemanticToken(
        string $value,
        string $label,
        string $pattern = self::SEMANTIC_TOKEN_PATTERN,
    ): void {
        if ($value === '' || preg_match($pattern, $value) !== 1) {
            throw new InvalidAuditEntryValueException($label.' must be a stable semantic token.');
        }
    }

    private static function assertUuid(?string $value, string $label): void
    {
        if ($value === null || preg_match(self::UUID_PATTERN, $value) !== 1) {
            throw new InvalidAuditEntryValueException($label.' must be a valid UUID.');
        }
    }

    /**
     * @param  array<string, scalar>  $safeMetadata
     * @return array<string, scalar>
     */
    private static function validateSafeMetadata(array $safeMetadata): array
    {
        foreach ($safeMetadata as $key => $value) {
            if (! in_array($key, self::SAFE_METADATA_KEYS, true)) {
                throw new InvalidAuditEntryValueException('Safe metadata contains an unapproved key.');
            }

            self::assertSafeMetadataValue($value, $key);
        }

        try {
            $serialized = json_encode(
                $safeMetadata,
                JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
            );
        } catch (\JsonException $exception) {
            throw new InvalidAuditEntryValueException('Safe metadata could not be serialized.', 0, $exception);
        }

        if (strlen($serialized) > 4096) {
            throw new InvalidAuditEntryValueException('Safe metadata must not exceed 4096 bytes when serialized.');
        }

        return $safeMetadata;
    }

    private static function assertSafeMetadataValue(mixed $value, string $key): void
    {
        if (! is_string($value) && ! is_int($value) && ! is_float($value) && ! is_bool($value)) {
            throw new InvalidAuditEntryValueException('Safe metadata values must be scalar.');
        }

        if (is_string($value)) {
            self::assertSafeMetadataString($value, $key);
        }
    }

    private static function assertSafeMetadataString(string $value, string $key): void
    {
        $forbiddenPatterns = [
            '/password_hash/i',
            '/plain_password/i',
            '/session_id/i',
            '/csrf_token/i',
            '/access_token/i',
            '/refresh_token/i',
            '/authorization/i',
            '/cookie/i',
            '/credential/i',
            '/patient/i',
            '/clinical/i',
            '/trace/i',
            '/secret/i',
            '/password/i',
        ];

        foreach ($forbiddenPatterns as $pattern) {
            if (preg_match($pattern, $value) === 1) {
                throw new InvalidAuditEntryValueException(sprintf('Safe metadata value for %s contains forbidden content.', $key));
            }
        }
    }
}
