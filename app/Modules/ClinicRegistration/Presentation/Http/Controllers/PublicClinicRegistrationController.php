<?php

declare(strict_types=1);

namespace App\Modules\ClinicRegistration\Presentation\Http\Controllers;

use App\Modules\ClinicRegistration\Application\StartClinicRegistrationService;
use App\Modules\ClinicRegistration\Application\ViewCurrentClinicRegistrationService;
use App\Modules\ClinicRegistration\Contracts\Commands\StartClinicRegistrationCommand;
use App\Modules\ClinicRegistration\Contracts\Tracking\RegistrationTrackingCredentialInterface;
use App\Modules\Commercial\Application\ListAvailableCommercialOffersService;
use DateTimeImmutable;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final readonly class PublicClinicRegistrationController
{
    public function __construct(private RegistrationTrackingCredentialInterface $tracking) {}

    public function __invoke(
        Request $request,
        ViewCurrentClinicRegistrationService $registrations,
        StartClinicRegistrationService $start,
        ListAvailableCommercialOffersService $offers,
    ): Response {
        $credential = $this->tracking->establish();
        $registration = $registrations->execute($credential)
            ?? $start->execute(new StartClinicRegistrationCommand(
                $credential,
                new DateTimeImmutable,
                $this->correlationId($request),
            ));

        return Inertia::render('ClinicRegistration/PublicClinicRegistration', [
            'registration' => (array) $registration,
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
            'homeUrl' => route('root'),
        ]);
    }

    private function correlationId(Request $request): string
    {
        $correlationId = $request->attributes->get('correlation_id');

        return is_string($correlationId) ? $correlationId : $this->tracking->establish();
    }
}
