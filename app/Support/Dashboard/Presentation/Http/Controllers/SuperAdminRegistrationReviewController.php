<?php

declare(strict_types=1);

namespace App\Support\Dashboard\Presentation\Http\Controllers;

use App\Modules\ClinicRegistration\Application\ArchiveClinicRegistrationService;
use App\Modules\ClinicRegistration\Application\DecideClinicRegistrationService;
use App\Modules\ClinicRegistration\Application\StartClinicRegistrationReviewService;
use App\Modules\ClinicRegistration\Application\UpdateClinicRegistrationByAdministratorService;
use App\Modules\ClinicRegistration\Contracts\Commands\ArchiveClinicRegistrationCommand;
use App\Modules\ClinicRegistration\Contracts\Commands\DecideClinicRegistrationCommand;
use App\Modules\ClinicRegistration\Contracts\Commands\StartClinicRegistrationReviewCommand;
use App\Modules\ClinicRegistration\Contracts\Commands\UpdateClinicRegistrationByAdministratorCommand;
use App\Modules\ClinicRegistration\Domain\Exceptions\InvalidClinicRegistrationTransitionException;
use App\Modules\ClinicRegistration\Domain\Exceptions\InvalidClinicRegistrationValueException;
use App\Modules\ClinicRegistration\Domain\Exceptions\StaleClinicRegistrationWriteException;
use App\Support\Authorization\Application\AuthorizationContext;
use App\Support\Dashboard\Application\SuperAdmin\Registrations\SuperAdminRegistrationReviewPage;
use App\Support\Provisioning\Application\ActivateApprovedFreeTrialService;
use DateTimeImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

final readonly class SuperAdminRegistrationReviewController
{
    public function index(Request $request, SuperAdminRegistrationReviewPage $page): Response
    {
        $view = $page->fromTrustedContext(
            $request->attributes->get(AuthorizationContext::class),
            $request->query(),
        );

        return Inertia::render($view->component, $view->props);
    }

    public function review(
        string $registrationId,
        Request $request,
        StartClinicRegistrationReviewService $reviews,
    ): JsonResponse {
        $validated = $request->validate([
            'expected_version' => ['required', 'integer', 'min:1'],
        ]);
        $context = $this->context($request);

        try {
            $version = $reviews->execute(new StartClinicRegistrationReviewCommand(
                $registrationId,
                (int) $validated['expected_version'],
                $context->identityId,
                $this->correlationId($request),
                new DateTimeImmutable,
            ));
        } catch (InvalidClinicRegistrationTransitionException|StaleClinicRegistrationWriteException $exception) {
            return response()->json(['message' => $exception->getMessage()], 409);
        }

        return response()->json(['version' => $version]);
    }

    public function decide(
        string $registrationId,
        Request $request,
        DecideClinicRegistrationService $decisions,
    ): JsonResponse {
        $validated = $request->validate([
            'outcome' => ['required', 'in:approved,rejected,correction_requested'],
            'reason_category' => ['required', 'string', 'max:100'],
            'correction_instructions' => [
                'nullable',
                'required_if:outcome,correction_requested',
                'string',
                'max:2000',
            ],
            'expected_version' => ['required', 'integer', 'min:1'],
        ]);
        $context = $this->context($request);

        try {
            $version = $decisions->execute(new DecideClinicRegistrationCommand(
                $registrationId,
                (string) Str::uuid(),
                (string) $validated['outcome'],
                (string) $validated['reason_category'],
                isset($validated['correction_instructions'])
                    ? (string) $validated['correction_instructions']
                    : null,
                (int) $validated['expected_version'],
                $context->identityId,
                $this->correlationId($request),
                new DateTimeImmutable,
            ));
        } catch (InvalidClinicRegistrationTransitionException|InvalidClinicRegistrationValueException|StaleClinicRegistrationWriteException $exception) {
            return response()->json(['message' => $exception->getMessage()], 409);
        } catch (Throwable $exception) {
            $reference = strtoupper(substr(str_replace('-', '', $this->correlationId($request)), 0, 8));
            $failureCode = $exception instanceof QueryException
                ? 'REGISTRATION_DATABASE_CONFLICT'
                : 'REGISTRATION_DECISION_FAILED';

            try {
                Log::error('Clinic Registration decision failed.', [
                    'registration_id' => $registrationId,
                    'failure_code' => $failureCode,
                    'reference' => $reference,
                    'exception' => $exception,
                ]);
            } catch (Throwable) {
                // Logging failure must not replace the safe operator response.
            }

            return response()->json([
                'message' => sprintf(
                    'The decision could not be recorded. Error code %s, reference %s.',
                    $failureCode,
                    $reference,
                ),
            ], 500);
        }

        if ((string) $validated['outcome'] === 'approved') {
            // The registration decision above has already committed by this
            // point. Free trial activation is a best-effort follow-up step —
            // if it fails, the admin must still see their approval succeeded
            // (not a misleading 500 for an action that actually went through)
            // and the failure must still be visible to an operator.
            try {
                app(ActivateApprovedFreeTrialService::class)->execute(
                    $registrationId,
                    $this->correlationId($request),
                );
            } catch (Throwable $exception) {
                Log::error('Free trial activation failed after Clinic Registration approval.', [
                    'registration_id' => $registrationId,
                    'exception' => $exception,
                ]);
            }
        }

        return response()->json(['version' => $version]);
    }

    public function update(
        string $registrationId,
        Request $request,
        UpdateClinicRegistrationByAdministratorService $updates,
    ): JsonResponse {
        $validated = $request->validate([
            'clinic_name' => ['required', 'string', 'max:200'],
            'clinic_email' => ['required', 'email', 'max:254'],
            'clinic_phone' => ['required', 'string', 'max:40'],
            'clinic_address' => ['required', 'string', 'max:1000'],
            'expected_version' => ['required', 'integer', 'min:1'],
        ]);
        $context = $this->context($request);

        try {
            $version = $updates->execute(new UpdateClinicRegistrationByAdministratorCommand(
                $registrationId,
                (string) $validated['clinic_name'],
                (string) $validated['clinic_email'],
                (string) $validated['clinic_phone'],
                (string) $validated['clinic_address'],
                (int) $validated['expected_version'],
                $context->identityId,
                $this->correlationId($request),
                new DateTimeImmutable,
            ));
        } catch (InvalidClinicRegistrationTransitionException|InvalidClinicRegistrationValueException|StaleClinicRegistrationWriteException $exception) {
            return response()->json(['message' => $exception->getMessage()], 409);
        }

        return response()->json(['version' => $version]);
    }

    public function archive(
        string $registrationId,
        Request $request,
        ArchiveClinicRegistrationService $archives,
    ): JsonResponse {
        $validated = $request->validate([
            'expected_version' => ['required', 'integer', 'min:1'],
        ]);
        $context = $this->context($request);

        try {
            $version = $archives->execute(new ArchiveClinicRegistrationCommand(
                $registrationId,
                (int) $validated['expected_version'],
                $context->identityId,
                $this->correlationId($request),
                new DateTimeImmutable,
            ));
        } catch (InvalidClinicRegistrationTransitionException|StaleClinicRegistrationWriteException $exception) {
            return response()->json(['message' => $exception->getMessage()], 409);
        }

        return response()->json(['version' => $version]);
    }

    private function context(Request $request): AuthorizationContext
    {
        $context = $request->attributes->get(AuthorizationContext::class);
        abort_unless($context instanceof AuthorizationContext, 403);

        return $context;
    }

    private function correlationId(Request $request): string
    {
        $correlationId = $request->attributes->get('correlation_id');

        return is_string($correlationId) && $correlationId !== ''
            ? $correlationId
            : (string) Str::uuid();
    }
}
