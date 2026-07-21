<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Commercial\Application;

use App\Modules\Commercial\Application\Audit\CommercialOfferAuditTrail;
use App\Modules\Commercial\Application\CancelCommercialOfferService;
use App\Modules\Commercial\Application\ClaimCommercialOfferService;
use App\Modules\Commercial\Application\CommercialOfferDataAssembler;
use App\Modules\Commercial\Application\CommercialOfferIdentifierGeneratorInterface;
use App\Modules\Commercial\Application\Exceptions\CommercialOfferVersionMismatchException;
use App\Modules\Commercial\Application\Exceptions\CommercialSelectionUnavailableException;
use App\Modules\Commercial\Application\Exceptions\UntrustedCommercialOfferConsumerException;
use App\Modules\Commercial\Application\ListAvailableCommercialOffersService;
use App\Modules\Commercial\Application\PrepareCommercialOfferService;
use App\Modules\Commercial\Application\ResolveCommercialSelectionService;
use App\Modules\Commercial\Application\TrustedCommercialOfferConsumers;
use App\Modules\Commercial\Application\ViewCurrentCommercialOfferService;
use App\Modules\Commercial\Contracts\Commands\CancelCommercialOfferCommand;
use App\Modules\Commercial\Contracts\Commands\ClaimCommercialOfferCommand;
use App\Modules\Commercial\Contracts\Commands\PrepareCommercialOfferCommand;
use App\Modules\Commercial\Contracts\Events\CommercialOfferEventPublisherInterface;
use App\Modules\Commercial\Contracts\ReferenceData\PlanOfferingQueryInterface;
use App\Modules\Commercial\Contracts\ReferenceData\PlanOfferingReferenceData;
use App\Modules\Commercial\Contracts\Repositories\CommercialOfferRepositoryInterface;
use App\Modules\Commercial\Contracts\Transactions\CommercialTransactionInterface;
use App\Modules\Commercial\Domain\CommercialOffer;
use App\Modules\Commercial\Domain\ValueObjects\CommercialOfferId;
use App\Modules\Commercial\Domain\ValueObjects\CommercialOfferStatus;
use App\Modules\Commercial\Domain\ValueObjects\PlatformIdentityReference;
use App\Modules\PlatformAdministration\Contracts\AuditEntry\AuditEntryData;
use App\Modules\PlatformAdministration\Contracts\AuditEntry\AuditEntryRecorderInterface;
use App\Modules\PlatformAdministration\Domain\AuditEntry\AuditEntry;
use App\Modules\PlatformAdministration\Domain\AuditEntry\ValueObjects\AuditActorType;
use App\Modules\PlatformAdministration\Domain\AuditEntry\ValueObjects\AuditEntryId;
use App\Modules\PlatformAdministration\Domain\AuditEntry\ValueObjects\AuditOutcomeType;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class CommercialOfferApplicationServicesTest extends TestCase
{
    public function test_list_resolve_prepare_cancel_and_claim_flow_records_audit(): void
    {
        $referenceData = new RecordingPlanOfferingQuery([$this->offering()]);
        $repository = new InMemoryCommercialOfferRepository;
        $audit = new RecordingCommercialAuditEntryRecorder;
        $events = new RecordingCommercialEventPublisher;
        $transaction = new RecordingCommercialTransaction;

        $available = (new ListAvailableCommercialOffersService($referenceData))->execute($this->time());
        self::assertCount(1, $available);

        $prepare = new PrepareCommercialOfferService(
            new SequentialCommercialOfferIdentifierGenerator([$this->uuid(11)]),
            $repository,
            new ResolveCommercialSelectionService($referenceData),
            new CommercialOfferDataAssembler,
            new CommercialOfferAuditTrail($audit),
            $events,
            $transaction,
            30,
        );
        $prepared = $prepare->execute(new PrepareCommercialOfferCommand(
            $this->uuid(2),
            $this->uuid(3),
            'offering-basic-monthly',
            $this->time(),
            $this->uuid(90),
        ));

        self::assertSame('prepared', $prepared->status);
        self::assertSame(1, $repository->saveCalls);
        self::assertSame(1, $transaction->calls);

        $viewed = (new ViewCurrentCommercialOfferService($repository, new CommercialOfferDataAssembler))->execute($this->uuid(2));
        self::assertSame($prepared->id, $viewed?->id);

        $cancelled = (new CancelCommercialOfferService(
            $repository,
            new CommercialOfferDataAssembler,
            new CommercialOfferAuditTrail($audit),
            $events,
            $transaction,
        ))->execute(new CancelCommercialOfferCommand($this->uuid(2), $prepared->id, 1, $this->time(), $this->uuid(91)));

        self::assertSame('cancelled', $cancelled->status);

        $repository = new InMemoryCommercialOfferRepository;
        $audit = new RecordingCommercialAuditEntryRecorder;
        $prepared = $prepare = new PrepareCommercialOfferService(
            new SequentialCommercialOfferIdentifierGenerator([$this->uuid(12)]),
            $repository,
            new ResolveCommercialSelectionService($referenceData),
            new CommercialOfferDataAssembler,
            new CommercialOfferAuditTrail($audit),
            $events,
            $transaction,
            30,
        );
        $offer = $prepared->execute(new PrepareCommercialOfferCommand($this->uuid(2), $this->uuid(3), 'offering-basic-monthly', $this->time(), $this->uuid(92)));

        $claimed = (new ClaimCommercialOfferService(
            $repository,
            new CommercialOfferDataAssembler,
            new CommercialOfferAuditTrail($audit),
            $events,
            new TrustedCommercialOfferConsumers(['payment']),
            $transaction,
        ))->claim(new ClaimCommercialOfferCommand($offer->id, $this->uuid(15), 'payment', 1, $this->time('+10 minutes'), $this->uuid(93)));

        self::assertSame('claimed', $claimed->status);
        self::assertSame($this->uuid(15), $claimed->claimedPaymentId);
        self::assertSame([
            'commercial.offer.prepare',
            'commercial.offer.claim',
        ], array_map(static fn (AuditEntryData $entry): string => $entry->action, $audit->entries));
    }

    public function test_unavailable_selection_and_untrusted_consumer_fail_closed(): void
    {
        $this->expectException(CommercialSelectionUnavailableException::class);
        (new ResolveCommercialSelectionService(new RecordingPlanOfferingQuery([])))->execute('missing', $this->time());
    }

    public function test_untrusted_consumer_is_rejected(): void
    {
        $this->expectException(UntrustedCommercialOfferConsumerException::class);

        (new ClaimCommercialOfferService(
            new InMemoryCommercialOfferRepository,
            new CommercialOfferDataAssembler,
            new CommercialOfferAuditTrail(new RecordingCommercialAuditEntryRecorder),
            new RecordingCommercialEventPublisher,
            new TrustedCommercialOfferConsumers(['payment']),
            new RecordingCommercialTransaction,
        ))->offerForCheckout($this->uuid(1), 'not_payment', $this->time());
    }

    public function test_version_mismatch_rejects_stale_cancel(): void
    {
        $referenceData = new RecordingPlanOfferingQuery([$this->offering()]);
        $repository = new InMemoryCommercialOfferRepository;
        $offer = (new PrepareCommercialOfferService(
            new SequentialCommercialOfferIdentifierGenerator([$this->uuid(13)]),
            $repository,
            new ResolveCommercialSelectionService($referenceData),
            new CommercialOfferDataAssembler,
            new CommercialOfferAuditTrail(new RecordingCommercialAuditEntryRecorder),
            new RecordingCommercialEventPublisher,
            new RecordingCommercialTransaction,
            30,
        ))->execute(new PrepareCommercialOfferCommand($this->uuid(2), $this->uuid(3), 'offering-basic-monthly', $this->time(), $this->uuid(94)));

        $this->expectException(CommercialOfferVersionMismatchException::class);

        (new CancelCommercialOfferService(
            $repository,
            new CommercialOfferDataAssembler,
            new CommercialOfferAuditTrail(new RecordingCommercialAuditEntryRecorder),
            new RecordingCommercialEventPublisher,
            new RecordingCommercialTransaction,
        ))->execute(new CancelCommercialOfferCommand($this->uuid(2), $offer->id, 99, $this->time(), $this->uuid(95)));
    }

    public function test_audit_failure_rolls_back_transaction_boundary(): void
    {
        $transaction = new RecordingCommercialTransaction;

        $this->expectException(RuntimeException::class);

        (new PrepareCommercialOfferService(
            new SequentialCommercialOfferIdentifierGenerator([$this->uuid(14)]),
            new InMemoryCommercialOfferRepository,
            new ResolveCommercialSelectionService(new RecordingPlanOfferingQuery([$this->offering()])),
            new CommercialOfferDataAssembler,
            new CommercialOfferAuditTrail(new FailingCommercialAuditEntryRecorder),
            new RecordingCommercialEventPublisher,
            $transaction,
            30,
        ))->execute(new PrepareCommercialOfferCommand($this->uuid(2), $this->uuid(3), 'offering-basic-monthly', $this->time(), $this->uuid(96)));
    }

    private function offering(): PlanOfferingReferenceData
    {
        return new PlanOfferingReferenceData(
            'offering-basic-monthly',
            'plan-basic',
            'monthly',
            'Basic',
            'Monthly',
            3000,
            'MYR',
            '2026-07-21',
            '2026-08-20',
            'catalogue-v1',
            'capability-v1',
            1,
        );
    }

    private function time(string $modifier = ''): DateTimeImmutable
    {
        $time = new DateTimeImmutable('2026-07-21T00:00:00Z');

        return $modifier === '' ? $time : $time->modify($modifier);
    }

    private function uuid(int $suffix): string
    {
        return sprintf('00000000-0000-4000-8000-%012d', $suffix);
    }
}

final class RecordingPlanOfferingQuery implements PlanOfferingQueryInterface
{
    /**
     * @param  list<PlanOfferingReferenceData>  $offerings
     */
    public function __construct(private array $offerings) {}

    public function listAvailable(string $effectiveDate): array
    {
        return $this->offerings;
    }

    public function resolveForCheckout(string $planOfferingId, string $effectiveDate): ?PlanOfferingReferenceData
    {
        foreach ($this->offerings as $offering) {
            if ($offering->planOfferingId === $planOfferingId) {
                return $offering;
            }
        }

        return null;
    }
}

final class SequentialCommercialOfferIdentifierGenerator implements CommercialOfferIdentifierGeneratorInterface
{
    /**
     * @param  list<string>  $ids
     */
    public function __construct(private array $ids) {}

    public function generate(): string
    {
        return array_shift($this->ids) ?? '00000000-0000-4000-8000-000000999999';
    }
}

final class RecordingCommercialEventPublisher implements CommercialOfferEventPublisherInterface
{
    public function publish(array $events): void
    {
        //
    }
}

final class RecordingCommercialTransaction implements CommercialTransactionInterface
{
    public int $calls = 0;

    public function run(callable $operation): mixed
    {
        $this->calls++;

        return $operation();
    }
}

final class RecordingCommercialAuditEntryRecorder implements AuditEntryRecorderInterface
{
    /**
     * @var list<AuditEntryData>
     */
    public array $entries = [];

    public function record(AuditEntryData $auditEntry): AuditEntry
    {
        $this->entries[] = $auditEntry;

        return AuditEntry::record(
            new AuditEntryId($auditEntry->auditEntryId),
            $auditEntry->occurredAt,
            AuditActorType::from($auditEntry->actor->type),
            $auditEntry->actor->identityId,
            $auditEntry->tenantId,
            $auditEntry->action,
            $auditEntry->target->type,
            $auditEntry->target->id,
            AuditOutcomeType::from($auditEntry->outcome->outcome),
            $auditEntry->outcome->reasonCode,
            $auditEntry->correlationId,
            $auditEntry->safeMetadata,
        );
    }
}

final class FailingCommercialAuditEntryRecorder implements AuditEntryRecorderInterface
{
    public function record(AuditEntryData $auditEntry): AuditEntry
    {
        throw new RuntimeException('audit failed');
    }
}

final class InMemoryCommercialOfferRepository implements CommercialOfferRepositoryInterface
{
    /**
     * @var array<string, CommercialOffer>
     */
    private array $offers = [];

    public int $saveCalls = 0;

    public function find(CommercialOfferId $commercialOfferId): ?CommercialOffer
    {
        return $this->offers[$commercialOfferId->value] ?? null;
    }

    public function findCurrentForPlatformIdentity(PlatformIdentityReference $platformIdentity): ?CommercialOffer
    {
        foreach ($this->offers as $offer) {
            if ($offer->platformIdentity->value === $platformIdentity->value && $offer->status === CommercialOfferStatus::Prepared) {
                return $offer;
            }
        }

        return null;
    }

    public function save(CommercialOffer $commercialOffer): void
    {
        $this->saveCalls++;
        $commercialOffer->synchronizeVersion($commercialOffer->version() + 1);
        $this->offers[$commercialOffer->id->value] = $commercialOffer;
    }
}
