<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Support\Identity\CurrentUserInterface;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Handles `GET /` for the application's own entry point once no session is
 * present — the public marketing page. Routing (see `routes/web.php`) only
 * ever sends this controller requests for the app's own recognized hosts;
 * every other host keeps matching the unchanged `public-website.home` route.
 *
 * Authenticated visitors are sent to the existing `dashboard` route, which
 * already branches by role — this never decides a role itself. The `login`
 * route (see `RootEntryController`) remains the actual sign-in experience.
 */
final readonly class MarketingHomeController
{
    public function __construct(private CurrentUserInterface $currentUser) {}

    public function __invoke(Request $request): Response|RedirectResponse
    {
        if ($this->currentUser->resolve() !== null) {
            return redirect()->route('dashboard');
        }

        return Inertia::render('Shared/Marketing/HomePage', [
            'loginUrl' => route('login', [], false),
            'clinicRegistrationUrl' => route('clinic-registration.browser', [], false),
            'privacyUrl' => route('public-website.privacy', [], false),
            'termsUrl' => route('public-website.terms', [], false),
            'templatePreviewUrl' => route('templates.preview', ['slug' => 'syifa-dental'], false),
            'carePreviewUrl' => route('templates.preview', ['slug' => 'syifa-care'], false),
            'specialistPreviewUrl' => route('templates.preview', ['slug' => 'syifa-specialist'], false),
            'aestheticPreviewUrl' => route('templates.preview', ['slug' => 'syifa-aesthetic'], false),
        ]);
    }
}
