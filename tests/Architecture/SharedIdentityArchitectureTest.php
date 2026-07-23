<?php

declare(strict_types=1);

namespace Tests\Architecture;

use App\Support\Identity\AuthenticatedIdentity;
use App\Support\Identity\AuthenticatedIdentityInterface;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * Sprint 3A Phase 2/6: `app/Support/Identity` is the one shared boundary
 * every actor type's identity is normalized through. The Contracts and the
 * value object they return must stay framework-agnostic and immutable —
 * only the concrete resolver and its service provider are allowed to know
 * about Illuminate or either module's Infrastructure.
 */
final class SharedIdentityArchitectureTest extends TestCase
{
    private const string DIRECTORY = __DIR__.'/../../app/Support/Identity';

    public function test_contracts_and_value_objects_have_no_framework_dependency(): void
    {
        foreach ([
            'AuthenticatedIdentityInterface.php',
            'AuthenticatedIdentity.php',
            'ActorType.php',
            'CurrentUserInterface.php',
            'TenantResolverInterface.php',
            'RoleResolverInterface.php',
            'PermissionResolverInterface.php',
        ] as $file) {
            $path = self::DIRECTORY.'/'.$file;
            self::assertFileExists($path);
            $contents = file_get_contents($path);
            self::assertIsString($contents);
            self::assertStringNotContainsString('Illuminate\\', $contents, $file);
            self::assertStringNotContainsString('Eloquent', $contents, $file);
            self::assertStringNotContainsString('App\\Modules\\', $contents, $file);
        }
    }

    public function test_authenticated_identity_is_immutable_and_exposes_only_the_approved_shape(): void
    {
        $reflection = new ReflectionClass(AuthenticatedIdentity::class);
        self::assertTrue($reflection->isReadOnly());
        self::assertTrue($reflection->implementsInterface(AuthenticatedIdentityInterface::class));

        $parameters = array_map(
            static fn (\ReflectionParameter $parameter): string => $parameter->getName(),
            $reflection->getConstructor()?->getParameters() ?? [],
        );
        self::assertSame(['actorType', 'identityId', 'tenantId', 'role', 'name'], $parameters);
    }

    public function test_only_the_resolver_and_provider_depend_on_illuminate_or_module_infrastructure(): void
    {
        $frameworkAware = ['CurrentUserResolver.php', 'IdentityServiceProvider.php'];

        foreach (glob(self::DIRECTORY.'/*.php') ?: [] as $path) {
            $basename = basename($path);
            if (in_array($basename, $frameworkAware, true)) {
                continue;
            }

            $contents = file_get_contents($path);
            self::assertIsString($contents);
            self::assertStringNotContainsString('Illuminate\\', $contents, $basename);
        }
    }

    public function test_no_eloquent_model_or_new_module_was_introduced(): void
    {
        self::assertDirectoryDoesNotExist(dirname(__DIR__, 2).'/app/Modules/Identity');

        $modelSubclasses = array_filter(
            glob(self::DIRECTORY.'/*.php') ?: [],
            static fn (string $path): bool => str_contains(file_get_contents($path) ?: '', 'extends Model'),
        );
        self::assertSame([], array_values($modelSubclasses));
    }
}
