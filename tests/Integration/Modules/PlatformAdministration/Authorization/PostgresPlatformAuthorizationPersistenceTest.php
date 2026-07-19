<?php

declare(strict_types=1);

namespace Tests\Integration\Modules\PlatformAdministration\Authorization;

use App\Modules\PlatformAdministration\Application\Authorization\AuthorizePlatformActionService;
use App\Modules\PlatformAdministration\Application\PlatformIdentity\GetPlatformIdentityService;
use App\Modules\PlatformAdministration\Contracts\PlatformIdentity\PlatformIdentityData;
use App\Modules\PlatformAdministration\Contracts\PlatformIdentity\PlatformIdentityLookupInterface;
use App\Modules\PlatformAdministration\Domain\Authorization\PlatformAuthorizationService;
use App\Modules\PlatformAdministration\Infrastructure\Persistence\Authorization\Mappers\PlatformAuthorizationPersistenceMapper;
use App\Modules\PlatformAdministration\Infrastructure\Persistence\Authorization\PostgresCategoryGrantLookup;
use App\Modules\PlatformAdministration\Infrastructure\Persistence\Authorization\PostgresPlatformAdministratorLookup;
use App\Modules\PlatformAdministration\Infrastructure\Persistence\Authorization\PostgresPlatformCategoryLookup;
use App\Modules\PlatformAdministration\Infrastructure\Persistence\Authorization\PostgresPlatformPermissionLookup;
use DateTimeImmutable;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Every fact this suite persists and queries — Platform Administrator profile, Category,
 * Permission, and Category Grant — is exercised against real, disposable PostgreSQL. Platform
 * Identity resolution uses a disposable in-memory double instead, because no production
 * PostgreSQL adapter for Platform Identity (with its `role`/`name` fields) exists anywhere in
 * this codebase yet — only `platform_workforce_credentials` (id + account status, no role or
 * name) is real. That gap is intentionally not filled here; see the accompanying report's CTO
 * blocker. The `platform_workforce_credentials` row is still real and is the actual FK anchor
 * `platform_administrators.platform_identity_id` references.
 */
final class PostgresPlatformAuthorizationPersistenceTest extends TestCase
{
    private const string PLATFORM_IDENTITY_ID = '00000000-0000-4000-8000-000000000010';

    private const string ADMINISTRATOR_ID = '00000000-0000-4000-8000-000000000001';

    private const string CATEGORY_KEY = 'commercial_catalogue';

    private ?ConnectionInterface $connection = null;

    /** @var list<Migration> */
    private array $migrations = [];

    protected function setUp(): void
    {
        parent::setUp();
        $dsn = getenv('PLATFORM_ADMINISTRATION_POSTGRES_TEST_DSN');

        if (! is_string($dsn) || $dsn === '') {
            self::markTestSkipped(
                'Requires PLATFORM_ADMINISTRATION_POSTGRES_TEST_DSN for a dedicated disposable PostgreSQL database.',
            );
        }

        config()->set('database.default', 'platform_administration_authorization_integration');
        config()->set('database.connections.platform_administration_authorization_integration', [
            'driver' => 'pgsql',
            'url' => $dsn,
            'charset' => 'utf8',
            'prefix' => '',
            'prefix_indexes' => true,
            'search_path' => 'public',
            'sslmode' => 'prefer',
        ]);
        DB::purge('platform_administration_authorization_integration');
        $this->connection = DB::connection('platform_administration_authorization_integration');

        foreach ([
            'platform_category_grant_permissions',
            'platform_category_grants',
            'platform_permissions',
            'platform_categories',
            'platform_administrators',
            'platform_workforce_credentials',
        ] as $table) {
            Schema::dropIfExists($table);
        }

        foreach ([
            'database/migrations/platform_administration/2026_07_13_000001_create_platform_workforce_credentials_table.php',
            'database/migrations/platform_administration/2026_07_16_000001_create_platform_authorization_tables.php',
        ] as $path) {
            $migration = require base_path($path);
            self::assertInstanceOf(Migration::class, $migration);
            $migration->up();
            $this->migrations[] = $migration;
        }

        $this->seedWorkforceIdentityAnchor(self::PLATFORM_IDENTITY_ID);
    }

    protected function tearDown(): void
    {
        foreach (array_reverse($this->migrations) as $migration) {
            $migration->down();
        }

        DB::purge('platform_administration_authorization_integration');
        parent::tearDown();
    }

    public function test_it_persists_and_reconstitutes_every_authorization_fact_by_its_stable_key(): void
    {
        $this->seedBaseline();

        $administrator = $this->administratorLookup()->findByPlatformIdentityId(self::PLATFORM_IDENTITY_ID);
        $category = $this->categoryLookup()->findCategory(self::CATEGORY_KEY);
        $permission = $this->permissionLookup()->findPermission('commercial_catalogue.manage');
        $grant = $this->grantLookup()->findGrant(self::ADMINISTRATOR_ID, self::CATEGORY_KEY);

        self::assertNotNull($administrator);
        self::assertNotNull($category);
        self::assertNotNull($permission);
        self::assertNotNull($grant);

        self::assertSame(self::ADMINISTRATOR_ID, $administrator->administratorId);
        self::assertSame(self::PLATFORM_IDENTITY_ID, $administrator->platformIdentityId);
        self::assertSame('active', $administrator->status);
        self::assertSame(self::CATEGORY_KEY, $category->categoryKey);
        self::assertSame('commercial_catalogue.manage', $permission->permissionKey);
        self::assertSame(self::CATEGORY_KEY, $permission->categoryKey);
        self::assertSame(['commercial_catalogue.manage', 'commercial_catalogue.view'], $grant->permissionKeys);

        self::assertNull($this->administratorLookup()->findByPlatformIdentityId('00000000-0000-4000-8000-000000099999'));
        self::assertNull($this->categoryLookup()->findCategory('unknown_category'));
        self::assertNull($this->permissionLookup()->findPermission('unknown_permission'));
        self::assertNull($this->grantLookup()->findGrant(self::ADMINISTRATOR_ID, 'unknown_category'));
    }

    public function test_a_malformed_platform_identity_id_fails_closed_at_the_adapter(): void
    {
        self::assertNull($this->administratorLookup()->findByPlatformIdentityId('does-not-exist'));
    }

    public function test_permission_membership_is_returned_in_deterministic_order_regardless_of_insertion_order(): void
    {
        $this->seedAdministrator();
        $this->seedCategory();
        $this->seedPermission('commercial_catalogue.view');
        $this->seedPermission('commercial_catalogue.manage');
        $grantId = $this->seedGrant();
        $this->attachPermission($grantId, 'commercial_catalogue.view');
        $this->attachPermission($grantId, 'commercial_catalogue.manage');

        $first = $this->grantLookup()->findGrant(self::ADMINISTRATOR_ID, self::CATEGORY_KEY);
        $second = $this->grantLookup()->findGrant(self::ADMINISTRATOR_ID, self::CATEGORY_KEY);

        self::assertNotNull($first);
        self::assertNotNull($second);
        self::assertSame(['commercial_catalogue.manage', 'commercial_catalogue.view'], $first->permissionKeys);
        self::assertSame($first->permissionKeys, $second->permissionKeys);
    }

    public function test_no_destructive_deletion_is_possible_once_a_permission_is_granted(): void
    {
        $this->seedBaseline();

        $this->expectException(QueryException::class);
        $this->connection()->table('platform_permissions')->where('key', 'commercial_catalogue.manage')->delete();
    }

    public function test_one_platform_identity_maps_to_at_most_one_administrator_profile(): void
    {
        $this->seedAdministrator();

        $this->expectException(QueryException::class);
        $this->connection()->table('platform_administrators')->insert([
            'administrator_id' => (string) Str::uuid(),
            'platform_identity_id' => self::PLATFORM_IDENTITY_ID,
            'status' => 'active',
            'created_at' => $this->timestamp(),
            'updated_at' => $this->timestamp(),
        ]);
    }

    public function test_an_administrator_profile_without_a_valid_platform_identity_is_rejected_by_the_foreign_key(): void
    {
        $this->expectException(QueryException::class);
        $this->connection()->table('platform_administrators')->insert([
            'administrator_id' => (string) Str::uuid(),
            'platform_identity_id' => (string) Str::uuid(),
            'status' => 'active',
            'created_at' => $this->timestamp(),
            'updated_at' => $this->timestamp(),
        ]);
    }

    public function test_authorization_tables_carry_no_tenant_context(): void
    {
        $this->seedBaseline();

        foreach ([
            'platform_administrators',
            'platform_categories',
            'platform_permissions',
            'platform_category_grants',
            'platform_category_grant_permissions',
        ] as $table) {
            $columns = $this->connection()->select(
                "SELECT column_name FROM information_schema.columns WHERE table_schema = 'public' AND table_name = ?",
                [$table],
            );
            $columnNames = array_map(static fn (object $column): string => (string) $column->column_name, $columns);

            self::assertSame([], array_intersect($columnNames, ['tenant_id', 'assignment_id', 'tenant_context']), $table);
        }

        $administratorColumns = array_map(
            static fn (object $column): string => (string) $column->column_name,
            $this->connection()->select(
                "SELECT column_name FROM information_schema.columns WHERE table_schema = 'public' AND table_name = 'platform_administrators'",
            ),
        );
        self::assertSame([], array_intersect($administratorColumns, ['password_hash', 'session_token', 'mfa_secret', 'role']));
    }

    public function test_end_to_end_authorization_allows_and_denies_against_real_postgres(): void
    {
        $this->seedBaseline();

        $service = new AuthorizePlatformActionService(
            new GetPlatformIdentityService($this->identityLookup()),
            $this->administratorLookup(),
            $this->categoryLookup(),
            $this->permissionLookup(),
            $this->grantLookup(),
            new PlatformAuthorizationService,
        );

        $allowed = $service->authorize(self::PLATFORM_IDENTITY_ID, self::CATEGORY_KEY, 'commercial_catalogue.manage', '2026-07-16T05:30:00Z');
        self::assertTrue($allowed->allowed);
        self::assertSame('allowed', $allowed->reason);

        $this->connection()->table('platform_administrators')
            ->where('platform_identity_id', self::PLATFORM_IDENTITY_ID)
            ->update(['status' => 'suspended']);

        $suspended = $service->authorize(self::PLATFORM_IDENTITY_ID, self::CATEGORY_KEY, 'commercial_catalogue.manage', '2026-07-16T05:30:00Z');
        self::assertFalse($suspended->allowed);
        self::assertSame('administrator_not_active', $suspended->reason);

        $this->connection()->table('platform_administrators')
            ->where('platform_identity_id', self::PLATFORM_IDENTITY_ID)
            ->update(['status' => 'active']);
        $this->connection()->table('platform_category_grants')
            ->where('administrator_id', self::ADMINISTRATOR_ID)
            ->where('category_key', self::CATEGORY_KEY)
            ->update(['status' => 'revoked']);

        $revoked = $service->authorize(self::PLATFORM_IDENTITY_ID, self::CATEGORY_KEY, 'commercial_catalogue.manage', '2026-07-16T05:30:00Z');
        self::assertFalse($revoked->allowed);
        self::assertSame('grant_not_active', $revoked->reason);

        $noSuchIdentity = $service->authorize('00000000-0000-4000-8000-000000099999', self::CATEGORY_KEY, 'commercial_catalogue.manage', '2026-07-16T05:30:00Z');
        self::assertFalse($noSuchIdentity->allowed);
        self::assertSame('platform_identity_not_found', $noSuchIdentity->reason);
    }

    public function test_a_super_admin_identity_without_an_administrator_profile_is_denied(): void
    {
        $this->seedWorkforceIdentityAnchor('00000000-0000-4000-8000-000000000020');
        $this->seedCategory();
        $this->seedPermission('commercial_catalogue.manage');

        $service = new AuthorizePlatformActionService(
            new GetPlatformIdentityService($this->identityLookup('00000000-0000-4000-8000-000000000020')),
            $this->administratorLookup(),
            $this->categoryLookup(),
            $this->permissionLookup(),
            $this->grantLookup(),
            new PlatformAuthorizationService,
        );

        $decision = $service->authorize('00000000-0000-4000-8000-000000000020', self::CATEGORY_KEY, 'commercial_catalogue.manage', '2026-07-16T05:30:00Z');
        self::assertFalse($decision->allowed);
        self::assertSame('administrator_profile_not_found', $decision->reason);
    }

    private function seedBaseline(): void
    {
        $this->seedAdministrator();
        $this->seedCategory();
        $this->seedPermission('commercial_catalogue.manage');
        $this->seedPermission('commercial_catalogue.view');
        $grantId = $this->seedGrant();
        $this->attachPermission($grantId, 'commercial_catalogue.manage');
        $this->attachPermission($grantId, 'commercial_catalogue.view');
    }

    private function seedWorkforceIdentityAnchor(string $platformIdentityId): void
    {
        $this->connection()->table('platform_workforce_credentials')->insert([
            'platform_identity_id' => $platformIdentityId,
            'normalized_email' => 'super-admin-'.substr($platformIdentityId, -6).'@example.test',
            'password_hash' => password_hash('disposable-test-fixture', PASSWORD_BCRYPT),
            'email_verification_status' => 'verified',
            'email_verified_at' => $this->timestamp(),
            'account_status' => 'active',
            'failed_attempt_count' => 0,
            'lockout_until' => null,
            'version' => 1,
            'created_at' => $this->timestamp(),
            'updated_at' => $this->timestamp(),
        ]);
    }

    private function seedAdministrator(): void
    {
        $this->connection()->table('platform_administrators')->insert([
            'administrator_id' => self::ADMINISTRATOR_ID,
            'platform_identity_id' => self::PLATFORM_IDENTITY_ID,
            'status' => 'active',
            'created_at' => $this->timestamp(),
            'updated_at' => $this->timestamp(),
        ]);
    }

    private function seedCategory(): void
    {
        $this->connection()->table('platform_categories')->insert([
            'id' => (string) Str::uuid(),
            'key' => self::CATEGORY_KEY,
            'name' => 'Commercial Catalogue',
            'description' => 'Governs Commercial Catalogue authoring.',
            'status' => 'active',
            'created_at' => $this->timestamp(),
            'updated_at' => $this->timestamp(),
        ]);
    }

    private function seedPermission(string $key): void
    {
        $this->connection()->table('platform_permissions')->insert([
            'id' => (string) Str::uuid(),
            'key' => $key,
            'category_key' => self::CATEGORY_KEY,
            'name' => $key,
            'description' => 'Test-only disposable permission fixture.',
            'status' => 'active',
            'created_at' => $this->timestamp(),
            'updated_at' => $this->timestamp(),
        ]);
    }

    private function seedGrant(): string
    {
        $grantId = (string) Str::uuid();
        $this->connection()->table('platform_category_grants')->insert([
            'id' => $grantId,
            'administrator_id' => self::ADMINISTRATOR_ID,
            'category_key' => self::CATEGORY_KEY,
            'status' => 'active',
            'granted_at' => $this->timestamp(),
            'created_at' => $this->timestamp(),
            'updated_at' => $this->timestamp(),
        ]);

        return $grantId;
    }

    private function attachPermission(string $grantId, string $permissionKey): void
    {
        $this->connection()->table('platform_category_grant_permissions')->insert([
            'grant_id' => $grantId,
            'permission_key' => $permissionKey,
        ]);
    }

    private function identityLookup(string $platformIdentityId = self::PLATFORM_IDENTITY_ID): PlatformIdentityLookupInterface
    {
        return new class($platformIdentityId) implements PlatformIdentityLookupInterface
        {
            public function __construct(private string $id) {}

            public function findById(string $platformIdentityId): ?PlatformIdentityData
            {
                return $platformIdentityId === $this->id
                    ? new PlatformIdentityData($this->id, 'super-admin@example.test', 'Disposable Super Admin', 'super_admin', 'active')
                    : null;
            }
        };
    }

    private function administratorLookup(): PostgresPlatformAdministratorLookup
    {
        return new PostgresPlatformAdministratorLookup($this->connection(), new PlatformAuthorizationPersistenceMapper);
    }

    private function categoryLookup(): PostgresPlatformCategoryLookup
    {
        return new PostgresPlatformCategoryLookup($this->connection(), new PlatformAuthorizationPersistenceMapper);
    }

    private function permissionLookup(): PostgresPlatformPermissionLookup
    {
        return new PostgresPlatformPermissionLookup($this->connection(), new PlatformAuthorizationPersistenceMapper);
    }

    private function grantLookup(): PostgresCategoryGrantLookup
    {
        return new PostgresCategoryGrantLookup($this->connection(), new PlatformAuthorizationPersistenceMapper);
    }

    private function connection(): ConnectionInterface
    {
        self::assertNotNull($this->connection);

        return $this->connection;
    }

    private function timestamp(): string
    {
        return (new DateTimeImmutable('2026-07-16T05:30:00Z'))->format('Y-m-d H:i:s.uP');
    }
}
