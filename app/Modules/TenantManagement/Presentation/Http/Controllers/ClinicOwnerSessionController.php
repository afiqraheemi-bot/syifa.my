<?php

declare(strict_types=1);

namespace App\Modules\TenantManagement\Presentation\Http\Controllers;

use App\Modules\TenantManagement\Application\Session\CreateClinicOwnerSessionService;
use App\Modules\TenantManagement\Application\Session\DeleteClinicOwnerSessionService;
use App\Modules\TenantManagement\Contracts\Authentication\CentralClinicOwnerLoginSelectorInterface;
use App\Modules\TenantManagement\Contracts\Session\AuthenticatedClinicOwnerSessionData;
use App\Modules\TenantManagement\Presentation\Http\Requests\CreateClinicOwnerSessionRequest;
use App\Modules\TenantManagement\Presentation\Http\Resources\ClinicOwnerSessionResource;
use App\Modules\TenantManagement\Presentation\Http\Responses\ProblemDetailsResponse;
use DateTimeImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final readonly class ClinicOwnerSessionController
{
    public function store(
        CreateClinicOwnerSessionRequest $request,
        CreateClinicOwnerSessionService $sessions,
        CentralClinicOwnerLoginSelectorInterface $centralLoginSelector,
    ): JsonResponse {
        /** @var array{email: string, password: string} $credentials */
        $credentials = $request->safe()->only(['email', 'password']);
        $trustedSelector = $request->getHost();
        if (in_array($trustedSelector, config('tenant_management.central_login_hosts', []), true)) {
            $trustedSelector = $centralLoginSelector->selectorFor($credentials['email'])
                ?? $trustedSelector;
        }

        $session = $sessions->execute(
            $trustedSelector,
            $credentials['email'],
            $credentials['password'],
            new DateTimeImmutable,
            $request->boolean('remember'),
        );

        if ($session === null) {
            return ProblemDetailsResponse::make(
                $request,
                'authentication_failed',
                'Authentication Failed',
                401,
                'The supplied credentials could not be authenticated.',
            );
        }

        return (new ClinicOwnerSessionResource($session))->response()->setStatusCode(201);
    }

    public function show(Request $request): JsonResponse
    {
        $session = $request->attributes->get('clinic_owner_session');

        if (! $session instanceof AuthenticatedClinicOwnerSessionData) {
            return ProblemDetailsResponse::make(
                $request,
                'session_invalid',
                'Session Invalid',
                401,
                'The current session is not valid.',
            );
        }

        return (new ClinicOwnerSessionResource($session))->response();
    }

    public function destroy(DeleteClinicOwnerSessionService $sessions): Response
    {
        $sessions->execute();

        return response()->noContent();
    }
}
