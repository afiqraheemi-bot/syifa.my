<?php

declare(strict_types=1);

namespace Tests\Architecture;

use PHPUnit\Framework\TestCase;

final class PlatformAdministrationRuntimeWiringArchitectureTest extends TestCase
{
    public function test_bootstrap_registers_the_platform_administration_provider(): void
    {
        $contents = file_get_contents(dirname(__DIR__, 2).'/bootstrap/providers.php');

        self::assertIsString($contents);
        self::assertStringContainsString('PlatformAdministrationServiceProvider::class', $contents);
    }

    public function test_provider_binds_only_the_approved_real_adapter_contracts(): void
    {
        $provider = dirname(__DIR__, 2).'/app/Modules/PlatformAdministration/Infrastructure/PlatformAdministrationServiceProvider.php';
        $contents = file_get_contents($provider);

        self::assertIsString($contents);
        self::assertStringContainsString('PlatformIdentityLookupInterface::class', $contents);
        self::assertStringContainsString('PlatformSessionStoreInterface::class', $contents);
        self::assertStringContainsString('PlatformPrincipalResolverInterface::class', $contents);
        self::assertStringContainsString('PlatformSessionAuthenticationInterface::class', $contents);
        self::assertStringContainsString('PostgresPlatformWorkforceCredentialAdapter::class', $contents);
        self::assertStringContainsString('PlatformWorkforceCredentialLookupInterface::class', $contents);
        self::assertStringContainsString('CredentialVerificationInterface::class', $contents);
        self::assertStringContainsString('migrations/platform_administration', $contents);
        self::assertStringNotContainsString('PasswordBlocklistInterface', $contents);
        self::assertStringNotContainsString('TrustedTenantSelectorInterface', $contents);
        self::assertStringNotContainsString('TenantContextResolverInterface', $contents);
        self::assertStringNotContainsString('Fake', $contents);
        self::assertStringNotContainsString('Permissive', $contents);
    }

    public function test_provider_introduces_no_route_identity_persistence_or_new_migration(): void
    {
        $root = dirname(__DIR__, 2);
        $provider = file_get_contents(
            $root.'/app/Modules/PlatformAdministration/Infrastructure/PlatformAdministrationServiceProvider.php',
        );
        self::assertIsString($provider);
        self::assertStringNotContainsString('Route::', $provider);
        self::assertStringNotContainsString('PlatformIdentityRepository', $provider);
        self::assertSame(
            [
                $root.'/database/migrations/platform_administration/2026_07_13_000001_create_platform_workforce_credentials_table.php',
                $root.'/database/migrations/platform_administration/2026_07_16_000001_create_platform_authorization_tables.php',
                $root.'/database/migrations/platform_administration/2026_07_19_000001_add_name_and_role_to_platform_workforce_credentials_table.php',
            ],
            glob($root.'/database/migrations/platform_administration/*.php') ?: [],
        );
    }
}
