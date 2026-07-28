<?php

declare(strict_types=1);

namespace App\Support\Dashboard\Presentation\Http\Controllers;

use App\Support\Authorization\Application\AuthorizationContext;
use App\Support\Dashboard\Application\Notifications\NotificationHistoryPage;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final readonly class NotificationHistoryController
{
    public function __invoke(Request $request, NotificationHistoryPage $page): Response
    {
        $view = $page->fromTrustedContext(
            $request->attributes->get(AuthorizationContext::class),
            $request->query(),
        );

        return Inertia::render($view->component, $view->props);
    }
}
