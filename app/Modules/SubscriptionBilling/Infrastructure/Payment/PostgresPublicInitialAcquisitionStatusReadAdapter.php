<?php

declare(strict_types=1);

namespace App\Modules\SubscriptionBilling\Infrastructure\Payment;

use App\Modules\ClinicRegistration\Contracts\Checkout\PublicInitialAcquisitionStatusData;
use App\Modules\ClinicRegistration\Contracts\Checkout\PublicInitialAcquisitionStatusReadInterface;
use DateTimeImmutable;
use Illuminate\Database\ConnectionInterface;
use RuntimeException;
use Throwable;

final readonly class PostgresPublicInitialAcquisitionStatusReadAdapter implements PublicInitialAcquisitionStatusReadInterface
{
    public function __construct(private ConnectionInterface $connection) {}

    public function forRegistration(string $clinicRegistrationReference): ?PublicInitialAcquisitionStatusData
    {
        $row = $this->connection->table('initial_acquisition_checkout_sessions as checkout')
            ->join('payments as payment', 'payment.id', '=', 'checkout.payment_id')
            ->where('checkout.clinic_registration_id', $clinicRegistrationReference)
            ->orderByDesc('checkout.created_at')
            ->select([
                'payment.status',
                'payment.amount_minor',
                'payment.currency',
                'payment.domain_last_changed_at',
            ])
            ->first();

        if ($row === null) {
            return null;
        }

        try {
            $amountMinor = filter_var($row->amount_minor, FILTER_VALIDATE_INT);
            if (! is_int($amountMinor) || $amountMinor <= 0) {
                throw new RuntimeException('Initial acquisition payment amount is invalid.');
            }

            return new PublicInitialAcquisitionStatusData(
                (string) $row->status,
                $amountMinor,
                (string) $row->currency,
                (new DateTimeImmutable((string) $row->domain_last_changed_at))->format(DATE_ATOM),
            );
        } catch (Throwable $exception) {
            throw new RuntimeException('Initial acquisition payment status is invalid.', previous: $exception);
        }
    }
}
