<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Support\Identity\CurrentUserInterface;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Handles `GET /` for the application's own entry point — distinct from a
 * tenant's public Website. Routing (see `routes/web.php`) only ever sends
 * this controller requests for the configured `app.url` host plus the
 * common local-development aliases `127.0.0.1` and `localhost`; every other
 * host keeps matching the existing, unchanged `public-website.home` route,
 * so tenant delivery and the "unknown host is a safe 404" contract are both
 * fully preserved.
 *
 * Authenticated visitors are sent to the existing `dashboard` route, which
 * already branches by role — this never decides a role itself. Unauthenticated
 * visitors receive a presentation-only Inertia page configured with the
 * existing credential boundaries. The page never asks the visitor to select
 * a role: authenticated authority remains an exclusive backend decision.
 */
final readonly class RootEntryController
{
    public function __construct(private CurrentUserInterface $currentUser) {}

    public function __invoke(): Response|RedirectResponse
    {
        if ($this->currentUser->resolve() !== null) {
            return redirect()->route('dashboard');
        }

        return Inertia::render('Shared/Authentication/LoginEntry', [
            'clinicOwnerSessionUrl' => url('/api/v1/sessions'),
            'clinicOwnerForgotPasswordUrl' => route('clinic-owner.password.forgot'),
            'platformForgotPasswordUrl' => route('platform.password.forgot'),
            'platformSessionUrl' => url('/api/v1/platform/sessions'),
            'platformMfaUrl' => route('platform-sessions.mfa'),
            'dashboardUrl' => url('/dashboard'),
            'clinicRegistrationUrl' => route('clinic-registration.browser', [], false),
            'clinicRegistrationLoginUrl' => route('clinic-registration.access.login'),
        ]);
    }

    /** @return list<string> */
    public static function appEntryHosts(): array
    {
        $configuredHost = parse_url((string) config('app.url'), PHP_URL_HOST);

        return array_values(array_unique(array_filter([
            is_string($configuredHost) ? strtolower($configuredHost) : null,
            '127.0.0.1',
            'localhost',
        ])));
    }

    /** @return list<string> */
    public static function tenantAdminBaseDomains(): array
    {
        $domains = config('tenant_management.admin_base_domains', []);

        if (! is_array($domains)) {
            return [];
        }

        return array_values(array_filter(array_map(
            static fn (mixed $domain): ?string => is_string($domain) && $domain !== ''
                ? strtolower(trim($domain))
                : null,
            $domains,
        )));
    }
}
