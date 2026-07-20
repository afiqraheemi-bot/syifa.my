<?php

declare(strict_types=1);

namespace App\Modules\SubscriptionBilling\Application\CommercialCatalogue;

use App\Modules\PlatformAdministration\Contracts\AuditEntry\AuditActorData;
use App\Modules\PlatformAdministration\Contracts\AuditEntry\AuditEntryData;
use App\Modules\PlatformAdministration\Contracts\AuditEntry\AuditEntryRecorderInterface;
use App\Modules\PlatformAdministration\Contracts\AuditEntry\AuditOutcomeData;
use App\Modules\PlatformAdministration\Contracts\AuditEntry\AuditTargetData;
use App\Modules\PlatformAdministration\Domain\AuditEntry\ValueObjects\AuditActorType;
use App\Modules\PlatformAdministration\Domain\AuditEntry\ValueObjects\AuditOutcomeType;
use App\Modules\SubscriptionBilling\Contracts\CommercialCatalogue\CreatePlanCommand;
use App\Modules\SubscriptionBilling\Contracts\Repositories\PlanRepositoryInterface;
use App\Modules\SubscriptionBilling\Domain\Aggregates\Subscription\ValueObjects\PlanId;
use App\Modules\SubscriptionBilling\Domain\CommercialCatalogue\Plan;
use App\Modules\SubscriptionBilling\Domain\CommercialCatalogue\ValueObjects\PlanCode;
use App\Modules\SubscriptionBilling\Domain\CommercialCatalogue\ValueObjects\PlanLifecycle;
use App\Modules\SubscriptionBilling\Domain\CommercialCatalogue\ValueObjects\PlanName;
use DateTimeImmutable;
use DateTimeZone;

final readonly class CreatePlanService
{
    private const string AUDIT_ACTION = 'commercial_catalogue.plan.create';

    private const string AUDIT_TARGET_TYPE = 'commercial_catalogue.plan';

    public function __construct(
        private CommercialCatalogueIdentifierGeneratorInterface $identifiers,
        private PlanRepositoryInterface $plans,
        private AuditEntryRecorderInterface $auditEntries,
    ) {}

    public function execute(CreatePlanCommand $command): Plan
    {
        $occurredAt = self::instant($command->occurredAt);

        $plan = new Plan(
            new PlanId($this->identifiers->generate()),
            new PlanName($command->name),
            new PlanCode($command->code),
            $command->description,
            PlanLifecycle::draft(),
            $command->displayOrder,
            $occurredAt,
            $occurredAt,
        );

        $this->plans->save($plan);
        $this->recordAudit($plan, $command->occurredAt, $command->actorPlatformIdentityId, $command->correlationId);

        return $plan;
    }

    private function recordAudit(Plan $plan, string $occurredAt, string $actorPlatformIdentityId, string $correlationId): void
    {
        $this->auditEntries->record(new AuditEntryData(
            self::auditEntryId($occurredAt, $actorPlatformIdentityId, $correlationId, $plan->id->value),
            self::instant($occurredAt),
            new AuditActorData(AuditActorType::PlatformIdentity->value, $actorPlatformIdentityId),
            null,
            self::AUDIT_ACTION,
            new AuditTargetData(self::AUDIT_TARGET_TYPE, $plan->id->value),
            new AuditOutcomeData(AuditOutcomeType::Succeeded->value, null),
            $correlationId,
            [
                'resource_type' => self::AUDIT_TARGET_TYPE,
                'resource_label' => $plan->code->value,
                'target_label' => sprintf(
                    'version=%d;changed_fields=%s',
                    $plan->version(),
                    'code,name,description,display_order,status',
                ),
            ],
        ));
    }

    private static function instant(string $value): DateTimeImmutable
    {
        return new DateTimeImmutable($value, new DateTimeZone('UTC'));
    }

    private static function auditEntryId(string $occurredAt, string $actorPlatformIdentityId, string $correlationId, string $targetId): string
    {
        $hex = substr(hash('sha256', implode('|', [self::AUDIT_ACTION, $targetId, $occurredAt, $actorPlatformIdentityId, $correlationId])), 0, 32);
        $hex[12] = '5';
        $hex[16] = dechex((hexdec($hex[16]) & 0x3) | 0x8);

        return sprintf(
            '%s-%s-%s-%s-%s',
            substr($hex, 0, 8),
            substr($hex, 8, 4),
            substr($hex, 12, 4),
            substr($hex, 16, 4),
            substr($hex, 20, 12),
        );
    }
}
