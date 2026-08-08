<?php

declare(strict_types=1);

namespace App\Modules\AcquisitionOffer\Presentation\Http\Controllers;

use App\Modules\AcquisitionOffer\Application\CancelCommercialOfferService;
use App\Modules\AcquisitionOffer\Application\Exceptions\CommercialOfferNotFoundException;
use App\Modules\AcquisitionOffer\Application\Exceptions\CommercialOfferVersionMismatchException;
use App\Modules\AcquisitionOffer\Application\GetCommercialOfferService;
use App\Modules\AcquisitionOffer\Application\ListAvailableCommercialOffersService;
use App\Modules\AcquisitionOffer\Application\ViewCurrentCommercialOfferService;
use App\Modules\AcquisitionOffer\Contracts\Commands\CancelCommercialOfferCommand;
use App\Modules\AcquisitionOffer\Domain\Exceptions\InvalidCommercialOfferTransitionException;
use App\Modules\AcquisitionOffer\Presentation\Http\Requests\CancelCommercialOfferRequest;
use App\Modules\AcquisitionOffer\Presentation\Http\Resources\AvailableCommercialOfferResource;
use App\Modules\AcquisitionOffer\Presentation\Http\Resources\CommercialOfferResource;
use App\Modules\AcquisitionOffer\Presentation\Http\Responses\ProblemDetailsResponse;
use App\Modules\PlatformAdministration\Contracts\Authentication\PlatformPrincipalResolverInterface;
use DateTimeImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final readonly class CommercialOfferController
{
    public function __construct(private PlatformPrincipalResolverInterface $principals) {}

    public function availableOffers(Request $request, ListAvailableCommercialOffersService $offers): JsonResponse
    {
        if ($this->principal($request) === null) {
            return $this->unauthenticated($request);
        }

        return new JsonResponse([
            'data' => array_map(
                static fn ($offer): array => (new AvailableCommercialOfferResource($offer))->toArray($request),
                $offers->execute(new DateTimeImmutable),
            ),
        ]);
    }

    public function current(Request $request, ViewCurrentCommercialOfferService $offers): JsonResponse
    {
        $principal = $this->principal($request);
        if ($principal === null) {
            return $this->unauthenticated($request);
        }

        $offer = $offers->execute($principal);

        if ($offer === null) {
            return $this->notFound($request);
        }

        return (new CommercialOfferResource($offer))->response();
    }

    public function show(Request $request, string $offerId, GetCommercialOfferService $offers): JsonResponse
    {
        $principal = $this->principal($request);
        if ($principal === null) {
            return $this->unauthenticated($request);
        }

        try {
            $offer = $offers->execute($principal, $offerId);
        } catch (CommercialOfferNotFoundException|InvalidCommercialOfferTransitionException) {
            return $this->notFound($request);
        }

        return (new CommercialOfferResource($offer))->response();
    }

    public function cancel(CancelCommercialOfferRequest $request, string $offerId, CancelCommercialOfferService $offers): JsonResponse
    {
        $principal = $this->principal($request);
        if ($principal === null) {
            return $this->unauthenticated($request);
        }

        try {
            $offer = $offers->execute(new CancelCommercialOfferCommand(
                $principal,
                $offerId,
                $request->expectedVersion(),
                new DateTimeImmutable,
                $this->correlationId($request),
            ));
        } catch (CommercialOfferNotFoundException) {
            return $this->notFound($request);
        } catch (CommercialOfferVersionMismatchException) {
            return $this->conflict($request, 'commercial.version_conflict', 'Commercial Offer version does not match.');
        } catch (InvalidCommercialOfferTransitionException $exception) {
            return $this->conflict($request, 'commercial.invalid_transition', $exception->getMessage());
        }

        return (new CommercialOfferResource($offer))->response();
    }

    private function principal(Request $request): ?string
    {
        return $this->principals->resolve(new DateTimeImmutable)?->platformIdentityId;
    }

    private function correlationId(Request $request): string
    {
        $correlationId = $request->attributes->get('correlation_id');

        return is_string($correlationId) ? $correlationId : '00000000-0000-4000-8000-000000000000';
    }

    private function unauthenticated(Request $request): JsonResponse
    {
        return ProblemDetailsResponse::make(
            $request,
            'commercial.authentication_required',
            'Authentication Required',
            401,
            'An authenticated platform identity is required.',
        );
    }

    private function notFound(Request $request): JsonResponse
    {
        return ProblemDetailsResponse::make($request, 'commercial.offer_not_found', 'Commercial Offer Not Found', 404, 'The commercial offer could not be found.');
    }

    private function conflict(Request $request, string $type, string $detail): JsonResponse
    {
        return ProblemDetailsResponse::make($request, $type, 'Commercial Offer Conflict', 409, $detail);
    }
}
