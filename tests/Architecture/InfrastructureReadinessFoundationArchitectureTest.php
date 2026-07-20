<?php

declare(strict_types=1);

namespace Tests\Architecture;

use PHPUnit\Framework\TestCase;

final class InfrastructureReadinessFoundationArchitectureTest extends TestCase
{
    public function test_infrastructure_readiness_validator_is_registered_once(): void
    {
        $provider = $this->source(dirname(__DIR__, 2).'/app/Providers/AppServiceProvider.php');

        self::assertSame(2, substr_count($provider, 'InfrastructureReadinessValidator'));
        self::assertSame(1, substr_count($provider, '->singleton(InfrastructureReadinessValidator::class)'));
    }

    public function test_infrastructure_readiness_configuration_is_centralized(): void
    {
        $config = $this->source(dirname(__DIR__, 2).'/config/infrastructure_readiness.php');

        foreach ([
            'capabilities',
            'cache',
            'queue',
            'session',
            'logging',
            'filesystem',
            'default_config_key',
            'configured_options_key',
        ] as $expected) {
            self::assertStringContainsString($expected, $config);
        }
    }

    public function test_infrastructure_readiness_has_no_business_module_or_provider_specific_dependency(): void
    {
        foreach ($this->phpFilesIn(dirname(__DIR__, 2).'/app/Support/Infrastructure') as $file) {
            $source = $this->source($file);

            foreach ([
                'App\\Modules\\',
                'Domain\\',
                'Application\\',
                'Authentication\\',
                'Authorization\\',
                'Controller',
                'Route::',
                'DB::',
                'Cache::',
                'Queue::',
                'Storage::',
                'Cloudflare',
                'AWS',
                'Docker',
                'Kubernetes',
                'Redis',
                'Prometheus',
                'OpenTelemetry',
            ] as $forbidden) {
                self::assertStringNotContainsString($forbidden, $source);
            }
        }
    }

    public function test_infrastructure_readiness_reporting_does_not_expose_sensitive_information_keys(): void
    {
        foreach ($this->phpFilesIn(dirname(__DIR__, 2).'/app/Support/Infrastructure') as $file) {
            $source = $this->source($file);

            foreach ([
                'APP_KEY',
                'DB_PASSWORD',
                'REDIS_PASSWORD',
                'AWS_SECRET',
                'SECRET_ACCESS_KEY',
                'env(',
            ] as $forbidden) {
                self::assertStringNotContainsString($forbidden, $source);
            }
        }
    }

    /**
     * @return list<string>
     */
    private function phpFilesIn(string ...$directories): array
    {
        $files = [];

        foreach ($directories as $directory) {
            if (! is_dir($directory)) {
                continue;
            }

            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS),
            );

            foreach ($iterator as $file) {
                if ($file instanceof \SplFileInfo && $file->isFile() && $file->getExtension() === 'php') {
                    $files[] = $file->getPathname();
                }
            }
        }

        sort($files);

        return $files;
    }

    private function source(string $file): string
    {
        $source = file_get_contents($file);

        self::assertIsString($source);

        return $source;
    }
}
