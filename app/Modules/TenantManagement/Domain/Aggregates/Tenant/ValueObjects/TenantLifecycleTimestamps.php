<?php

declare(strict_types=1);

namespace App\Modules\TenantManagement\Domain\Aggregates\Tenant\ValueObjects;

use App\Modules\TenantManagement\Domain\Aggregates\Tenant\Exceptions\InvalidTenantLifecycleTransitionException;
use DateTimeImmutable;

final readonly class TenantLifecycleTimestamps
{
    public function __construct(
        public DateTimeImmutable $provisionedAt,
        public ?DateTimeImmutable $activatedAt = null,
        public ?DateTimeImmutable $suspendedAt = null,
        public ?DateTimeImmutable $reactivatedAt = null,
        public ?DateTimeImmutable $offboardingStartedAt = null,
        public ?DateTimeImmutable $deletedOrAnonymizedAt = null,
    ) {}

    public function activated(DateTimeImmutable $occurredAt): self
    {
        $this->assertChronological($occurredAt);

        return new self($this->provisionedAt, $occurredAt);
    }

    public function suspended(DateTimeImmutable $occurredAt): self
    {
        $this->assertChronological($occurredAt);

        return new self(
            $this->provisionedAt,
            $this->activatedAt,
            $occurredAt,
            $this->reactivatedAt,
            $this->offboardingStartedAt,
            $this->deletedOrAnonymizedAt,
        );
    }

    public function reactivated(DateTimeImmutable $occurredAt): self
    {
        $this->assertChronological($occurredAt);

        return new self(
            $this->provisionedAt,
            $this->activatedAt,
            $this->suspendedAt,
            $occurredAt,
            $this->offboardingStartedAt,
            $this->deletedOrAnonymizedAt,
        );
    }

    public function offboardingStarted(DateTimeImmutable $occurredAt): self
    {
        $this->assertChronological($occurredAt);

        return new self(
            $this->provisionedAt,
            $this->activatedAt,
            $this->suspendedAt,
            $this->reactivatedAt,
            $occurredAt,
            $this->deletedOrAnonymizedAt,
        );
    }

    public function closed(DateTimeImmutable $occurredAt): self
    {
        $this->assertChronological($occurredAt);

        return new self(
            $this->provisionedAt,
            $this->activatedAt,
            $this->suspendedAt,
            $this->reactivatedAt,
            $this->offboardingStartedAt,
            $occurredAt,
        );
    }

    public function atOrAfterLatest(DateTimeImmutable $candidate): DateTimeImmutable
    {
        $latest = $this->latest();

        return $candidate < $latest ? $latest->modify('+1 microsecond') : $candidate;
    }

    private function assertChronological(DateTimeImmutable $occurredAt): void
    {
        if ($occurredAt < $this->latest()) {
            throw new InvalidTenantLifecycleTransitionException(
                'A Tenant lifecycle transition cannot occur before its previous transition.',
            );
        }
    }

    private function latest(): DateTimeImmutable
    {
        $latest = $this->provisionedAt;

        foreach ([
            $this->activatedAt,
            $this->suspendedAt,
            $this->reactivatedAt,
            $this->offboardingStartedAt,
            $this->deletedOrAnonymizedAt,
        ] as $timestamp) {
            if ($timestamp !== null && $timestamp > $latest) {
                $latest = $timestamp;
            }
        }

        return $latest;
    }
}
