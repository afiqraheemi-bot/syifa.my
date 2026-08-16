<?php

declare(strict_types=1);

namespace App\Modules\PlatformAdministration\Presentation\Http\Controllers;

use App\Modules\PlatformAdministration\Application\Authentication\LogoutPlatformSessionService;
use App\Modules\PlatformAdministration\Contracts\Authentication\PlatformMfaChallengeInterface;
use App\Modules\PlatformAdministration\Contracts\Authentication\PlatformPrincipal;
use App\Modules\PlatformAdministration\Contracts\Authentication\PlatformSessionAuthenticationInterface;
use App\Modules\PlatformAdministration\Presentation\Http\Requests\PlatformMfaChallengeRequest;
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
        PlatformMfaChallengeInterface $mfa,
    ): JsonResponse {
        /** @var array{email: string, password: string} $credentials */
        $credentials = $request->safe()->only(['email', 'password']);
        $mfaEnabled = (bool) config('platform_administration.mfa.enabled', false);
        $principal = $sessions->authenticate(
            $credentials['email'],
            $credentials['password'],
            new DateTimeImmutable,
            $request->boolean('remember'),
            ! $mfaEnabled,
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

        if (! $mfaEnabled) {
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

        $challenge = $mfa->begin(
            $principal,
            mb_strtolower(trim($credentials['email'])),
            $request->boolean('remember'),
            new DateTimeImmutable,
        );

        return response()->json([
            'data' => [
                'authenticated' => false,
                'state' => $challenge->state,
                'setup_key' => $challenge->setupKey,
                'provisioning_uri' => $challenge->provisioningUri,
                'csrf_token' => $request->session()->token(),
            ],
        ], 202);
    }

    public function completeMfa(
        PlatformMfaChallengeRequest $request,
        PlatformMfaChallengeInterface $mfa,
    ): JsonResponse {
        $principal = $mfa->complete((string) $request->validated('code'), new DateTimeImmutable);

        if (! $principal instanceof PlatformPrincipal) {
            return ProblemDetailsResponse::make(
                $request,
                'mfa_authentication_failed',
                'Authentication Failed',
                401,
                'The supplied authentication code could not be verified.',
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
