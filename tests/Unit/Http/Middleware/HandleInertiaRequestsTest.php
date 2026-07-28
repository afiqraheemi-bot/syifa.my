<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Middleware;

use App\Http\Middleware\HandleInertiaRequests;
use App\Support\Identity\ActorType;
use App\Support\Identity\AuthenticatedIdentity;
use App\Support\Identity\AuthenticatedIdentityInterface;
use App\Support\Identity\CurrentUserInterface;
use Illuminate\Http\Request;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class HandleInertiaRequestsTest extends TestCase
{
    #[DataProvider('actors')]
    public function test_logout_endpoint_is_supplied_from_server_side_identity(
        ActorType $actorType,
        string $role,
        ?string $tenantId,
        string $expectedPath,
    ): void {
        $identity = new AuthenticatedIdentity(
            $actorType,
            'identity-1',
            $tenantId,
            $role,
            'Test User',
        );
        $this->app->instance(
            CurrentUserInterface::class,
            new class($identity) implements CurrentUserInterface
            {
                public function __construct(
                    private readonly AuthenticatedIdentityInterface $identity,
                ) {}

                public function resolve(): ?AuthenticatedIdentityInterface
                {
                    return $this->identity;
                }
            },
        );

        $shared = $this->app->make(HandleInertiaRequests::class)->share(Request::create('/dashboard'));
        $authentication = $shared['authentication'];

        self::assertIsCallable($authentication);
        self::assertSame(url($expectedPath), $authentication()['logout_url']);
    }

    /** @return iterable<string, array{ActorType, string, ?string, string}> */
    public static function actors(): iterable
    {
        yield 'clinic owner' => [
            ActorType::ClinicOwner,
            'clinic_owner',
            'tenant-1',
            '/api/v1/sessions/current',
        ];
        yield 'website designer' => [
            ActorType::PlatformIdentity,
            'website_designer',
            null,
            '/api/v1/platform/sessions/current',
        ];
        yield 'super admin' => [
            ActorType::PlatformIdentity,
            'super_admin',
            null,
            '/api/v1/platform/sessions/current',
        ];
    }
}
