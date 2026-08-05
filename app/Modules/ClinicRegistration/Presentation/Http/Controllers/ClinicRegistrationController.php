<?php

declare(strict_types=1);

namespace App\Modules\ClinicRegistration\Presentation\Http\Controllers;

use App\Modules\ClinicRegistration\Application\CancelClinicRegistrationService;
use App\Modules\ClinicRegistration\Application\Exceptions\ClinicRegistrationNotFoundException;
use App\Modules\ClinicRegistration\Application\Exceptions\ClinicRegistrationVersionMismatchException;
use App\Modules\ClinicRegistration\Application\ResubmitClinicRegistrationCorrectionService;
use App\Modules\ClinicRegistration\Application\StartClinicRegistrationService;
use App\Modules\ClinicRegistration\Application\SubmitClinicRegistrationService;
use App\Modules\ClinicRegistration\Application\UpdateClinicRegistrationDraftService;
use App\Modules\ClinicRegistration\Application\ViewCurrentClinicRegistrationService;
use App\Modules\ClinicRegistration\Contracts\Commands\CancelClinicRegistrationCommand;
use App\Modules\ClinicRegistration\Contracts\Commands\ResubmitClinicRegistrationCorrectionCommand;
use App\Modules\ClinicRegistration\Contracts\Commands\StartClinicRegistrationCommand;
use App\Modules\ClinicRegistration\Contracts\Commands\SubmitClinicRegistrationCommand;
use App\Modules\ClinicRegistration\Contracts\Commands\UpdateClinicRegistrationDraftCommand;
use App\Modules\ClinicRegistration\Contracts\Tracking\RegistrationTrackingCredentialInterface;
use App\Modules\ClinicRegistration\Domain\Exceptions\InvalidClinicRegistrationTransitionException;
use App\Modules\ClinicRegistration\Presentation\Http\Requests\StartClinicRegistrationRequest;
use App\Modules\ClinicRegistration\Presentation\Http\Requests\TransitionClinicRegistrationRequest;
use App\Modules\ClinicRegistration\Presentation\Http\Requests\UpdateClinicRegistrationDraftRequest;
use App\Modules\ClinicRegistration\Presentation\Http\Resources\ClinicRegistrationResource;
use App\Modules\ClinicRegistration\Presentation\Http\Responses\ProblemDetailsResponse;
use App\Modules\WebsiteBuilder\Contracts\PublicAddress\PublicWebsiteAddressAvailabilityInterface;
use DateTimeImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final readonly class ClinicRegistrationController
{
    public function __construct(private RegistrationTrackingCredentialInterface $tracking) {}

    public function store(StartClinicRegistrationRequest $request, StartClinicRegistrationService $registrations): JsonResponse
    {
        $credential = $this->tracking->establish();

        $registration = $registrations->execute(new StartClinicRegistrationCommand(
            $credential,
            new DateTimeImmutable,
            $this->correlationId($request),
        ));

        return (new ClinicRegistrationResource($registration))->response()->setStatusCode(201);
    }

    public function show(Request $request, ViewCurrentClinicRegistrationService $registrations): JsonResponse
    {
        $credential = $this->tracking->current();
        if ($credential === null) {
            return $this->notFound($request);
        }

        $registration = $registrations->execute($credential);

        if ($registration === null) {
            return ProblemDetailsResponse::make(
                $request,
                'clinic_registration.not_found',
                'Clinic Registration Not Found',
                404,
                'The current clinic registration could not be found.',
            );
        }

        return (new ClinicRegistrationResource($registration))->response();
    }

    public function update(
        UpdateClinicRegistrationDraftRequest $request,
        UpdateClinicRegistrationDraftService $registrations,
        PublicWebsiteAddressAvailabilityInterface $addresses,
    ): JsonResponse {
        $credential = $this->tracking->current();
        if ($credential === null) {
            return $this->notFound($request);
        }

        $validated = $request->validated();
        $preferredSubdomain = $validated['preferred_subdomain'] ?? null;
        if (is_string($preferredSubdomain)
            && ! $addresses->available($preferredSubdomain, $credential)) {
            return ProblemDetailsResponse::make(
                $request,
                'clinic_registration.website_address_unavailable',
                'Website Address Unavailable',
                422,
                'The selected Website address is no longer available.',
                ['preferred_subdomain' => ['Alamat Website ini telah digunakan.']],
            );
        }

        try {
            $registration = $registrations->execute(new UpdateClinicRegistrationDraftCommand(
                $credential,
                $validated['clinic_name'] ?? null,
                $validated['clinic_email'] ?? null,
                $validated['clinic_phone'] ?? null,
                $validated['clinic_address'] ?? null,
                $validated['selected_plan_offering_reference'] ?? null,
                $validated['selected_billing_option_reference'] ?? null,
                $validated['commercial_snapshot_version'] ?? null,
                (int) $validated['expected_version'],
                new DateTimeImmutable,
                $this->correlationId($request),
                $request->declarations(),
                $validated['preferred_subdomain'] ?? null,
                $validated['selected_website_template'] ?? null,
            ));
        } catch (ClinicRegistrationNotFoundException) {
            return $this->notFound($request);
        } catch (ClinicRegistrationVersionMismatchException) {
            return $this->conflict($request, 'clinic_registration.concurrency_conflict', 'Clinic registration version does not match.');
        } catch (InvalidClinicRegistrationTransitionException $exception) {
            return $this->conflict($request, 'clinic_registration.invalid_transition', $exception->getMessage());
        } catch (QueryException $exception) {
            if ((string) $exception->getCode() !== '23505') {
                throw $exception;
            }

            return ProblemDetailsResponse::make(
                $request,
                'clinic_registration.website_address_unavailable',
                'Website Address Unavailable',
                422,
                'The selected Website address is no longer available.',
                ['preferred_subdomain' => ['Alamat Website ini telah digunakan.']],
            );
        }

        return (new ClinicRegistrationResource($registration))->response();
    }

    public function submit(TransitionClinicRegistrationRequest $request, SubmitClinicRegistrationService $registrations): JsonResponse
    {
        $credential = $this->tracking->current();
        if ($credential === null) {
            return $this->notFound($request);
        }

        try {
            $registration = $registrations->execute(new SubmitClinicRegistrationCommand(
                $credential,
                $request->expectedVersion(),
                new DateTimeImmutable,
                $this->correlationId($request),
            ));
        } catch (ClinicRegistrationNotFoundException) {
            return $this->notFound($request);
        } catch (ClinicRegistrationVersionMismatchException) {
            return $this->conflict($request, 'clinic_registration.concurrency_conflict', 'Clinic registration version does not match.');
        } catch (InvalidClinicRegistrationTransitionException $exception) {
            return $this->conflict($request, 'clinic_registration.invalid_transition', $exception->getMessage());
        }

        return (new ClinicRegistrationResource($registration))->response();
    }

    public function cancel(TransitionClinicRegistrationRequest $request, CancelClinicRegistrationService $registrations): JsonResponse
    {
        $credential = $this->tracking->current();
        if ($credential === null) {
            return $this->notFound($request);
        }

        try {
            $registration = $registrations->execute(new CancelClinicRegistrationCommand(
                $credential,
                $request->expectedVersion(),
                new DateTimeImmutable,
                $this->correlationId($request),
            ));
        } catch (ClinicRegistrationNotFoundException) {
            return $this->notFound($request);
        } catch (ClinicRegistrationVersionMismatchException) {
            return $this->conflict($request, 'clinic_registration.concurrency_conflict', 'Clinic registration version does not match.');
        } catch (InvalidClinicRegistrationTransitionException $exception) {
            return $this->conflict($request, 'clinic_registration.invalid_transition', $exception->getMessage());
        }

        return (new ClinicRegistrationResource($registration))->response();
    }

    public function resubmit(
        UpdateClinicRegistrationDraftRequest $request,
        ResubmitClinicRegistrationCorrectionService $registrations,
    ): JsonResponse {
        $credential = $this->tracking->current();
        if ($credential === null) {
            return $this->notFound($request);
        }
        $validated = $request->validated();

        try {
            $registration = $registrations->execute(new ResubmitClinicRegistrationCorrectionCommand(
                $credential,
                $validated['clinic_name'] ?? null,
                $validated['clinic_email'] ?? null,
                $validated['clinic_phone'] ?? null,
                $validated['clinic_address'] ?? null,
                $request->declarations(),
                (int) $validated['expected_version'],
                new DateTimeImmutable,
                $validated['preferred_subdomain'] ?? null,
                $validated['selected_website_template'] ?? null,
            ));
        } catch (ClinicRegistrationNotFoundException) {
            return $this->notFound($request);
        } catch (ClinicRegistrationVersionMismatchException) {
            return $this->conflict($request, 'clinic_registration.concurrency_conflict', 'Clinic registration version does not match.');
        } catch (InvalidClinicRegistrationTransitionException $exception) {
            return $this->conflict($request, 'clinic_registration.invalid_transition', $exception->getMessage());
        }

        return (new ClinicRegistrationResource($registration))->response();
    }

    private function correlationId(Request $request): string
    {
        $correlationId = $request->attributes->get('correlation_id');

        return is_string($correlationId) ? $correlationId : '00000000-0000-4000-8000-000000000000';
    }

    private function notFound(Request $request): JsonResponse
    {
        return ProblemDetailsResponse::make(
            $request,
            'clinic_registration.not_found',
            'Clinic Registration Not Found',
            404,
            'The current clinic registration could not be found.',
        );
    }

    private function conflict(Request $request, string $type, string $detail): JsonResponse
    {
        return ProblemDetailsResponse::make($request, $type, 'Clinic Registration Conflict', 409, $detail);
    }
}
