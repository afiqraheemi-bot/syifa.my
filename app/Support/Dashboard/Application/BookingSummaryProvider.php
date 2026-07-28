<?php

declare(strict_types=1);

namespace App\Support\Dashboard\Application;

use App\Modules\Booking\Contracts\Queries\ClinicOwnerBookingReadInterface;
use App\Support\Authorization\Application\AuthorizationContext;
use LogicException;

final readonly class BookingSummaryProvider implements DashboardSectionProviderInterface
{
    public function __construct(private ClinicOwnerBookingReadInterface $bookings) {}

    public function provide(AuthorizationContext $context): DashboardSectionProjection
    {
        if ($context->tenantId === null) {
            throw new LogicException('Booking summary requires a trusted Tenant identifier.');
        }

        $counts = $this->bookings->countByStatus($context->tenantId);
        $total = array_sum($counts);
        $submitted = $counts['submitted'] ?? 0;
        $confirmed = $counts['confirmed'] ?? 0;

        return new DashboardSectionProjection('bookingSummary', [
            'key' => 'bookings',
            'label' => 'Bookings',
            'value' => (string) $total,
            'detail' => "{$submitted} awaiting confirmation · {$confirmed} confirmed.",
            'tone' => $total > 0 ? 'positive' : 'neutral',
        ]);
    }
}
