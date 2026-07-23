<?php

declare(strict_types=1);

namespace Tests\Architecture;

use App\Support\Authorization\Application\AuthorizationContext;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionClass;

final class SharedAuthorizationArchitectureTest extends TestCase
{
    public function test_authorization_context_is_immutable_and_framework_free(): void
    {
        $reflection = new ReflectionClass(AuthorizationContext::class);
        self::assertTrue($reflection->isReadOnly());

        $filename = $reflection->getFileName();
        self::assertIsString($filename);
        $source = file_get_contents($filename);
        self::assertIsString($source);
        self::assertStringNotContainsString('Illuminate\\', $source);
        self::assertStringNotContainsString('Eloquent', $source);
    }

    public function test_controllers_never_resolve_roles_or_permissions_directly(): void
    {
        $source = $this->phpSource(dirname(__DIR__, 2).'/app', '/Presentation/Http/Controllers/');

        foreach ([
            'PermissionResolverInterface',
            'RoleResolverInterface',
            'AuthorizationService',
            'AuthenticatedPermissionResolver',
            'AuthenticatedRoleResolver',
            '->currentRole(',
            '->can(',
        ] as $forbidden) {
            self::assertStringNotContainsString($forbidden, $source);
        }
    }

    public function test_middleware_and_policies_consume_only_the_authorization_service(): void
    {
        foreach ([
            dirname(__DIR__, 2).'/app/Support/Authorization/Http/Middleware',
            dirname(__DIR__, 2).'/app/Support/Authorization/Policies',
        ] as $directory) {
            $source = $this->phpSource($directory);

            self::assertStringContainsString('AuthorizationService', $source);
            self::assertStringNotContainsString('PermissionResolverInterface', $source);
            self::assertStringNotContainsString('RoleResolverInterface', $source);
            self::assertStringNotContainsString('CurrentUserInterface', $source);
            self::assertStringNotContainsString('Illuminate\\Database\\Eloquent', $source);
        }
    }

    public function test_authenticated_routes_compose_authentication_before_authorization(): void
    {
        $routes = (string) file_get_contents(dirname(__DIR__, 2).'/routes/web.php');

        self::assertMatchesRegularExpression(
            "/AuthenticateClinicOwnerSessionMiddleware::class,\\s*\\R\\s*['\"]authorize\\.context:clinic_owner,clinic_owner/",
            $routes,
        );
        self::assertMatchesRegularExpression(
            "/AuthenticatePlatformSessionMiddleware::class,\\s*\\R\\s*['\"]authorize\\.context:platform_identity,super_admin,website_designer/",
            $routes,
        );
        self::assertSame(
            1,
            substr_count($routes, "'authorize.context:platform_identity,super_admin,website_designer'"),
        );
    }

    public function test_shared_authorization_exposes_no_eloquent_models(): void
    {
        $source = $this->phpSource(dirname(__DIR__, 2).'/app/Support/Authorization');

        self::assertStringNotContainsString('Illuminate\\Database\\Eloquent', $source);
        self::assertStringNotContainsString('extends Model', $source);
    }

    private function phpSource(string $directory, ?string $pathContains = null): string
    {
        $source = '';
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory));
        foreach ($iterator as $file) {
            $path = $file->getPathname();
            if (! $file->isFile() || $file->getExtension() !== 'php' || ($pathContains !== null && ! str_contains($path, $pathContains))) {
                continue;
            }

            $source .= (string) file_get_contents($path);
        }

        return $source;
    }
}
