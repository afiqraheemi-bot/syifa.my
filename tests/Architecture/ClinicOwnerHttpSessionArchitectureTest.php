<?php

declare(strict_types=1);

namespace Tests\Architecture;

use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

final class ClinicOwnerHttpSessionArchitectureTest extends TestCase
{
    public function test_session_is_not_an_aggregate_and_forbidden_artifacts_do_not_exist(): void
    {
        self::assertDirectoryDoesNotExist($this->root().'/app/Modules/TenantManagement/Domain/Aggregates/Session');
        self::assertFileDoesNotExist($this->root().'/app/Models/User.php');
        self::assertEmpty(glob($this->root().'/database/migrations/*sessions*') ?: []);

        $routes = file_get_contents($this->root().'/routes/web.php');
        self::assertIsString($routes);
        foreach (['password', 'reset', 'mfa', 'verification', 'register'] as $forbidden) {
            self::assertStringNotContainsString("Route::{$forbidden}", $routes);
        }
    }

    public function test_authentication_transport_is_confined_to_the_approved_tenant_management_presentation_path(): void
    {
        $modules = $this->root().'/app/Modules';

        foreach ($this->phpFilesIn($modules) as $file) {
            if (preg_match('/(?:Controller|Middleware|Request|Resource)\.php$/', $file) !== 1) {
                continue;
            }

            self::assertStringContainsString('/Presentation/', $file, $file);
            if (str_contains($file, 'ClinicOwnerSession') || str_contains($file, 'CreateClinicOwnerSession')) {
                self::assertStringContainsString('/TenantManagement/Presentation/Http/', $file, $file);
            }
        }

        foreach (['Onboarding', 'PlatformAdministration'] as $unrelatedModule) {
            foreach ($this->phpFilesIn($modules.'/'.$unrelatedModule) as $file) {
                $source = file_get_contents($file);
                self::assertIsString($source);
                self::assertStringNotContainsString('ClinicOwnerSession', $source, $file);
                self::assertStringNotContainsString('/api/v1/sessions', $source, $file);
            }
        }
    }

    public function test_route_source_contains_only_the_three_approved_session_definitions(): void
    {
        $routes = file_get_contents($this->root().'/routes/web.php');
        self::assertIsString($routes);
        self::assertSame(
            3,
            substr_count($routes, "Route::post('/sessions'")
                + substr_count($routes, "Route::get('/sessions/current'")
                + substr_count($routes, "Route::delete('/sessions/current'"),
        );
        self::assertSame(1, substr_count($routes, "Route::post('/sessions'"));
        self::assertSame(1, substr_count($routes, "Route::get('/sessions/current'"));
        self::assertSame(1, substr_count($routes, "Route::delete('/sessions/current'"));
        foreach (['password', 'reset', 'mfa', 'verification', 'register', 'permission', 'rbac'] as $forbidden) {
            self::assertStringNotContainsString($forbidden, strtolower($routes));
        }
    }

    public function test_application_session_layer_has_no_laravel_or_infrastructure_dependency(): void
    {
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(
            $this->root().'/app/Modules/TenantManagement/Application/Session',
        ));

        foreach ($iterator as $file) {
            if (! $file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            $source = file_get_contents($file->getPathname());
            self::assertIsString($source);
            self::assertStringNotContainsString('Illuminate\\', $source);
            self::assertStringNotContainsString('Infrastructure\\', $source);
        }
    }

    public function test_production_session_configuration_is_redis_and_hardened(): void
    {
        $environment = file_get_contents($this->root().'/.env.example');
        self::assertIsString($environment);
        self::assertStringContainsString('SESSION_DRIVER=redis', $environment);
        self::assertStringContainsString('SESSION_CONNECTION=session', $environment);
        self::assertStringContainsString('SESSION_LIFETIME=120', $environment);
        self::assertStringContainsString('SESSION_ENCRYPT=true', $environment);
        self::assertStringContainsString('SESSION_SECURE_COOKIE=true', $environment);
        self::assertStringContainsString('AUTH_SESSION_ABSOLUTE_LIFETIME=720', $environment);

        $sessionConfiguration = file_get_contents($this->root().'/config/session.php');
        self::assertIsString($sessionConfiguration);
        self::assertStringContainsString("env('SESSION_HTTP_ONLY', true)", $sessionConfiguration);
        self::assertStringContainsString("env('SESSION_SAME_SITE', 'lax')", $sessionConfiguration);
    }

    /** @return list<string> */
    private function phpFilesIn(string $directory): array
    {
        $files = [];
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory));

        foreach ($iterator as $file) {
            if ($file instanceof SplFileInfo && $file->isFile() && $file->getExtension() === 'php') {
                $files[] = $file->getPathname();
            }
        }

        return $files;
    }

    private function root(): string
    {
        return dirname(__DIR__, 2);
    }
}
