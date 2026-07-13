<?php

declare(strict_types=1);

namespace Tests\Integration\Modules\TenantManagement\Session;

use App\Modules\TenantManagement\Contracts\Authentication\ClinicOwnerAuthenticatedPrincipal;
use App\Modules\TenantManagement\Contracts\Authentication\ClinicOwnerAuthenticationCommand;
use App\Modules\TenantManagement\Contracts\Authentication\ClinicOwnerAuthenticationInterface;
use App\Modules\TenantManagement\Contracts\Authentication\ClinicOwnerAuthenticationOutcome;
use App\Modules\TenantManagement\Contracts\Authentication\ClinicOwnerAuthenticationResult;
use App\Modules\TenantManagement\Contracts\Authentication\Signals\ClinicOwnerAuthenticationSucceeded;
use App\Modules\TenantManagement\Contracts\TenantContext\TenantContextData;
use App\Modules\TenantManagement\Contracts\TenantContext\TenantContextResolutionData;
use App\Modules\TenantManagement\Contracts\TenantContext\TenantContextResolverInterface;
use DateTimeImmutable;
use Illuminate\Redis\RedisManager;
use Illuminate\Session\Store;
use Illuminate\Testing\TestResponse;
use Predis\Client;
use Tests\TestCase;

final class RedisClinicOwnerSessionTest extends TestCase
{
    public const TENANT_ID = '00000000-0000-4000-8000-000000000001';

    private int $redisPort;

    private RedisManager $redis;

    private MutableRedisContextResolver $tenantContexts;

    protected function setUp(): void
    {
        parent::setUp();

        $port = getenv('SESSION_REDIS_TEST_PORT');
        if (! is_string($port) || $port === '') {
            self::markTestSkipped('Requires SESSION_REDIS_TEST_PORT for a disposable Redis-protocol server.');
        }
        $this->redisPort = (int) $port;

        config()->set('session.driver', 'redis');
        config()->set('session.connection', 'session');
        config()->set('session.encrypt', true);
        config()->set('session.lifetime', 120);
        config()->set('database.redis.client', 'predis');
        config()->set('database.redis.options.prefix', 'syifa-045-session-test:');
        foreach (['default' => 0, 'cache' => 1, 'session' => 2] as $connection => $database) {
            config()->set("database.redis.{$connection}", [
                'host' => '127.0.0.1',
                'port' => $this->redisPort,
                'database' => $database,
            ]);
        }

        $this->redis = $this->app->make('redis');
        foreach (['default', 'cache', 'session'] as $connection) {
            $this->redis->purge($connection);
            $this->redis->connection($connection)->flushdb();
        }
        $this->resetSessionDriver();

        $this->tenantContexts = new MutableRedisContextResolver;
        $this->app->instance(TenantContextResolverInterface::class, $this->tenantContexts);
        $this->app->instance(ClinicOwnerAuthenticationInterface::class, new SuccessfulRedisAuthentication);
        SuccessfulRedisAuthentication::$sessionIdBeforeAuthentication = null;
    }

    protected function tearDown(): void
    {
        config()->set('database.redis.session.port', $this->redisPort);
        $this->replaceRedisManager();
        foreach (['default', 'cache', 'session'] as $connection) {
            $this->redis->purge($connection);
            $this->redis->connection($connection)->flushdb();
            $this->redis->purge($connection);
        }

        parent::tearDown();
    }

    public function test_real_backend_persists_minimum_state_rotates_identifier_and_uses_bounded_ttl(): void
    {
        $response = $this->login();

        $response->assertCreated()->assertJsonPath('data.tenant.id', self::TENANT_ID);
        $oldId = SuccessfulRedisAuthentication::$sessionIdBeforeAuthentication;
        $newId = $this->sessionStore()->getId();
        self::assertNotNull($oldId);
        self::assertNotSame($oldId, $newId);
        self::assertSame([], $this->sessionKeys($oldId));

        $keys = $this->sessionKeys($newId);
        self::assertCount(1, $keys);
        $client = $this->rawSessionClient();
        $ttl = (int) $client->ttl($keys[0]);
        self::assertGreaterThan(0, $ttl);
        self::assertLessThanOrEqual(7200, $ttl);
        self::assertSame(0, (int) $this->redis->connection('cache')->dbsize());

        $raw = (string) $client->get($keys[0]);
        foreach (['a private passphrase', 'password_hash', 'permissions', 'Tenant.php'] as $forbidden) {
            self::assertStringNotContainsString($forbidden, $raw);
        }
    }

    public function test_current_session_reads_redis_and_updates_last_activity(): void
    {
        $this->login()->assertCreated();
        /** @var array<string, string> $before */
        $before = $this->sessionStore()->get('clinic_owner_authentication');
        $before['last_activity_at'] = (new DateTimeImmutable)->modify('-10 minutes')->format(DATE_RFC3339);
        $this->sessionStore()->put('clinic_owner_authentication', $before);
        $this->sessionStore()->save();

        $this->getJson('https://clinic.app.syifa.my/api/v1/sessions/current')
            ->assertOk()
            ->assertJsonPath('data.tenant.id', self::TENANT_ID);

        /** @var array<string, string> $after */
        $after = $this->sessionStore()->get('clinic_owner_authentication');
        self::assertNotSame($before['last_activity_at'], $after['last_activity_at']);
    }

    public function test_idle_and_absolute_expiry_invalidate_real_backend_state(): void
    {
        foreach (['last_activity_at' => '-121 minutes', 'absolute_expires_at' => '-1 minute'] as $field => $modifier) {
            $this->login()->assertCreated();
            /** @var array<string, string> $state */
            $state = $this->sessionStore()->get('clinic_owner_authentication');
            $state[$field] = (new DateTimeImmutable)->modify($modifier)->format(DATE_RFC3339);
            $this->sessionStore()->put('clinic_owner_authentication', $state);
            $this->sessionStore()->save();

            $this->getJson('https://clinic.app.syifa.my/api/v1/sessions/current')
                ->assertUnauthorized()
                ->assertJsonPath('type', 'session_invalid');
            self::assertNull($this->sessionStore()->get('clinic_owner_authentication'));
        }
    }

    public function test_logout_invalidates_backend_state_and_is_idempotent(): void
    {
        $this->login()->assertCreated();
        $authenticatedSessionId = $this->sessionStore()->getId();

        $this->deleteJson('https://clinic.app.syifa.my/api/v1/sessions/current')->assertNoContent();
        self::assertNotSame($authenticatedSessionId, $this->sessionStore()->getId());
        self::assertNull($this->sessionStore()->get('clinic_owner_authentication'));
        $this->withCookie((string) config('session.cookie'), $authenticatedSessionId)
            ->getJson('https://clinic.app.syifa.my/api/v1/sessions/current')
            ->assertUnauthorized()
            ->assertJsonPath('type', 'session_invalid');
        $this->deleteJson('https://clinic.app.syifa.my/api/v1/sessions/current')->assertNoContent();
    }

    public function test_tenant_or_authority_revocation_invalidates_the_redis_session(): void
    {
        foreach (['tenant_suspended', 'authority_revoked'] as $reason) {
            $this->login()->assertCreated();
            $this->tenantContexts->valid = false;
            $this->tenantContexts->rejectionReason = $reason;

            $this->getJson('https://clinic.app.syifa.my/api/v1/sessions/current')
                ->assertUnauthorized()
                ->assertJsonPath('type', 'session_invalid');
            self::assertNull($this->sessionStore()->get('clinic_owner_authentication'));
            $this->tenantContexts->valid = true;
        }
    }

    public function test_redis_outage_fails_without_an_authenticated_client_side_session(): void
    {
        config()->set('database.redis.session.port', 1);
        $this->replaceRedisManager();
        $this->resetSessionDriver();

        $response = $this->postJson('https://clinic.app.syifa.my/api/v1/sessions', [
            'email' => 'owner@example.test',
            'password' => 'a private passphrase',
        ]);

        $response->assertStatus(500)
            ->assertJsonPath('type', 'internal_error')
            ->assertHeader('Content-Type', 'application/problem+json')
            ->assertHeader('X-Request-ID')
            ->assertHeader('X-Correlation-ID');
        self::assertStringNotContainsString('"authenticated":true', $response->getContent());
    }

    private function login(): TestResponse
    {
        return $this->postJson('https://clinic.app.syifa.my/api/v1/sessions', [
            'email' => 'owner@example.test',
            'password' => 'a private passphrase',
        ]);
    }

    private function sessionStore(): Store
    {
        return $this->app->make('session.store');
    }

    /** @return list<string> */
    private function sessionKeys(string $sessionId): array
    {
        /** @var list<string> $keys */
        $keys = $this->rawSessionClient()->keys('*'.$sessionId.'*');

        return $keys;
    }

    private function rawSessionClient(): Client
    {
        return new Client([
            'host' => '127.0.0.1',
            'port' => $this->redisPort,
            'database' => 2,
        ]);
    }

    private function resetSessionDriver(): void
    {
        $this->app->make('session')->forgetDrivers();
        $this->app->forgetInstance('session.store');
    }

    private function replaceRedisManager(): void
    {
        $configuration = config('database.redis');
        self::assertIsArray($configuration);
        $this->redis = new RedisManager($this->app, 'predis', $configuration);
        $this->app->instance('redis', $this->redis);
        $this->app->forgetInstance('cache');
        $this->app->forgetInstance('cache.store');
    }
}

final class SuccessfulRedisAuthentication implements ClinicOwnerAuthenticationInterface
{
    public static ?string $sessionIdBeforeAuthentication = null;

    public function authenticate(ClinicOwnerAuthenticationCommand $command): ClinicOwnerAuthenticationResult
    {
        self::$sessionIdBeforeAuthentication = request()->session()->getId();
        $principal = new ClinicOwnerAuthenticatedPrincipal(
            RedisClinicOwnerSessionTest::TENANT_ID,
            '00000000-0000-4000-8000-000000000002',
            '00000000-0000-4000-8000-000000000003',
        );

        return new ClinicOwnerAuthenticationResult(
            ClinicOwnerAuthenticationOutcome::Authenticated,
            $principal,
            new TenantContextData(null, $principal->tenantId, 'clinic_owner', null),
            new ClinicOwnerAuthenticationSucceeded(
                $principal->tenantId,
                $principal->authorityId,
                $principal->clinicOwnerIdentityId,
                $command->attemptedAt,
            ),
        );
    }
}

final class MutableRedisContextResolver implements TenantContextResolverInterface
{
    public bool $valid = true;

    public string $rejectionReason = '';

    public function resolve(TenantContextResolutionData $resolution): ?TenantContextData
    {
        return $this->valid ? new TenantContextData(null, $resolution->tenantId, 'clinic_owner', null) : null;
    }
}
