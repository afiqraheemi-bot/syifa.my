<?php

declare(strict_types=1);

namespace App\Modules\Commercial\Presentation\Http\Controllers;

use App\Modules\ClinicRegistration\Application\ViewCurrentClinicRegistrationService;
use App\Modules\ClinicRegistration\Contracts\Checkout\PublicInitialAcquisitionCheckoutInterface;
use App\Modules\ClinicRegistration\Contracts\Checkout\StartPublicInitialAcquisitionCheckoutCommand;
use App\Modules\ClinicRegistration\Contracts\Tracking\RegistrationTrackingCredentialInterface;
use App\Modules\ClinicRegistration\Presentation\Http\Requests\SelectInitialCommercialOfferRequest;
use App\Modules\Commercial\Application\Exceptions\ClinicRegistrationOwnershipMismatchException;
use App\Modules\Commercial\Application\Exceptions\CommercialSelectionUnavailableException;
use App\Modules\Commercial\Application\ListAvailableCommercialOffersService;
use DateTimeImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final readonly class PublicCommercialOfferController
{
    public function __construct(private RegistrationTrackingCredentialInterface $tracking) {}

    public function index(
        Request $request,
        ViewCurrentClinicRegistrationService $registrations,
        ListAvailableCommercialOffersService $offers,
    ): Response {
        $credential = $this->tracking->current();
        $registration = $credential === null ? null : $registrations->execute($credential);

        if ($registration === null || $registration->status !== 'submitted') {
            throw new NotFoundHttpException;
        }

        return Inertia::render('ClinicRegistration/PublicCommercialOffers', [
            'registrationStatus' => $registration->status,
            'clinicName' => $registration->clinicName,
            'offers' => array_map(static fn ($offer): array => [
                'planOfferingId' => $offer->planOfferingId,
                'planName' => $offer->planName,
                'billingCycleName' => $offer->billingCycleName,
                'formattedPrice' => sprintf(
                    '%s %s',
                    $offer->currency,
                    number_format($offer->amountMinor / 100, 2, '.', ','),
                ),
                'includedSetup' => 'Managed website onboarding and initial website setup',
            ], $offers->execute(new DateTimeImmutable)),
            'selectionUrl' => route('clinic-registration.offers.select'),
            'homeUrl' => route('root'),
        ]);
    }

    public function select(
        SelectInitialCommercialOfferRequest $request,
        PublicInitialAcquisitionCheckoutInterface $checkouts,
    ): JsonResponse {
        $credential = $this->tracking->current();
        if ($credential === null) {
            throw new NotFoundHttpException;
        }

        try {
            $checkout = $checkouts->execute(new StartPublicInitialAcquisitionCheckoutCommand(
                $credential,
                $request->planOfferingId(),
                new DateTimeImmutable,
                $this->correlationId($request),
            ));
        } catch (ClinicRegistrationOwnershipMismatchException) {
            throw new NotFoundHttpException;
        } catch (CommercialSelectionUnavailableException $exception) {
            return new JsonResponse([
                'type' => 'commercial.selection_unavailable',
                'detail' => $exception->getMessage(),
            ], 422);
        }

        if ($checkout->status !== 'ready') {
            return new JsonResponse([
                'type' => 'payment.provider_not_ready',
                'detail' => $checkout->status === 'provider_not_ready'
                    ? 'Payment provider is not ready. Please try again later.'
                    : 'Hosted checkout is temporarily unavailable. Please try again later.',
            ], 503);
        }

        return new JsonResponse([
            'redirect_action' => [
                'kind' => 'redirect',
                'method' => 'GET',
                'destination' => $checkout->redirectDestination,
            ],
        ]);
    }

    private function correlationId(Request $request): string
    {
        $correlationId = $request->attributes->get('correlation_id');

        return is_string($correlationId) ? $correlationId : 'unavailable';
    }
}
