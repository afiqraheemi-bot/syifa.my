<?php

declare(strict_types=1);

namespace App\Modules\PlatformAdministration\Presentation\Http\Controllers;

use App\Modules\PlatformAdministration\Application\Authentication\LogoutPlatformSessionService;
use App\Modules\PlatformAdministration\Contracts\Authentication\PlatformPrincipal;
use App\Modules\PlatformAdministration\Contracts\Authentication\PlatformSessionAuthenticationInterface;
use App\Modules\PlatformAdministration\Presentation\Http\Requests\PlatformSessionLoginRequest;
use App\Modules\PlatformAdministration\Presentation\Http\Responses\ProblemDetailsResponse;
use DateTimeImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final readonly class PlatformSessionController
{
    public function store(
        PlatformSessionLoginRequest $request,
        PlatformSessionAuthenticationInterface $sessions,
    ): JsonResponse {
        /** @var array{email: string, password: string} $credentials */
        $credentials = $request->safe()->only(['email', 'password']);
        $principal = $sessions->authenticate(
            $credentials['email'],
            $credentials['password'],
            new DateTimeImmutable,
            $request->boolean('remember'),
        );

        if (! $principal instanceof PlatformPrincipal) {
            return ProblemDetailsResponse::make(
                $request,
                'authentication_failed',
                'Authentication Failed',
                401,
                'The supplied credentials could not be authenticated.',
            );
        }

        return response()->json([
            'data' => [
                'authenticated' => true,
                'principal' => [
                    'platform_identity_id' => $principal->platformIdentityId,
                    'role' => $principal->role,
                    'name' => $principal->name,
                ],
            ],
        ], 201);
    }

    public function show(Request $request): JsonResponse
    {
        $principal = $request->attributes->get('platform_principal');

        if (! $principal instanceof PlatformPrincipal) {
            return ProblemDetailsResponse::make(
                $request,
                'session_invalid',
                'Session Invalid',
                401,
                'The current platform session is not valid.',
            );
        }

        return response()->json([
            'data' => [
                'authenticated' => true,
                'principal' => [
                    'platform_identity_id' => $principal->platformIdentityId,
                    'role' => $principal->role,
                    'name' => $principal->name,
                ],
            ],
        ]);
    }

    public function destroy(LogoutPlatformSessionService $sessions): Response
    {
        $sessions->execute();

        return response()->noContent();
    }
}
