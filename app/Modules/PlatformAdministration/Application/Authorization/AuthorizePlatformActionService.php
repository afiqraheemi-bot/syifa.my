<?php

declare(strict_types=1);

namespace App\Modules\PlatformAdministration\Application\Authorization;

use App\Modules\PlatformAdministration\Application\PlatformIdentity\GetPlatformIdentityService;
use App\Modules\PlatformAdministration\Contracts\AuditEntry\AuditActorData;
use App\Modules\PlatformAdministration\Contracts\AuditEntry\AuditCorrelationIdResolverInterface;
use App\Modules\PlatformAdministration\Contracts\AuditEntry\AuditEntryData;
use App\Modules\PlatformAdministration\Contracts\AuditEntry\AuditEntryRecorderInterface;
use App\Modules\PlatformAdministration\Contracts\AuditEntry\AuditOutcomeData;
use App\Modules\PlatformAdministration\Contracts\AuditEntry\AuditTargetData;
use App\Modules\PlatformAdministration\Contracts\Authorization\AuthorizationDecisionData;
use App\Modules\PlatformAdministration\Contracts\Authorization\CategoryGrantData;
use App\Modules\PlatformAdministration\Contracts\Authorization\CategoryGrantLookupInterface;
use App\Modules\PlatformAdministration\Contracts\Authorization\Exceptions\AmbiguousPlatformAdministratorProfileException;
use App\Modules\PlatformAdministration\Contracts\Authorization\PlatformAdministratorData;
use App\Modules\PlatformAdministration\Contracts\Authorization\PlatformAdministratorLookupInterface;
use App\Modules\PlatformAdministration\Contracts\Authorization\PlatformAuthorizationInterface;
use App\Modules\PlatformAdministration\Contracts\Authorization\PlatformCategoryData;
use App\Modules\PlatformAdministration\Contracts\Authorization\PlatformCategoryLookupInterface;
use App\Modules\PlatformAdministration\Contracts\Authorization\PlatformPermissionData;
use App\Modules\PlatformAdministration\Contracts\Authorization\PlatformPermissionLookupInterface;
use App\Modules\PlatformAdministration\Domain\AuditEntry\ValueObjects\AuditActorType;
use App\Modules\PlatformAdministration\Domain\AuditEntry\ValueObjects\AuditOutcomeType;
use App\Modules\PlatformAdministration\Domain\Authorization\AuthorizationDecision;
use App\Modules\PlatformAdministration\Domain\Authorization\CategoryGrant;
use App\Modules\PlatformAdministration\Domain\Authorization\PlatformAdministrator;
use App\Modules\PlatformAdministration\Domain\Authorization\PlatformAuthorizationService;
use App\Modules\PlatformAdministration\Domain\Authorization\PlatformCategory;
use App\Modules\PlatformAdministration\Domain\Authorization\PlatformPermission;
use App\Modules\PlatformAdministration\Domain\Authorization\ValueObjects\AuthorizationDecisionReason;
use App\Modules\PlatformAdministration\Domain\Authorization\ValueObjects\CategoryGrantStatus;
use App\Modules\PlatformAdministration\Domain\Authorization\ValueObjects\PlatformAdministratorId;
use App\Modules\PlatformAdministration\Domain\Authorization\ValueObjects\PlatformAdministratorStatus;
use App\Modules\PlatformAdministration\Domain\Authorization\ValueObjects\PlatformCategoryKey;
use App\Modules\PlatformAdministration\Domain\Authorization\ValueObjects\PlatformCategoryStatus;
use App\Modules\PlatformAdministration\Domain\Authorization\ValueObjects\PlatformPermissionKey;
use App\Modules\PlatformAdministration\Domain\Authorization\ValueObjects\PlatformPermissionStatus;
use App\Modules\PlatformAdministration\Domain\PlatformIdentity\Exceptions\InvalidPlatformIdentityIdException;
use App\Modules\PlatformAdministration\Domain\PlatformIdentity\PlatformIdentity;
use App\Modules\PlatformAdministration\Domain\PlatformIdentity\ValueObjects\PlatformIdentityId;
use DateTimeImmutable;
use DateTimeZone;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * The sole Application-layer path to {@see PlatformAuthorizationInterface::authorize()}.
 *
 * The caller supplies only the authenticated Platform Identity's own ID. This service resolves
 * every remaining authorization fact internally — identity existence and active status, the
 * canonical Super Admin role, the unique Platform Administrator governance profile for that
 * identity, and the category/permission/grant facts — failing closed the instant any fact
 * cannot be established. Only once every fact is confirmed to exist does it delegate the final
 * allow/deny determination exclusively to {@see PlatformAuthorizationService}. It never decides
 * an "active/connected" outcome itself for the facts Domain already evaluates — only "does this
 * fact exist at all", which the Domain evaluator cannot express because it operates on
 * already-hydrated objects.
 */
final readonly class AuthorizePlatformActionService implements PlatformAuthorizationInterface
{
    public function __construct(
        private GetPlatformIdentityService $identities,
        private PlatformAdministratorLookupInterface $administrators,
        private PlatformCategoryLookupInterface $categories,
        private PlatformPermissionLookupInterface $permissions,
        private CategoryGrantLookupInterface $grants,
        private PlatformAuthorizationService $evaluator,
        private AuditEntryRecorderInterface $auditEntries,
        private AuditCorrelationIdResolverInterface $correlationIds,
        private LoggerInterface $logger,
    ) {}

    public function authorize(
        string $platformIdentityId,
        string $categoryKey,
        string $permissionKey,
        string $effectiveDateTime,
    ): AuthorizationDecisionData {
        try {
            $identity = $this->identities->execute(new PlatformIdentityId($platformIdentityId));
        } catch (InvalidPlatformIdentityIdException) {
            return $this->denied($platformIdentityId, $categoryKey, $permissionKey, AuthorizationDecisionReason::PlatformIdentityNotFound, $effectiveDateTime);
        }

        if ($identity === null) {
            return $this->denied($platformIdentityId, $categoryKey, $permissionKey, AuthorizationDecisionReason::PlatformIdentityNotFound, $effectiveDateTime);
        }

        if (! $identity->isActive()) {
            return $this->denied($platformIdentityId, $categoryKey, $permissionKey, AuthorizationDecisionReason::PlatformIdentityNotActive, $effectiveDateTime, $identity);
        }

        if (! $identity->isSuperAdmin()) {
            return $this->denied($platformIdentityId, $categoryKey, $permissionKey, AuthorizationDecisionReason::SuperAdminRoleRequired, $effectiveDateTime, $identity);
        }

        try {
            $administratorData = $this->administrators->findByPlatformIdentityId($platformIdentityId);
        } catch (AmbiguousPlatformAdministratorProfileException) {
            return $this->denied($platformIdentityId, $categoryKey, $permissionKey, AuthorizationDecisionReason::AdministratorProfileAmbiguous, $effectiveDateTime, $identity);
        }

        if ($administratorData === null) {
            return $this->denied($platformIdentityId, $categoryKey, $permissionKey, AuthorizationDecisionReason::AdministratorProfileNotFound, $effectiveDateTime, $identity);
        }

        $categoryData = $this->categories->findCategory($categoryKey);
        if ($categoryData === null) {
            return $this->denied($platformIdentityId, $categoryKey, $permissionKey, AuthorizationDecisionReason::CategoryNotFound, $effectiveDateTime, $identity);
        }

        $permissionData = $this->permissions->findPermission($permissionKey);
        if ($permissionData === null) {
            return $this->denied($platformIdentityId, $categoryKey, $permissionKey, AuthorizationDecisionReason::PermissionNotFound, $effectiveDateTime, $identity);
        }

        if ($permissionData->categoryKey !== $categoryKey) {
            return $this->denied($platformIdentityId, $categoryKey, $permissionKey, AuthorizationDecisionReason::PermissionCategoryMismatch, $effectiveDateTime, $identity);
        }

        $grantData = $this->grants->findGrant($administratorData->administratorId, $categoryKey);
        if ($grantData === null) {
            return $this->denied($platformIdentityId, $categoryKey, $permissionKey, AuthorizationDecisionReason::GrantNotFound, $effectiveDateTime, $identity);
        }

        if ($grantData->permissionKeys === []) {
            return $this->denied($platformIdentityId, $categoryKey, $permissionKey, AuthorizationDecisionReason::PermissionNotGranted, $effectiveDateTime, $identity);
        }

        $decision = $this->evaluator->evaluate(
            $this->toAdministrator($administratorData),
            $this->toCategory($categoryData),
            $this->toPermission($permissionData),
            $this->toGrant($grantData),
        );

        return $this->toData($platformIdentityId, $categoryKey, $permissionKey, $decision, $effectiveDateTime);
    }

    private function denied(
        string $platformIdentityId,
        string $categoryKey,
        string $permissionKey,
        AuthorizationDecisionReason $reason,
        string $effectiveDateTime,
        ?PlatformIdentity $identity = null,
    ): AuthorizationDecisionData {
        $decision = $this->toData(
            $platformIdentityId,
            $categoryKey,
            $permissionKey,
            AuthorizationDecision::denied($reason),
            $effectiveDateTime,
        );

        $this->auditAuthorizationDenial($decision, $identity, $effectiveDateTime);

        return $decision;
    }

    private function toData(
        string $platformIdentityId,
        string $categoryKey,
        string $permissionKey,
        AuthorizationDecision $decision,
        string $effectiveDateTime,
    ): AuthorizationDecisionData {
        return new AuthorizationDecisionData(
            $platformIdentityId,
            $categoryKey,
            $permissionKey,
            $decision->allowed,
            $decision->reason->value,
            self::instant($effectiveDateTime)->format('Y-m-d\TH:i:s\Z'),
        );
    }

    private function auditAuthorizationDenial(
        AuthorizationDecisionData $decision,
        ?PlatformIdentity $identity,
        string $effectiveDateTime,
    ): void {
        $correlationId = $this->correlationIds->resolve();
        $evaluatedAt = self::instant($effectiveDateTime);

        try {
            $this->auditEntries->record(new AuditEntryData(
                self::auditEntryId(),
                $evaluatedAt,
                new AuditActorData(
                    $identity === null ? AuditActorType::Anonymous->value : AuditActorType::PlatformIdentity->value,
                    $identity?->id->value,
                ),
                null,
                'platform.authorization.evaluate',
                new AuditTargetData('platform_permission', $decision->permissionKey),
                new AuditOutcomeData(AuditOutcomeType::Denied->value, $decision->reason),
                $correlationId,
                array_filter([
                    'actor_role' => $identity?->role->value,
                    'category_key' => $decision->categoryKey,
                    'permission_key' => $decision->permissionKey,
                ], static fn (mixed $value): bool => $value !== null),
            ));
        } catch (Throwable) {
            $this->logger->critical('platform.security.audit.emergency', [
                'correlation_id' => $correlationId,
                'actor_type' => $identity === null ? AuditActorType::Anonymous->value : AuditActorType::PlatformIdentity->value,
                'actor_identity_id' => $identity?->id->value,
                'action' => 'platform.authorization.evaluate',
                'outcome' => AuditOutcomeType::Denied->value,
                'reason_code' => $decision->reason,
                'timestamp' => $evaluatedAt->format('Y-m-d\TH:i:s\Z'),
            ]);
        }
    }

    private function toAdministrator(PlatformAdministratorData $data): PlatformAdministrator
    {
        return new PlatformAdministrator(
            new PlatformAdministratorId($data->administratorId),
            new PlatformIdentityId($data->platformIdentityId),
            PlatformAdministratorStatus::from($data->status),
        );
    }

    private function toCategory(PlatformCategoryData $data): PlatformCategory
    {
        return new PlatformCategory(
            new PlatformCategoryKey($data->categoryKey),
            $data->name,
            $data->description,
            PlatformCategoryStatus::from($data->status),
        );
    }

    private function toPermission(PlatformPermissionData $data): PlatformPermission
    {
        return new PlatformPermission(
            new PlatformPermissionKey($data->permissionKey),
            new PlatformCategoryKey($data->categoryKey),
            $data->name,
            $data->description,
            PlatformPermissionStatus::from($data->status),
        );
    }

    private function toGrant(CategoryGrantData $data): CategoryGrant
    {
        return new CategoryGrant(
            new PlatformAdministratorId($data->administratorId),
            new PlatformCategoryKey($data->categoryKey),
            array_map(
                static fn (string $key): PlatformPermissionKey => new PlatformPermissionKey($key),
                $data->permissionKeys,
            ),
            CategoryGrantStatus::from($data->status),
            new DateTimeImmutable($data->grantedAt),
        );
    }

    private static function instant(string $value): DateTimeImmutable
    {
        return new DateTimeImmutable($value, new DateTimeZone('UTC'));
    }

    private static function auditEntryId(): string
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0F) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3F) | 0x80);
        $hex = bin2hex($bytes);

        return sprintf(
            '%s-%s-%s-%s-%s',
            substr($hex, 0, 8),
            substr($hex, 8, 4),
            substr($hex, 12, 4),
            substr($hex, 16, 4),
            substr($hex, 20, 12),
        );
    }
}
