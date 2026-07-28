<?php

declare(strict_types=1);

namespace App\Modules\TenantManagement\Presentation\Http\Controllers;

use App\Modules\TenantManagement\Application\Authentication\ActivateClinicOwnerAccessService;
use App\Modules\TenantManagement\Contracts\Administration\ClinicOwnerSetupTokenVerifierInterface;
use DateTimeImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

final readonly class ClinicOwnerSetupController
{
    public function show(string $token, Request $request): Response
    {
        return Inertia::render('TenantManagement/Authentication/ClinicOwnerSetup', [
            'token' => $token,
            'email' => (string) $request->query('email', ''),
            'submitUrl' => route('clinic-owner.setup.complete'),
            'loginUrl' => route('root'),
        ]);
    }

    public function complete(
        Request $request,
        ClinicOwnerSetupTokenVerifierInterface $tokens,
        ActivateClinicOwnerAccessService $access,
    ): RedirectResponse {
        $validated = $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email:rfc', 'max:254'],
            'password' => ['required', 'string', 'confirmed', 'max:4096'],
        ]);

        try {
            $credential = $tokens->resolve((string) $validated['email'], (string) $validated['token']);
            if ($credential === null) {
                return back()->withErrors(['email' => 'This setup link is invalid or has expired.']);
            }
            $access->execute(
                $credential->tenantId,
                $credential->authorityId,
                (string) $validated['password'],
                new DateTimeImmutable,
            );
            $tokens->consume($credential);
        } catch (Throwable) {
            return back()->withErrors([
                'email' => 'Clinic Owner access could not be activated. Request a new setup link.',
            ]);
        }

        return redirect()->route('root', ['clinic_owner_setup' => 'complete']);
    }
}
