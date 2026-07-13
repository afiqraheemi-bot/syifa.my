<?php

declare(strict_types=1);

namespace Tests\Architecture;

use App\Modules\TenantManagement\Domain\Aggregates\Tenant\Entities\ClinicOwnerAuthority;
use App\Modules\TenantManagement\Domain\Aggregates\Tenant\Tenant;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionClass;
use SplFileInfo;

final class TenantAggregateFoundationArchitectureTest extends TestCase
{
    public function test_tenant_is_the_only_aggregate_root_in_its_aggregate_directory(): void
    {
        $root = dirname(__DIR__, 2).'/app/Modules/TenantManagement/Domain/Aggregates/Tenant';

        self::assertFileExists($root.'/Tenant.php');
        self::assertFileExists($root.'/Entities/ClinicOwnerAuthority.php');
        self::assertFalse((new ReflectionClass(Tenant::class))->isReadOnly());
        self::assertTrue((new ReflectionClass(ClinicOwnerAuthority::class))->isReadOnly());
        self::assertFalse(method_exists(ClinicOwnerAuthority::class, 'releaseDomainEvents'));
    }

    public function test_tenant_domain_has_no_framework_or_outer_layer_dependencies(): void
    {
        $root = dirname(__DIR__, 2).'/app/Modules/TenantManagement/Domain/Aggregates/Tenant';

        foreach ($this->phpFilesIn($root) as $file) {
            $contents = file_get_contents($file);
            self::assertIsString($contents);
            self::assertStringNotContainsString('Illuminate\\', $contents, $file);
            self::assertStringNotContainsString('Laravel\\', $contents, $file);
            self::assertStringNotContainsString('Infrastructure\\', $contents, $file);
            self::assertStringNotContainsString('Presentation\\', $contents, $file);
            self::assertStringNotContainsString('Eloquent', $contents, $file);
        }
    }

    public function test_tenant_domain_does_not_import_another_module_domain(): void
    {
        $root = dirname(__DIR__, 2).'/app/Modules/TenantManagement/Domain/Aggregates/Tenant';

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

    public function test_no_delivery_or_independent_authority_persistence_artifact_exists(): void
    {
        $module = dirname(__DIR__, 2).'/app/Modules/TenantManagement';

        foreach ($this->phpFilesIn($module) as $file) {
            self::assertDoesNotMatchRegularExpression(
                '/(?:Controller|Middleware|QueueJob|Notification)\.php$/',
                $file,
            );
        }

        self::assertFileDoesNotExist(
            $module.'/Infrastructure/Persistence/Repositories/ClinicOwnerAuthorityRepository.php',
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
