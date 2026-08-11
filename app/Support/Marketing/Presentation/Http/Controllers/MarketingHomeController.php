<?php

declare(strict_types=1);

namespace App\Support\Marketing\Presentation\Http\Controllers;

use App\Modules\AcquisitionOffer\Application\ListAvailableCommercialOffersService;
use App\Modules\SubscriptionBilling\Contracts\CommercialCatalogue\CommercialCatalogueQueryInterface;
use App\Support\Identity\CurrentUserInterface;
use DateTimeImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
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
 *
 * This lives outside `app/Http/Controllers` because it depends on Commercial
 * Catalogue module services to show live pricing — `app/Http/Controllers` is
 * reserved for module-free operations/root-entry concerns (see
 * `ProductionOperationsFoundationArchitectureTest`).
 */
final readonly class MarketingHomeController
{
    public function __construct(
        private CurrentUserInterface $currentUser,
        private ListAvailableCommercialOffersService $offers,
        private CommercialCatalogueQueryInterface $catalogue,
    ) {}

    public function __invoke(Request $request): Response|RedirectResponse
    {
        if ($this->currentUser->resolve() !== null) {
            return redirect()->route('dashboard');
        }

        return $this->renderHome(true);
    }

    public function preview(): Response
    {
        return $this->renderHome(false);
    }

    private function renderHome(bool $registrationEnabled): Response
    {
        $availableOffers = Schema::hasTable('commercial_catalogue_plan_offerings')
            ? $this->offers->execute(new DateTimeImmutable)
            : [];
        $packages = array_map(function ($offer): array {
            $plan = $this->catalogue->findPlan($offer->planId);

            return [
                'id' => $offer->planOfferingId,
                'name' => $offer->planName,
                'description' => $plan?->description,
                'billingCycle' => $offer->billingCycleName,
                'price' => $offer->currency.' '.number_format($offer->amountMinor / 100, 2, '.', ','),
                'amountMinor' => $offer->amountMinor,
                'isTrial' => $offer->amountMinor === 0,
            ];
        }, $availableOffers);

        return Inertia::render('Shared/Marketing/HomePage', [
            'loginUrl' => route('login', [], false),
            'clinicRegistrationUrl' => route('clinic-registration.browser', [], false),
            'privacyUrl' => route('public-website.privacy', [], false),
            'termsUrl' => route('public-website.terms', [], false),
            'templatePreviewUrl' => route('templates.preview', ['slug' => 'syifa-dental'], false),
            'carePreviewUrl' => route('templates.preview', ['slug' => 'syifa-care'], false),
            'specialistPreviewUrl' => route('templates.preview', ['slug' => 'syifa-specialist'], false),
            'aestheticPreviewUrl' => route('templates.preview', ['slug' => 'syifa-aesthetic'], false),
            'packages' => $packages,
            'packagePreview' => ! $registrationEnabled,
        ]);
    }
}
