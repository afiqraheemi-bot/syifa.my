<?php

declare(strict_types=1);

namespace Tests\Integration\Modules\PlatformAdministration\WorkforceCredentials;

use App\Modules\PlatformAdministration\Contracts\WorkforceCredentials\CredentialVerificationResult;
use App\Modules\PlatformAdministration\Contracts\WorkforceCredentials\PlatformWorkforceCredentialData;
use App\Modules\PlatformAdministration\Infrastructure\Persistence\WorkforceCredentials\Exceptions\StalePlatformWorkforceCredentialWriteException;
use App\Modules\PlatformAdministration\Infrastructure\Persistence\WorkforceCredentials\Mappers\PlatformWorkforceCredentialPersistenceMapper;
use App\Modules\PlatformAdministration\Infrastructure\Persistence\WorkforceCredentials\PostgresPlatformWorkforceCredentialAdapter;
use DateTimeImmutable;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\QueryException;
use Illuminate\Hashing\BcryptHasher;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

final class PostgresPlatformWorkforceCredentialAdapterTest extends TestCase
{
    private const string PASSWORD = 'Synthetic-Password-123';

    private ?ConnectionInterface $connection = null;

    private ?PostgresPlatformWorkforceCredentialAdapter $adapter = null;

    private ?Migration $migration = null;

    protected function setUp(): void
    {
        parent::setUp();
        $dsn = getenv('PLATFORM_ADMINISTRATION_POSTGRES_TEST_DSN');

        if (! is_string($dsn) || $dsn === '') {
            self::markTestSkipped(
                'Requires PLATFORM_ADMINISTRATION_POSTGRES_TEST_DSN for a dedicated disposable PostgreSQL database.',
            );
        }

        config()->set('database.default', 'platform_administration_postgres_integration');
        config()->set('database.connections.platform_administration_postgres_integration', [
            'driver' => 'pgsql',
            'url' => $dsn,
            'charset' => 'utf8',
            'prefix' => '',
            'prefix_indexes' => true,
            'search_path' => 'public',
            'sslmode' => 'prefer',
        ]);
        DB::purge('platform_administration_postgres_integration');
        $this->connection = DB::connection('platform_administration_postgres_integration');
        Schema::dropIfExists('platform_workforce_credentials');
        $migration = require base_path(
            'database/migrations/platform_administration/2026_07_13_000001_create_platform_workforce_credentials_table.php',
        );
        self::assertInstanceOf(Migration::class, $migration);
        $this->migration = $migration;
        $this->migration->up();
        $this->adapter = new PostgresPlatformWorkforceCredentialAdapter(
            $this->connection,
            new PlatformWorkforceCredentialPersistenceMapper,
            new BcryptHasher(['rounds' => 4]),
        );
    }

    protected function tearDown(): void
    {
        if ($this->migration !== null) {
            $this->migration->down();
        }

        DB::purge('platform_administration_postgres_integration');
        parent::tearDown();
    }

    public function test_it_persists_loads_normalizes_and_hashes_a_credential(): void
    {
        $stored = $this->store(email: '  DESIGNER@Example.Test  ');
        $loaded = $this->adapter()->findByNormalizedEmail('Designer@EXAMPLE.test');
        $passwordHash = $this->connection()->table('platform_workforce_credentials')->value('password_hash');

        self::assertNotNull($loaded);
        self::assertSame('designer@example.test', $stored->normalizedEmail);
        self::assertSame($stored->platformIdentityId, $loaded->platformIdentityId);
        self::assertIsString($passwordHash);
        self::assertNotSame(self::PASSWORD, $passwordHash);
        self::assertTrue(password_verify(self::PASSWORD, $passwordHash));
    }

    public function test_it_verifies_a_correct_password_and_rejects_an_incorrect_one(): void
    {
        $credential = $this->store();

        self::assertEquals(
            new CredentialVerificationResult(true, $credential->platformIdentityId),
            $this->adapter()->verify($credential->normalizedEmail, self::PASSWORD, $this->time()),
        );
        self::assertEquals(
            new CredentialVerificationResult(false, null),
            $this->adapter()->verify($credential->normalizedEmail, 'incorrect-password', $this->time('+1 minute')),
        );
    }

    public function test_platform_wide_normalized_email_uniqueness_is_enforced(): void
    {
        $this->store(email: 'designer@example.test');

        $this->expectException(QueryException::class);
        $this->store(identitySuffix: 2, email: ' DESIGNER@EXAMPLE.TEST ');
    }

    public function test_five_failures_lock_the_credential_for_fifteen_minutes(): void
    {
        $credential = $this->store();

        for ($attempt = 0; $attempt < 5; $attempt++) {
            self::assertFalse(
                $this->adapter()->verify(
                    $credential->normalizedEmail,
                    'incorrect-password',
                    $this->time('+'.$attempt.' minutes'),
                )->verified,
            );
        }

        $locked = $this->adapter()->findByNormalizedEmail($credential->normalizedEmail);
        self::assertNotNull($locked);
        self::assertSame(5, $locked->failedAttemptCount);
        self::assertSame(
            $this->time('+19 minutes')->getTimestamp(),
            $locked->lockoutUntil?->getTimestamp(),
        );
        self::assertFalse(
            $this->adapter()->verify($credential->normalizedEmail, self::PASSWORD, $this->time('+18 minutes'))->verified,
        );
    }

    public function test_lockout_expiry_permits_verification_and_success_clears_attempt_state(): void
    {
        $credential = $this->store();

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->adapter()->verify(
                $credential->normalizedEmail,
                'incorrect-password',
                $this->time('+'.$attempt.' minutes'),
            );
        }

        self::assertTrue(
            $this->adapter()->verify($credential->normalizedEmail, self::PASSWORD, $this->time('+20 minutes'))->verified,
        );
        $reloaded = $this->adapter()->findByNormalizedEmail($credential->normalizedEmail);
        self::assertNotNull($reloaded);
        self::assertSame(0, $reloaded->failedAttemptCount);
        self::assertNull($reloaded->lockoutUntil);
    }

    public function test_success_clears_failures_before_lockout(): void
    {
        $credential = $this->store();
        $this->adapter()->verify($credential->normalizedEmail, 'incorrect-password', $this->time());
        $this->adapter()->verify($credential->normalizedEmail, 'incorrect-password', $this->time('+1 minute'));

        self::assertTrue(
            $this->adapter()->verify($credential->normalizedEmail, self::PASSWORD, $this->time('+2 minutes'))->verified,
        );
        $reloaded = $this->adapter()->findByNormalizedEmail($credential->normalizedEmail);
        self::assertNotNull($reloaded);
        self::assertSame(0, $reloaded->failedAttemptCount);
        self::assertNull($reloaded->lockoutUntil);
    }

    public function test_a_stale_credential_state_write_is_rejected(): void
    {
        $this->store();
        $first = $this->adapter()->findByNormalizedEmail('designer@example.test');
        $stale = $this->adapter()->findByNormalizedEmail('designer@example.test');
        self::assertNotNull($first);
        self::assertNotNull($stale);
        $this->adapter()->saveState($first, $this->time('+1 minute'));

        $this->expectException(StalePlatformWorkforceCredentialWriteException::class);
        $this->adapter()->saveState($stale, $this->time('+2 minutes'));
    }

    public function test_unknown_and_incorrect_identities_receive_the_same_rejected_result(): void
    {
        $credential = $this->store();
        $unknown = $this->adapter()->verify('unknown@example.test', 'incorrect-password', $this->time());
        $incorrect = $this->adapter()->verify($credential->normalizedEmail, 'incorrect-password', $this->time());

        self::assertEquals($incorrect, $unknown);
        self::assertFalse($unknown->verified);
        self::assertNull($unknown->platformIdentityId);
    }

    public function test_adapter_persists_and_returns_no_tenant_authority_data(): void
    {
        $credential = $this->store();
        $columns = $this->connection()->select(
            "SELECT column_name FROM information_schema.columns WHERE table_schema = 'public' AND table_name = 'platform_workforce_credentials'",
        );
        $columnNames = array_map(
            static fn (object $column): string => (string) $column->column_name,
            $columns,
        );

        self::assertSame([], array_intersect($columnNames, [
            'tenant_id', 'assignment_id', 'tenant_context', 'permissions',
        ]));
        self::assertFalse(property_exists($credential, 'tenantId'));
        self::assertFalse(property_exists($credential, 'assignment'));
        self::assertFalse(property_exists($credential, 'permissions'));
    }

    private function store(
        int $identitySuffix = 1,
        string $email = 'designer@example.test',
    ): PlatformWorkforceCredentialData {
        return $this->adapter()->store(
            sprintf('00000000-0000-4000-8000-%012d', $identitySuffix),
            $email,
            self::PASSWORD,
            'active',
            true,
            $this->time(),
            $this->time(),
        );
    }

    private function adapter(): PostgresPlatformWorkforceCredentialAdapter
    {
        self::assertNotNull($this->adapter);

        return $this->adapter;
    }

    private function connection(): ConnectionInterface
    {
        self::assertNotNull($this->connection);

        return $this->connection;
    }

    private function time(string $modifier = ''): DateTimeImmutable
    {
        $time = new DateTimeImmutable('2026-07-13T10:00:00+08:00');

        return $modifier === '' ? $time : $time->modify($modifier);
    }
}
