<?php

declare(strict_types=1);

namespace App\Support\Dashboard\Presentation\Http\Controllers;

use App\Modules\PlatformAdministration\Infrastructure\Authentication\PlatformIdentityAuthenticatable;
use App\Modules\TenantManagement\Infrastructure\Authentication\ClinicOwnerAuthenticatable;
use App\Support\Authorization\Application\AuthorizationContext;
use App\Support\Identity\ActorType;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Every actor type shares one `preferred_locale` concept but stores it on
 * its own authentication table (no aggregate governs it, same reasoning as
 * the account profile fields in ClinicOwnerAccountController::updateProfile
 * — it is presentation preference, not a business invariant).
 */
final readonly class DashboardLocalePreferenceController
{
    public function update(Request $request): RedirectResponse
    {
        $context = $request->attributes->get(AuthorizationContext::class);
        if (! $context instanceof AuthorizationContext) {
            abort(403);
        }

        $validated = $request->validate([
            'locale' => ['required', 'string', 'in:en,ms'],
        ]);
        $locale = (string) $validated['locale'];

        match ($context->actorType) {
            ActorType::ClinicOwner->value => ClinicOwnerAuthenticatable::query()
                ->where('id', $context->identityId)
                ->update(['preferred_locale' => $locale]),
            ActorType::PlatformIdentity->value => PlatformIdentityAuthenticatable::query()
                ->where('platform_identity_id', $context->identityId)
                ->update(['preferred_locale' => $locale]),
            default => abort(403),
        };

        return back();
    }
}
