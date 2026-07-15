<?php

declare(strict_types=1);

namespace Tests\Contract\Modules\PlatformAdministration\Authorization;

use App\Modules\PlatformAdministration\Contracts\Authorization\AuthorizationDecisionData;
use App\Modules\PlatformAdministration\Contracts\Authorization\CategoryGrantData;
use App\Modules\PlatformAdministration\Contracts\Authorization\CategoryGrantLookupInterface;
use App\Modules\PlatformAdministration\Contracts\Authorization\PlatformAdministratorData;
use App\Modules\PlatformAdministration\Contracts\Authorization\PlatformAdministratorLookupInterface;
use App\Modules\PlatformAdministration\Contracts\Authorization\PlatformAuthorizationInterface;
use App\Modules\PlatformAdministration\Contracts\Authorization\PlatformCategoryData;
use App\Modules\PlatformAdministration\Contracts\Authorization\PlatformCategoryLookupInterface;
use App\Modules\PlatformAdministration\Contracts\Authorization\PlatformPermissionData;
use App\Modules\PlatformAdministration\Contracts\Authorization\PlatformPermissionLookupInterface;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionProperty;

final class PlatformAuthorizationContractsTest extends TestCase
{
    public function test_read_models_are_immutable_and_complete(): void
    {
        self::assertTrue((new ReflectionClass(PlatformAdministratorData::class))->isReadOnly());
        self::assertTrue((new ReflectionClass(PlatformCategoryData::class))->isReadOnly());
        self::assertTrue((new ReflectionClass(PlatformPermissionData::class))->isReadOnly());
        self::assertTrue((new ReflectionClass(CategoryGrantData::class))->isReadOnly());
        self::assertTrue((new ReflectionClass(AuthorizationDecisionData::class))->isReadOnly());

        self::assertSame(['administratorId', 'status'], $this->propertyNames(PlatformAdministratorData::class));
        self::assertSame(
            ['categoryKey', 'name', 'description', 'status'],
            $this->propertyNames(PlatformCategoryData::class),
        );
        self::assertSame(
            ['permissionKey', 'categoryKey', 'name', 'description', 'status'],
            $this->propertyNames(PlatformPermissionData::class),
        );
        self::assertSame(
            ['administratorId', 'categoryKey', 'permissionKeys', 'status', 'grantedAt'],
            $this->propertyNames(CategoryGrantData::class),
        );
        self::assertSame(
            ['administratorId', 'categoryKey', 'permissionKey', 'allowed', 'reason', 'evaluatedAt'],
            $this->propertyNames(AuthorizationDecisionData::class),
        );
    }

    public function test_lookup_interfaces_expose_the_expected_narrow_signatures(): void
    {
        $administrators = new class implements PlatformAdministratorLookupInterface
        {
            public function findAdministrator(string $administratorId): ?PlatformAdministratorData
            {
                return $administratorId === 'admin-id'
                    ? new PlatformAdministratorData($administratorId, 'active')
                    : null;
            }
        };
        $categories = new class implements PlatformCategoryLookupInterface
        {
            public function findCategory(string $categoryKey): ?PlatformCategoryData
            {
                return $categoryKey === 'commercial_catalogue'
                    ? new PlatformCategoryData($categoryKey, 'Commercial Catalogue', 'Description', 'active')
                    : null;
            }
        };
        $permissions = new class implements PlatformPermissionLookupInterface
        {
            public function findPermission(string $permissionKey): ?PlatformPermissionData
            {
                return $permissionKey === 'manage'
                    ? new PlatformPermissionData($permissionKey, 'commercial_catalogue', 'Manage', 'Description', 'active')
                    : null;
            }
        };
        $grants = new class implements CategoryGrantLookupInterface
        {
            public function findGrant(string $administratorId, string $categoryKey): ?CategoryGrantData
            {
                return $administratorId === 'admin-id' && $categoryKey === 'commercial_catalogue'
                    ? new CategoryGrantData($administratorId, $categoryKey, ['manage'], 'active', '2026-07-15T05:30:00Z')
                    : null;
            }
        };

        $administrator = $administrators->findAdministrator('admin-id');
        $category = $categories->findCategory('commercial_catalogue');
        $permission = $permissions->findPermission('manage');
        $grant = $grants->findGrant('admin-id', 'commercial_catalogue');

        self::assertNotNull($administrator);
        self::assertNotNull($category);
        self::assertNotNull($permission);
        self::assertNotNull($grant);

        self::assertSame('active', $administrator->status);
        self::assertSame('commercial_catalogue', $category->categoryKey);
        self::assertSame('commercial_catalogue', $permission->categoryKey);
        self::assertSame(['manage'], $grant->permissionKeys);

        self::assertNull($administrators->findAdministrator('unknown-id'));
        self::assertNull($categories->findCategory('unknown_category'));
        self::assertNull($permissions->findPermission('unknown_permission'));
        self::assertNull($grants->findGrant('admin-id', 'unknown_category'));
    }

    public function test_platform_authorization_interface_is_the_sole_decision_boundary_and_fails_closed(): void
    {
        $authorization = new class implements PlatformAuthorizationInterface
        {
            public function authorize(
                string $administratorId,
                string $categoryKey,
                string $permissionKey,
                string $effectiveDateTime,
            ): AuthorizationDecisionData {
                return new AuthorizationDecisionData(
                    $administratorId,
                    $categoryKey,
                    $permissionKey,
                    $administratorId !== '' && $categoryKey !== '' && $permissionKey !== '',
                    $administratorId !== '' && $categoryKey !== '' && $permissionKey !== '' ? 'allowed' : 'permission_not_granted',
                    $effectiveDateTime,
                );
            }
        };

        $decision = $authorization->authorize('admin-id', 'commercial_catalogue', 'manage', '2026-07-15T05:30:00Z');

        self::assertTrue($decision->allowed);
        self::assertSame('allowed', $decision->reason);

        $denied = $authorization->authorize('', 'commercial_catalogue', 'manage', '2026-07-15T05:30:00Z');
        self::assertFalse($denied->allowed);
    }

    public function test_public_contract_surface_carries_no_rbac_tenant_or_entitlement_concerns(): void
    {
        foreach ([
            PlatformAdministratorData::class,
            PlatformCategoryData::class,
            PlatformPermissionData::class,
            CategoryGrantData::class,
            AuthorizationDecisionData::class,
        ] as $class) {
            $properties = $this->propertyNames($class);
            self::assertSame(
                [],
                array_intersect($properties, ['tenantId', 'permission', 'permissions', 'role', 'policy', 'entitlement', 'capabilityKeys']),
            );
        }
    }

    public function test_canonical_calendar_date_and_utc_instant_formats_are_used(): void
    {
        self::assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/', '2026-07-15T05:30:00Z');
    }

    /**
     * @param  class-string  $class
     * @return list<string>
     */
    private function propertyNames(string $class): array
    {
        return array_map(
            static fn (ReflectionProperty $property): string => $property->getName(),
            (new ReflectionClass($class))->getProperties(),
        );
    }
}
