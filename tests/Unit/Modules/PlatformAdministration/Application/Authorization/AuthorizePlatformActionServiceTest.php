<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\PlatformAdministration\Application\Authorization;

use App\Modules\PlatformAdministration\Application\Authorization\AuthorizePlatformActionService;
use App\Modules\PlatformAdministration\Application\PlatformIdentity\GetPlatformIdentityService;
use App\Modules\PlatformAdministration\Contracts\Authorization\AuthorizationDecisionData;
use App\Modules\PlatformAdministration\Contracts\Authorization\CategoryGrantData;
use App\Modules\PlatformAdministration\Contracts\Authorization\CategoryGrantLookupInterface;
use App\Modules\PlatformAdministration\Contracts\Authorization\Exceptions\AmbiguousPlatformAdministratorProfileException;
use App\Modules\PlatformAdministration\Contracts\Authorization\PlatformAdministratorData;
use App\Modules\PlatformAdministration\Contracts\Authorization\PlatformAdministratorLookupInterface;
use App\Modules\PlatformAdministration\Contracts\Authorization\PlatformCategoryData;
use App\Modules\PlatformAdministration\Contracts\Authorization\PlatformCategoryLookupInterface;
use App\Modules\PlatformAdministration\Contracts\Authorization\PlatformPermissionData;
use App\Modules\PlatformAdministration\Contracts\Authorization\PlatformPermissionLookupInterface;
use App\Modules\PlatformAdministration\Contracts\PlatformIdentity\PlatformIdentityData;
use App\Modules\PlatformAdministration\Contracts\PlatformIdentity\PlatformIdentityLookupInterface;
use App\Modules\PlatformAdministration\Domain\Authorization\PlatformAuthorizationService;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class AuthorizePlatformActionServiceTest extends TestCase
{
    private const string PLATFORM_IDENTITY_ID = '00000000-0000-4000-8000-000000000001';

    private const string ADMINISTRATOR_ID = '00000000-0000-4000-8000-000000000002';

    private const string CATEGORY_KEY = 'commercial_catalogue';

    private const string PERMISSION_KEY = 'commercial_catalogue.manage';

    private const string EFFECTIVE_AT = '2026-07-16T05:30:00Z';

    public function test_an_active_super_admin_identity_with_a_full_grant_is_allowed(): void
    {
        $decision = $this->service()->authorize(self::PLATFORM_IDENTITY_ID, self::CATEGORY_KEY, self::PERMISSION_KEY, self::EFFECTIVE_AT);

        self::assertTrue($decision->allowed);
        self::assertSame('allowed', $decision->reason);
        self::assertSame(self::PLATFORM_IDENTITY_ID, $decision->platformIdentityId);
        self::assertSame(self::CATEGORY_KEY, $decision->categoryKey);
        self::assertSame(self::PERMISSION_KEY, $decision->permissionKey);
        self::assertSame(self::EFFECTIVE_AT, $decision->evaluatedAt);
    }

    public function test_it_denies_when_platform_identity_is_not_found(): void
    {
        $decision = $this->service(identityId: 'unknown-identity')->authorize(
            self::PLATFORM_IDENTITY_ID,
            self::CATEGORY_KEY,
            self::PERMISSION_KEY,
            self::EFFECTIVE_AT,
        );

        $this->assertDenied($decision, 'platform_identity_not_found');
    }

    public function test_a_malformed_platform_identity_id_fails_closed(): void
    {
        $decision = $this->service()->authorize('not-a-uuid', self::CATEGORY_KEY, self::PERMISSION_KEY, self::EFFECTIVE_AT);

        $this->assertDenied($decision, 'platform_identity_not_found');
    }

    public function test_it_denies_when_platform_identity_is_inactive(): void
    {
        $decision = $this->service(identityStatus: 'suspended')->authorize(
            self::PLATFORM_IDENTITY_ID,
            self::CATEGORY_KEY,
            self::PERMISSION_KEY,
            self::EFFECTIVE_AT,
        );

        $this->assertDenied($decision, 'platform_identity_not_active');
    }

    public function test_it_denies_a_non_super_admin_identity(): void
    {
        $decision = $this->service(identityRole: 'website_designer')->authorize(
            self::PLATFORM_IDENTITY_ID,
            self::CATEGORY_KEY,
            self::PERMISSION_KEY,
            self::EFFECTIVE_AT,
        );

        $this->assertDenied($decision, 'super_admin_role_required');
    }

    public function test_role_alone_never_authorizes_without_an_administrator_profile(): void
    {
        $decision = $this->service(administratorPlatformIdentityId: 'a-different-identity')->authorize(
            self::PLATFORM_IDENTITY_ID,
            self::CATEGORY_KEY,
            self::PERMISSION_KEY,
            self::EFFECTIVE_AT,
        );

        $this->assertDenied($decision, 'administrator_profile_not_found');
    }

    public function test_category_grant_without_the_super_admin_role_never_authorizes(): void
    {
        $decision = $this->service(identityRole: 'website_designer')->authorize(
            self::PLATFORM_IDENTITY_ID,
            self::CATEGORY_KEY,
            self::PERMISSION_KEY,
            self::EFFECTIVE_AT,
        );

        self::assertFalse($decision->allowed);
        self::assertSame('super_admin_role_required', $decision->reason);
    }

    public function test_it_denies_when_the_administrator_profile_is_ambiguous(): void
    {
        $administrators = new class implements PlatformAdministratorLookupInterface
        {
            public function findByPlatformIdentityId(string $platformIdentityId): ?PlatformAdministratorData
            {
                throw new AmbiguousPlatformAdministratorProfileException('ambiguous');
            }
        };

        $decision = $this->service(administrators: $administrators)->authorize(
            self::PLATFORM_IDENTITY_ID,
            self::CATEGORY_KEY,
            self::PERMISSION_KEY,
            self::EFFECTIVE_AT,
        );

        $this->assertDenied($decision, 'administrator_profile_ambiguous');
    }

    public function test_it_denies_when_the_administrator_profile_is_suspended(): void
    {
        $decision = $this->service(administratorStatus: 'suspended')->authorize(
            self::PLATFORM_IDENTITY_ID,
            self::CATEGORY_KEY,
            self::PERMISSION_KEY,
            self::EFFECTIVE_AT,
        );

        $this->assertDenied($decision, 'administrator_not_active');
    }

    public function test_it_denies_when_category_is_not_found(): void
    {
        $decision = $this->service(categoryKey: 'unknown_category')->authorize(
            self::PLATFORM_IDENTITY_ID,
            self::CATEGORY_KEY,
            self::PERMISSION_KEY,
            self::EFFECTIVE_AT,
        );

        $this->assertDenied($decision, 'category_not_found');
    }

    public function test_it_denies_when_category_is_retired(): void
    {
        $decision = $this->service(categoryStatus: 'retired')->authorize(
            self::PLATFORM_IDENTITY_ID,
            self::CATEGORY_KEY,
            self::PERMISSION_KEY,
            self::EFFECTIVE_AT,
        );

        $this->assertDenied($decision, 'category_not_active');
    }

    public function test_it_denies_when_permission_is_not_found(): void
    {
        $decision = $this->service(permissionKey: 'unknown_permission')->authorize(
            self::PLATFORM_IDENTITY_ID,
            self::CATEGORY_KEY,
            self::PERMISSION_KEY,
            self::EFFECTIVE_AT,
        );

        $this->assertDenied($decision, 'permission_not_found');
    }

    public function test_it_denies_when_permission_is_inactive(): void
    {
        $decision = $this->service(permissionStatus: 'retired')->authorize(
            self::PLATFORM_IDENTITY_ID,
            self::CATEGORY_KEY,
            self::PERMISSION_KEY,
            self::EFFECTIVE_AT,
        );

        $this->assertDenied($decision, 'permission_not_active');
    }

    public function test_it_denies_when_permission_belongs_to_another_category(): void
    {
        $decision = $this->service(permissionCategoryKey: 'platform_settings')->authorize(
            self::PLATFORM_IDENTITY_ID,
            self::CATEGORY_KEY,
            self::PERMISSION_KEY,
            self::EFFECTIVE_AT,
        );

        $this->assertDenied($decision, 'permission_category_mismatch');
    }

    public function test_it_denies_when_grant_is_not_found(): void
    {
        $decision = $this->service(grantCategoryKey: 'no_such_grant_here')->authorize(
            self::PLATFORM_IDENTITY_ID,
            self::CATEGORY_KEY,
            self::PERMISSION_KEY,
            self::EFFECTIVE_AT,
        );

        $this->assertDenied($decision, 'grant_not_found');
    }

    public function test_it_denies_when_grant_is_revoked(): void
    {
        $decision = $this->service(grantStatus: 'revoked')->authorize(
            self::PLATFORM_IDENTITY_ID,
            self::CATEGORY_KEY,
            self::PERMISSION_KEY,
            self::EFFECTIVE_AT,
        );

        $this->assertDenied($decision, 'grant_not_active');
    }

    public function test_it_denies_when_permission_is_not_included_in_the_grant(): void
    {
        $decision = $this->service(grantPermissionKeys: ['commercial_catalogue.view'])->authorize(
            self::PLATFORM_IDENTITY_ID,
            self::CATEGORY_KEY,
            self::PERMISSION_KEY,
            self::EFFECTIVE_AT,
        );

        $this->assertDenied($decision, 'permission_not_granted');
    }

    public function test_permission_alone_never_authorizes_without_being_granted(): void
    {
        $decision = $this->service(grantPermissionKeys: [])->authorize(
            self::PLATFORM_IDENTITY_ID,
            self::CATEGORY_KEY,
            self::PERMISSION_KEY,
            self::EFFECTIVE_AT,
        );

        self::assertFalse($decision->allowed);
    }

    public function test_an_unexpected_infrastructure_failure_propagates_and_is_never_converted_to_a_decision(): void
    {
        $categories = new class implements PlatformCategoryLookupInterface
        {
            public function findCategory(string $categoryKey): ?PlatformCategoryData
            {
                throw new RuntimeException('connection lost');
            }
        };

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('connection lost');
        $this->service(categoryLookup: $categories)->authorize(
            self::PLATFORM_IDENTITY_ID,
            self::CATEGORY_KEY,
            self::PERMISSION_KEY,
            self::EFFECTIVE_AT,
        );
    }

    public function test_no_raw_permission_list_is_returned_on_the_decision(): void
    {
        $decision = $this->service()->authorize(self::PLATFORM_IDENTITY_ID, self::CATEGORY_KEY, self::PERMISSION_KEY, self::EFFECTIVE_AT);

        self::assertFalse(property_exists($decision, 'permissionKeys'));
        self::assertFalse(property_exists($decision, 'permissions'));
    }

    private function assertDenied(AuthorizationDecisionData $decision, string $reason): void
    {
        self::assertFalse($decision->allowed);
        self::assertSame($reason, $decision->reason);
    }

    private function service(
        ?string $identityId = null,
        string $identityStatus = 'active',
        string $identityRole = 'super_admin',
        ?PlatformCategoryLookupInterface $categoryLookup = null,
        ?PlatformAdministratorLookupInterface $administrators = null,
        ?string $administratorPlatformIdentityId = null,
        string $administratorStatus = 'active',
        ?string $categoryKey = null,
        string $categoryStatus = 'active',
        ?string $permissionKey = null,
        string $permissionStatus = 'active',
        ?string $permissionCategoryKey = null,
        ?string $grantCategoryKey = null,
        string $grantStatus = 'active',
        ?array $grantPermissionKeys = null,
    ): AuthorizePlatformActionService {
        $identities = new class($identityId ?? self::PLATFORM_IDENTITY_ID, $identityStatus, $identityRole) implements PlatformIdentityLookupInterface
        {
            public function __construct(private string $id, private string $status, private string $role) {}

            public function findById(string $platformIdentityId): ?PlatformIdentityData
            {
                return $platformIdentityId === $this->id
                    ? new PlatformIdentityData($this->id, 'admin@example.test', 'Super Admin', $this->role, $this->status)
                    : null;
            }
        };

        $administrators ??= new class(self::ADMINISTRATOR_ID, $administratorPlatformIdentityId ?? self::PLATFORM_IDENTITY_ID, $administratorStatus) implements PlatformAdministratorLookupInterface
        {
            public function __construct(
                private string $administratorId,
                private string $platformIdentityId,
                private string $status,
            ) {}

            public function findByPlatformIdentityId(string $platformIdentityId): ?PlatformAdministratorData
            {
                return $platformIdentityId === $this->platformIdentityId
                    ? new PlatformAdministratorData($this->administratorId, $platformIdentityId, $this->status)
                    : null;
            }
        };

        $categoryLookup ??= new class($categoryKey ?? self::CATEGORY_KEY, $categoryStatus) implements PlatformCategoryLookupInterface
        {
            public function __construct(private string $key, private string $status) {}

            public function findCategory(string $categoryKey): ?PlatformCategoryData
            {
                return $categoryKey === $this->key
                    ? new PlatformCategoryData($this->key, 'Commercial Catalogue', 'Governs Commercial Catalogue authoring.', $this->status)
                    : null;
            }
        };

        $permissions = new class($permissionKey ?? self::PERMISSION_KEY, $permissionCategoryKey ?? self::CATEGORY_KEY, $permissionStatus) implements PlatformPermissionLookupInterface
        {
            public function __construct(private string $key, private string $categoryKey, private string $status) {}

            public function findPermission(string $permissionKey): ?PlatformPermissionData
            {
                return $permissionKey === $this->key
                    ? new PlatformPermissionData($this->key, $this->categoryKey, 'Manage', 'Author Commercial Catalogue records.', $this->status)
                    : null;
            }
        };

        $grants = new class(self::ADMINISTRATOR_ID, $grantCategoryKey ?? self::CATEGORY_KEY, $grantPermissionKeys ?? [self::PERMISSION_KEY], $grantStatus) implements CategoryGrantLookupInterface
        {
            /** @param list<string> $permissionKeys */
            public function __construct(
                private string $administratorId,
                private string $categoryKey,
                private array $permissionKeys,
                private string $status,
            ) {}

            public function findGrant(string $administratorId, string $categoryKey): ?CategoryGrantData
            {
                return $administratorId === $this->administratorId && $categoryKey === $this->categoryKey
                    ? new CategoryGrantData($administratorId, $categoryKey, $this->permissionKeys, $this->status, '2026-07-15T05:30:00Z')
                    : null;
            }
        };

        return new AuthorizePlatformActionService(
            new GetPlatformIdentityService($identities),
            $administrators,
            $categoryLookup,
            $permissions,
            $grants,
            new PlatformAuthorizationService,
        );
    }
}
