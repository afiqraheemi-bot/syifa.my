<?php

declare(strict_types=1);

namespace App\Support\Dashboard\Application\Booking;

use App\Modules\Booking\Application\ServiceSetup\ManageServiceSetupService;
use App\Modules\Booking\Application\ServiceSetup\ServiceSetupData;
use App\Support\Authorization\Application\AuthorizationContext;
use App\Support\Dashboard\Application\ClinicOwnerDashboardNavigation;
use App\Support\Dashboard\Application\DashboardPageView;
use LogicException;

final readonly class ClinicOwnerServiceSetupPage
{
    public function __construct(private ManageServiceSetupService $services) {}

    public function fromTrustedContext(mixed $context): DashboardPageView
    {
        if (! $context instanceof AuthorizationContext || $context->tenantId === null) {
            throw new LogicException('Authenticated Service Setup context was not established.');
        }

        return new DashboardPageView('TenantManagement/Booking/ClinicOwnerServiceSetup', [
            'navigation' => ClinicOwnerDashboardNavigation::items('services'),
            'breadcrumbs' => [
                ['key' => 'dashboard', 'label' => __('booking.breadcrumb_dashboard'), 'href' => route('dashboard')],
                ['key' => 'services', 'label' => __('booking.breadcrumb_services')],
            ],
            'pageTitle' => __('booking.services_page_title'),
            'pageDescription' => __('booking.services_page_description'),
            'identityName' => $context->name,
            'contextLabel' => 'Clinic Owner workspace',
            'services' => array_map(static fn (ServiceSetupData $service): array => get_object_vars($service), $this->services->list($context->tenantId)),
            'operationsUrl' => route('dashboard.services'),
        ]);
    }
}
