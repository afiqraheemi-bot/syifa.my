<?php

declare(strict_types=1);

namespace Tests\Architecture;

use PHPUnit\Framework\TestCase;

final class ProductionOperationsFoundationArchitectureTest extends TestCase
{
    public function test_operations_routes_are_registered_in_one_central_route_group(): void
    {
        $routes = $this->source(dirname(__DIR__, 2).'/routes/web.php');

        self::assertSame(1, substr_count($routes, "->name('operations.')"));
        self::assertStringContainsString('OperationsController::class', $routes);

        foreach (['health', 'ready', 'live', 'info'] as $endpoint) {
            self::assertStringContainsString("operations.endpoints.$endpoint", $routes);
        }
    }

    public function test_operations_configuration_is_centralized(): void
    {
        $config = $this->source(dirname(__DIR__, 2).'/config/operations.php');

        foreach ([
            'prefix',
            'endpoints',
            'application',
            'checks',
            'health',
            'readiness',
            'liveness',
        ] as $expected) {
            self::assertStringContainsString($expected, $config);
        }
    }

    public function test_operations_foundation_has_no_business_or_module_dependency(): void
    {
        foreach ($this->phpFilesIn(
            dirname(__DIR__, 2).'/app/Http/Controllers',
            dirname(__DIR__, 2).'/app/Http/Operations',
        ) as $file) {
            $source = $this->source($file);

            foreach ([
                'App\\Modules\\',
                'Domain\\',
                'Application\\',
                'Authentication\\',
                'Authorization\\',
                'DB::',
                'Cache::',
                'Queue::',
                'Storage::',
                'Prometheus',
                'OpenTelemetry',
                'Datadog',
                'NewRelic',
                'CloudWatch',
            ] as $forbidden) {
                self::assertStringNotContainsString($forbidden, $source);
            }
        }
    }

    public function test_operations_foundation_exposes_no_sensitive_information_keys(): void
    {
        $responseFactory = $this->source(dirname(__DIR__, 2).'/app/Http/Operations/OperationsResponseFactory.php');

        foreach ([
            'APP_KEY',
            'APP_ENV',
            'APP_DEBUG',
            'DB_PASSWORD',
            'REDIS_PASSWORD',
            'AWS_SECRET',
            'env(',
            'phpversion',
            'laravelVersion',
        ] as $forbidden) {
            self::assertStringNotContainsString($forbidden, $responseFactory);
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
