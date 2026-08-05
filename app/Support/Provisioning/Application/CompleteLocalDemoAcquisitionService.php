<?php

declare(strict_types=1);

namespace App\Support\Provisioning\Application;

use App\Modules\ClinicRegistration\Contracts\Checkout\CompleteLocalDemoAcquisitionInterface;
use App\Modules\ClinicRegistration\Contracts\Queries\ClinicRegistrationQueryInterface;
use App\Modules\Commercial\Application\PrepareCommercialOfferService;
use App\Modules\Commercial\Contracts\Commands\PrepareInitialCommercialOfferCommand;
use App\Modules\SubscriptionBilling\Application\Payment\CreateInitialAcquisitionPaymentService;
use App\Modules\SubscriptionBilling\Application\Subscription\ActivateSubscriptionFromVerifiedPaymentService;
use App\Modules\SubscriptionBilling\Contracts\Payment\CreateInitialAcquisitionPaymentCommand;
use App\Modules\SubscriptionBilling\Contracts\Payment\PaymentAuditInterface;
use App\Modules\SubscriptionBilling\Contracts\Payment\PaymentOutboxRepositoryInterface;
use App\Modules\SubscriptionBilling\Contracts\Payment\PaymentRepositoryInterface;
use App\Modules\SubscriptionBilling\Contracts\Payment\PaymentTransactionInterface;
use App\Modules\SubscriptionBilling\Domain\Aggregates\Payment\ValueObjects\PaymentId;
use App\Modules\SubscriptionBilling\Domain\Aggregates\Payment\ValueObjects\PaymentStatus;
use App\Modules\SubscriptionBilling\Domain\Aggregates\Payment\ValueObjects\ProviderReference;
use App\Support\Provisioning\Infrastructure\PublishSubscriptionProvisioningOutboxService;
use DateTimeImmutable;
use RuntimeException;

final readonly class CompleteLocalDemoAcquisitionService implements CompleteLocalDemoAcquisitionInterface
{
    public function __construct(
        private ClinicRegistrationQueryInterface $registrations,
        private PrepareCommercialOfferService $offers,
        private CreateInitialAcquisitionPaymentService $payments,
        private PaymentRepositoryInterface $paymentRepository,
        private PaymentOutboxRepositoryInterface $paymentOutbox,
        private PaymentAuditInterface $paymentAudit,
        private PaymentTransactionInterface $transactions,
        private ActivateSubscriptionFromVerifiedPaymentService $subscriptions,
        private PublishSubscriptionProvisioningOutboxService $subscriptionOutbox,
        private ProcessProvisioningWorkflowService $provisioning,
    ) {}

    public function execute(string $trackingCredential, string $correlationId): void
    {
        $now = new DateTimeImmutable;
        $registration = $this->registrations->currentForTrackingCredential($trackingCredential);
        if (in_array($registration?->status, ['provisioned', 'completed'], true)) {
            return;
        }

        if ($registration === null || $registration->status !== 'approved'
            || $registration->selectedPlanOfferingReference === null
            || $registration->reservedTenantId === null) {
            throw new RuntimeException('Approved Clinic Registration is unavailable for demo payment.');
        }

        $offer = $this->offers->executeForInitialAcquisition(new PrepareInitialCommercialOfferCommand(
            $trackingCredential,
            $registration->selectedPlanOfferingReference,
            $now,
            $correlationId,
        ));
        $paymentData = $this->payments->execute(new CreateInitialAcquisitionPaymentCommand(
            $registration->id,
            $offer->id,
            $registration->reservedTenantId,
            $now,
            $correlationId,
        ));
        $eventId = $this->identifier($paymentData->paymentId, 'demo-verified-payment');

        $this->transactions->run(function () use ($paymentData, $eventId, $now, $correlationId): void {
            $payment = $this->paymentRepository->find(new PaymentId($paymentData->paymentId))
                ?? throw new RuntimeException('Demo acquisition Payment could not be loaded.');

            if ($payment->status === PaymentStatus::Draft) {
                $attemptId = $this->identifier($payment->id->value, 'demo-attempt');
                $providerReference = new ProviderReference('demo_local', 'demo-'.$payment->id->value);
                $payment->start($attemptId, 'demo_local', $now);
                $payment->markPending($providerReference, $now);
                $payment->markSucceeded($now);
                $this->paymentRepository->save($payment);
                $this->paymentAudit->record('payment.succeeded', $payment, $now, $correlationId);
            } elseif ($payment->status !== PaymentStatus::Succeeded) {
                throw new RuntimeException('Existing acquisition Payment cannot be completed by the local demo flow.');
            }

            $this->paymentOutbox->add(
                $eventId,
                'VerifiedPaymentSucceeded',
                $payment->id->value,
                ['payment_id' => $payment->id->value, 'demo' => true],
                $now,
            );
        });

        $activation = $this->subscriptions->register(
            $eventId,
            $paymentData->paymentId,
            $registration->reservedTenantId,
            $now,
        );
        $this->subscriptions->execute($activation->id, $now);
        $this->subscriptionOutbox->publishNext();

        for ($step = 0; $step < 6; $step++) {
            if (! $this->provisioning->processNext()) {
                break;
            }
        }
    }

    private function identifier(string $source, string $purpose): string
    {
        $hex = substr(hash('sha256', $source.'|'.$purpose), 0, 32);
        $hex[12] = '5';
        $hex[16] = dechex((hexdec($hex[16]) & 3) | 8);

        return sprintf('%s-%s-%s-%s-%s', substr($hex, 0, 8), substr($hex, 8, 4), substr($hex, 12, 4), substr($hex, 16, 4), substr($hex, 20));
    }
}
