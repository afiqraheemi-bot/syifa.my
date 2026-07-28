<?php

declare(strict_types=1);

namespace App\Modules\Notification\Contracts;

interface TransactionalNotificationGatewayInterface
{
    public function bookingReceived(
        string $tenantId,
        string $bookingId,
        string $bookingReference,
        ?string $patientEmail,
    ): void;
}
