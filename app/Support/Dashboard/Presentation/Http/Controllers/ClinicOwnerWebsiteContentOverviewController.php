<?php

declare(strict_types=1);

namespace App\Support\Dashboard\Presentation\Http\Controllers;

use App\Support\Authorization\Application\AuthorizationContext;
use App\Support\Dashboard\Application\Website\Content\ClinicOwnerWebsiteContentOverviewPage;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final readonly class ClinicOwnerWebsiteContentOverviewController
{
    public function __invoke(
        Request $request,
        ClinicOwnerWebsiteContentOverviewPage $content,
    ): Response {
        $page = $content->fromTrustedContext(
            $request->attributes->get(AuthorizationContext::class),
        );

        return Inertia::render($page->component, $page->props);
    }
}
