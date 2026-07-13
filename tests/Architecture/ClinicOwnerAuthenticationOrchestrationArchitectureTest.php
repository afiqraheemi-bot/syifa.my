<?php

declare(strict_types=1);

namespace Tests\Architecture;

use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

final class ClinicOwnerAuthenticationOrchestrationArchitectureTest extends TestCase
{
    public function test_orchestration_depends_only_on_approved_contract_boundaries(): void
    {
        $service = dirname(__DIR__, 2).'/app/Modules/TenantManagement/Application/Authentication/AuthenticateClinicOwnerService.php';
        $contents = file_get_contents($service);

        self::assertIsString($contents);
        self::assertStringContainsString('ClinicOwnerCredentialVerificationInterface', $contents);
        self::assertStringContainsString('TrustedTenantSelectorInterface', $contents);
        self::assertStringContainsString('ResolveTenantContextService', $contents);
        self::assertStringNotContainsString('new ResolveTenantContextService', $contents);
        self::assertStringNotContainsString('TenantRepositoryInterface', $contents);
        self::assertStringNotContainsString('Infrastructure\\', $contents);
        self::assertStringNotContainsString('clinic_owner_authorities', $contents);
        self::assertStringNotContainsString('password_hash', $contents);
    }

    public function test_no_transport_session_or_new_aggregate_artifact_was_introduced(): void
    {
        $root = dirname(__DIR__, 2);

        self::assertDirectoryDoesNotExist($root.'/app/Modules/TenantManagement/Presentation/Authentication');
        self::assertDirectoryDoesNotExist($root.'/app/Modules/TenantManagement/Domain/Aggregates/Authentication');
        self::assertDirectoryDoesNotExist($root.'/app/Modules/TenantManagement/Domain/Aggregates/AuthenticatedPrincipal');
        self::assertSame([], glob($root.'/database/migrations/**/*authentication*') ?: []);

        foreach ($this->phpFilesIn($root.'/app/Modules/TenantManagement') as $file) {
            self::assertDoesNotMatchRegularExpression(
                '/(?:AuthenticationController|AuthenticationMiddleware|SessionRepository|LoginController)\.php$/',
                $file,
            );
        }
    }

    public function test_password_blocklist_has_no_production_implementation_or_binding(): void
    {
        $root = dirname(__DIR__, 2);
        $matches = [];

        foreach ($this->phpFilesIn($root.'/app') as $file) {
            if (str_contains(basename($file), 'PasswordBlocklist')) {
                $matches[] = $file;
            }
        }

        self::assertSame(
            [$root.'/app/Modules/TenantManagement/Contracts/Authentication/PasswordBlocklistInterface.php'],
            $matches,
        );
        $provider = file_get_contents(
            $root.'/app/Modules/TenantManagement/Infrastructure/TenantManagementServiceProvider.php',
        );
        self::assertIsString($provider);
        self::assertStringNotContainsString('PasswordBlocklistInterface', $provider);
    }

    public function test_domain_remains_framework_independent(): void
    {
        $root = dirname(__DIR__, 2).'/app/Modules/TenantManagement/Domain';

        foreach ($this->phpFilesIn($root) as $file) {
            $contents = file_get_contents($file);
            self::assertIsString($contents);
            self::assertStringNotContainsString('Illuminate\\', $contents, $file);
            self::assertStringNotContainsString('Application\\Authentication', $contents, $file);
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

        sort($files);

        return $files;
    }
}
