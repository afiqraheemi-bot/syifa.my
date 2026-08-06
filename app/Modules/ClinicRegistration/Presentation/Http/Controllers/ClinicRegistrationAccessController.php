<?php

declare(strict_types=1);

namespace App\Modules\ClinicRegistration\Presentation\Http\Controllers;

use App\Modules\ClinicRegistration\Application\ViewCurrentClinicRegistrationService;
use App\Modules\ClinicRegistration\Contracts\Authentication\ClinicRegistrationAccessInterface;
use App\Modules\ClinicRegistration\Contracts\Authentication\ClinicRegistrationLoginInterface;
use App\Modules\ClinicRegistration\Contracts\Tracking\RegistrationTrackingCredentialInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use RuntimeException;

final readonly class ClinicRegistrationAccessController
{
    public function configure(
        Request $request,
        RegistrationTrackingCredentialInterface $tracking,
        ViewCurrentClinicRegistrationService $registrations,
        ClinicRegistrationAccessInterface $access,
    ): JsonResponse {
        $validated = $request->validate([
            'password' => ['required', 'string', 'min:12', 'confirmed'],
        ]);
        $credential = $tracking->current();
        abort_if($credential === null, 404);
        $registration = $registrations->execute($credential);
        abort_if($registration === null || $registration->clinicEmail === null, 404);

        if ($access->configured($registration->id)) {
            return response()->json([
                'message' => 'Login permohonan telah dikonfigurasi.',
            ], 409);
        }

        try {
            $access->configure($registration->id, $registration->clinicEmail, $validated['password']);
        } catch (RuntimeException) {
            return response()->json([
                'message' => 'Login permohonan tidak dapat dikonfigurasi.',
            ], 409);
        }

        return response()->json(['data' => ['configured' => true]], 201);
    }

    public function login(
        Request $request,
        ClinicRegistrationLoginInterface $login,
    ): JsonResponse {
        $validated = $request->validate([
            'email' => ['required', 'string', 'email', 'max:254'],
            'password' => ['required', 'string', 'max:255'],
            'remember' => ['sometimes', 'boolean'],
        ]);
        $result = $login->execute(
            $validated['email'],
            $validated['password'],
            $request->boolean('remember'),
        );
        if (! $result->authenticated) {
            return response()->json([
                'message' => 'Permohonan tidak dapat disahkan.',
            ], 401);
        }

        return response()->json([
            'data' => [
                'authenticated' => true,
                'redirect' => $result->clinicOwner
                    ? route('dashboard')
                    : route('clinic-registration.browser'),
            ],
        ]);
    }

    public function logout(RegistrationTrackingCredentialInterface $tracking): RedirectResponse
    {
        $tracking->forget();

        return redirect()->route('root');
    }
}
