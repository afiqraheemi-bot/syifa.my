<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\SubscriptionBilling\Application\Payment;

use App\Modules\Commercial\Contracts\Checkout\CommercialOfferCheckoutInterface;
use App\Modules\Commercial\Contracts\Commands\ClaimCommercialOfferCommand;
use App\Modules\Commercial\Contracts\Data\CommercialOfferData;
use App\Modules\PlatformAdministration\Contracts\Authentication\PlatformPrincipal;
use App\Modules\SubscriptionBilling\Application\Payment\ClaimCommercialOfferService;
use App\Modules\SubscriptionBilling\Application\Payment\CreateInitialAcquisitionPaymentService;
use App\Modules\SubscriptionBilling\Application\Payment\CreatePaymentService;
use App\Modules\SubscriptionBilling\Application\Payment\Exceptions\CommercialOfferMissingTenantIdException;
use App\Modules\SubscriptionBilling\Application\Payment\Exceptions\CommercialOfferUnavailableForPaymentException;
use App\Modules\SubscriptionBilling\Application\Payment\Exceptions\UnauthorizedPaymentInitiationException;
use App\Modules\SubscriptionBilling\Application\Payment\PaymentDataAssembler;
use App\Modules\SubscriptionBilling\Application\Payment\PaymentIdentifierGeneratorInterface;
use App\Modules\SubscriptionBilling\Application\Payment\StartPaymentService;
use App\Modules\SubscriptionBilling\Contracts\Payment\CreateInitialAcquisitionPaymentCommand;
use App\Modules\SubscriptionBilling\Contracts\Payment\CreatePaymentCommand;
use App\Modules\SubscriptionBilling\Contracts\Payment\PaymentAuditInterface;
use App\Modules\SubscriptionBilling\Contracts\Payment\PaymentProviderInterface;
use App\Modules\SubscriptionBilling\Contracts\Payment\PaymentProviderRegistryInterface;
use App\Modules\SubscriptionBilling\Contracts\Payment\PaymentRepositoryInterface;
use App\Modules\SubscriptionBilling\Contracts\Payment\PaymentTransactionInterface;
use App\Modules\SubscriptionBilling\Contracts\Payment\PaymentTransitionCommand;
use App\Modules\SubscriptionBilling\Contracts\Payment\ProviderConfigurationVerification;
use App\Modules\SubscriptionBilling\Contracts\Payment\ProviderPaymentRequest;
use App\Modules\SubscriptionBilling\Contracts\Payment\ProviderPaymentResult;
use App\Modules\SubscriptionBilling\Contracts\Payment\ProviderPaymentVerification;
use App\Modules\SubscriptionBilling\Contracts\Payment\ProviderPaymentVerificationRequest;
use App\Modules\SubscriptionBilling\Contracts\Payment\ProviderWebhookEvent;
use App\Modules\SubscriptionBilling\Domain\Aggregates\Payment\Payment;
use App\Modules\SubscriptionBilling\Domain\Aggregates\Payment\ValueObjects\IdempotencyKey;
use App\Modules\SubscriptionBilling\Domain\Aggregates\Payment\ValueObjects\PaymentId;
use App\Modules\SubscriptionBilling\Domain\Aggregates\Payment\ValueObjects\ProviderReference;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class PaymentApplicationServicesTest extends TestCase
{
    public function test_create_payment_claims_commercial_offer_and_records_audit(): void
    {
        $checkout = new InMemoryCommercialOfferCheckout($this->offer());
        $repository = new InMemoryPaymentRepository;
        $audit = new RecordingPaymentAudit;

        $created = $this->createService($checkout, $repository, $audit)->execute(
            new PlatformPrincipal($this->uuid(2), 'super_admin', 'Afiq'),
            new CreatePaymentCommand($this->uuid(11), 'idem-1', $this->time(), $this->uuid(90)),
        );

        self::assertSame($this->uuid(20), $created->paymentId);
        self::assertSame('draft', $created->status);
        self::assertSame($this->uuid(20), $checkout->claimedPaymentId);
        self::assertSame(['payment.create'], $audit->actions);

        $duplicate = $this->createService($checkout, $repository, $audit)->execute(
            new PlatformPrincipal($this->uuid(2), 'super_admin', 'Afiq'),
            new CreatePaymentCommand($this->uuid(11), 'idem-1', $this->time(), $this->uuid(91)),
        );

        self::assertSame($created->paymentId, $duplicate->paymentId);
        self::assertSame(1, $checkout->claimCalls);

        $stored = $repository->find(new PaymentId($created->paymentId));
        self::assertSame($this->uuid(6), $stored?->tenantId?->value);
    }

    public function test_create_payment_fails_closed_when_commercial_offer_has_no_tenant_id(): void
    {
        $checkout = new InMemoryCommercialOfferCheckout($this->offer(withTenantId: false));

        $this->expectException(CommercialOfferMissingTenantIdException::class);

        $this->createService($checkout, new InMemoryPaymentRepository, new RecordingPaymentAudit)->execute(
            new PlatformPrincipal($this->uuid(2), 'super_admin', 'Afiq'),
            new CreatePaymentCommand($this->uuid(11), 'idem-1', $this->time(), $this->uuid(90)),
        );
    }

    public function test_create_payment_rejects_untrusted_platform_principal(): void
    {
        $this->expectException(UnauthorizedPaymentInitiationException::class);

        $this->createService(new InMemoryCommercialOfferCheckout($this->offer()), new InMemoryPaymentRepository, new RecordingPaymentAudit)
            ->execute(
                new PlatformPrincipal($this->uuid(99), 'super_admin', 'Afiq'),
                new CreatePaymentCommand($this->uuid(11), 'idem-1', $this->time(), $this->uuid(90)),
            );
    }

    public function test_initial_acquisition_creates_and_reuses_registration_owned_payment_without_attempt(): void
    {
        $checkout = new InMemoryCommercialOfferCheckout($this->acquisitionOffer());
        $repository = new InMemoryPaymentRepository;
        $audit = new RecordingPaymentAudit;
        $service = new CreateInitialAcquisitionPaymentService(
            new SequentialPaymentIdentifierGenerator([$this->uuid(25)]),
            $checkout,
            new ClaimCommercialOfferService($checkout),
            $repository,
            new PaymentDataAssembler,
            $audit,
            new ImmediatePaymentTransaction,
        );
        $command = new CreateInitialAcquisitionPaymentCommand(
            $this->uuid(3),
            $this->uuid(11),
            $this->uuid(6),
            $this->time(),
            $this->uuid(92),
        );

        $created = $service->execute($command);
        $duplicate = $service->execute($command);

        self::assertSame($created->paymentId, $duplicate->paymentId);
        self::assertSame($this->uuid(3), $created->clinicRegistrationId);
        self::assertNull($created->platformIdentityId);
        self::assertSame($this->uuid(6), $created->tenantId);
        self::assertSame(3000, $created->amountMinor);
        self::assertSame('MYR', $created->currency);
        self::assertSame(1, $checkout->claimCalls);
        self::assertSame(['payment.create_initial_acquisition'], $audit->actions);
        self::assertSame([], $repository->find(new PaymentId($created->paymentId))?->attempts);
    }

    public function test_initial_acquisition_rejects_foreign_registration(): void
    {
        $this->expectException(CommercialOfferUnavailableForPaymentException::class);

        (new CreateInitialAcquisitionPaymentService(
            new SequentialPaymentIdentifierGenerator([$this->uuid(25)]),
            $checkout = new InMemoryCommercialOfferCheckout($this->acquisitionOffer()),
            new ClaimCommercialOfferService($checkout),
            new InMemoryPaymentRepository,
            new PaymentDataAssembler,
            new RecordingPaymentAudit,
            new ImmediatePaymentTransaction,
        ))->execute(new CreateInitialAcquisitionPaymentCommand(
            $this->uuid(99),
            $this->uuid(11),
            $this->uuid(6),
            $this->time(),
            $this->uuid(93),
        ));
    }

    public function test_start_payment_uses_provider_neutral_contract_and_records_audit(): void
    {
        $checkout = new InMemoryCommercialOfferCheckout($this->offer());
        $repository = new InMemoryPaymentRepository;
        $audit = new RecordingPaymentAudit;
        $payment = $this->createService($checkout, $repository, $audit)->execute(
            new PlatformPrincipal($this->uuid(2), 'super_admin', 'Afiq'),
            new CreatePaymentCommand($this->uuid(11), 'idem-1', $this->time(), $this->uuid(90)),
        );

        $started = (new StartPaymentService(
            new SequentialPaymentIdentifierGenerator([$this->uuid(21)]),
            $repository,
            new RecordingPaymentProvider,
            new PaymentDataAssembler,
            $audit,
            new ImmediatePaymentTransaction,
        ))->execute(new PaymentTransitionCommand($payment->paymentId, 1, $this->time('+1 minute'), $this->uuid(91)));

        self::assertSame('pending', $started->status);
        self::assertSame('provider-neutral', $started->providerKey);
        self::assertSame(['payment.create', 'payment.start', 'payment.pending'], $audit->actions);
    }

    private function createService(InMemoryCommercialOfferCheckout $checkout, InMemoryPaymentRepository $repository, RecordingPaymentAudit $audit): CreatePaymentService
    {
        return new CreatePaymentService(
            new SequentialPaymentIdentifierGenerator([$this->uuid(20)]),
            $checkout,
            new ClaimCommercialOfferService($checkout),
            $repository,
            new PaymentDataAssembler,
            $audit,
            new ImmediatePaymentTransaction,
        );
    }

    private function offer(string $status = 'prepared', bool $withTenantId = true): CommercialOfferData
    {
        return new CommercialOfferData(
            id: $this->uuid(11),
            platformIdentityId: $this->uuid(2),
            clinicRegistrationId: $this->uuid(3),
            tenantId: $withTenantId ? $this->uuid(6) : null,
            status: $status,
            planOfferingId: 'offering-basic-monthly',
            planId: 'plan-basic',
            billingCycleId: 'monthly',
            billingPeriodStart: '2026-07-21',
            billingPeriodEnd: '2026-08-20',
            offeringConfigurationVersion: 'catalogue-v1',
            capabilityConfigurationReference: 'capability-v1',
            subtotalAmountMinor: 3000,
            totalAmountMinor: 3000,
            currency: 'MYR',
            expiresAt: '2026-07-21T00:30:00+00:00',
            claimedPaymentId: null,
            claimedAt: null,
            cancelledAt: null,
            expiredAt: null,
            version: 1,
            lineItems: [],
        );
    }

    private function acquisitionOffer(): CommercialOfferData
    {
        $offer = $this->offer();

        return new CommercialOfferData(
            id: $offer->id,
            platformIdentityId: null,
            clinicRegistrationId: $offer->clinicRegistrationId,
            tenantId: $offer->tenantId,
            status: $offer->status,
            planOfferingId: $offer->planOfferingId,
            planId: $offer->planId,
            billingCycleId: $offer->billingCycleId,
            billingPeriodStart: $offer->billingPeriodStart,
            billingPeriodEnd: $offer->billingPeriodEnd,
            offeringConfigurationVersion: $offer->offeringConfigurationVersion,
            capabilityConfigurationReference: $offer->capabilityConfigurationReference,
            subtotalAmountMinor: $offer->subtotalAmountMinor,
            totalAmountMinor: $offer->totalAmountMinor,
            currency: $offer->currency,
            expiresAt: $offer->expiresAt,
            claimedPaymentId: null,
            claimedAt: null,
            cancelledAt: null,
            expiredAt: null,
            version: $offer->version,
            lineItems: $offer->lineItems,
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

final class SequentialPaymentIdentifierGenerator implements PaymentIdentifierGeneratorInterface
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

final class InMemoryCommercialOfferCheckout implements CommercialOfferCheckoutInterface
{
    public ?string $claimedPaymentId = null;

    public int $claimCalls = 0;

    public function __construct(private CommercialOfferData $offer) {}

    public function offerForCheckout(string $commercialOfferId, string $trustedConsumer, DateTimeImmutable $occurredAt): ?CommercialOfferData
    {
        return $commercialOfferId === $this->offer->id ? $this->offer : null;
    }

    public function initialAcquisitionOfferForCheckout(
        string $commercialOfferId,
        string $clinicRegistrationReference,
        string $trustedConsumer,
        DateTimeImmutable $occurredAt,
    ): ?CommercialOfferData {
        return $commercialOfferId === $this->offer->id
            && $clinicRegistrationReference === $this->offer->clinicRegistrationId
            && $this->offer->platformIdentityId === null
                ? $this->offer
                : null;
    }

    public function claim(ClaimCommercialOfferCommand $command): CommercialOfferData
    {
        if ($this->claimedPaymentId !== null && $this->claimedPaymentId !== $command->paymentId) {
            throw new RuntimeException('Commercial Offer already claimed.');
        }

        $this->claimCalls++;
        $this->claimedPaymentId = $command->paymentId;
        $this->offer = new CommercialOfferData(
            id: $this->offer->id,
            platformIdentityId: $this->offer->platformIdentityId,
            clinicRegistrationId: $this->offer->clinicRegistrationId,
            tenantId: $this->offer->tenantId,
            status: 'claimed',
            planOfferingId: $this->offer->planOfferingId,
            planId: $this->offer->planId,
            billingCycleId: $this->offer->billingCycleId,
            billingPeriodStart: $this->offer->billingPeriodStart,
            billingPeriodEnd: $this->offer->billingPeriodEnd,
            offeringConfigurationVersion: $this->offer->offeringConfigurationVersion,
            capabilityConfigurationReference: $this->offer->capabilityConfigurationReference,
            subtotalAmountMinor: $this->offer->subtotalAmountMinor,
            totalAmountMinor: $this->offer->totalAmountMinor,
            currency: $this->offer->currency,
            expiresAt: $this->offer->expiresAt,
            claimedPaymentId: $command->paymentId,
            claimedAt: $command->occurredAt->format(DATE_ATOM),
            cancelledAt: null,
            expiredAt: null,
            version: $this->offer->version + 1,
            lineItems: [],
        );

        return $this->offer;
    }
}

final class InMemoryPaymentRepository implements PaymentRepositoryInterface
{
    /** @var array<string, Payment> */
    private array $payments = [];

    public function find(PaymentId $paymentId): ?Payment
    {
        return $this->payments[$paymentId->value] ?? null;
    }

    public function findByIdempotencyKey(IdempotencyKey $idempotencyKey): ?Payment
    {
        foreach ($this->payments as $payment) {
            if ($payment->idempotencyKey->value === $idempotencyKey->value) {
                return $payment;
            }
        }

        return null;
    }

    public function findByProviderReference(ProviderReference $providerReference): ?Payment
    {
        foreach ($this->payments as $payment) {
            if ($payment->providerReference?->providerKey === $providerReference->providerKey
                && $payment->providerReference?->providerPaymentReference === $providerReference->providerPaymentReference) {
                return $payment;
            }
        }

        return null;
    }

    public function save(Payment $payment): void
    {
        $payment->synchronizeVersion($payment->version() + 1);
        $this->payments[$payment->id->value] = $payment;
    }
}

final class RecordingPaymentAudit implements PaymentAuditInterface
{
    /** @var list<string> */
    public array $actions = [];

    public function record(string $action, Payment $payment, DateTimeImmutable $occurredAt, string $correlationId): void
    {
        $this->actions[] = $action;
    }
}

final class RecordingPaymentProvider implements PaymentProviderInterface, PaymentProviderRegistryInterface
{
    public function providerKey(): string
    {
        return 'provider-neutral';
    }

    public function start(ProviderPaymentRequest $request): ProviderPaymentResult
    {
        return new ProviderPaymentResult('provider-neutral', 'provider-payment-reference');
    }

    public function defaultForNewAttempt(): PaymentProviderInterface
    {
        return $this;
    }

    public function forNewAttempt(string $providerKey): PaymentProviderInterface
    {
        return $this;
    }

    public function forExistingAttempt(string $providerKey): PaymentProviderInterface
    {
        return $this;
    }

    public function verify(ProviderPaymentVerificationRequest $request): ProviderPaymentVerification
    {
        throw new \LogicException;
    }

    public function verifyWebhook(string $rawPayload, array $headers): ProviderWebhookEvent
    {
        throw new \LogicException;
    }

    public function credentialsConfigured(): bool
    {
        return true;
    }

    public function verifyConfiguration(): ProviderConfigurationVerification
    {
        return new ProviderConfigurationVerification(true, 'verified');
    }
}

final class ImmediatePaymentTransaction implements PaymentTransactionInterface
{
    public function run(callable $operation): mixed
    {
        return $operation();
    }
}
