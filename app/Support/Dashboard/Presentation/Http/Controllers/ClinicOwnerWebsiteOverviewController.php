<?php

declare(strict_types=1);

namespace App\Support\Dashboard\Presentation\Http\Controllers;

use App\Support\Authorization\Application\AuthorizationContext;
use App\Support\Dashboard\Application\Website\ClinicOwnerWebsiteOverviewPage;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final readonly class ClinicOwnerWebsiteOverviewController
{
    public function __invoke(
        Request $request,
        ClinicOwnerWebsiteOverviewPage $website,
    ): Response {
        $page = $website->fromTrustedContext(
            $request->attributes->get(AuthorizationContext::class),
        );

        return Inertia::render($page->component, $page->props);
    }
}
