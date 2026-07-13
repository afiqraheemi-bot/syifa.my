<?php

declare(strict_types=1);

namespace Tests\Architecture;

use App\Modules\TenantManagement\Contracts\TenantRouting\TenantAdminRoutingLookupInterface;
use App\Modules\TenantManagement\Domain\Aggregates\Tenant\Tenant;
use App\Modules\TenantManagement\Infrastructure\Persistence\Lookups\PostgresTenantAdminRoutingLookup;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionClass;
use SplFileInfo;

final class TenantAdminRoutingLabelFoundationArchitectureTest extends TestCase
{
    public function test_foundation_has_no_website_builder_or_custom_domain_dependency(): void
    {
        $module = dirname(__DIR__, 2).'/app/Modules/TenantManagement';

        foreach ($this->phpFilesIn($module) as $file) {
            $contents = file_get_contents($file);
            self::assertIsString($contents);
            self::assertStringNotContainsString('WebsiteBuilder', $contents, $file);
            self::assertStringNotContainsString('CustomDomain', $contents, $file);
        }
    }

    public function test_routing_label_remains_inside_the_existing_tenant_aggregate(): void
    {
        $aggregate = dirname(__DIR__, 2).'/app/Modules/TenantManagement/Domain/Aggregates/Tenant';

        self::assertFileExists($aggregate.'/Tenant.php');
        self::assertFileExists($aggregate.'/ValueObjects/TenantAdminRoutingLabel.php');
        self::assertSame(Tenant::class, (new ReflectionClass(Tenant::class))->getName());
        self::assertFileDoesNotExist($aggregate.'/TenantAdminRoutingLabel.php');
    }

    public function test_lookup_is_read_only_and_not_a_general_or_aggregate_repository(): void
    {
        $contract = new ReflectionClass(TenantAdminRoutingLookupInterface::class);
        $adapter = new ReflectionClass(PostgresTenantAdminRoutingLookup::class);
        $methodNames = array_map(
            static fn ($method): string => $method->getName(),
            $contract->getMethods(),
        );

        self::assertSame(['resolve'], $methodNames);
        self::assertStringNotContainsString('Repository', $contract->getName());
        self::assertStringNotContainsString('Repository', $adapter->getName());
        self::assertFalse($adapter->hasMethod('save'));
        self::assertFalse($adapter->hasMethod('find'));
    }

    public function test_domain_remains_framework_independent(): void
    {
        $domain = dirname(__DIR__, 2).'/app/Modules/TenantManagement/Domain/Aggregates/Tenant';

        foreach ($this->phpFilesIn($domain) as $file) {
            $contents = file_get_contents($file);
            self::assertIsString($contents);
            self::assertStringNotContainsString('Illuminate\\', $contents, $file);
            self::assertStringNotContainsString('Infrastructure\\', $contents, $file);
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
