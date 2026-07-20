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
use App\Modules\SubscriptionBilling\Application\CommercialCatalogue\Exceptions\CommercialCatalogueResourceNotFoundException;
use App\Modules\SubscriptionBilling\Application\CommercialCatalogue\Exceptions\CommercialCatalogueVersionMismatchException;
use App\Modules\SubscriptionBilling\Contracts\CommercialCatalogue\UpdatePlanDetailsCommand;
use App\Modules\SubscriptionBilling\Contracts\Repositories\PlanRepositoryInterface;
use App\Modules\SubscriptionBilling\Domain\Aggregates\Subscription\ValueObjects\PlanId;
use App\Modules\SubscriptionBilling\Domain\CommercialCatalogue\Plan;
use App\Modules\SubscriptionBilling\Domain\CommercialCatalogue\ValueObjects\PlanName;
use DateTimeImmutable;
use DateTimeZone;

final readonly class UpdatePlanDetailsService
{
    private const string AUDIT_ACTION = 'commercial_catalogue.plan.update';

    private const string AUDIT_TARGET_TYPE = 'commercial_catalogue.plan';

    public function __construct(
        private PlanRepositoryInterface $plans,
        private AuditEntryRecorderInterface $auditEntries,
    ) {}

    public function execute(UpdatePlanDetailsCommand $command): Plan
    {
        $plan = $this->requirePlan($command->planId);
        $this->assertExpectedVersion($plan, $command->expectedVersion);

        $updated = new Plan(
            $plan->id,
            new PlanName($command->name),
            $plan->code,
            $command->description,
            $plan->lifecycle,
            $command->displayOrder,
            $plan->createdAt,
            self::instant($command->occurredAt),
            $plan->version(),
        );

        $this->plans->save($updated);
        $this->recordAudit($updated, $command->occurredAt, $command->actorPlatformIdentityId, $command->correlationId);

        return $updated;
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
                    'name,description,display_order',
                ),
            ],
        ));
    }

    private function requirePlan(string $planId): Plan
    {
        $plan = $this->plans->findById(new PlanId($planId));

        if ($plan === null) {
            throw new CommercialCatalogueResourceNotFoundException('Plan', $planId);
        }

        return $plan;
    }

    private function assertExpectedVersion(Plan $plan, int $expectedVersion): void
    {
        if ($plan->version() !== $expectedVersion) {
            throw new CommercialCatalogueVersionMismatchException(
                'Plan',
                $plan->id->value,
                $expectedVersion,
                $plan->version(),
            );
        }
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
