<?php

declare(strict_types=1);

namespace App\Modules\SubscriptionBilling\Infrastructure\Persistence\Repositories;

use App\Modules\SubscriptionBilling\Contracts\Payment\PaymentVerificationApplication;
use App\Modules\SubscriptionBilling\Contracts\Payment\PaymentVerificationApplicationRepositoryInterface;
use App\Modules\SubscriptionBilling\Contracts\Payment\PaymentVerificationApplicationResultCode;
use App\Modules\SubscriptionBilling\Contracts\Payment\PaymentVerificationApplicationStatus;
use DateTimeImmutable;
use DateTimeInterface;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Str;
use stdClass;

final readonly class PostgresPaymentVerificationApplicationRepository implements PaymentVerificationApplicationRepositoryInterface
{
    public function __construct(private ConnectionInterface $connection) {}

    public function register(string $receiptId, DateTimeImmutable $now): PaymentVerificationApplication
    {
        $timestamp = $this->timestamp($now);
        $this->connection->table('payment_verification_applications')->insertOrIgnore([
            'id' => (string) Str::uuid(), 'provider_webhook_receipt_id' => $receiptId, 'status' => 'pending',
            'attempt_count' => 0, 'created_at' => $timestamp, 'updated_at' => $timestamp,
        ]);
        $row = $this->connection->table('payment_verification_applications')->where('provider_webhook_receipt_id', $receiptId)->first();

        return $this->map($row);
    }

    public function claim(string $applicationId, DateTimeImmutable $now, int $leaseSeconds): ?PaymentVerificationApplication
    {
        $token = (string) Str::uuid();
        $timestamp = $this->timestamp($now);
        $lease = $this->timestamp($now->modify('+'.$leaseSeconds.' seconds'));
        $rows = $this->connection->select(<<<'SQL'
            UPDATE payment_verification_applications
            SET status='processing', processing_claim_token=?, processing_started_at=?, processing_lease_expires_at=?,
                attempt_count=attempt_count+1, last_attempt_at=?, next_attempt_at=NULL, updated_at=?
            WHERE id=? AND (status='pending' OR (status='retry_pending' AND next_attempt_at<=?) OR (status='processing' AND processing_lease_expires_at<=?))
            RETURNING *
            SQL, [$token, $timestamp, $lease, $timestamp, $timestamp, $applicationId, $timestamp, $timestamp]);

        return isset($rows[0]) ? $this->map($rows[0]) : null;
    }

    public function find(string $applicationId): ?PaymentVerificationApplication
    {
        $row = $this->connection->table('payment_verification_applications')->where('id', $applicationId)->first();

        return $row instanceof stdClass ? $this->map($row) : null;
    }

    public function complete(string $applicationId, string $claimToken, PaymentVerificationApplicationStatus $status, PaymentVerificationApplicationResultCode $resultCode, DateTimeImmutable $now, ?DateTimeImmutable $nextAttemptAt = null): bool
    {
        return $this->connection->table('payment_verification_applications')
            ->where('id', $applicationId)->where('processing_claim_token', $claimToken)->where('status', 'processing')
            ->update([
                'status' => $status->value, 'result_code' => $resultCode->value, 'processing_claim_token' => null,
                'processing_lease_expires_at' => null, 'next_attempt_at' => $nextAttemptAt?->format('Y-m-d H:i:s.uP'),
                'completed_at' => $status === PaymentVerificationApplicationStatus::RetryPending ? null : $this->timestamp($now),
                'updated_at' => $this->timestamp($now),
            ]) === 1;
    }

    private function map(mixed $row): PaymentVerificationApplication
    {
        if (! $row instanceof stdClass) {
            throw new \RuntimeException('Payment verification application storage is invalid.');
        }

        return new PaymentVerificationApplication(
            (string) $row->id, (string) $row->provider_webhook_receipt_id,
            PaymentVerificationApplicationStatus::from((string) $row->status), (int) $row->attempt_count,
            isset($row->processing_claim_token) ? (string) $row->processing_claim_token : null,
            $this->date($row->processing_lease_expires_at ?? null), $this->date($row->next_attempt_at ?? null),
            isset($row->result_code) ? PaymentVerificationApplicationResultCode::from((string) $row->result_code) : null,
        );
    }

    private function timestamp(DateTimeInterface $value): string
    {
        return $value->format('Y-m-d H:i:s.uP');
    }

    private function date(mixed $value): ?DateTimeImmutable
    {
        return $value === null ? null : new DateTimeImmutable((string) $value);
    }
}
