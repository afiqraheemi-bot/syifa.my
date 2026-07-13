<?php

declare(strict_types=1);

namespace Tests\Architecture;

use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

final class WebsiteDesignerAssignmentFoundationArchitectureTest extends TestCase
{
    public function test_assignment_is_composed_inside_the_onboarding_job_aggregate_namespace(): void
    {
        $root = dirname(__DIR__, 2);

        self::assertFileExists(
            $root.'/app/Modules/Onboarding/Domain/Aggregates/OnboardingJob/Entities/WebsiteDesignerAssignment.php',
        );
        self::assertDirectoryDoesNotExist(
            $root.'/app/Modules/Onboarding/Domain/Aggregates/WebsiteDesignerAssignment',
        );
        self::assertDirectoryDoesNotExist($root.'/app/Modules/WebsiteDesignerAssignment');
    }

    public function test_assignment_references_other_contexts_only_by_local_identifiers(): void
    {
        $root = dirname(__DIR__, 2).'/app/Modules/Onboarding';

        foreach ($this->phpFilesIn($root) as $file) {
            $contents = file_get_contents($file);
            self::assertIsString($contents);
            self::assertStringNotContainsString(
                'App\\Modules\\PlatformAdministration\\',
                $contents,
                $file,
            );
            self::assertStringNotContainsString(
                'App\\Modules\\TenantManagement\\',
                $contents,
                $file,
            );
        }
    }

    public function test_assignment_domain_is_framework_independent(): void
    {
        $root = dirname(__DIR__, 2).'/app/Modules/Onboarding/Domain/Aggregates/OnboardingJob';

        foreach ($this->phpFilesIn($root) as $file) {
            $contents = file_get_contents($file);
            self::assertIsString($contents);
            self::assertStringNotContainsString('Illuminate\\', $contents, $file);
            self::assertStringNotContainsString('Eloquent', $contents, $file);
            self::assertStringNotContainsString('Infrastructure\\', $contents, $file);
            self::assertStringNotContainsString('Presentation\\', $contents, $file);
        }
    }

    public function test_assignment_has_no_delivery_or_persistence_implementation(): void
    {
        $root = dirname(__DIR__, 2);

        self::assertDirectoryDoesNotExist($root.'/app/Modules/Onboarding/Infrastructure/Assignments');
        self::assertDirectoryDoesNotExist($root.'/app/Modules/Onboarding/Presentation/Assignments');

        foreach ($this->phpFilesIn($root.'/app/Modules/Onboarding') as $file) {
            self::assertDoesNotMatchRegularExpression(
                '/(?:Controller|Middleware|AssignmentRepository|Model|QueueJob)\.php$/',
                $file,
            );
        }

        self::assertFileDoesNotExist(
            $root.'/app/Modules/Onboarding/Infrastructure/Persistence/Repositories/WebsiteDesignerAssignmentRepository.php',
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
