<?php

declare(strict_types=1);

namespace Tests\Architecture;

use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

final class RepositoryContractsArchitectureTest extends TestCase
{
    public function test_exactly_four_repository_interfaces_exist(): void
    {
        $root = dirname(__DIR__, 2).'/app/Modules/SubscriptionBilling/Contracts/Repositories';

        self::assertSame(
            [
                'BillingOptionRepositoryInterface.php',
                'CapabilityDefinitionRepositoryInterface.php',
                'PlanOfferingRepositoryInterface.php',
                'PlanRepositoryInterface.php',
            ],
            $this->phpBasenamesIn($root),
        );
    }

    public function test_no_repository_implementation_or_generic_repository_base_exists(): void
    {
        $root = dirname(__DIR__, 2).'/app/Modules/SubscriptionBilling';

        foreach ($this->phpFilesIn($root) as $file) {
            $name = basename($file);

            if (str_contains($file, '/Contracts/Repositories/')) {
                continue;
            }

            self::assertDoesNotMatchRegularExpression('/Repository.*\.php$/', $name, $file);
            self::assertDoesNotMatchRegularExpression('/(?:BaseRepository|CrudRepository|GenericRepository|RepositoryBase)\.php$/', $name, $file);
        }
    }

    public function test_repository_contract_surface_is_framework_free_and_has_no_database_or_persistence_dependency(): void
    {
        $root = dirname(__DIR__, 2).'/app/Modules/SubscriptionBilling/Contracts/Repositories';

        foreach ($this->phpFilesIn($root) as $file) {
            $contents = file_get_contents($file);
            self::assertIsString($contents, $file);

            foreach ([
                'Illuminate\\',
                'Laravel\\',
                'Infrastructure\\',
                'Presentation\\',
                'Eloquent',
                'Model',
                'Migration',
                'DB::',
                'PDO',
                'QueryBuilder',
                'Cache',
                'Queue',
                'Notification',
                'Controller',
                'Request',
                'Response',
                'Resource',
                'Middleware',
                'RepositoryBase',
                'CrudRepository',
                'UnitOfWork',
            ] as $forbidden) {
                self::assertStringNotContainsString($forbidden, $contents, $file);
            }

            self::assertDoesNotMatchRegularExpression('/use App\\\\Modules\\\\(?!SubscriptionBilling\\\\)/', $contents, $file);
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

    /** @return list<string> */
    private function phpBasenamesIn(string $directory): array
    {
        return array_map(static fn (string $file): string => basename($file), $this->phpFilesIn($directory));
    }
}
