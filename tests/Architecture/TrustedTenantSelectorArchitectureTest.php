<?php

declare(strict_types=1);

namespace Tests\Architecture;

use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

final class TrustedTenantSelectorArchitectureTest extends TestCase
{
    public function test_selector_uses_only_the_read_only_routing_lookup_boundary(): void
    {
        $selector = $this->root().'/app/Modules/TenantManagement/Infrastructure/TenantRouting/TenantAdminHostTrustedTenantSelector.php';
        $contents = file_get_contents($selector);

        self::assertIsString($contents);
        self::assertStringContainsString('TenantAdminRoutingLookupInterface', $contents);
        self::assertStringNotContainsString('PostgresTenantAdminRoutingLookup', $contents);
        self::assertStringNotContainsString('TenantRepositoryInterface', $contents);
        self::assertStringNotContainsString('ConnectionInterface', $contents);
        self::assertStringNotContainsString('Illuminate\\Database', $contents);
        self::assertStringNotContainsString('DB::', $contents);
        self::assertStringNotContainsString('Eloquent', $contents);
    }

    public function test_selector_has_no_website_or_custom_domain_dependency(): void
    {
        $directory = $this->root().'/app/Modules/TenantManagement/Infrastructure/TenantRouting';

        foreach ($this->phpFilesIn($directory) as $file) {
            $contents = file_get_contents($file);
            self::assertIsString($contents);
            self::assertStringNotContainsString('WebsiteBuilder', $contents, $file);
            self::assertStringNotContainsString('CustomDomain', $contents, $file);
            self::assertStringNotContainsString('ClinicOwnerEmail', $contents, $file);
        }
    }

    public function test_routing_adapter_contains_no_transport_session_or_tenant_context_artifact(): void
    {
        $module = $this->root().'/app/Modules/TenantManagement';

        foreach ($this->phpFilesIn($module.'/Infrastructure/TenantRouting') as $file) {
            self::assertDoesNotMatchRegularExpression(
                '/(?:Controller|Middleware|Session|Cookie|Request)\.php$/',
                $file,
            );
        }

        self::assertDirectoryDoesNotExist(
            $module.'/Domain/Aggregates/TrustedTenantSelector',
        );
    }

    public function test_task_introduces_no_migration_or_route_definition(): void
    {
        $root = $this->root();

        self::assertCount(5, glob($root.'/database/migrations/tenant_management/*.php') ?: []);
        $provider = file_get_contents(
            $root.'/app/Modules/TenantManagement/Infrastructure/TenantManagementServiceProvider.php',
        );
        self::assertIsString($provider);
        self::assertStringNotContainsString('Route::', $provider);
        self::assertStringNotContainsString('PasswordBlocklistInterface', $provider);
    }

    public function test_admin_base_domains_use_dedicated_non_secret_configuration(): void
    {
        $root = $this->root();
        $configuration = file_get_contents($root.'/config/tenant_management.php');
        $environmentExample = file_get_contents($root.'/.env.example');

        self::assertIsString($configuration);
        self::assertIsString($environmentExample);
        self::assertStringContainsString('TENANT_ADMIN_BASE_DOMAINS', $configuration);
        self::assertStringContainsString("'app.syifa.my'", $configuration);
        self::assertStringContainsString('TENANT_ADMIN_BASE_DOMAINS=app.syifa.my', $environmentExample);
        self::assertStringContainsString('LOCAL_DEMO_TENANT_ROUTING_LABEL=demo-clinic', $environmentExample);
        self::assertStringNotContainsString('preg_', $configuration);
        self::assertStringNotContainsString('tenant_id', strtolower($configuration));
    }

    public function test_local_demo_selection_is_environment_gated_and_server_owned(): void
    {
        $root = $this->root();
        $provider = file_get_contents(
            $root.'/app/Modules/TenantManagement/Infrastructure/TenantManagementServiceProvider.php',
        );
        $selector = file_get_contents(
            $root.'/app/Modules/TenantManagement/Infrastructure/TenantRouting/TenantAdminHostTrustedTenantSelector.php',
        );

        self::assertIsString($provider);
        self::assertIsString($selector);
        self::assertStringContainsString("environment(['local', 'testing'])", $provider);
        self::assertStringContainsString('local_demo_tenant.enabled', $provider);
        self::assertStringContainsString("localDemoTenantString(\$application, 'routing_label')", $provider);
        self::assertStringContainsString('hash_equals($this->localHost, $selectorReference)', $selector);

        foreach (['Request', 'Cookie', 'Header', 'query(', 'tenant_id'] as $forbidden) {
            self::assertStringNotContainsString($forbidden, $selector);
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

    private function root(): string
    {
        return dirname(__DIR__, 2);
    }
}
