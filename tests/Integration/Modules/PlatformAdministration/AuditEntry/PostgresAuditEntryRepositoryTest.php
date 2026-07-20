<?php

declare(strict_types=1);

namespace Tests\Integration\Modules\PlatformAdministration\AuditEntry;

use App\Modules\PlatformAdministration\Domain\AuditEntry\AuditEntry;
use App\Modules\PlatformAdministration\Domain\AuditEntry\ValueObjects\AuditActorType;
use App\Modules\PlatformAdministration\Domain\AuditEntry\ValueObjects\AuditEntryId;
use App\Modules\PlatformAdministration\Domain\AuditEntry\ValueObjects\AuditOutcomeType;
use App\Modules\PlatformAdministration\Infrastructure\Persistence\AuditEntry\Mappers\AuditEntryPersistenceMapper;
use App\Modules\PlatformAdministration\Infrastructure\Persistence\AuditEntry\PostgresAuditEntryRepository;
use DateTimeImmutable;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

final class PostgresAuditEntryRepositoryTest extends TestCase
{
    private ?ConnectionInterface $connection = null;

    private ?PostgresAuditEntryRepository $repository = null;

    private ?AuditEntryPersistenceMapper $mapper = null;

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

        config()->set('database.default', 'platform_administration_audit_integration');
        config()->set('database.connections.platform_administration_audit_integration', [
            'driver' => 'pgsql',
            'url' => $dsn,
            'charset' => 'utf8',
            'prefix' => '',
            'prefix_indexes' => true,
            'search_path' => 'public',
            'sslmode' => 'prefer',
        ]);
        DB::purge('platform_administration_audit_integration');
        $this->connection = DB::connection('platform_administration_audit_integration');
        Schema::dropIfExists('audit_entries');

        $migration = require base_path(
            'database/migrations/platform_administration/2026_07_20_000001_create_audit_entries_table.php',
        );
        self::assertInstanceOf(Migration::class, $migration);
        $this->migrations[] = $migration;
        $migration->up();

        $this->mapper = new AuditEntryPersistenceMapper;
        $this->repository = new PostgresAuditEntryRepository($this->connection(), $this->mapper);
    }

    protected function tearDown(): void
    {
        foreach (array_reverse($this->migrations) as $migration) {
            $migration->down();
        }

        DB::purge('platform_administration_audit_integration');
        parent::tearDown();
    }

    public function test_it_appends_and_round_trips_an_audit_entry_without_foreign_keys(): void
    {
        $entry = $this->entry();
        $persisted = $this->repository()->append($entry);

        self::assertSame($entry->id->value, $persisted->id->value);

        $row = $this->connection()->table('audit_entries')
            ->where('audit_entry_id', $entry->id->value)
            ->first();

        self::assertNotNull($row);

        $reconstituted = $this->mapper()->toDomain($row);

        self::assertSame($entry->id->value, $reconstituted->id->value);
        self::assertSame($entry->occurredAt->format('Y-m-d H:i:s.uP'), $reconstituted->occurredAt->format('Y-m-d H:i:s.uP'));
        self::assertSame($entry->actorType, $reconstituted->actorType);
        self::assertSame($entry->actorIdentityId, $reconstituted->actorIdentityId);
        self::assertSame($entry->tenantId, $reconstituted->tenantId);
        self::assertSame($entry->action, $reconstituted->action);
        self::assertSame($entry->targetType, $reconstituted->targetType);
        self::assertSame($entry->targetId, $reconstituted->targetId);
        self::assertSame($entry->outcome, $reconstituted->outcome);
        self::assertSame($entry->reasonCode, $reconstituted->reasonCode);
        self::assertSame($entry->correlationId, $reconstituted->correlationId);
        self::assertSame($entry->safeMetadata, $reconstituted->safeMetadata);

        self::assertSame(
            'jsonb',
            (string) $this->connection()->selectOne(
                'select pg_typeof(safe_metadata) as type from audit_entries where audit_entry_id = ?',
                [$entry->id->value],
            )->type,
        );
        self::assertSame(
            0,
            (int) $this->connection()->selectOne(
                "select count(*) as count from information_schema.table_constraints where table_schema = 'public' and table_name = 'audit_entries' and constraint_type = 'FOREIGN KEY'",
            )->count,
        );

        foreach ([
            'audit_entries_occurred_at_index',
            'audit_entries_correlation_id_index',
            'audit_entries_actor_identity_occurred_at_index',
            'audit_entries_tenant_occurred_at_index',
            'audit_entries_action_occurred_at_index',
            'audit_entries_target_occurred_at_index',
        ] as $indexName) {
            self::assertNotFalse(
                $this->connection()->selectOne(
                    "select 1 as found from pg_indexes where schemaname = 'public' and tablename = 'audit_entries' and indexname = ?",
                    [$indexName],
                ),
                $indexName,
            );
        }
    }

    public function test_it_rejects_duplicate_primary_keys_at_the_database_boundary(): void
    {
        $entry = $this->entry();
        $this->repository()->append($entry);

        $this->expectException(QueryException::class);
        $this->repository()->append($entry);
    }

    private function entry(): AuditEntry
    {
        return AuditEntry::record(
            new AuditEntryId('00000000-0000-4000-8000-000000000301'),
            new DateTimeImmutable('2026-07-20T03:30:00Z'),
            AuditActorType::PlatformIdentity,
            '00000000-0000-4000-8000-000000000101',
            null,
            'platform.authorization.evaluate',
            'platform_permission',
            'commercial_catalogue.manage',
            AuditOutcomeType::Denied,
            'authorization_denied',
            '00000000-0000-4000-8000-000000000302',
            ['actor_role' => 'super_admin', 'resource_label' => 'commercial catalogue'],
        );
    }

    private function connection(): ConnectionInterface
    {
        return $this->connection ?? throw new \LogicException('Connection not initialised.');
    }

    private function repository(): PostgresAuditEntryRepository
    {
        return $this->repository ?? throw new \LogicException('Repository not initialised.');
    }

    private function mapper(): AuditEntryPersistenceMapper
    {
        return $this->mapper ?? throw new \LogicException('Mapper not initialised.');
    }
}
