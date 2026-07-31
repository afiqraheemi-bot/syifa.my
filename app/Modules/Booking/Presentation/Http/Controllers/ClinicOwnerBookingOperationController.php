<?php

declare(strict_types=1);

namespace App\Modules\Booking\Presentation\Http\Controllers;

use App\Modules\Booking\Contracts\Operations\ClinicOwnerBookingOperationsInterface;
use App\Modules\Booking\Presentation\Http\Requests\RescheduleClinicOwnerBookingRequest;
use App\Support\Authorization\Application\AuthorizationContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final readonly class ClinicOwnerBookingOperationController
{
    public function confirm(
        string $bookingId,
        Request $request,
        ClinicOwnerBookingOperationsInterface $operations,
    ): RedirectResponse {
        $context = $this->context($request);
        $operations->confirm(
            $this->tenantId($context),
            $bookingId,
            $context->identityId,
            $context->role,
        );

        return $this->redirect($request, $bookingId);
    }

    public function cancel(
        string $bookingId,
        Request $request,
        ClinicOwnerBookingOperationsInterface $operations,
    ): RedirectResponse {
        $context = $this->context($request);
        $operations->cancel(
            $this->tenantId($context),
            $bookingId,
            $context->identityId,
            $context->role,
        );

        return $this->redirect($request, $bookingId);
    }

    public function complete(
        string $bookingId,
        Request $request,
        ClinicOwnerBookingOperationsInterface $operations,
    ): RedirectResponse {
        $context = $this->context($request);
        $operations->complete(
            $this->tenantId($context),
            $bookingId,
            $context->identityId,
            $context->role,
        );

        return $this->redirect($request, $bookingId);
    }

    public function reschedule(
        string $bookingId,
        RescheduleClinicOwnerBookingRequest $request,
        ClinicOwnerBookingOperationsInterface $operations,
    ): RedirectResponse {
        $context = $this->context($request);
        $operations->reschedule(
            $this->tenantId($context),
            $bookingId,
            $request->string('appointment_date')->toString(),
            $request->string('appointment_time')->toString(),
            $context->identityId,
            $context->role,
        );

        return $this->redirect($request, $bookingId);
    }

    private function context(Request $request): AuthorizationContext
    {
        $context = $request->attributes->get(AuthorizationContext::class);
        abort_unless($context instanceof AuthorizationContext, 403);

        return $context;
    }

    private function tenantId(AuthorizationContext $context): string
    {
        abort_if($context->tenantId === null, 403);

        return $context->tenantId;
    }

    private function redirect(Request $request, string $bookingId): RedirectResponse
    {
        if ($request->boolean('return_to_detail')) {
            return to_route('dashboard.bookings.show', ['bookingId' => $bookingId], 303);
        }

        return to_route('dashboard.bookings', status: 303);
    }
}
