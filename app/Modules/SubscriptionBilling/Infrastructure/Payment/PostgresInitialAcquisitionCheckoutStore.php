<?php

declare(strict_types=1);

namespace App\Modules\SubscriptionBilling\Infrastructure\Payment;

use App\Modules\SubscriptionBilling\Contracts\Payment\InitialAcquisitionCheckoutState;
use App\Modules\SubscriptionBilling\Contracts\Payment\InitialAcquisitionCheckoutStoreInterface;
use App\Modules\SubscriptionBilling\Contracts\Renewal\ExpiryAuthority;
use App\Modules\SubscriptionBilling\Contracts\Renewal\PaymentSession;
use App\Modules\SubscriptionBilling\Contracts\Renewal\RedirectAction;
use DateTimeImmutable;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Str;
use LogicException;
use stdClass;

final readonly class PostgresInitialAcquisitionCheckoutStore implements InitialAcquisitionCheckoutStoreInterface
{
    public function __construct(private ConnectionInterface $connection) {}

    public function begin(
        string $clinicRegistrationReference,
        string $commercialOfferReference,
        string $paymentId,
        DateTimeImmutable $commercialOfferValidUntil,
        DateTimeImmutable $occurredAt,
    ): InitialAcquisitionCheckoutState {
        return $this->connection->transaction(function () use (
            $clinicRegistrationReference,
            $commercialOfferReference,
            $paymentId,
            $commercialOfferValidUntil,
            $occurredAt,
        ): InitialAcquisitionCheckoutState {
            $existing = $this->connection->table('initial_acquisition_checkout_sessions')
                ->where('clinic_registration_id', $clinicRegistrationReference)
                ->where('commercial_offer_id', $commercialOfferReference)
                ->lockForUpdate()
                ->first();

            if ($existing instanceof stdClass) {
                return $this->state($existing);
            }

            $timestamp = $occurredAt->format('Y-m-d H:i:s.uP');
            $id = (string) Str::uuid();
            $this->connection->table('initial_acquisition_checkout_sessions')->insert([
                'id' => $id,
                'clinic_registration_id' => $clinicRegistrationReference,
                'commercial_offer_id' => $commercialOfferReference,
                'payment_id' => $paymentId,
                'stage' => 'session_pending',
                'commercial_offer_valid_until' => $commercialOfferValidUntil->format('Y-m-d H:i:s.uP'),
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ]);

            return new InitialAcquisitionCheckoutState(
                $id,
                $clinicRegistrationReference,
                $commercialOfferReference,
                $paymentId,
                'session_pending',
            );
        });
    }

    public function sessionReady(
        string $applicationId,
        string $paymentId,
        PaymentSession $session,
        DateTimeImmutable $occurredAt,
    ): InitialAcquisitionCheckoutState {
        return $this->connection->transaction(function () use (
            $applicationId,
            $paymentId,
            $session,
            $occurredAt,
        ): InitialAcquisitionCheckoutState {
            $row = $this->connection->table('initial_acquisition_checkout_sessions')
                ->where('id', $applicationId)
                ->lockForUpdate()
                ->first();

            if (! $row instanceof stdClass || $row->payment_id !== $paymentId) {
                throw new LogicException('Initial acquisition checkout Payment lineage does not match.');
            }

            if ($row->stage === 'session_ready') {
                return $this->state($row);
            }

            $this->connection->table('initial_acquisition_checkout_sessions')
                ->where('id', $applicationId)
                ->update([
                    'stage' => 'session_ready',
                    'session_id' => $session->sessionId,
                    'redirect_destination' => $session->redirectAction->destination,
                    'session_expires_at' => $session->expiresAt?->format('Y-m-d H:i:s.uP'),
                    'expiry_authority' => $session->expiryAuthority->value,
                    'updated_at' => $occurredAt->format('Y-m-d H:i:s.uP'),
                ]);

            return $this->state(
                $this->connection->table('initial_acquisition_checkout_sessions')
                    ->where('id', $applicationId)
                    ->first(),
            );
        });
    }

    private function state(?stdClass $row): InitialAcquisitionCheckoutState
    {
        if (! $row instanceof stdClass) {
            throw new LogicException('Initial acquisition checkout storage state is missing.');
        }

        $session = $row->stage === 'session_ready'
            ? new PaymentSession(
                (string) $row->session_id,
                new RedirectAction((string) $row->redirect_destination),
                new DateTimeImmutable((string) $row->session_expires_at),
                ExpiryAuthority::from((string) $row->expiry_authority),
            )
            : null;

        return new InitialAcquisitionCheckoutState(
            (string) $row->id,
            (string) $row->clinic_registration_id,
            (string) $row->commercial_offer_id,
            (string) $row->payment_id,
            (string) $row->stage,
            $session,
        );
    }
}
