<?php

declare(strict_types=1);

namespace App\Modules\PlatformAdministration\Presentation\Http\Controllers;

use App\Modules\PlatformAdministration\Application\PasswordConfirmation\ConfirmPlatformPasswordService;
use App\Modules\PlatformAdministration\Presentation\Http\Requests\PlatformConfirmPasswordRequest;
use App\Modules\PlatformAdministration\Presentation\Http\Responses\ProblemDetailsResponse;
use DateTimeImmutable;
use Illuminate\Http\JsonResponse;

final readonly class PlatformPasswordConfirmationController
{
    public function confirm(
        PlatformConfirmPasswordRequest $request,
        ConfirmPlatformPasswordService $confirmation,
    ): JsonResponse {
        /** @var array{password: string} $input */
        $input = $request->safe()->only(['password']);
        $confirmed = $confirmation->execute($input['password'], new DateTimeImmutable);

        if (! $confirmed) {
            return ProblemDetailsResponse::make(
                $request,
                'password_confirmation_failed',
                'Password Confirmation Failed',
                422,
                'The supplied password could not be confirmed.',
            );
        }

        return response()->json(['data' => ['confirmed' => true]]);
    }
}
