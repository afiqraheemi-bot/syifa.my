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
use DateTimeImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

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
