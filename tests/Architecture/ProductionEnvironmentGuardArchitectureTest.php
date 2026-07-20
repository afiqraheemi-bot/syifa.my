<?php

declare(strict_types=1);

namespace Tests\Architecture;

use PHPUnit\Framework\TestCase;

final class ProductionEnvironmentGuardArchitectureTest extends TestCase
{
    public function test_production_guard_is_registered_once_by_the_application_service_provider(): void
    {
        $provider = $this->source(dirname(__DIR__, 2).'/app/Providers/AppServiceProvider.php');

        self::assertSame(2, substr_count($provider, 'ProductionEnvironmentGuard'));
        self::assertSame(1, substr_count($provider, '->validate()'));
    }

    public function test_production_guard_configuration_is_centralized(): void
    {
        $config = $this->source(dirname(__DIR__, 2).'/config/production_guard.php');

        foreach ([
            'enabled',
            'validate_console',
            'protected_environments',
            'required_config',
            'expected_values',
            'required_url_schemes',
            'app.key',
            'app.debug',
            'session.secure',
        ] as $expected) {
            self::assertStringContainsString($expected, $config);
        }
    }

    public function test_production_guard_has_no_business_or_module_dependency(): void
    {
        foreach ($this->phpFilesIn(dirname(__DIR__, 2).'/app/Support/Production') as $file) {
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
                'Kubernetes',
                'Docker',
            ] as $forbidden) {
                self::assertStringNotContainsString($forbidden, $source);
            }
        }
    }

    public function test_production_guard_source_does_not_expose_sensitive_configuration_values(): void
    {
        foreach ($this->phpFilesIn(dirname(__DIR__, 2).'/app/Support/Production') as $file) {
            $source = $this->source($file);

            foreach ([
                'APP_KEY',
                'DB_PASSWORD',
                'REDIS_PASSWORD',
                'AWS_SECRET',
                'SECRET_ACCESS_KEY',
                'password_hash',
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
