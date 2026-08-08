<?php

declare(strict_types=1);

namespace App\Support\Dashboard\Presentation\Http\Middleware;

use App\Support\Authorization\Application\AuthorizationService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Keep stale cross-workspace preview tabs inside the current actor's boundary.
 *
 * A Clinic Owner may legitimately retain a Designer preview URL after changing
 * roles in the same browser. The Designer resource remains forbidden; a normal
 * document navigation is redirected to the tenant-scoped Owner equivalent.
 * JSON requests continue to the authorization middleware and remain fail-closed.
 */
final readonly class RedirectMisdirectedWebsitePreviewRequest
{
    private const CATEGORY = 'shared.authenticated-route';

    public function __construct(private AuthorizationService $authorization) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->isMethod('GET') || $request->expectsJson()) {
            return $next($request);
        }

        $context = $this->authorization->resolve(self::CATEGORY);

        if ($context?->actorType !== 'clinic_owner' || $context->role !== 'clinic_owner') {
            return $next($request);
        }

        if ($request->routeIs('dashboard.onboarding.booking-preview')) {
            return redirect()->route('dashboard.website.booking-preview');
        }

        if ($request->routeIs('dashboard.onboarding.preview')) {
            return redirect()->route('dashboard.website.preview');
        }

        return $next($request);
    }
}
