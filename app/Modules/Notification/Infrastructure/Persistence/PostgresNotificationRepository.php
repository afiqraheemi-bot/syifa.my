<?php

declare(strict_types=1);

namespace App\Modules\Notification\Infrastructure\Persistence;

use App\Modules\Notification\Contracts\DuplicateNotificationIntentException;
use App\Modules\Notification\Contracts\NotificationRepositoryInterface;
use App\Modules\Notification\Domain\DeliveryAttempt;
use App\Modules\Notification\Domain\Notification;
use App\Modules\Notification\Domain\NotificationStatus;
use DateTimeImmutable;
use Illuminate\Contracts\Encryption\Encrypter;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\QueryException;
use RuntimeException;
use stdClass;

final readonly class PostgresNotificationRepository implements NotificationRepositoryInterface
{
    public function __construct(
        private ConnectionInterface $connection,
        private Encrypter $encrypter,
    ) {}

    public function find(string $notificationId): ?Notification
    {
        $row = $this->connection->table('notifications')->where('notification_id', $notificationId)->first();

        return $row === null ? null : $this->map($row);
    }

    public function findByIdempotencyKey(string $idempotencyKey): ?Notification
    {
        $row = $this->connection->table('notifications')->where('idempotency_key', $idempotencyKey)->first();

        return $row === null ? null : $this->map($row);
    }

    public function save(Notification $notification): void
    {
        $record = [
            'tenant_id' => $notification->tenantId,
            'notification_template_id' => $notification->templateId,
            'category' => $notification->category,
            'trigger_type' => $notification->triggerType,
            'trigger_id' => $notification->triggerId,
            'idempotency_key' => $notification->idempotencyKey,
            'recipient_reference' => $notification->recipientReference,
            'recipient_email_encrypted' => $this->encrypter->encryptString($notification->recipientEmail),
            'subject_encrypted' => $this->encrypter->encryptString($notification->subject),
            'body_encrypted' => $this->encrypter->encryptString($notification->body),
            'status' => $notification->status->value,
            'version' => $notification->version,
            'created_at' => $notification->createdAt,
            'updated_at' => $notification->updatedAt,
        ];

        $this->connection->transaction(function () use ($notification, $record): void {
            $updated = $this->connection->table('notifications')
                ->where('notification_id', $notification->id)
                ->where('version', $notification->version - 1)
                ->update($record);

            if ($updated === 0 && ! $this->connection->table('notifications')->where('notification_id', $notification->id)->exists()) {
                try {
                    $this->connection->table('notifications')->insert([
                        'notification_id' => $notification->id,
                        ...$record,
                    ]);
                } catch (QueryException $exception) {
                    if ($exception->getCode() === '23505') {
                        throw new DuplicateNotificationIntentException(
                            'Notification intent already exists.',
                            0,
                            $exception,
                        );
                    }

                    throw $exception;
                }
            } elseif ($updated === 0) {
                throw new RuntimeException('Notification optimistic version conflict.');
            }

            foreach ($notification->attempts as $attempt) {
                $this->connection->table('notification_delivery_attempts')->insertOrIgnore([
                    'notification_id' => $notification->id,
                    'sequence' => $attempt->sequence,
                    'attempted_at' => $attempt->attemptedAt,
                    'outcome' => $attempt->outcome,
                    'retry_eligible' => $attempt->retryEligible,
                    'reason_code' => $attempt->reasonCode,
                ]);
            }
        });
    }

    private function map(stdClass $row): Notification
    {
        $attempts = $this->connection->table('notification_delivery_attempts')
            ->where('notification_id', $row->notification_id)
            ->orderBy('sequence')
            ->get()
            ->map(static fn (stdClass $attempt): DeliveryAttempt => new DeliveryAttempt(
                (int) $attempt->sequence,
                new DateTimeImmutable((string) $attempt->attempted_at),
                (string) $attempt->outcome,
                (bool) $attempt->retry_eligible,
                $attempt->reason_code === null ? null : (string) $attempt->reason_code,
            ))
            ->all();

        return new Notification(
            (string) $row->notification_id,
            $row->tenant_id === null ? null : (string) $row->tenant_id,
            (string) $row->notification_template_id,
            (string) $row->category,
            (string) $row->trigger_type,
            (string) $row->trigger_id,
            (string) $row->idempotency_key,
            (string) $row->recipient_reference,
            $this->encrypter->decryptString((string) $row->recipient_email_encrypted),
            $this->encrypter->decryptString((string) $row->subject_encrypted),
            $this->encrypter->decryptString((string) $row->body_encrypted),
            NotificationStatus::from((string) $row->status),
            new DateTimeImmutable((string) $row->created_at),
            new DateTimeImmutable((string) $row->updated_at),
            array_values($attempts),
            (int) $row->version,
        );
    }
}
