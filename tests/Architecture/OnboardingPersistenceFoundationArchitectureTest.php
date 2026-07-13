<?php

declare(strict_types=1);

namespace Tests\Architecture;

use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

final class OnboardingPersistenceFoundationArchitectureTest extends TestCase
{
    public function test_onboarding_job_has_exactly_one_repository_implementation(): void
    {
        $root = dirname(__DIR__, 2).'/app/Modules/Onboarding';

        self::assertSame(
            [$root.'/Infrastructure/Persistence/Repositories/PostgresOnboardingJobRepository.php'],
            glob($root.'/Infrastructure/Persistence/Repositories/*Repository.php') ?: [],
        );
        self::assertFileDoesNotExist(
            $root.'/Infrastructure/Persistence/Repositories/WebsiteDesignerAssignmentRepository.php',
        );
    }

    public function test_onboarding_persistence_has_no_cross_module_dependency(): void
    {
        $root = dirname(__DIR__, 2).'/app/Modules/Onboarding/Infrastructure/Persistence';

        foreach ($this->phpFilesIn($root) as $file) {
            $contents = file_get_contents($file);
            self::assertIsString($contents);
            self::assertDoesNotMatchRegularExpression(
                '/use App\\\\Modules\\\\(?!Onboarding\\\\)/',
                $contents,
                $file,
            );
        }
    }

    public function test_onboarding_domain_remains_framework_independent(): void
    {
        $root = dirname(__DIR__, 2).'/app/Modules/Onboarding/Domain/Aggregates/OnboardingJob';

        foreach ($this->phpFilesIn($root) as $file) {
            $contents = file_get_contents($file);
            self::assertIsString($contents);
            self::assertStringNotContainsString('Illuminate\\', $contents, $file);
            self::assertStringNotContainsString('Eloquent', $contents, $file);
            self::assertStringNotContainsString('Infrastructure\\', $contents, $file);
        }
    }

    public function test_migration_is_owned_only_by_onboarding(): void
    {
        $root = dirname(__DIR__, 2);
        $migration = $root.'/database/migrations/onboarding/2026_07_13_000001_create_onboarding_job_aggregate_tables.php';

        self::assertFileExists($migration);
        $contents = file_get_contents($migration);
        self::assertIsString($contents);
        self::assertStringContainsString("Schema::create('onboarding_jobs'", $contents);
        self::assertStringContainsString("Schema::create('website_designer_assignments'", $contents);
        self::assertStringNotContainsString('password', $contents);
        self::assertStringNotContainsString('session', $contents);
        self::assertStringNotContainsString('notification', $contents);
        self::assertStringNotContainsString('booking', $contents);
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
