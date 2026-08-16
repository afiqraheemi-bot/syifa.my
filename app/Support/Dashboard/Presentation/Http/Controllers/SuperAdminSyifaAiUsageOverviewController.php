<?php

declare(strict_types=1);

namespace App\Support\Dashboard\Presentation\Http\Controllers;

use App\Support\Authorization\Application\AuthorizationContext;
use App\Support\Dashboard\Application\SuperAdmin\SyifaAiUsage\SuperAdminSyifaAiUsageOverviewPage;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final readonly class SuperAdminSyifaAiUsageOverviewController
{
    public function __invoke(Request $request, SuperAdminSyifaAiUsageOverviewPage $page): Response
    {
        $view = $page->fromTrustedContext($request->attributes->get(AuthorizationContext::class));

        return Inertia::render($view->component, $view->props);
    }
}
