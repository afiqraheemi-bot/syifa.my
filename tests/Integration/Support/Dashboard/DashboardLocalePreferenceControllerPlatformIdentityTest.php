<?php

declare(strict_types=1);

namespace Tests\Integration\Support\Dashboard;

use App\Modules\PlatformAdministration\Infrastructure\Persistence\WorkforceCredentials\Mappers\PlatformWorkforceCredentialPersistenceMapper;
use App\Modules\PlatformAdministration\Infrastructure\Persistence\WorkforceCredentials\PostgresPlatformWorkforceCredentialAdapter;
use App\Support\Authorization\Application\AuthorizationContext;
use App\Support\Dashboard\Presentation\Http\Controllers\DashboardLocalePreferenceController;
use DateTimeImmutable;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Hashing\BcryptHasher;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

final class DashboardLocalePreferenceControllerPlatformIdentityTest extends TestCase
{
    private const string CONNECTION = 'dashboard_locale_preference_platform_identity_integration';

    private ?ConnectionInterface $connection = null;

    /** @var list<Migration> */
    private array $migrations = [];

    protected function setUp(): void
    {
        parent::setUp();
        $dsn = getenv('PLATFORM_ADMINISTRATION_POSTGRES_TEST_DSN');
        if (! is_string($dsn) || $dsn === '') {
            self::markTestSkipped('Requires PLATFORM_ADMINISTRATION_POSTGRES_TEST_DSN for a dedicated disposable PostgreSQL database.');
        }

        config()->set('database.default', self::CONNECTION);
        config()->set('database.connections.'.self::CONNECTION, [
            'driver' => 'pgsql', 'url' => $dsn, 'charset' => 'utf8', 'prefix' => '',
            'prefix_indexes' => true, 'search_path' => 'public', 'sslmode' => 'prefer',
        ]);
        DB::purge(self::CONNECTION);
        $this->connection = DB::connection(self::CONNECTION);

        Schema::dropIfExists('platform_workforce_credentials');

        foreach ([
            '2026_07_13_000001_create_platform_workforce_credentials_table.php',
            '2026_08_28_000001_add_preferred_locale_to_platform_workforce_credentials.php',
        ] as $file) {
            $migration = require base_path('database/migrations/platform_administration/'.$file);
            self::assertInstanceOf(Migration::class, $migration);
            $this->migrations[] = $migration;
            $migration->up();
        }
    }

    protected function tearDown(): void
    {
        foreach (array_reverse($this->migrations) as $migration) {
            $migration->down();
        }
        DB::purge(self::CONNECTION);
        parent::tearDown();
    }

    public function test_a_platform_identity_can_switch_their_preferred_locale_to_malay(): void
    {
        $adapter = new PostgresPlatformWorkforceCredentialAdapter(
            $this->connection(),
            new PlatformWorkforceCredentialPersistenceMapper,
            new BcryptHasher(['rounds' => 4]),
        );
        $credential = $adapter->store(
            $this->uuid(1),
            'designer@example.test',
            'Synthetic-Password-123',
            'active',
            true,
            $this->time(),
            $this->time(),
        );

        $context = new AuthorizationContext('platform_identity', $credential->platformIdentityId, null, 'super_admin', 'Super Admin', 'shared.authenticated-route', []);
        $request = Request::create('/dashboard/preferences/locale', 'PATCH', ['locale' => 'ms']);
        $request->attributes->set(AuthorizationContext::class, $context);

        $this->app->make(DashboardLocalePreferenceController::class)->update($request);

        self::assertSame(
            'ms',
            $this->connection()->table('platform_workforce_credentials')
                ->where('platform_identity_id', $credential->platformIdentityId)
                ->value('preferred_locale'),
        );
    }

    private function connection(): ConnectionInterface
    {
        self::assertNotNull($this->connection);

        return $this->connection;
    }

    private function time(): DateTimeImmutable
    {
        return new DateTimeImmutable('2026-07-13T10:00:00+08:00');
    }

    private function uuid(int $suffix): string
    {
        return sprintf('00000000-0000-4000-8000-%012d', $suffix);
    }
}
