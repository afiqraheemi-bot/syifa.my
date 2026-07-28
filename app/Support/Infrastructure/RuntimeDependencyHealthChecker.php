<?php

declare(strict_types=1);

namespace App\Support\Infrastructure;

use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Database\DatabaseManager;
use Illuminate\Filesystem\FilesystemManager;
use Illuminate\Redis\RedisManager;
use Throwable;

final readonly class RuntimeDependencyHealthChecker
{
    public function __construct(
        private ConfigRepository $config,
        private DatabaseManager $database,
        private RedisManager $redis,
        private FilesystemManager $filesystem,
    ) {}

    public function check(): RuntimeDependencyHealthResult
    {
        if ($this->config->get('operations.runtime_checks.enabled') !== true) {
            return new RuntimeDependencyHealthResult([]);
        }

        return new RuntimeDependencyHealthResult([
            $this->probe('database', fn (): bool => $this->database->connection()->select('SELECT 1') !== []),
            $this->probe('redis', fn (): bool => $this->pingRedisConnection(
                $this->requiredStringConfig('operations.runtime_checks.redis_connection'),
            )),
            $this->probe('queue', fn (): bool => $this->pingQueue()),
            $this->probe('storage', fn (): bool => $this->probeStorage()),
        ]);
    }

    /**
     * @param  callable(): bool  $probe
     * @return array{name: string, status: string, required: bool, reason_code?: string}
     */
    private function probe(string $name, callable $probe): array
    {
        try {
            if ($probe()) {
                return [
                    'name' => $name,
                    'status' => 'ready',
                    'required' => true,
                ];
            }
        } catch (Throwable) {
            // Health responses intentionally collapse dependency exceptions
            // into a stable, non-sensitive reason code.
        }

        return [
            'name' => $name,
            'status' => 'not_ready',
            'required' => true,
            'reason_code' => 'unavailable',
        ];
    }

    private function pingQueue(): bool
    {
        $queueConnection = $this->requiredStringConfig('queue.default');
        $driver = $this->config->get("queue.connections.$queueConnection.driver");

        if ($driver !== 'redis') {
            return false;
        }

        $redisConnection = $this->config->get("queue.connections.$queueConnection.connection", 'default');

        return is_string($redisConnection)
            && $redisConnection !== ''
            && $this->pingRedisConnection($redisConnection);
    }

    private function pingRedisConnection(string $connection): bool
    {
        $response = $this->redis->connection($connection)->command('ping');

        if ($response === true || $response === 'PONG' || $response === '+PONG') {
            return true;
        }

        return $response instanceof \Stringable
            && in_array((string) $response, ['PONG', '+PONG'], true);
    }

    private function probeStorage(): bool
    {
        $disk = $this->requiredStringConfig('filesystems.default');
        $path = '.health/'.bin2hex(random_bytes(16));
        $filesystem = $this->filesystem->disk($disk);

        try {
            if (! $filesystem->put($path, 'ready')) {
                return false;
            }

            return $filesystem->get($path) === 'ready';
        } finally {
            $filesystem->delete($path);
        }
    }

    private function requiredStringConfig(string $key): string
    {
        $value = $this->config->get($key);

        if (! is_string($value) || $value === '') {
            throw new \RuntimeException('Required runtime dependency configuration is unavailable.');
        }

        return $value;
    }
}
