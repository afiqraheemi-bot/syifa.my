<?php

declare(strict_types=1);

namespace Tests\Architecture;

use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

final class ClinicOwnerTenantContextResolverArchitectureTest extends TestCase
{
    public function test_resolver_uses_the_tenant_repository_without_storage_bypass(): void
    {
        $resolver = $this->resolverContents();

        self::assertStringContainsString('TenantRepositoryInterface', $resolver);
        self::assertStringNotContainsString('PostgresTenantRepository', $resolver);
        self::assertStringNotContainsString('ConnectionInterface', $resolver);
        self::assertStringNotContainsString('Illuminate\\Database', $resolver);
        self::assertStringNotContainsString('DB::', $resolver);
        self::assertStringNotContainsString('Eloquent', $resolver);
        self::assertStringNotContainsString('table(', $resolver);
    }

    public function test_resolver_has_no_cross_module_or_deferred_role_dependency(): void
    {
        $resolver = $this->resolverContents();

        self::assertStringNotContainsString('PlatformAdministration', $resolver);
        self::assertStringNotContainsString('Onboarding', $resolver);
        self::assertStringNotContainsString('WebsiteDesignerAssignment', $resolver);
        self::assertStringNotContainsString('SuperAdmin', $resolver);
        self::assertStringNotContainsString('Permission', $resolver);
    }

    public function test_tenant_context_remains_runtime_only_and_not_an_aggregate(): void
    {
        $root = $this->root();

        self::assertDirectoryDoesNotExist(
            $root.'/app/Modules/TenantManagement/Domain/Aggregates/TenantContext',
        );
        self::assertDirectoryDoesNotExist(
            $root.'/app/Modules/TenantManagement/Infrastructure/TenantContext/Persistence',
        );
        self::assertSame([], glob($root.'/database/migrations/**/*tenant_context*') ?: []);
    }

    public function test_no_transport_session_cache_or_delivery_artifact_was_added(): void
    {
        $module = $this->root().'/app/Modules/TenantManagement';

        foreach ($this->phpFilesIn($module) as $file) {
            self::assertDoesNotMatchRegularExpression(
                '/(?:Controller|Middleware|Session|Cookie|Request|Listener)\.php$/',
                $file,
            );
        }

        $resolver = $this->resolverContents();
        self::assertStringNotContainsString('Cache', $resolver);
        self::assertStringNotContainsString('Log', $resolver);
        self::assertStringNotContainsString('dispatch', $resolver);
    }

    public function test_domain_remains_framework_independent(): void
    {
        $domain = $this->root().'/app/Modules/TenantManagement/Domain';

        foreach ($this->phpFilesIn($domain) as $file) {
            $contents = file_get_contents($file);
            self::assertIsString($contents);
            self::assertStringNotContainsString('Illuminate\\', $contents, $file);
            self::assertStringNotContainsString('Infrastructure\\', $contents, $file);
        }
    }

    private function resolverContents(): string
    {
        $contents = file_get_contents(
            $this->root().'/app/Modules/TenantManagement/Infrastructure/TenantContext/ClinicOwnerTenantContextResolver.php',
        );
        self::assertIsString($contents);

        return $contents;
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
