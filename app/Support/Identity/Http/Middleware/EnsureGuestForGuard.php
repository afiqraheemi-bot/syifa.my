<?php

declare(strict_types=1);

namespace App\Support\Identity\Http\Middleware;

use Closure;
use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sprint 3A Phase 4: the Guest gate for the named Guard — rejects a request
 * that already carries a valid session for that actor type, so a route
 * meant only for unauthenticated callers (e.g. a future login form
 * pre-check) cannot be reached mid-session by mistake.
 */
final readonly class EnsureGuestForGuard
{
    public function __construct(private AuthFactory $auth) {}

    public function handle(Request $request, Closure $next, string $guard): Response|JsonResponse
    {
        if ($this->auth->guard($guard)->check()) {
            return new JsonResponse([
                'type' => 'already_authenticated',
                'title' => 'Already Authenticated',
                'status' => 409,
                'detail' => 'This action is only available to unauthenticated requests.',
            ], 409, ['Content-Type' => 'application/problem+json']);
        }

        return $next($request);
    }
}
