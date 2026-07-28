<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\Infrastructure\RuntimeDependencyHealthChecker;
use Illuminate\Config\Repository;
use Illuminate\Database\Connection;
use Illuminate\Database\DatabaseManager;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Filesystem\FilesystemManager;
use Illuminate\Redis\Connections\Connection as RedisConnection;
use Illuminate\Redis\RedisManager;
use Mockery;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class RuntimeDependencyHealthCheckerTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    public function test_required_runtime_dependencies_are_actively_probed(): void
    {
        $database = Mockery::mock(DatabaseManager::class);
        $databaseConnection = Mockery::mock(Connection::class);
        $database->expects('connection')->once()->andReturn($databaseConnection);
        $databaseConnection->expects('select')->with('SELECT 1')->once()->andReturn([(object) ['one' => 1]]);

        $redis = Mockery::mock(RedisManager::class);
        $redisConnection = Mockery::mock(RedisConnection::class);
        $redis->expects('connection')->with('default')->twice()->andReturn($redisConnection);
        $redisConnection->expects('command')->with('ping')->twice()->andReturn('PONG');

        $filesystem = Mockery::mock(FilesystemManager::class);
        $disk = Mockery::mock(FilesystemAdapter::class);
        $filesystem->expects('disk')->with('local')->once()->andReturn($disk);
        $disk->expects('put')->once()->with(Mockery::pattern('#^\\.health/[a-f0-9]{32}$#'), 'ready')->andReturn(true);
        $disk->expects('get')->once()->andReturn('ready');
        $disk->expects('delete')->once()->andReturn(true);

        $report = (new RuntimeDependencyHealthChecker(
            $this->config(),
            $database,
            $redis,
            $filesystem,
        ))->check();

        self::assertTrue($report->isReady());
        self::assertSame(['database', 'redis', 'queue', 'storage'], array_column(
            $report->toArray()['dependencies'],
            'name',
        ));
    }

    public function test_dependency_failures_are_redacted_and_fail_readiness_closed(): void
    {
        $database = Mockery::mock(DatabaseManager::class);
        $database->expects('connection')->once()->andThrow(new RuntimeException('postgres://secret'));

        $redis = Mockery::mock(RedisManager::class);
        $redis->expects('connection')->twice()->andThrow(new RuntimeException('redis-password'));

        $filesystem = Mockery::mock(FilesystemManager::class);
        $filesystem->expects('disk')->once()->andThrow(new RuntimeException('storage-secret'));

        $report = (new RuntimeDependencyHealthChecker(
            $this->config(),
            $database,
            $redis,
            $filesystem,
        ))->check();
        $encoded = json_encode($report->toArray());

        self::assertFalse($report->isReady());
        self::assertIsString($encoded);
        self::assertStringNotContainsString('secret', $encoded);
        self::assertStringNotContainsString('password', $encoded);

        foreach ($report->toArray()['dependencies'] as $dependency) {
            self::assertSame('not_ready', $dependency['status']);
            self::assertSame('unavailable', $dependency['reason_code']);
        }
    }

    private function config(): Repository
    {
        return new Repository([
            'operations' => [
                'runtime_checks' => [
                    'enabled' => true,
                    'redis_connection' => 'default',
                ],
            ],
            'queue' => [
                'default' => 'redis',
                'connections' => [
                    'redis' => [
                        'driver' => 'redis',
                        'connection' => 'default',
                    ],
                ],
            ],
            'filesystems' => [
                'default' => 'local',
            ],
        ]);
    }
}
