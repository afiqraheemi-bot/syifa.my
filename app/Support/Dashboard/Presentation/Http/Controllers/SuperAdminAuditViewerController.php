<?php

declare(strict_types=1);

namespace App\Support\Dashboard\Presentation\Http\Controllers;

use App\Support\Authorization\Application\AuthorizationContext;
use App\Support\Dashboard\Application\SuperAdmin\Audit\SuperAdminAuditViewerPage;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final readonly class SuperAdminAuditViewerController
{
    public function __invoke(Request $request, SuperAdminAuditViewerPage $page): Response
    {
        $view = $page->fromTrustedContext(
            $request->attributes->get(AuthorizationContext::class),
            $request->query(),
        );

        return Inertia::render($view->component, $view->props);
    }
}
