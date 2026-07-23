<?php

declare(strict_types=1);

namespace App\Support\Dashboard\Application;

use App\Support\Authorization\Application\AuthorizationContext;

final readonly class BookingSummaryProvider implements DashboardSectionProviderInterface
{
    public function provide(AuthorizationContext $context): DashboardSectionProjection
    {
        return new DashboardSectionProjection('bookingSummary', [
            'key' => 'bookings',
            'label' => 'Bookings',
            'value' => 'Not available',
            'detail' => 'A complete booking-count query is not available yet.',
            'tone' => 'neutral',
        ]);
    }
}
