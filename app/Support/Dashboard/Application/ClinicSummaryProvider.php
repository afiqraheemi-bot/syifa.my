<?php

declare(strict_types=1);

namespace App\Support\Dashboard\Application;

use App\Modules\WebsiteBuilder\Contracts\Queries\ClinicSummaryReadInterface;
use App\Support\Authorization\Application\AuthorizationContext;

final readonly class ClinicSummaryProvider implements DashboardSectionProviderInterface
{
    public function __construct(private ClinicSummaryReadInterface $clinics) {}

    public function provide(AuthorizationContext $context): DashboardSectionProjection
    {
        $clinic = $context->tenantId === null ? null : $this->clinics->summary($context->tenantId);

        return new DashboardSectionProjection('clinicSummary', $clinic === null
            ? [
                'key' => 'clinic',
                'label' => 'Clinic',
                'value' => 'Not available',
                'detail' => 'Clinic information is not available yet.',
                'tone' => 'neutral',
            ]
            : [
                'key' => 'clinic',
                'label' => 'Clinic',
                'value' => $clinic->clinicName,
                'detail' => $clinic->operationalProfileConfigured
                    ? sprintf('Operational profile configured · %s', $clinic->timezone)
                    : sprintf('Operational profile setup is incomplete · %s', $clinic->timezone),
                'tone' => $clinic->operationalProfileConfigured ? 'positive' : 'neutral',
            ]);
    }
}
