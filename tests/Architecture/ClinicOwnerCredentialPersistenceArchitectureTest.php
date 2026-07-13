<?php

declare(strict_types=1);

namespace Tests\Architecture;

use PHPUnit\Framework\TestCase;

final class ClinicOwnerCredentialPersistenceArchitectureTest extends TestCase
{
    public function test_tenant_repository_is_the_only_clinic_owner_persistence_boundary(): void
    {
        $root = dirname(__DIR__, 2);
        $repositories = glob($root.'/app/Modules/TenantManagement/Infrastructure/Persistence/Repositories/*Repository.php') ?: [];

        self::assertSame(
            [$root.'/app/Modules/TenantManagement/Infrastructure/Persistence/Repositories/PostgresTenantRepository.php'],
            $repositories,
        );
        self::assertSame([], glob($root.'/app/**/*ClinicOwner*Credential*Repository.php') ?: []);
        self::assertDirectoryDoesNotExist($root.'/app/Modules/TenantManagement/Domain/Aggregates/ClinicOwnerCredential');
    }

    public function test_domain_has_no_framework_or_persistence_dependency(): void
    {
        $root = dirname(__DIR__, 2).'/app/Modules/TenantManagement/Domain/Aggregates/Tenant';
        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root));

        foreach ($files as $file) {
            if (! $file instanceof \SplFileInfo || ! $file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            $contents = file_get_contents($file->getPathname());
            self::assertIsString($contents);
            self::assertStringNotContainsString('Illuminate\\', $contents, $file->getPathname());
            self::assertStringNotContainsString('Infrastructure\\', $contents, $file->getPathname());
        }
    }

    public function test_credential_schema_is_composed_into_authority_without_forbidden_state(): void
    {
        $migration = dirname(__DIR__, 2).'/database/migrations/tenant_management/2026_07_13_000002_add_clinic_owner_credential_state.php';
        $contents = file_get_contents($migration);

        self::assertIsString($contents);
        self::assertStringContainsString("Schema::table('clinic_owner_authorities'", $contents);
        self::assertStringNotContainsString('Schema::create', $contents);
        self::assertStringNotContainsString('session', $contents);
        self::assertStringNotContainsString('mfa', $contents);
        self::assertStringNotContainsString('remember', $contents);
        self::assertStringNotContainsString('reset_token', $contents);
        self::assertStringNotContainsString('json', strtolower($contents));
    }
}
