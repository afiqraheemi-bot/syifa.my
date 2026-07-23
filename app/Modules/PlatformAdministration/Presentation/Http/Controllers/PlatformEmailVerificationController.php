<?php

declare(strict_types=1);

namespace App\Modules\PlatformAdministration\Presentation\Http\Controllers;

use App\Modules\PlatformAdministration\Application\EmailVerification\SendPlatformEmailVerificationNotificationService;
use App\Modules\PlatformAdministration\Application\EmailVerification\VerifyPlatformEmailService;
use App\Modules\PlatformAdministration\Presentation\Http\Responses\ProblemDetailsResponse;
use DateTimeImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final readonly class PlatformEmailVerificationController
{
    public function verify(
        Request $request,
        VerifyPlatformEmailService $verification,
        string $id,
        string $hash,
    ): JsonResponse {
        $verified = $verification->execute($id, $hash, new DateTimeImmutable);

        if (! $verified) {
            return ProblemDetailsResponse::make(
                $request,
                'email_verification_failed',
                'Email Verification Failed',
                422,
                'This verification link is invalid or has expired.',
            );
        }

        return response()->json(['data' => ['verified' => true]]);
    }

    public function resend(
        Request $request,
        SendPlatformEmailVerificationNotificationService $notifications,
    ): JsonResponse {
        $sent = $notifications->execute(new DateTimeImmutable);

        if (! $sent) {
            return ProblemDetailsResponse::make(
                $request,
                'session_invalid',
                'Session Invalid',
                401,
                'The current platform session is not valid.',
            );
        }

        return response()->json(['data' => ['sent' => true]]);
    }
}
