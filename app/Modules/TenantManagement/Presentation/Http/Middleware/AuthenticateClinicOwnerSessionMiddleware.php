<?php

declare(strict_types=1);

namespace App\Modules\TenantManagement\Presentation\Http\Middleware;

use App\Modules\TenantManagement\Application\Session\GetCurrentClinicOwnerSessionService;
use App\Modules\TenantManagement\Contracts\Session\AuthenticatedClinicOwnerSessionData;
use App\Modules\TenantManagement\Presentation\Http\Responses\ProblemDetailsResponse;
use Closure;
use DateTimeImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sprint 3A Phase 4: the one place a Clinic Owner route resolves and
 * validates the current session — mirrors
 * `AuthenticatePlatformSessionMiddleware` so both actor types reach their
 * Controllers the same way. The Controller reads the already-resolved
 * session from the request attribute instead of calling
 * `GetCurrentClinicOwnerSessionService` itself, so no Controller ever
 * re-implements this check.
 */
final readonly class AuthenticateClinicOwnerSessionMiddleware
{
    public function __construct(private GetCurrentClinicOwnerSessionService $sessions) {}

    public function handle(Request $request, Closure $next): Response|JsonResponse
    {
        $session = $this->sessions->execute(new DateTimeImmutable);

        if (! $session instanceof AuthenticatedClinicOwnerSessionData) {
            return ProblemDetailsResponse::make(
                $request,
                'session_invalid',
                'Session Invalid',
                401,
                'The current session is not valid.',
            );
        }

        $request->attributes->set('clinic_owner_session', $session);

        return $next($request);
    }
}
