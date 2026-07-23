<?php

declare(strict_types=1);

namespace App\Support\Dashboard\Presentation\Http\Controllers;

use App\Support\Authorization\Application\AuthorizationContext;
use App\Support\Dashboard\Application\Booking\ClinicOwnerBookingOverviewPage;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final readonly class ClinicOwnerBookingOverviewController
{
    public function __invoke(
        Request $request,
        ClinicOwnerBookingOverviewPage $bookings,
    ): Response {
        $page = $bookings->fromTrustedContext(
            $request->attributes->get(AuthorizationContext::class),
            $request->query(),
        );

        return Inertia::render($page->component, [
            ...$page->props,
            'csrfToken' => csrf_token(),
        ]);
    }
}
