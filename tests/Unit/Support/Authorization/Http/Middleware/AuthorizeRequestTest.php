<?php

declare(strict_types=1);

namespace Tests\Unit\Support\Authorization\Http\Middleware;

use App\Support\Authorization\Application\AuthorizationContext;
use App\Support\Authorization\Application\AuthorizationService;
use App\Support\Authorization\Http\Middleware\AuthorizeRequest;
use App\Support\Identity\ActorType;
use App\Support\Identity\AuthenticatedIdentity;
use App\Support\Identity\AuthenticatedIdentityInterface;
use App\Support\Identity\CurrentUserInterface;
use App\Support\Identity\PermissionResolverInterface;
use App\Support\Identity\RoleResolverInterface;
use Illuminate\Http\Request;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Response;

final class AuthorizeRequestTest extends TestCase
{
    public function test_matching_authenticated_context_may_continue(): void
    {
        $middleware = new AuthorizeRequest($this->authorization(
            ActorType::PlatformIdentity,
            'website_designer',
        ));
        $request = Request::create('/protected');

        $response = $middleware->handle(
            $request,
            static fn (): Response => new Response('allowed', 200),
            'platform_identity',
            'super_admin',
            'website_designer',
        );

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('allowed', $response->getContent());
        self::assertInstanceOf(
            AuthorizationContext::class,
            $request->attributes->get(AuthorizationContext::class),
        );
    }

    public function test_cross_role_access_is_denied(): void
    {
        $middleware = new AuthorizeRequest($this->authorization(
            ActorType::PlatformIdentity,
            'website_designer',
        ));

        $response = $middleware->handle(
            Request::create('/support'),
            static fn (): Response => new Response('must not run'),
            'platform_identity',
            'super_admin',
        );

        self::assertSame(403, $response->getStatusCode());
        self::assertStringContainsString('application/problem+json', (string) $response->headers->get('Content-Type'));
    }

    public function test_same_role_on_wrong_actor_type_is_denied(): void
    {
        $middleware = new AuthorizeRequest($this->authorization(
            ActorType::ClinicOwner,
            'clinic_owner',
        ));

        $response = $middleware->handle(
            Request::create('/platform'),
            static fn (): Response => new Response('must not run'),
            'platform_identity',
            'clinic_owner',
        );

        self::assertSame(403, $response->getStatusCode());
    }

    public function test_shared_authenticated_actor_mode_accepts_each_pre_authorized_actor(): void
    {
        foreach ([
            [ActorType::ClinicOwner, 'clinic_owner'],
            [ActorType::PlatformIdentity, 'website_designer'],
            [ActorType::PlatformIdentity, 'super_admin'],
        ] as [$actorType, $role]) {
            $middleware = new AuthorizeRequest($this->authorization($actorType, $role));

            $response = $middleware->handle(
                Request::create('/dashboard'),
                static fn (): Response => new Response('allowed'),
                'authenticated',
                'clinic_owner',
                'website_designer',
                'super_admin',
            );

            self::assertSame(200, $response->getStatusCode());
        }
    }

    public function test_missing_authenticated_context_fails_closed(): void
    {
        $middleware = new AuthorizeRequest($this->authorization());

        $response = $middleware->handle(
            Request::create('/protected'),
            static fn (): Response => new Response('must not run'),
            'platform_identity',
            'super_admin',
        );

        self::assertSame(403, $response->getStatusCode());
    }

    private function authorization(
        ?ActorType $actorType = null,
        ?string $role = null,
    ): AuthorizationService {
        $identity = $actorType === null || $role === null
            ? null
            : new AuthenticatedIdentity($actorType, 'identity-1', null, $role, 'Test User');

        return new AuthorizationService(
            new class($identity) implements CurrentUserInterface
            {
                public function __construct(private readonly ?AuthenticatedIdentityInterface $identity) {}

                public function resolve(): ?AuthenticatedIdentityInterface
                {
                    return $this->identity;
                }
            },
            new class($role) implements RoleResolverInterface
            {
                public function __construct(private readonly ?string $role) {}

                public function currentRole(): ?string
                {
                    return $this->role;
                }
            },
            new class implements PermissionResolverInterface
            {
                public function can(string $categoryKey, string $permissionKey): bool
                {
                    return false;
                }
            },
        );
    }
}
