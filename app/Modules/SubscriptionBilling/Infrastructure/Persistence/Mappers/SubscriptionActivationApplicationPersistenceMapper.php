<?php

declare(strict_types=1);

namespace App\Modules\SubscriptionBilling\Infrastructure\Persistence\Mappers;

use App\Modules\SubscriptionBilling\Contracts\Subscription\SubscriptionActivationApplication;
use App\Modules\SubscriptionBilling\Contracts\Subscription\SubscriptionActivationApplicationResultCode;
use App\Modules\SubscriptionBilling\Contracts\Subscription\SubscriptionActivationApplicationStatus;
use App\Modules\SubscriptionBilling\Infrastructure\Persistence\Records\SubscriptionActivationApplicationStorageRecord;

final class SubscriptionActivationApplicationPersistenceMapper
{
    public function toDomain(SubscriptionActivationApplicationStorageRecord $record): SubscriptionActivationApplication
    {
        return new SubscriptionActivationApplication(
            $record->id, $record->sourceEventId, $record->paymentId, $record->subscriptionId, $record->tenantId,
            SubscriptionActivationApplicationStatus::from($record->status), $record->attemptCount, $record->claimToken,
            $record->leaseExpiresAt, $record->nextAttemptAt,
            $record->resultCode === null ? null : SubscriptionActivationApplicationResultCode::from($record->resultCode),
        );
    }
}
