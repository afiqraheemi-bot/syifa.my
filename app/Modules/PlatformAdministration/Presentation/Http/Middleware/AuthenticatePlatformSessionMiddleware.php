<?php

declare(strict_types=1);

namespace App\Modules\PlatformAdministration\Presentation\Http\Middleware;

use App\Modules\PlatformAdministration\Contracts\Authentication\PlatformPrincipal;
use App\Modules\PlatformAdministration\Contracts\Authentication\PlatformPrincipalResolverInterface;
use App\Modules\PlatformAdministration\Presentation\Http\Responses\ProblemDetailsResponse;
use Closure;
use DateTimeImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final readonly class AuthenticatePlatformSessionMiddleware
{
    public function __construct(private PlatformPrincipalResolverInterface $principals) {}

    public function handle(Request $request, Closure $next): Response|JsonResponse
    {
        $principal = $this->principals->resolve(new DateTimeImmutable);

        if (! $principal instanceof PlatformPrincipal) {
            return ProblemDetailsResponse::make(
                $request,
                'session_invalid',
                'Session Invalid',
                401,
                'The current platform session is not valid.',
            );
        }

        $request->attributes->set('platform_principal', $principal);

        return $next($request);
    }
}
