<?php

declare(strict_types=1);

namespace Tests\Architecture;

use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

final class TenantContextFoundationArchitectureTest extends TestCase
{
    public function test_tenant_context_is_a_runtime_domain_object_not_an_aggregate(): void
    {
        $root = dirname(__DIR__, 2);

        self::assertFileExists(
            $root.'/app/Modules/TenantManagement/Domain/TenantContext/TenantContext.php',
        );
        self::assertDirectoryDoesNotExist(
            $root.'/app/Modules/TenantManagement/Domain/Aggregates/TenantContext',
        );
        self::assertDirectoryDoesNotExist($root.'/app/Modules/TenantContext');
    }

    public function test_tenant_context_domain_is_framework_independent(): void
    {
        $root = dirname(__DIR__, 2).'/app/Modules/TenantManagement/Domain/TenantContext';

        foreach ($this->phpFilesIn($root) as $file) {
            $contents = file_get_contents($file);
            self::assertIsString($contents);
            self::assertStringNotContainsString('Illuminate\\', $contents, $file);
            self::assertStringNotContainsString('Eloquent', $contents, $file);
            self::assertStringNotContainsString('Infrastructure\\', $contents, $file);
            self::assertStringNotContainsString('Presentation\\', $contents, $file);
        }
    }

    public function test_tenant_context_has_no_cross_context_domain_dependency(): void
    {
        $root = dirname(__DIR__, 2).'/app/Modules/TenantManagement/Domain/TenantContext';

        foreach ($this->phpFilesIn($root) as $file) {
            $contents = file_get_contents($file);
            self::assertIsString($contents);
            self::assertStringNotContainsString(
                'App\\Modules\\PlatformAdministration\\',
                $contents,
                $file,
            );
            self::assertStringNotContainsString('App\\Modules\\Onboarding\\', $contents, $file);
        }
    }

    public function test_tenant_context_has_no_delivery_or_persistence_implementation(): void
    {
        $root = dirname(__DIR__, 2);

        self::assertFileExists(
            $root.'/app/Modules/TenantManagement/Infrastructure/TenantContext/ClinicOwnerTenantContextResolver.php',
        );
        self::assertDirectoryDoesNotExist(
            $root.'/app/Modules/TenantManagement/Presentation/TenantContext',
        );

        foreach ($this->phpFilesIn($root.'/app/Modules/TenantManagement/Domain/TenantContext') as $file) {
            self::assertDoesNotMatchRegularExpression(
                '/(?:Controller|Middleware|Repository|Record|Model|Job)\.php$/',
                $file,
            );
        }

        self::assertDirectoryDoesNotExist(
            $root.'/app/Modules/TenantManagement/Infrastructure/TenantContext/Persistence',
        );
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
}
