<?php

declare(strict_types=1);

namespace Tests\Architecture;

use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

final class TenantPersistenceFoundationArchitectureTest extends TestCase
{
    public function test_tenant_has_exactly_one_repository_implementation(): void
    {
        $root = dirname(__DIR__, 2).'/app/Modules/TenantManagement';

        self::assertSame(
            [$root.'/Infrastructure/Persistence/Repositories/PostgresTenantRepository.php'],
            glob($root.'/Infrastructure/Persistence/Repositories/*Repository.php') ?: [],
        );
        self::assertFileDoesNotExist(
            $root.'/Infrastructure/Persistence/Repositories/ClinicOwnerAuthorityRepository.php',
        );
    }

    public function test_tenant_persistence_has_no_cross_module_dependency(): void
    {
        $root = dirname(__DIR__, 2).'/app/Modules/TenantManagement/Infrastructure/Persistence';

        foreach ($this->phpFilesIn($root) as $file) {
            $contents = file_get_contents($file);
            self::assertIsString($contents);
            self::assertDoesNotMatchRegularExpression(
                '/use App\\\\Modules\\\\(?!TenantManagement\\\\)/',
                $contents,
                $file,
            );
        }
    }

    public function test_tenant_domain_remains_framework_independent(): void
    {
        $root = dirname(__DIR__, 2).'/app/Modules/TenantManagement/Domain/Aggregates/Tenant';

        foreach ($this->phpFilesIn($root) as $file) {
            $contents = file_get_contents($file);
            self::assertIsString($contents);
            self::assertStringNotContainsString('Illuminate\\', $contents, $file);
            self::assertStringNotContainsString('Eloquent', $contents, $file);
            self::assertStringNotContainsString('Infrastructure\\', $contents, $file);
        }
    }

    public function test_migration_is_owned_only_by_tenant_management(): void
    {
        $root = dirname(__DIR__, 2);
        $migration = $root.'/database/migrations/tenant_management/2026_07_13_000001_create_tenant_aggregate_tables.php';

        self::assertFileExists($migration);
        $contents = file_get_contents($migration);
        self::assertIsString($contents);
        self::assertStringContainsString("Schema::create('tenants'", $contents);
        self::assertStringContainsString("Schema::create('clinic_owner_authorities'", $contents);
        self::assertStringNotContainsString('password', $contents);
        self::assertStringNotContainsString('session', $contents);
        self::assertStringNotContainsString('onboarding', mb_strtolower($contents));
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
