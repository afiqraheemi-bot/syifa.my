<?php

declare(strict_types=1);

namespace App\Modules\Notification\Domain;

use DateTimeImmutable;
use DomainException;

final class Notification
{
    /** @param list<DeliveryAttempt> $attempts */
    public function __construct(
        public readonly string $id,
        public readonly ?string $tenantId,
        public readonly string $templateId,
        public readonly string $category,
        public readonly string $triggerType,
        public readonly string $triggerId,
        public readonly string $idempotencyKey,
        public readonly string $recipientReference,
        public readonly string $recipientEmail,
        public readonly string $subject,
        public readonly string $body,
        public NotificationStatus $status,
        public readonly DateTimeImmutable $createdAt,
        public DateTimeImmutable $updatedAt,
        public array $attempts = [],
        public int $version = 1,
    ) {
        foreach ([$id, $templateId] as $identifier) {
            if (preg_match('/^[0-9a-f-]{36}$/i', $identifier) !== 1) {
                throw new DomainException('Notification identifiers must be UUIDs.');
            }
        }
        if ($tenantId !== null && preg_match('/^[0-9a-f-]{36}$/i', $tenantId) !== 1) {
            throw new DomainException('A Notification Tenant identifier must be a UUID.');
        }
        if (! filter_var($recipientEmail, FILTER_VALIDATE_EMAIL)) {
            throw new DomainException('A Notification recipient email must be valid.');
        }
        if ($category === '' || $triggerType === '' || $triggerId === '' || $idempotencyKey === '') {
            throw new DomainException('Notification lineage must be complete.');
        }
    }

    public function queue(DateTimeImmutable $at): void
    {
        if ($this->status !== NotificationStatus::Prepared) {
            throw new DomainException('Only a prepared Notification can be queued.');
        }

        $this->status = NotificationStatus::Queued;
        $this->touch($at);
    }

    public function recordAccepted(DateTimeImmutable $at): void
    {
        $this->appendAttempt($at, 'accepted', false, null);
        $this->status = NotificationStatus::Sent;
        $this->touch($at);
    }

    public function recordFailure(DateTimeImmutable $at, bool $retryEligible, string $reasonCode): void
    {
        $this->appendAttempt(
            $at,
            $retryEligible ? 'temporary_failure' : 'permanent_failure',
            $retryEligible,
            $reasonCode,
        );
        $this->status = $retryEligible ? NotificationStatus::Delayed : NotificationStatus::Failed;
        $this->touch($at);
    }

    private function appendAttempt(
        DateTimeImmutable $at,
        string $outcome,
        bool $retryEligible,
        ?string $reasonCode,
    ): void {
        $this->attempts[] = new DeliveryAttempt(
            count($this->attempts) + 1,
            $at,
            $outcome,
            $retryEligible,
            $reasonCode,
        );
    }

    private function touch(DateTimeImmutable $at): void
    {
        if ($at < $this->updatedAt) {
            throw new DomainException('Notification transitions must be chronological.');
        }

        $this->updatedAt = $at;
        $this->version++;
    }
}
