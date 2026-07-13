<?php

declare(strict_types=1);

namespace Tests\Architecture;

use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

final class PlatformWorkforceCredentialPersistenceArchitectureTest extends TestCase
{
    public function test_credential_persistence_is_platform_administration_owned_and_not_an_aggregate(): void
    {
        $root = dirname(__DIR__, 2);

        self::assertDirectoryExists($root.'/app/Modules/PlatformAdministration/Infrastructure/Persistence/WorkforceCredentials');
        self::assertDirectoryDoesNotExist($root.'/app/Modules/PlatformAdministration/Domain/Aggregates/WorkforceCredential');
        self::assertDirectoryDoesNotExist($root.'/app/Modules/Authentication');
        self::assertSame([], glob($root.'/app/Modules/TenantManagement/**/*Credential*') ?: []);
    }

    public function test_platform_identity_domain_remains_free_of_framework_and_persistence_dependencies(): void
    {
        $root = dirname(__DIR__, 2).'/app/Modules/PlatformAdministration/Domain';

        foreach ($this->phpFilesIn($root) as $file) {
            $contents = file_get_contents($file);
            self::assertIsString($contents);
            self::assertStringNotContainsString('Illuminate\\', $contents, $file);
            self::assertStringNotContainsString('Infrastructure\\', $contents, $file);
        }
    }

    public function test_migration_contains_only_the_technical_platform_record(): void
    {
        $migration = dirname(__DIR__, 2).'/database/migrations/platform_administration/2026_07_13_000001_create_platform_workforce_credentials_table.php';
        $contents = file_get_contents($migration);

        self::assertIsString($contents);
        self::assertStringContainsString("Schema::create('platform_workforce_credentials'", $contents);
        self::assertStringNotContainsString("'tenant_id'", $contents);
        self::assertStringNotContainsString('assignment_id', $contents);
        self::assertStringNotContainsString('permission', $contents);
        self::assertStringNotContainsString('session', $contents);
        self::assertStringNotContainsString('mfa', $contents);
        self::assertStringNotContainsString('token', $contents);
        self::assertStringNotContainsString('json', strtolower($contents));
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
