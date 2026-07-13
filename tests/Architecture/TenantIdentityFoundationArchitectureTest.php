<?php

declare(strict_types=1);

namespace Tests\Architecture;

use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

final class TenantIdentityFoundationArchitectureTest extends TestCase
{
    public function test_clinic_owner_identity_is_composed_inside_the_tenant_aggregate_namespace(): void
    {
        $root = dirname(__DIR__, 2);

        self::assertDirectoryExists(
            $root.'/app/Modules/TenantManagement/Domain/Aggregates/Tenant',
        );
        self::assertFileExists(
            $root.'/app/Modules/TenantManagement/Domain/Aggregates/Tenant/Entities/ClinicOwnerAuthority.php',
        );
        self::assertFileExists(
            $root.'/app/Modules/TenantManagement/Domain/Aggregates/Tenant/ValueObjects/ClinicOwnerIdentity.php',
        );
        self::assertFileDoesNotExist(
            $root.'/app/Modules/TenantManagement/Domain/Aggregates/ClinicOwnerIdentity.php',
        );
    }

    public function test_tenant_identity_does_not_introduce_an_aggregate_root(): void
    {
        $root = dirname(__DIR__, 2);

        self::assertDirectoryDoesNotExist($root.'/app/Modules/TenantIdentity');
        self::assertFileDoesNotExist(
            $root.'/app/Modules/TenantManagement/Domain/Aggregates/Tenant/ClinicOwnerIdentity.php',
        );
        self::assertFileDoesNotExist(
            $root.'/app/Modules/TenantManagement/Domain/Aggregates/Tenant/Entities/Tenant.php',
        );
    }

    public function test_tenant_identity_domain_is_framework_independent(): void
    {
        $root = dirname(__DIR__, 2).'/app/Modules/TenantManagement/Domain/Aggregates/Tenant';

        foreach ($this->phpFilesIn($root) as $file) {
            $contents = file_get_contents($file);
            self::assertIsString($contents);
            self::assertStringNotContainsString('Illuminate\\', $contents, $file);
            self::assertStringNotContainsString('Eloquent', $contents, $file);
            self::assertStringNotContainsString('Infrastructure\\', $contents, $file);
            self::assertStringNotContainsString('Presentation\\', $contents, $file);
        }
    }

    public function test_tenant_identity_has_no_delivery_or_persistence_implementation(): void
    {
        $root = dirname(__DIR__, 2);

        self::assertDirectoryDoesNotExist(
            $root.'/app/Modules/TenantManagement/Infrastructure/TenantIdentity',
        );
        self::assertDirectoryDoesNotExist(
            $root.'/app/Modules/TenantManagement/Presentation/TenantIdentity',
        );

        self::assertFileDoesNotExist(
            $root.'/app/Modules/TenantManagement/Infrastructure/Persistence/Repositories/ClinicOwnerAuthorityRepository.php',
        );
    }

    public function test_clinic_owner_identity_domain_types_exist_only_in_tenant_management(): void
    {
        $root = dirname(__DIR__, 2).'/app/Modules';

        foreach (glob($root.'/*', GLOB_ONLYDIR) ?: [] as $module) {
            if (basename($module) === 'TenantManagement') {
                continue;
            }

            self::assertDirectoryDoesNotExist($module.'/Domain/TenantIdentity');
            self::assertDirectoryDoesNotExist($module.'/Domain/ClinicOwnerIdentity');
        }
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
