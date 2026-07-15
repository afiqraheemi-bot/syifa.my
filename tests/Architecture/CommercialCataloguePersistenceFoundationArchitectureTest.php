<?php

declare(strict_types=1);

namespace Tests\Architecture;

use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

final class CommercialCataloguePersistenceFoundationArchitectureTest extends TestCase
{
    public function test_commercial_catalogue_has_exactly_four_repository_implementations(): void
    {
        $root = dirname(__DIR__, 2).'/app/Modules/SubscriptionBilling';
        $implementations = glob($root.'/Infrastructure/Persistence/Repositories/*Repository.php') ?: [];
        sort($implementations);

        self::assertSame(
            [
                $root.'/Infrastructure/Persistence/Repositories/PostgresBillingOptionRepository.php',
                $root.'/Infrastructure/Persistence/Repositories/PostgresCapabilityDefinitionRepository.php',
                $root.'/Infrastructure/Persistence/Repositories/PostgresPlanOfferingRepository.php',
                $root.'/Infrastructure/Persistence/Repositories/PostgresPlanRepository.php',
            ],
            $implementations,
        );
    }

    public function test_commercial_catalogue_persistence_has_no_cross_module_dependency(): void
    {
        $root = dirname(__DIR__, 2).'/app/Modules/SubscriptionBilling/Infrastructure/Persistence';

        foreach ($this->phpFilesIn($root) as $file) {
            $contents = file_get_contents($file);
            self::assertIsString($contents);

            self::assertDoesNotMatchRegularExpression(
                '/use App\\\\Modules\\\\(?!SubscriptionBilling\\\\)/',
                $contents,
                $file,
            );

            foreach (['Eloquent', 'Model', 'Http\\', 'Controller', 'Request', 'Response', 'Middleware', 'RepositoryBase', 'CrudRepository', 'UnitOfWork'] as $forbidden) {
                self::assertStringNotContainsString($forbidden, $contents, $file);
            }
        }
    }

    public function test_commercial_catalogue_migration_is_owned_only_by_subscription_billing(): void
    {
        $migration = dirname(__DIR__, 2).'/database/migrations/subscription_billing/2026_07_15_000001_create_commercial_catalogue_persistence_tables.php';

        self::assertFileExists($migration);
        $contents = file_get_contents($migration);
        self::assertIsString($contents);
        self::assertStringContainsString("Schema::create('commercial_catalogue_plans'", $contents);
        self::assertStringContainsString("Schema::create('commercial_catalogue_billing_options'", $contents);
        self::assertStringContainsString("Schema::create('commercial_catalogue_plan_offerings'", $contents);
        self::assertStringContainsString("Schema::create('commercial_catalogue_capabilities'", $contents);
        self::assertStringNotContainsString('tenant_id', strtolower($contents));
        self::assertStringNotContainsString('password', strtolower($contents));
        self::assertStringNotContainsString('session', strtolower($contents));
        self::assertStringNotContainsString('controller', strtolower($contents));
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
