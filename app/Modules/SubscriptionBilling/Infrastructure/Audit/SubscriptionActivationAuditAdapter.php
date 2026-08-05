<?php

declare(strict_types=1);

namespace App\Modules\SubscriptionBilling\Infrastructure\Audit;

use App\Modules\PlatformAdministration\Contracts\AuditEntry\AuditActorData;
use App\Modules\PlatformAdministration\Contracts\AuditEntry\AuditEntryData;
use App\Modules\PlatformAdministration\Contracts\AuditEntry\AuditEntryRecorderInterface;
use App\Modules\PlatformAdministration\Contracts\AuditEntry\AuditOutcomeData;
use App\Modules\PlatformAdministration\Contracts\AuditEntry\AuditTargetData;
use App\Modules\SubscriptionBilling\Contracts\Subscription\SubscriptionActivationApplicationResultCode;
use App\Modules\SubscriptionBilling\Contracts\Subscription\SubscriptionActivationAuditInterface;
use DateTimeImmutable;

final readonly class SubscriptionActivationAuditAdapter implements SubscriptionActivationAuditInterface
{
    public function __construct(private AuditEntryRecorderInterface $audit) {}

    public function record(string $action, string $applicationId, string $subscriptionId, string $paymentId, string $tenantId, SubscriptionActivationApplicationResultCode $resultCode, DateTimeImmutable $occurredAt): void
    {
        $this->audit->record(new AuditEntryData(
            auditEntryId: $this->id($action, $applicationId), occurredAt: $occurredAt,
            actor: new AuditActorData('system', null), tenantId: null, action: $action,
            target: new AuditTargetData('subscription', $subscriptionId), outcome: new AuditOutcomeData('succeeded', null),
            correlationId: $applicationId, safeMetadata: [
                'resource_type' => 'subscription_activation', 'resource_label' => $resultCode->value,
                'target_label' => sprintf('subscription_id=%s;payment_id=%s;tenant_id=%s', $subscriptionId, $paymentId, $tenantId),
            ],
        ));
    }

    private function id(string $action, string $applicationId): string
    {
        $hex = substr(hash('sha256', $action.'|'.$applicationId), 0, 32);
        $hex[12] = '5';
        $hex[16] = dechex((hexdec($hex[16]) & 3) | 8);

        return sprintf('%s-%s-%s-%s-%s', substr($hex, 0, 8), substr($hex, 8, 4), substr($hex, 12, 4), substr($hex, 16, 4), substr($hex, 20));
    }
}
