<?php

declare(strict_types=1);

namespace Tests\Architecture;

use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

final class PlatformIdentityFoundationArchitectureTest extends TestCase
{
    public function test_platform_identity_does_not_create_a_module_or_aggregate_root(): void
    {
        $root = dirname(__DIR__, 2);

        self::assertDirectoryDoesNotExist($root.'/app/Modules/PlatformIdentity');
        self::assertDirectoryDoesNotExist(
            $root.'/app/Modules/PlatformAdministration/Domain/Aggregates/PlatformIdentity',
        );
        self::assertDirectoryExists(
            $root.'/app/Modules/PlatformAdministration/Domain/PlatformIdentity',
        );
    }

    public function test_platform_identity_domain_is_framework_independent(): void
    {
        $root = dirname(__DIR__, 2).'/app/Modules/PlatformAdministration/Domain/PlatformIdentity';

        foreach ($this->phpFilesIn($root) as $file) {
            $contents = file_get_contents($file);
            self::assertIsString($contents);
            self::assertStringNotContainsString('Illuminate\\', $contents, $file);
            self::assertStringNotContainsString('Eloquent', $contents, $file);
            self::assertStringNotContainsString('Infrastructure\\', $contents, $file);
            self::assertStringNotContainsString('Presentation\\', $contents, $file);
        }
    }

    public function test_foundation_contains_no_delivery_or_persistence_implementation(): void
    {
        $root = dirname(__DIR__, 2);

        self::assertDirectoryDoesNotExist(
            $root.'/app/Modules/PlatformAdministration/Presentation/PlatformIdentity',
        );
        self::assertDirectoryDoesNotExist(
            $root.'/app/Modules/PlatformAdministration/Infrastructure/PlatformIdentity',
        );
        self::assertSame([], glob($root.'/database/migrations/*.php') ?: []);

        foreach ($this->phpFilesIn($root.'/app/Modules/PlatformAdministration/Domain') as $file) {
            self::assertDoesNotMatchRegularExpression(
                '/(?:Controller|Middleware|Repository|Record|Model|QueueJob)\.php$/',
                $file,
            );
        }
    }

    public function test_no_standalone_authentication_domain_or_module_exists(): void
    {
        $root = dirname(__DIR__, 2).'/app/Modules';

        self::assertDirectoryDoesNotExist($root.'/PlatformAdministration/Domain/Authentication');
        self::assertDirectoryDoesNotExist($root.'/PlatformAdministration/Application/Authentication');
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
