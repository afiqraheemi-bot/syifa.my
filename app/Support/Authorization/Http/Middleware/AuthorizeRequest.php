<?php

declare(strict_types=1);

namespace App\Support\Authorization\Http\Middleware;

use App\Support\Authorization\Application\AuthorizationContext;
use App\Support\Authorization\Application\AuthorizationService;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Shared route guard for authenticated actors.
 *
 * Authentication middleware must run first. This middleware derives one
 * immutable AuthorizationContext and makes its decision from that context
 * alone; routes and controllers never resolve roles or permissions.
 */
final readonly class AuthorizeRequest
{
    private const CATEGORY = 'shared.authenticated-route';

    private const ANY_AUTHENTICATED_ACTOR = 'authenticated';

    public function __construct(private AuthorizationService $authorization) {}

    public function handle(
        Request $request,
        Closure $next,
        string $actorType,
        string ...$allowedRoles,
    ): Response|JsonResponse|RedirectResponse {
        $context = $this->authorization->resolve(self::CATEGORY);

        if (! $this->allows($context, $actorType, array_values($allowedRoles))) {
            if ($context === null && ! $request->expectsJson()) {
                return new RedirectResponse('/');
            }

            return new JsonResponse([
                'type' => 'forbidden',
                'title' => 'Forbidden',
                'status' => 403,
                'detail' => 'The current identity is not authorized for this action.',
            ], 403, ['Content-Type' => 'application/problem+json']);
        }

        $request->attributes->set(AuthorizationContext::class, $context);

        return $next($request);
    }

    /**
     * @param  list<string>  $allowedRoles
     */
    private function allows(
        ?AuthorizationContext $context,
        string $actorType,
        array $allowedRoles,
    ): bool {
        return $context !== null
            && ($actorType === self::ANY_AUTHENTICATED_ACTOR || $context->actorType === $actorType)
            && $allowedRoles !== []
            && in_array($context->role, $allowedRoles, true);
    }
}
