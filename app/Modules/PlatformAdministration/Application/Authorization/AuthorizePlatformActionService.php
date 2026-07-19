<?php

declare(strict_types=1);

namespace App\Modules\PlatformAdministration\Application\Authorization;

use App\Modules\PlatformAdministration\Application\PlatformIdentity\GetPlatformIdentityService;
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
use App\Modules\PlatformAdministration\Domain\PlatformIdentity\ValueObjects\PlatformIdentityId;
use DateTimeImmutable;
use DateTimeZone;

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
            return $this->denied($platformIdentityId, $categoryKey, $permissionKey, AuthorizationDecisionReason::PlatformIdentityNotActive, $effectiveDateTime);
        }

        if (! $identity->isSuperAdmin()) {
            return $this->denied($platformIdentityId, $categoryKey, $permissionKey, AuthorizationDecisionReason::SuperAdminRoleRequired, $effectiveDateTime);
        }

        try {
            $administratorData = $this->administrators->findByPlatformIdentityId($platformIdentityId);
        } catch (AmbiguousPlatformAdministratorProfileException) {
            return $this->denied($platformIdentityId, $categoryKey, $permissionKey, AuthorizationDecisionReason::AdministratorProfileAmbiguous, $effectiveDateTime);
        }

        if ($administratorData === null) {
            return $this->denied($platformIdentityId, $categoryKey, $permissionKey, AuthorizationDecisionReason::AdministratorProfileNotFound, $effectiveDateTime);
        }

        $categoryData = $this->categories->findCategory($categoryKey);
        if ($categoryData === null) {
            return $this->denied($platformIdentityId, $categoryKey, $permissionKey, AuthorizationDecisionReason::CategoryNotFound, $effectiveDateTime);
        }

        $permissionData = $this->permissions->findPermission($permissionKey);
        if ($permissionData === null) {
            return $this->denied($platformIdentityId, $categoryKey, $permissionKey, AuthorizationDecisionReason::PermissionNotFound, $effectiveDateTime);
        }

        if ($permissionData->categoryKey !== $categoryKey) {
            return $this->denied($platformIdentityId, $categoryKey, $permissionKey, AuthorizationDecisionReason::PermissionCategoryMismatch, $effectiveDateTime);
        }

        $grantData = $this->grants->findGrant($administratorData->administratorId, $categoryKey);
        if ($grantData === null) {
            return $this->denied($platformIdentityId, $categoryKey, $permissionKey, AuthorizationDecisionReason::GrantNotFound, $effectiveDateTime);
        }

        if ($grantData->permissionKeys === []) {
            return $this->denied($platformIdentityId, $categoryKey, $permissionKey, AuthorizationDecisionReason::PermissionNotGranted, $effectiveDateTime);
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
    ): AuthorizationDecisionData {
        return $this->toData(
            $platformIdentityId,
            $categoryKey,
            $permissionKey,
            AuthorizationDecision::denied($reason),
            $effectiveDateTime,
        );
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
}
