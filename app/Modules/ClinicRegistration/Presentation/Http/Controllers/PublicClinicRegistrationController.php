<?php

declare(strict_types=1);

namespace App\Modules\ClinicRegistration\Presentation\Http\Controllers;

use App\Modules\ClinicRegistration\Application\StartClinicRegistrationService;
use App\Modules\ClinicRegistration\Application\ViewCurrentClinicRegistrationService;
use App\Modules\ClinicRegistration\Contracts\Authentication\ClinicRegistrationAccessInterface;
use App\Modules\ClinicRegistration\Contracts\Commands\StartClinicRegistrationCommand;
use App\Modules\ClinicRegistration\Contracts\Tracking\RegistrationTrackingCredentialInterface;
use App\Modules\Commercial\Application\ListAvailableCommercialOffersService;
use App\Modules\WebsiteBuilder\Contracts\PublicTemplate\PublicTemplateCatalogInterface;
use App\Support\Identity\CurrentUserInterface;
use DateTimeImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final readonly class PublicClinicRegistrationController
{
    public function __construct(
        private RegistrationTrackingCredentialInterface $tracking,
        private CurrentUserInterface $currentUser,
    ) {}

    public function __invoke(
        Request $request,
        ViewCurrentClinicRegistrationService $registrations,
        StartClinicRegistrationService $start,
        ListAvailableCommercialOffersService $offers,
        ClinicRegistrationAccessInterface $access,
        PublicTemplateCatalogInterface $templates,
    ): Response|RedirectResponse {
        if ($this->currentUser->resolve() !== null) {
            return redirect()->route('dashboard');
        }

        $credential = $this->tracking->establish();
        $registration = $registrations->execute($credential)
            ?? $start->execute(new StartClinicRegistrationCommand(
                $credential,
                new DateTimeImmutable,
                $this->correlationId($request),
            ));

        return Inertia::render('ClinicRegistration/PublicClinicRegistration', [
            'registration' => (array) $registration,
            'accessConfigured' => $access->configured($registration->id),
            'accessSetupUrl' => route('clinic-registration.access.configure'),
            'applicationLoginUrl' => route('root'),
            'applicationLogoutUrl' => route('clinic-registration.access.logout'),
            'offers' => array_map(static fn ($offer): array => [
                'planOfferingId' => $offer->planOfferingId,
                'billingCycleId' => $offer->billingCycleId,
                'planName' => $offer->planName,
                'billingCycleName' => $offer->billingCycleName,
                'amountMinor' => $offer->amountMinor,
                'currency' => $offer->currency,
                'configurationVersion' => $offer->configurationVersion,
            ], $offers->execute(new DateTimeImmutable)),
            'updateUrl' => route('clinic-registration.current.update'),
            'submitUrl' => route('clinic-registration.current.submit'),
            'resubmitUrl' => route('clinic-registration.current.resubmit'),
            'cancelUrl' => route('clinic-registration.current.cancel'),
            'offersUrl' => route('clinic-registration.offers'),
            'addressAvailabilityUrl' => route('clinic-registration.website-address.availability'),
            'websiteBaseDomain' => config('public_website_delivery.base_domain'),
            'templates' => array_map(static fn ($template): array => [
                'value' => $template->value,
                'label' => $template->label,
            ], $templates->options()),
            'homeUrl' => route('root', [], false),
        ]);
    }

    private function correlationId(Request $request): string
    {
        $correlationId = $request->attributes->get('correlation_id');

        return is_string($correlationId) ? $correlationId : $this->tracking->establish();
    }
}
