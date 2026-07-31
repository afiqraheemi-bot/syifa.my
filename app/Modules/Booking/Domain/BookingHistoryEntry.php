<?php

declare(strict_types=1);

namespace App\Modules\Booking\Domain;

use App\Modules\Booking\Domain\Exceptions\InvalidBookingValueException;
use App\Modules\Booking\Domain\ValueObjects\BookingActorType;
use App\Modules\Booking\Domain\ValueObjects\BookingHistoryEventType;
use App\Modules\Booking\Domain\ValueObjects\BookingSource;
use App\Modules\Booking\Domain\ValueObjects\ScheduledAppointment;
use DateTimeImmutable;

final readonly class BookingHistoryEntry
{
    /** @param array<string, string|int|bool|null> $payload */
    private function __construct(
        public string $id,
        public string $tenantId,
        public string $bookingId,
        public BookingHistoryEventType $eventType,
        public BookingActorType $actorType,
        public ?string $actorId,
        public DateTimeImmutable $occurredAt,
        public array $payload,
    ) {}

    public static function submitted(string $id, Booking $booking, BookingActorType $actorType, ?string $actorId, DateTimeImmutable $at, ?bool $consentAcknowledged = null): self
    {
        self::assertActor($actorType, $actorId);

        return new self($id, $booking->tenantId->value, $booking->id->value, BookingHistoryEventType::Submitted, $actorType, $actorId, $at, [
            'source' => $booking->source->value,
            ...self::snapshot($booking->scheduledAppointment()),
            ...($consentAcknowledged === null ? [] : ['consent_acknowledged' => $consentAcknowledged]),
        ]);
    }

    public static function confirmed(string $id, Booking $booking, string $actorId, DateTimeImmutable $at): self
    {
        return new self($id, $booking->tenantId->value, $booking->id->value, BookingHistoryEventType::Confirmed, BookingActorType::ClinicOwner, $actorId, $at, ['status' => 'confirmed']);
    }

    public static function rescheduled(string $id, Booking $booking, ScheduledAppointmentPair $pair, string $actorId, ?string $note, DateTimeImmutable $at): self
    {
        return new self($id, $booking->tenantId->value, $booking->id->value, BookingHistoryEventType::Rescheduled, BookingActorType::ClinicOwner, $actorId, $at, [
            ...self::prefixedSnapshot('old_', $pair->old),
            ...self::prefixedSnapshot('new_', $pair->new),
            'note' => $note,
        ]);
    }

    public static function cancelled(string $id, Booking $booking, string $actorId, ?string $reason, DateTimeImmutable $at): self
    {
        return new self($id, $booking->tenantId->value, $booking->id->value, BookingHistoryEventType::Cancelled, BookingActorType::ClinicOwner, $actorId, $at, ['status' => 'cancelled', 'reason' => $reason]);
    }

    public static function completed(string $id, Booking $booking, string $actorId, DateTimeImmutable $at): self
    {
        return new self($id, $booking->tenantId->value, $booking->id->value, BookingHistoryEventType::Completed, BookingActorType::ClinicOwner, $actorId, $at, ['status' => 'completed']);
    }

    /** @param array<string, string|int|bool|null> $payload */
    public static function reconstitute(string $id, string $tenantId, string $bookingId, string $eventType, string $actorType, ?string $actorId, DateTimeImmutable $occurredAt, array $payload): self
    {
        $type = BookingHistoryEventType::tryFrom($eventType);
        $actor = BookingActorType::tryFrom($actorType);
        if ($type === null || $actor === null || $id === '' || $tenantId === '' || $bookingId === '') {
            throw new InvalidBookingValueException('Stored Booking history identity is invalid.');
        }
        $required = match ($type) {
            BookingHistoryEventType::Submitted => ['source', 'local_date', 'local_start', 'local_end', 'timezone', 'starts_at_utc', 'ends_at_utc', 'duration_minutes'],
            BookingHistoryEventType::Confirmed => ['status'],
            BookingHistoryEventType::Rescheduled => ['old_local_date', 'old_local_start', 'old_local_end', 'old_timezone', 'old_starts_at_utc', 'old_ends_at_utc', 'old_duration_minutes', 'new_local_date', 'new_local_start', 'new_local_end', 'new_timezone', 'new_starts_at_utc', 'new_ends_at_utc', 'new_duration_minutes', 'note'],
            BookingHistoryEventType::Cancelled => ['status', 'reason'],
            BookingHistoryEventType::Completed => ['status'],
        };
        $optional = match ($type) {
            BookingHistoryEventType::Submitted => ['consent_acknowledged'],
            default => [],
        };
        $expected = $required;
        foreach ($optional as $key) {
            if (array_key_exists($key, $payload)) {
                $expected[] = $key;
            }
        }
        $actualKeys = array_keys($payload);
        sort($actualKeys);
        sort($expected);
        if ($actualKeys !== $expected) {
            throw new InvalidBookingValueException('Stored Booking history payload does not match its event schema.');
        }
        self::assertActor($actor, $actorId);
        if ($type === BookingHistoryEventType::Submitted) {
            $source = $payload['source'] ?? null;
            if (! is_string($source)) {
                throw new InvalidBookingValueException('Stored Booking history source is invalid.');
            }
            BookingSource::fromStored($source);
            if (array_key_exists('consent_acknowledged', $payload) && ! is_bool($payload['consent_acknowledged'])) {
                throw new InvalidBookingValueException('Stored Booking history consent acknowledgement is invalid.');
            }
        }

        return new self($id, $tenantId, $bookingId, $type, $actor, $actorId, $occurredAt, $payload);
    }

    private static function assertActor(BookingActorType $actorType, ?string $actorId): void
    {
        if (($actorType === BookingActorType::PublicVisitor && $actorId !== null)
            || ($actorType !== BookingActorType::PublicVisitor && ($actorId === null || trim($actorId) === ''))) {
            throw new InvalidBookingValueException('Booking history actor identity is invalid.');
        }
    }

    /** @return array<string, string|int> */
    private static function snapshot(ScheduledAppointment $scheduled): array
    {
        return self::prefixedSnapshot('', $scheduled);
    }

    /** @return array<string, string|int> */
    private static function prefixedSnapshot(string $prefix, ScheduledAppointment $scheduled): array
    {
        return [
            $prefix.'local_date' => $scheduled->localDate->value,
            $prefix.'local_start' => $scheduled->localStart->value,
            $prefix.'local_end' => $scheduled->localEnd->value,
            $prefix.'timezone' => $scheduled->timezone,
            $prefix.'starts_at_utc' => $scheduled->startsAtUtc->format(DATE_ATOM),
            $prefix.'ends_at_utc' => $scheduled->endsAtUtc->format(DATE_ATOM),
            $prefix.'duration_minutes' => $scheduled->durationMinutes,
        ];
    }
}
