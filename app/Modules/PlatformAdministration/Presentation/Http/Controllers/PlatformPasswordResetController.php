<?php

declare(strict_types=1);

namespace App\Modules\PlatformAdministration\Presentation\Http\Controllers;

use App\Modules\PlatformAdministration\Application\PasswordReset\RequestPlatformPasswordResetService;
use App\Modules\PlatformAdministration\Application\PasswordReset\ResetPlatformPasswordService;
use App\Modules\PlatformAdministration\Presentation\Http\Requests\PlatformForgotPasswordRequest;
use App\Modules\PlatformAdministration\Presentation\Http\Requests\PlatformResetPasswordRequest;
use App\Modules\PlatformAdministration\Presentation\Http\Responses\ProblemDetailsResponse;
use DateTimeImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * `forgotPassword`/`resetPassword` always return the same shape regardless of
 * whether the email exists or the token is valid — email enumeration is
 * prevented at the HTTP boundary, never left to the caller to infer from a
 * differing response. `show` is presentation-only: the token is opaque to
 * this controller and is verified only when the form is actually submitted.
 */
final readonly class PlatformPasswordResetController
{
    public function show(string $token, Request $request): Response
    {
        return Inertia::render('PlatformAdministration/Authentication/PlatformPasswordReset', [
            'token' => $token,
            'email' => (string) $request->query('email', ''),
            'submitUrl' => route('platform.password.reset.submit'),
            'loginUrl' => route('login'),
        ]);
    }

    public function forgotPassword(
        PlatformForgotPasswordRequest $request,
        RequestPlatformPasswordResetService $resets,
    ): JsonResponse {
        /** @var array{email: string} $input */
        $input = $request->safe()->only(['email']);
        $resets->execute($input['email'], new DateTimeImmutable);

        return response()->json([
            'data' => [
                'acknowledged' => true,
            ],
        ]);
    }

    public function resetPassword(
        PlatformResetPasswordRequest $request,
        ResetPlatformPasswordService $resets,
    ): JsonResponse {
        /** @var array{email: string, token: string, password: string} $input */
        $input = $request->safe()->only(['email', 'token', 'password']);
        $succeeded = $resets->execute($input['email'], $input['token'], $input['password'], new DateTimeImmutable);

        if (! $succeeded) {
            return ProblemDetailsResponse::make(
                $request,
                'password_reset_failed',
                'Password Reset Failed',
                422,
                'The password reset could not be completed. Request a new reset link and try again.',
            );
        }

        return response()->json([
            'data' => [
                'reset' => true,
            ],
        ]);
    }
}
