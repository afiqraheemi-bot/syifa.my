<?php

declare(strict_types=1);

namespace App\Support\Dashboard\Presentation\Http\Controllers;

use App\Support\Authorization\Application\AuthorizationContext;
use App\Support\Dashboard\Application\Booking\ClinicOwnerBookingDetailPage;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final readonly class ClinicOwnerBookingDetailController
{
    public function __invoke(
        Request $request,
        string $bookingId,
        ClinicOwnerBookingDetailPage $bookings,
    ): Response {
        $page = $bookings->fromTrustedContext(
            $request->attributes->get(AuthorizationContext::class),
            $bookingId,
        );
        abort_if($page === null, 404);

        return Inertia::render($page->component, $page->props);
    }
}
