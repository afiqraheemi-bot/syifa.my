<?php

declare(strict_types=1);

namespace Tests\Architecture;

use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

final class CommercialCataloguePresentationArchitectureTest extends TestCase
{
    public function test_presentation_surface_has_no_delivery_artifacts(): void
    {
        $root = dirname(__DIR__, 2).'/app/Modules/SubscriptionBilling/Presentation';

        self::assertDirectoryExists($root);
        self::assertDirectoryDoesNotExist($root.'/OpenApi');

        foreach ([
            'Controller',
            'Route',
            'FormRequest',
            'Middleware',
            'ServiceProvider',
            'Policy',
            'Gate',
        ] as $forbiddenName) {
            self::assertSame([], glob($root.'/*'.$forbiddenName.'*.php') ?: []);
        }
    }

    public function test_presentation_surface_contains_only_the_accepted_foundation_files(): void
    {
        $root = dirname(__DIR__, 2).'/app/Modules/SubscriptionBilling/Presentation';

        self::assertSame([
            $root.'/ApiVersion.php',
            $root.'/Collections/BaseCollection.php',
            $root.'/Contracts/ErrorResponseMapperInterface.php',
            $root.'/Resources/BaseResource.php',
            $root.'/Responses/BaseApiResponse.php',
            $root.'/Responses/CollectionResponse.php',
            $root.'/Responses/ProblemDetails.php',
            $root.'/Responses/ResourceResponse.php',
        ], $this->phpFilesIn($root));
    }

    public function test_presentation_surface_depends_only_on_application_and_contracts(): void
    {
        $root = dirname(__DIR__, 2).'/app/Modules/SubscriptionBilling/Presentation';

        foreach ($this->phpFilesIn($root) as $file) {
            $contents = file_get_contents($file);
            self::assertIsString($contents, $file);

            self::assertDoesNotMatchRegularExpression('/use App\\\\Modules\\\\(?!SubscriptionBilling\\\\(Application|Contracts|Presentation)\\\\)/', $contents, $file);

            foreach ([
                'Illuminate\\',
                'Laravel\\',
                'Infrastructure\\',
                'Persistence\\',
                'Database\\',
                'Eloquent',
                'Model',
                'Repository',
                'Controller',
                'Route',
                'FormRequest',
                'Middleware',
                'ServiceProvider',
                'Policy',
                'Gate',
            ] as $forbidden) {
                self::assertStringNotContainsString($forbidden, $contents, $file);
            }
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
}
