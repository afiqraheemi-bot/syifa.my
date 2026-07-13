<?php

declare(strict_types=1);

namespace Tests\Architecture;

use App\Modules\Onboarding\Domain\Aggregates\OnboardingJob\Entities\WebsiteDesignerAssignment;
use App\Modules\Onboarding\Domain\Aggregates\OnboardingJob\OnboardingJob;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionClass;
use SplFileInfo;

final class OnboardingJobAggregateFoundationArchitectureTest extends TestCase
{
    public function test_onboarding_job_is_the_root_and_assignment_remains_an_internal_entity(): void
    {
        $root = dirname(__DIR__, 2).'/app/Modules/Onboarding/Domain/Aggregates/OnboardingJob';

        self::assertFileExists($root.'/OnboardingJob.php');
        self::assertFileExists($root.'/Entities/WebsiteDesignerAssignment.php');
        self::assertFalse((new ReflectionClass(OnboardingJob::class))->isReadOnly());
        self::assertTrue((new ReflectionClass(WebsiteDesignerAssignment::class))->isReadOnly());
        self::assertFalse(method_exists(WebsiteDesignerAssignment::class, 'releaseDomainEvents'));
    }

    public function test_domain_is_free_of_laravel_and_outer_layer_dependencies(): void
    {
        $root = dirname(__DIR__, 2).'/app/Modules/Onboarding/Domain/Aggregates/OnboardingJob';

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

    public function test_external_aggregate_references_are_local_identifiers_only(): void
    {
        $root = dirname(__DIR__, 2).'/app/Modules/Onboarding/Domain/Aggregates/OnboardingJob';

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

    public function test_assignment_is_constructed_only_by_onboarding_job_or_its_reconstitution_mapper(): void
    {
        $module = dirname(__DIR__, 2).'/app/Modules/Onboarding';
        $aggregateRoot = $module.'/Domain/Aggregates/OnboardingJob/OnboardingJob.php';
        $persistenceMapper = $module.'/Infrastructure/Persistence/Mappers/OnboardingJobPersistenceMapper.php';

        foreach ($this->phpFilesIn($module) as $file) {
            if (in_array($file, [$aggregateRoot, $persistenceMapper], true)) {
                continue;
            }

            $contents = file_get_contents($file);
            self::assertIsString($contents);
            self::assertStringNotContainsString('new WebsiteDesignerAssignment(', $contents, $file);
        }
    }

    public function test_no_delivery_or_independent_assignment_persistence_artifact_exists(): void
    {
        $module = dirname(__DIR__, 2).'/app/Modules/Onboarding';

        foreach ($this->phpFilesIn($module) as $file) {
            self::assertDoesNotMatchRegularExpression(
                '/(?:Controller|Middleware|AssignmentRepository|Model|QueueJob|Notification)\.php$/',
                $file,
            );
        }

        self::assertFileDoesNotExist(
            $module.'/Infrastructure/Persistence/Repositories/WebsiteDesignerAssignmentRepository.php',
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
