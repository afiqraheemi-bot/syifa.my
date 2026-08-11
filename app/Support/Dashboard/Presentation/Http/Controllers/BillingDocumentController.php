<?php

declare(strict_types=1);

namespace App\Support\Dashboard\Presentation\Http\Controllers;

use App\Support\Authorization\Application\AuthorizationContext;
use App\Support\Dashboard\Application\DashboardPageView;
use App\Support\Dashboard\Application\Subscription\BillingDocumentPage;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final readonly class BillingDocumentController
{
    public function clinicOwnerInvoice(Request $request, string $paymentId, BillingDocumentPage $page): Response
    {
        return $this->render($page->forClinicOwner(
            $request->attributes->get(AuthorizationContext::class),
            $paymentId,
            'invoice',
        ));
    }

    public function clinicOwnerReceipt(Request $request, string $paymentId, BillingDocumentPage $page): Response
    {
        return $this->render($page->forClinicOwner(
            $request->attributes->get(AuthorizationContext::class),
            $paymentId,
            'receipt',
        ));
    }

    public function superAdminInvoice(Request $request, string $paymentId, BillingDocumentPage $page): Response
    {
        return $this->render($page->forSuperAdmin(
            $request->attributes->get(AuthorizationContext::class),
            $paymentId,
            'invoice',
        ));
    }

    public function superAdminReceipt(Request $request, string $paymentId, BillingDocumentPage $page): Response
    {
        return $this->render($page->forSuperAdmin(
            $request->attributes->get(AuthorizationContext::class),
            $paymentId,
            'receipt',
        ));
    }

    private function render(DashboardPageView $view): Response
    {
        return Inertia::render($view->component, $view->props);
    }
}
