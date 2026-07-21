<?php

declare(strict_types=1);

namespace App\Modules\Commercial\Application\Audit;

use App\Modules\Commercial\Domain\CommercialOffer;
use App\Modules\PlatformAdministration\Contracts\AuditEntry\AuditActorData;
use App\Modules\PlatformAdministration\Contracts\AuditEntry\AuditEntryData;
use App\Modules\PlatformAdministration\Contracts\AuditEntry\AuditEntryRecorderInterface;
use App\Modules\PlatformAdministration\Contracts\AuditEntry\AuditOutcomeData;
use App\Modules\PlatformAdministration\Contracts\AuditEntry\AuditTargetData;
use App\Modules\PlatformAdministration\Domain\AuditEntry\ValueObjects\AuditActorType;
use App\Modules\PlatformAdministration\Domain\AuditEntry\ValueObjects\AuditOutcomeType;
use DateTimeImmutable;
use DateTimeZone;

final readonly class CommercialOfferAuditTrail
{
    private const string TARGET_TYPE = 'commercial.offer';

    public function __construct(private AuditEntryRecorderInterface $auditEntries) {}

    public function recordForPlatformIdentity(
        string $action,
        CommercialOffer $offer,
        DateTimeImmutable $occurredAt,
        string $correlationId,
    ): void {
        $this->record($action, $offer, $occurredAt, $correlationId, AuditActorType::PlatformIdentity, $offer->platformIdentity->value);
    }

    public function recordForSystem(
        string $action,
        CommercialOffer $offer,
        DateTimeImmutable $occurredAt,
        string $correlationId,
    ): void {
        $this->record($action, $offer, $occurredAt, $correlationId, AuditActorType::System, null);
    }

    private function record(
        string $action,
        CommercialOffer $offer,
        DateTimeImmutable $occurredAt,
        string $correlationId,
        AuditActorType $actorType,
        ?string $actorIdentityId,
    ): void {
        $occurredAt = $occurredAt->setTimezone(new DateTimeZone('UTC'));

        $this->auditEntries->record(new AuditEntryData(
            $this->auditEntryId($action, $offer->id->value, $occurredAt, $correlationId),
            $occurredAt,
            new AuditActorData($actorType->value, $actorIdentityId),
            null,
            $action,
            new AuditTargetData(self::TARGET_TYPE, $offer->id->value),
            new AuditOutcomeData(AuditOutcomeType::Succeeded->value, null),
            $correlationId,
            ['resource_type' => self::TARGET_TYPE],
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
