<?php

declare(strict_types=1);

namespace App\Support\Dashboard\Presentation\Http\Controllers;

use App\Support\Authorization\Application\AuthorizationContext;
use App\Support\Dashboard\Application\WebsiteDesigner\Job\WebsiteDesignerJobDetailPage;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final readonly class WebsiteDesignerJobDetailController
{
    public function __invoke(
        Request $request,
        WebsiteDesignerJobDetailPage $page,
        string $jobId,
    ): Response {
        $view = $page->fromTrustedContext(
            $request->attributes->get(AuthorizationContext::class),
            $jobId,
        );
        abort_if($view === null, 404);

        return Inertia::render($view->component, $view->props);
    }
}
