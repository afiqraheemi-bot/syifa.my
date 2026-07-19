<?php

declare(strict_types=1);

namespace Tests\Architecture;

use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

final class PlatformAdministrationSessionAuthenticationArchitectureTest extends TestCase
{
    public function test_session_authentication_surface_exists_only_in_the_platform_administration_namespaces(): void
    {
        $root = dirname(__DIR__, 2).'/app/Modules/PlatformAdministration';

        foreach ([
            $root.'/Application/Authentication/AuthenticatePlatformSessionService.php',
            $root.'/Application/Authentication/LogoutPlatformSessionService.php',
            $root.'/Application/Authentication/PlatformPrincipalResolver.php',
            $root.'/Contracts/Authentication/PlatformPrincipal.php',
            $root.'/Contracts/Authentication/PlatformPrincipalResolverInterface.php',
            $root.'/Contracts/Authentication/PlatformSessionAuthenticationInterface.php',
            $root.'/Contracts/Authentication/PlatformSessionState.php',
            $root.'/Contracts/Authentication/PlatformSessionStoreInterface.php',
            $root.'/Infrastructure/Session/LaravelPlatformSessionStore.php',
            $root.'/Presentation/Http/Controllers/PlatformSessionController.php',
            $root.'/Presentation/Http/Middleware/AuthenticatePlatformSessionMiddleware.php',
            $root.'/Presentation/Http/Requests/PlatformSessionLoginRequest.php',
            $root.'/Presentation/Http/Responses/ProblemDetailsResponse.php',
        ] as $file) {
            self::assertFileExists($file);
        }

        self::assertSame(
            [
                $root.'/Application/Authentication/AuthenticatePlatformSessionService.php',
                $root.'/Application/Authentication/LogoutPlatformSessionService.php',
                $root.'/Application/Authentication/PlatformPrincipalResolver.php',
                $root.'/Contracts/Authentication/PlatformPrincipal.php',
                $root.'/Contracts/Authentication/PlatformPrincipalResolverInterface.php',
                $root.'/Contracts/Authentication/PlatformSessionAuthenticationInterface.php',
                $root.'/Contracts/Authentication/PlatformSessionState.php',
                $root.'/Contracts/Authentication/PlatformSessionStoreInterface.php',
                $root.'/Infrastructure/Session/LaravelPlatformSessionStore.php',
                $root.'/Presentation/Http/Controllers/PlatformSessionController.php',
                $root.'/Presentation/Http/Middleware/AuthenticatePlatformSessionMiddleware.php',
                $root.'/Presentation/Http/Requests/PlatformSessionLoginRequest.php',
                $root.'/Presentation/Http/Responses/ProblemDetailsResponse.php',
            ],
            $this->phpFilesIn($root.'/Application/Authentication', $root.'/Contracts/Authentication', $root.'/Infrastructure/Session', $root.'/Presentation/Http'),
        );
    }

    public function test_session_authentication_surface_is_framework_free_in_the_application_layer_and_has_no_authorization_coupling(): void
    {
        foreach ($this->phpFilesIn(
            dirname(__DIR__, 2).'/app/Modules/PlatformAdministration/Application/Authentication',
        ) as $file) {
            $source = file_get_contents($file);
            self::assertIsString($source, $file);
            foreach (['Controller', 'Request', 'Route::', 'Http\\', 'Sanctum', 'Passport', 'JWT', 'OAuth', 'AuthorizationInterface', 'actorPlatformIdentityId'] as $forbidden) {
                self::assertStringNotContainsString($forbidden, $source, $file);
            }
            self::assertDoesNotMatchRegularExpression(
                '/use App\\\\Modules\\\\(?!PlatformAdministration\\\\(Application|Contracts|Domain)\\\\)/',
                $source,
                $file,
            );
        }
    }

    public function test_presentation_surface_only_depends_on_application_and_contracts(): void
    {
        foreach ($this->phpFilesIn(dirname(__DIR__, 2).'/app/Modules/PlatformAdministration/Presentation/Http') as $file) {
            $source = file_get_contents($file);
            self::assertIsString($source, $file);
            foreach (['Infrastructure', 'Persistence', 'Database', 'Eloquent', 'Repository', 'AuthorizationInterface', 'actorPlatformIdentityId'] as $forbidden) {
                self::assertStringNotContainsString($forbidden, $source, $file);
            }
            self::assertDoesNotMatchRegularExpression(
                '/use App\\\\Modules\\\\(?!PlatformAdministration\\\\(Application|Contracts|Presentation)\\\\)/',
                $source,
                $file,
            );
        }
    }

    /**
     * @return list<string>
     */
    private function phpFilesIn(string ...$paths): array
    {
        $files = [];

        foreach ($paths as $path) {
            if (! is_dir($path)) {
                continue;
            }

            $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path));

            /** @var SplFileInfo $file */
            foreach ($iterator as $file) {
                if (! $file->isFile() || $file->getExtension() !== 'php') {
                    continue;
                }

                $files[] = $file->getPathname();
            }
        }

        sort($files);

        return $files;
    }
}
