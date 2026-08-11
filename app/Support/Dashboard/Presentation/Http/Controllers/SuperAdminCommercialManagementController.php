<?php

declare(strict_types=1);

namespace App\Support\Dashboard\Presentation\Http\Controllers;

use App\Support\Authorization\Application\AuthorizationContext;
use App\Support\Dashboard\Application\SuperAdmin\Commercial\SuperAdminCommercialManagementPage;
use App\Support\Dashboard\Application\SuperAdmin\Commercial\SuperAdminCommercialMutationPage;
use Illuminate\Http\Request;
use Inertia\Response;

final readonly class SuperAdminCommercialManagementController
{
    public function index(Request $request, SuperAdminCommercialManagementPage $page): Response
    {
        $view = $page->index(
            $request->attributes->get(AuthorizationContext::class),
        );

        return inertia($view->component, $view->props);
    }

    public function createPlan(Request $request, SuperAdminCommercialMutationPage $page): Response
    {
        $view = $page->createPlan($request->attributes->get(AuthorizationContext::class));

        return inertia($view->component, $view->props);
    }

    public function createPackage(Request $request, SuperAdminCommercialMutationPage $page): Response
    {
        $view = $page->createPackage($request->attributes->get(AuthorizationContext::class));

        return inertia($view->component, $view->props);
    }

    public function createBillingOption(
        Request $request,
        SuperAdminCommercialMutationPage $page,
    ): Response {
        $view = $page->createBillingOption(
            $request->attributes->get(AuthorizationContext::class),
        );

        return inertia($view->component, $view->props);
    }

    public function createCapability(
        Request $request,
        SuperAdminCommercialMutationPage $page,
    ): Response {
        $view = $page->createCapability(
            $request->attributes->get(AuthorizationContext::class),
        );

        return inertia($view->component, $view->props);
    }

    public function editCapability(
        Request $request,
        string $capabilityId,
        SuperAdminCommercialMutationPage $page,
    ): Response {
        $view = $page->editCapability(
            $request->attributes->get(AuthorizationContext::class),
            $capabilityId,
        );

        return inertia($view->component, $view->props);
    }

    public function editBillingOption(
        Request $request,
        string $billingOptionId,
        SuperAdminCommercialMutationPage $page,
    ): Response {
        $view = $page->editBillingOption(
            $request->attributes->get(AuthorizationContext::class),
            $billingOptionId,
        );

        return inertia($view->component, $view->props);
    }

    public function editPlan(
        Request $request,
        string $planId,
        SuperAdminCommercialMutationPage $page,
    ): Response {
        $view = $page->editPlan(
            $request->attributes->get(AuthorizationContext::class),
            $planId,
        );

        return inertia($view->component, $view->props);
    }

    public function createOffering(
        Request $request,
        string $planId,
        SuperAdminCommercialMutationPage $page,
    ): Response {
        $view = $page->createOffering(
            $request->attributes->get(AuthorizationContext::class),
            $planId,
        );

        return inertia($view->component, $view->props);
    }

    public function editOffering(
        Request $request,
        string $planId,
        string $offeringId,
        SuperAdminCommercialMutationPage $page,
    ): Response {
        $view = $page->editOffering(
            $request->attributes->get(AuthorizationContext::class),
            $planId,
            $offeringId,
        );

        return inertia($view->component, $view->props);
    }

    public function showPlan(
        Request $request,
        string $planId,
        SuperAdminCommercialManagementPage $page,
    ): Response {
        $view = $page->detail(
            $request->attributes->get(AuthorizationContext::class),
            $planId,
        );

        return inertia($view->component, $view->props);
    }

    public function showOffering(
        Request $request,
        string $planId,
        string $offeringId,
        SuperAdminCommercialManagementPage $page,
    ): Response {
        $view = $page->detail(
            $request->attributes->get(AuthorizationContext::class),
            $planId,
            $offeringId,
        );

        return inertia($view->component, $view->props);
    }
}
