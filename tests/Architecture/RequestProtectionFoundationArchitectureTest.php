<?php

declare(strict_types=1);

namespace Tests\Architecture;

use PHPUnit\Framework\TestCase;

final class RequestProtectionFoundationArchitectureTest extends TestCase
{
    public function test_rate_limiters_are_registered_only_by_the_central_request_protection_registrar(): void
    {
        $root = dirname(__DIR__, 2);
        $files = $this->phpFilesIn($root.'/app', $root.'/routes');
        $owners = [];

        foreach ($files as $file) {
            $source = $this->source($file);

            if (str_contains($source, 'RateLimiter::for')) {
                $owners[] = str_replace($root.'/', '', $file);
            }
        }

        self::assertSame([
            'app/Http/RateLimiting/RequestProtectionRateLimiters.php',
        ], $owners);
    }

    public function test_request_protection_configuration_contains_the_approved_profiles(): void
    {
        $config = $this->source(dirname(__DIR__, 2).'/config/request_protection.php');

        foreach ([
            'platform_login',
            'platform_session',
            'platform_administration',
            'public',
            'clinic_owner_session',
            'max_attempts',
            'decay_seconds',
            'key_parts',
        ] as $expected) {
            self::assertStringContainsString($expected, $config);
        }
    }

    public function test_routes_reference_named_limiters_without_numeric_limits(): void
    {
        $routes = $this->source(dirname(__DIR__, 2).'/routes/web.php');

        foreach ([
            'throttle:platform.login',
            'throttle:platform.session',
            'throttle:platform.admin',
            'throttle:clinic-owner-session',
            'throttle:public.default',
        ] as $expected) {
            self::assertStringContainsString($expected, $routes);
        }

        foreach ([
            'throttle:platform'.'-login',
            'throttle:platform'.'-session',
            'throttle:platform'.'-administration',
        ] as $oldLimiterName) {
            self::assertStringNotContainsString($oldLimiterName, $routes);
        }
        self::assertDoesNotMatchRegularExpression('/throttle:\d/', $routes);
    }

    public function test_request_protection_has_no_business_layer_dependency(): void
    {
        foreach ($this->phpFilesIn(dirname(__DIR__, 2).'/app/Http/RateLimiting') as $file) {
            $source = $this->source($file);

            foreach ([
                'App\\Modules\\',
                'Domain\\',
                'Application\\',
                'Authorization\\',
                'Authentication\\',
                'Controller',
                'Route::',
                'Cloudflare',
                'AWS',
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
