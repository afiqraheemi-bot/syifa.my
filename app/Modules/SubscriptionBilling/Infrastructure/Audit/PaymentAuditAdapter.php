<?php

declare(strict_types=1);

namespace App\Modules\SubscriptionBilling\Infrastructure\Audit;

use App\Modules\PlatformAdministration\Contracts\AuditEntry\AuditActorData;
use App\Modules\PlatformAdministration\Contracts\AuditEntry\AuditEntryData;
use App\Modules\PlatformAdministration\Contracts\AuditEntry\AuditEntryRecorderInterface;
use App\Modules\PlatformAdministration\Contracts\AuditEntry\AuditOutcomeData;
use App\Modules\PlatformAdministration\Contracts\AuditEntry\AuditTargetData;
use App\Modules\PlatformAdministration\Domain\AuditEntry\ValueObjects\AuditActorType;
use App\Modules\PlatformAdministration\Domain\AuditEntry\ValueObjects\AuditOutcomeType;
use App\Modules\SubscriptionBilling\Contracts\Payment\PaymentAuditInterface;
use App\Modules\SubscriptionBilling\Domain\Aggregates\Payment\Payment;
use DateTimeImmutable;
use DateTimeZone;

final readonly class PaymentAuditAdapter implements PaymentAuditInterface
{
    private const string TARGET_TYPE = 'payment';

    /**
     * AuditEntry::record() rejects any action whose final dot-segment names
     * an outcome rather than an operation (e.g. "succeeded", "failed"). These
     * two call sites are the only ones affected; the alias is applied only
     * for the persisted audit action, not the caller-facing action string.
     */
    private const array OUTCOME_ENCODING_ACTION_ALIASES = [
        'payment.succeeded' => 'payment.mark_succeeded',
        'payment.failed' => 'payment.mark_failed',
    ];

    public function __construct(private AuditEntryRecorderInterface $auditEntries) {}

    public function record(string $action, Payment $payment, DateTimeImmutable $occurredAt, string $correlationId): void
    {
        $occurredAt = $occurredAt->setTimezone(new DateTimeZone('UTC'));
        $auditAction = self::OUTCOME_ENCODING_ACTION_ALIASES[$action] ?? $action;
        $this->auditEntries->record(new AuditEntryData(
            auditEntryId: $this->auditEntryId($auditAction, $payment->id->value, $occurredAt, $correlationId),
            occurredAt: $occurredAt,
            actor: new AuditActorData(AuditActorType::PlatformIdentity->value, $payment->platformIdentityId->value),
            tenantId: null,
            action: $auditAction,
            target: new AuditTargetData(self::TARGET_TYPE, $payment->id->value),
            outcome: new AuditOutcomeData(AuditOutcomeType::Succeeded->value, null),
            correlationId: $correlationId,
            safeMetadata: [
                'resource_type' => self::TARGET_TYPE,
                'resource_label' => $payment->status->value,
                'target_label' => sprintf(
                    'commercial_offer_id=%s;currency=%s;amount_minor=%d',
                    $payment->commercialOfferId->value,
                    $payment->currency->value,
                    $payment->amount->minorUnits,
                ),
            ],
        ));
    }

    private function auditEntryId(string $action, string $targetId, DateTimeImmutable $occurredAt, string $correlationId): string
    {
        $hex = substr(hash('sha256', implode('|', [$action, $targetId, $occurredAt->format('c'), $correlationId])), 0, 32);
        $hex[12] = '5';
        $hex[16] = dechex((hexdec($hex[16]) & 0x3) | 0x8);

        return sprintf('%s-%s-%s-%s-%s', substr($hex, 0, 8), substr($hex, 8, 4), substr($hex, 12, 4), substr($hex, 16, 4), substr($hex, 20, 12));
    }
}
