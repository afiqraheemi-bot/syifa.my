<?php

declare(strict_types=1);

namespace Tests\Integration\Modules\PlatformAdministration\Authentication;

use App\Modules\PlatformAdministration\Contracts\Authentication\PlatformSessionAuthenticationInterface;
use App\Modules\PlatformAdministration\Contracts\WorkforceCredentials\CredentialVerificationInterface;
use App\Modules\PlatformAdministration\Contracts\WorkforceCredentials\PlatformWorkforceCredentialLookupInterface;
use App\Modules\PlatformAdministration\Infrastructure\Authentication\PlatformIdentityUserProvider;
use DateTimeImmutable;
use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Illuminate\Contracts\Auth\StatefulGuard;
use Illuminate\Contracts\Hashing\Hasher;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Sprint 3A Phase 1: proves the real, container-wired `platform_identity`
 * Guard — not a Unit-level fake — genuinely authenticates against a real
 * Postgres row via `Auth::attempt()`, and that logout genuinely clears it.
 * This is the first end-to-end confirmation that native Guard registration
 * (added on top of the pre-existing, unchanged `CredentialVerificationInterface`)
 * actually works, since every other existing test either mocks the Guard or
 * fakes the Contracts-level credential lookup.
 *
 * Row-level isolation only, per the established pattern (see
 * BookingSubmissionGatewayAdapterIntegrationTest) — never touches table
 * structure, only inserts/deletes its own uniquely-generated row, with a
 * `hasTable()` skip-guard for the shared disposable database.
 */
final class PlatformIdentityGuardIntegrationTest extends TestCase
{
    private const string CONNECTION = 'platform_identity_guard_integration';

    private const string PASSWORD = 'Synthetic-Password-123!';

    private ?ConnectionInterface $connection = null;

    /** @var list<string> */
    private array $seededIdentityIds = [];

    protected function setUp(): void
    {
        parent::setUp();
        $dsn = getenv('PLATFORM_ADMINISTRATION_POSTGRES_TEST_DSN');

        if (! is_string($dsn) || $dsn === '') {
            self::markTestSkipped('Requires PLATFORM_ADMINISTRATION_POSTGRES_TEST_DSN for a dedicated disposable PostgreSQL database.');
        }

        config()->set('database.default', self::CONNECTION);
        config()->set('database.connections.'.self::CONNECTION, [
            'driver' => 'pgsql',
            'url' => $dsn,
            'charset' => 'utf8',
            'prefix' => '',
            'prefix_indexes' => true,
            'search_path' => 'public',
            'sslmode' => 'prefer',
        ]);
        DB::purge(self::CONNECTION);
        $this->connection = DB::connection(self::CONNECTION);

        $schema = $this->connection->getSchemaBuilder();

        if (! $schema->hasTable('platform_workforce_credentials') || ! $schema->hasColumn('platform_workforce_credentials', 'remember_token')) {
            self::markTestSkipped('Requires the full, freshly-migrated schema on the disposable database.');
        }
    }

    protected function tearDown(): void
    {
        if ($this->connection !== null) {
            foreach ($this->seededIdentityIds as $identityId) {
                $this->connection->table('platform_workforce_credentials')->where('platform_identity_id', $identityId)->delete();
            }
            DB::purge(self::CONNECTION);
        }
        parent::tearDown();
    }

    public function test_a_real_native_guard_attempt_authenticates_against_a_real_row_and_logout_clears_it(): void
    {
        $identityId = (string) Str::uuid();
        $email = $this->seedIdentity($identityId, self::PASSWORD);

        $guard = $this->guard();

        self::assertFalse($guard->check());

        $attempted = $guard->attempt(['email' => $email, 'password' => self::PASSWORD]);

        self::assertTrue($attempted);
        self::assertTrue($guard->check());
        self::assertSame($identityId, $guard->id());

        $guard->logout();

        self::assertFalse($guard->check());
    }

    public function test_a_real_native_guard_attempt_rejects_the_wrong_password(): void
    {
        $email = $this->seedIdentity((string) Str::uuid(), self::PASSWORD);

        $guard = $this->guard();

        self::assertFalse($guard->attempt(['email' => $email, 'password' => 'wrong']));
        self::assertFalse($guard->check());
    }

    public function test_a_real_native_guard_attempt_rejects_an_unknown_email(): void
    {
        $guard = $this->guard();

        self::assertFalse($guard->attempt(['email' => 'nobody-'.Str::uuid().'@example.test', 'password' => self::PASSWORD]));
        self::assertFalse($guard->check());
    }

    /**
     * Exercises the real production call path — `AuthenticatePlatformSessionService`,
     * not a bare `Guard::attempt()` — because "remember" is deliberately
     * finished in `LaravelPlatformSessionStore::establish()` against a real,
     * freshly-queried row (see that class's own docblock for why: the
     * minimally-hydrated object `Guard::attempt()` itself uses for
     * verification never carries a real `password_hash`, which Laravel's
     * recaller cookie requires).
     */
    public function test_a_remembered_login_persists_a_real_remember_token_recoverable_via_retrieve_by_token(): void
    {
        $identityId = (string) Str::uuid();
        $email = $this->seedIdentity($identityId, self::PASSWORD);

        self::assertNull($this->storedRememberToken($identityId));

        $principal = $this->app->make(PlatformSessionAuthenticationInterface::class)
            ->authenticate($email, self::PASSWORD, new DateTimeImmutable, true);

        self::assertNotNull($principal);

        $storedToken = $this->storedRememberToken($identityId);
        self::assertIsString($storedToken);
        self::assertNotSame('', $storedToken);

        $provider = $this->userProvider();
        $recalled = $provider->retrieveByToken($identityId, $storedToken);
        self::assertNotNull($recalled);
        self::assertSame($identityId, $recalled->getAuthIdentifier());

        self::assertNull($provider->retrieveByToken($identityId, 'a-completely-wrong-token'));
    }

    public function test_logout_cycles_the_remember_token_so_the_old_one_can_never_be_replayed(): void
    {
        $identityId = (string) Str::uuid();
        $email = $this->seedIdentity($identityId, self::PASSWORD);

        $this->app->make(PlatformSessionAuthenticationInterface::class)
            ->authenticate($email, self::PASSWORD, new DateTimeImmutable, true);
        $tokenBeforeLogout = $this->storedRememberToken($identityId);
        self::assertIsString($tokenBeforeLogout);

        $this->guard()->logout();

        self::assertNull($this->userProvider()->retrieveByToken($identityId, $tokenBeforeLogout));
    }

    private function guard(): StatefulGuard
    {
        $guard = $this->app->make(AuthFactory::class)->guard('platform_identity');
        self::assertInstanceOf(StatefulGuard::class, $guard);

        return $guard;
    }

    private function userProvider(): PlatformIdentityUserProvider
    {
        return new PlatformIdentityUserProvider(
            $this->app->make(PlatformWorkforceCredentialLookupInterface::class),
            $this->app->make(CredentialVerificationInterface::class),
            $this->app->make(Hasher::class),
        );
    }

    private function storedRememberToken(string $identityId): ?string
    {
        $row = $this->connection->table('platform_workforce_credentials')->where('platform_identity_id', $identityId)->first();
        self::assertNotNull($row);
        $token = $row->remember_token;

        return is_string($token) ? $token : null;
    }

    private function seedIdentity(string $identityId, string $password): string
    {
        $this->seededIdentityIds[] = $identityId;
        $email = 'designer-'.$identityId.'@example.test';
        $now = now();
        $this->connection->table('platform_workforce_credentials')->insert([
            'platform_identity_id' => $identityId,
            'normalized_email' => $email,
            'password_hash' => Hash::make($password),
            'email_verification_status' => 'verified',
            'email_verified_at' => $now,
            'account_status' => 'active',
            'failed_attempt_count' => 0,
            'lockout_until' => null,
            'name' => 'Website Designer',
            'role' => 'website_designer',
            'version' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return $email;
    }
}
