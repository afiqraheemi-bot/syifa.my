<?php

declare(strict_types=1);

namespace App\Support\Dashboard\Presentation\Http\Controllers;

use App\Modules\TenantManagement\Application\Authentication\ChangeClinicOwnerPasswordService;
use App\Modules\TenantManagement\Application\Authentication\Exceptions\InvalidClinicOwnerPasswordException;
use App\Modules\TenantManagement\Application\Session\DeleteClinicOwnerSessionService;
use App\Modules\TenantManagement\Domain\Aggregates\Tenant\ValueObjects\ClinicOwnerAuthorityId;
use App\Modules\TenantManagement\Domain\Aggregates\Tenant\ValueObjects\TenantId;
use App\Modules\TenantManagement\Infrastructure\Authentication\ClinicOwnerAuthenticatable;
use App\Support\Authorization\Application\AuthorizationContext;
use App\Support\Dashboard\Application\ClinicOwnerDashboardNavigation;
use Illuminate\Contracts\Hashing\Hasher;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

final readonly class ClinicOwnerAccountController
{
    public function show(Request $request): Response
    {
        [$context, $owner] = $this->owner($request);

        return Inertia::render('TenantManagement/Account/ClinicOwnerAccountSettings', [
            'navigation' => ClinicOwnerDashboardNavigation::items('account'),
            'breadcrumbs' => [
                ['key' => 'dashboard', 'label' => 'Dashboard', 'href' => route('dashboard')],
                ['key' => 'account', 'label' => 'Account & Security'],
            ],
            'pageTitle' => 'Account & Security',
            'pageDescription' => 'Manage your Clinic Owner profile, password, and account access.',
            'identityName' => $owner->name,
            'contextLabel' => 'Clinic Owner workspace',
            'profile' => [
                'name' => $owner->name,
                'email' => $owner->email,
                'emailVerified' => $owner->hasVerifiedEmail(),
                'profileUpdateUrl' => route('dashboard.account.profile.update'),
                'passwordUpdateUrl' => route('dashboard.account.password.update'),
            ],
            'security' => [
                'authenticatedTenantId' => $context->tenantId,
                'forgotPasswordUrl' => route('clinic-owner.password.forgot'),
            ],
            'feedback' => [
                'status' => session('account_status'),
            ],
        ]);
    }

    public function updateProfile(Request $request): RedirectResponse
    {
        [, $owner] = $this->owner($request);
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
        ]);

        if (trim((string) $validated['name']) === '') {
            throw ValidationException::withMessages(['name' => 'The owner name is required.']);
        }

        $owner->forceFill(['name' => trim((string) $validated['name'])])->save();

        return back()->with('account_status', 'Profile updated successfully.');
    }

    public function updatePassword(
        Request $request,
        Hasher $hasher,
        ChangeClinicOwnerPasswordService $passwords,
        DeleteClinicOwnerSessionService $sessions,
    ): RedirectResponse {
        [$context, $owner] = $this->owner($request);
        $validated = $request->validate([
            'current_password' => ['required', 'string', 'max:4096'],
            'password' => ['required', 'string', 'confirmed', 'max:1024'],
        ]);

        if (! $hasher->check((string) $validated['current_password'], (string) $owner->password_hash)) {
            throw ValidationException::withMessages([
                'current_password' => 'The current password is incorrect.',
            ]);
        }

        try {
            $passwords->execute(
                new TenantId((string) $context->tenantId),
                new ClinicOwnerAuthorityId((string) $owner->id),
                (string) $validated['password'],
            );
        } catch (InvalidClinicOwnerPasswordException $exception) {
            throw ValidationException::withMessages(['password' => $exception->getMessage()]);
        }

        $sessions->execute();

        return redirect()->route('login', ['password_changed' => 'complete']);
    }

    /** @return array{AuthorizationContext, ClinicOwnerAuthenticatable} */
    private function owner(Request $request): array
    {
        $context = $request->attributes->get(AuthorizationContext::class);
        if (! $context instanceof AuthorizationContext || $context->tenantId === null) {
            abort(403);
        }

        $owner = ClinicOwnerAuthenticatable::query()
            ->where('id', $context->identityId)
            ->where('tenant_id', $context->tenantId)
            ->where('authority_status', 'active')
            ->firstOrFail();

        return [$context, $owner];
    }
}
