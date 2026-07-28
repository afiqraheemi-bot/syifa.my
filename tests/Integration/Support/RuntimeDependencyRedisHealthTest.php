<?php

declare(strict_types=1);

namespace Tests\Integration\Support;

use App\Support\Infrastructure\RuntimeDependencyHealthChecker;
use Illuminate\Database\DatabaseManager;
use Illuminate\Filesystem\FilesystemManager;
use Illuminate\Redis\RedisManager;
use Tests\TestCase;

final class RuntimeDependencyRedisHealthTest extends TestCase
{
    public function test_readiness_actively_checks_real_redis_for_cache_and_queue_health(): void
    {
        $port = getenv('RELEASE_REDIS_TEST_PORT') ?: getenv('SESSION_REDIS_TEST_PORT');

        if (! is_string($port) || filter_var($port, FILTER_VALIDATE_INT) === false) {
            self::markTestSkipped('Requires RELEASE_REDIS_TEST_PORT or SESSION_REDIS_TEST_PORT.');
        }

        config()->set('operations.runtime_checks.enabled', true);
        config()->set('operations.runtime_checks.redis_connection', 'release_support');
        config()->set('queue.default', 'redis');
        config()->set('queue.connections.redis.driver', 'redis');
        config()->set('queue.connections.redis.connection', 'release_support');
        config()->set('filesystems.default', 'local');

        $redisConfig = config('database.redis');
        self::assertIsArray($redisConfig);
        $redisConfig['release_support'] = [
            'host' => '127.0.0.1',
            'password' => null,
            'port' => (int) $port,
            'database' => 14,
        ];

        $redis = new RedisManager($this->app, 'predis', $redisConfig);
        $checker = new RuntimeDependencyHealthChecker(
            config(),
            $this->app->make(DatabaseManager::class),
            $redis,
            $this->app->make(FilesystemManager::class),
        );

        $report = $checker->check();

        self::assertTrue($report->isReady());
        self::assertSame('ready', $report->toArray()['status']);
        self::assertSame('ready', $report->toArray()['dependencies'][1]['status']);
        self::assertSame('ready', $report->toArray()['dependencies'][2]['status']);

        $this->app->instance(RuntimeDependencyHealthChecker::class, $checker);

        $this->getJson('/health/ready')
            ->assertOk()
            ->assertJsonPath('checks.dependencies.status', 'ready')
            ->assertJsonPath('checks.dependencies.dependencies.0.name', 'database')
            ->assertJsonPath('checks.dependencies.dependencies.1.name', 'redis')
            ->assertJsonPath('checks.dependencies.dependencies.2.name', 'queue')
            ->assertJsonPath('checks.dependencies.dependencies.3.name', 'storage');

        $redis->disconnect('release_support');
    }
}
