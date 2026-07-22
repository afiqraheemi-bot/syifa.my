<?php

declare(strict_types=1);

namespace App\Modules\Booking\Infrastructure\Persistence\Repositories;

use App\Modules\Booking\Contracts\Repositories\BookingHistoryRepositoryInterface;
use App\Modules\Booking\Domain\BookingHistoryEntry;
use App\Modules\Booking\Domain\Exceptions\InvalidBookingValueException;
use App\Modules\Booking\Domain\ValueObjects\BookingId;
use App\Modules\Booking\Domain\ValueObjects\TenantId;
use App\Modules\Booking\Infrastructure\Persistence\Exceptions\InvalidBookingStorageStateException;
use DateTimeImmutable;
use DateTimeInterface;
use Illuminate\Database\ConnectionInterface;
use JsonException;
use stdClass;

final readonly class PostgresBookingHistoryRepository implements BookingHistoryRepositoryInterface
{
    public function __construct(private ConnectionInterface $connection) {}

    public function append(BookingHistoryEntry $entry): void
    {
        $this->connection->table('booking_history')->insert([
            'id' => $entry->id,
            'tenant_id' => $entry->tenantId,
            'booking_id' => $entry->bookingId,
            'event_type' => $entry->eventType->value,
            'actor_type' => $entry->actorType->value,
            'actor_id' => $entry->actorId,
            'occurred_at' => $entry->occurredAt->format('Y-m-d H:i:s.uP'),
            'payload' => json_encode($entry->payload, JSON_THROW_ON_ERROR),
        ]);
    }

    public function forBooking(TenantId $tenantId, BookingId $bookingId): array
    {
        return array_values(array_map(
            fn (stdClass $row): BookingHistoryEntry => $this->entry($row),
            $this->connection->table('booking_history')
                ->where('tenant_id', $tenantId->value)
                ->where('booking_id', $bookingId->value)
                ->orderBy('occurred_at')->orderBy('id')->get()->all(),
        ));
    }

    private function entry(stdClass $row): BookingHistoryEntry
    {
        try {
            $payload = json_decode($this->string($row, 'payload'), true, flags: JSON_THROW_ON_ERROR);
            if (! is_array($payload)) {
                throw new JsonException('History payload is not an object.');
            }

            return BookingHistoryEntry::reconstitute(
                $this->string($row, 'id'), $this->string($row, 'tenant_id'), $this->string($row, 'booking_id'),
                $this->string($row, 'event_type'), $this->string($row, 'actor_type'), $this->nullableString($row, 'actor_id'),
                $this->dateTime($row->occurred_at ?? null), $payload,
            );
        } catch (InvalidBookingValueException|JsonException $exception) {
            throw new InvalidBookingStorageStateException('Stored Booking history failed validation.', 0, $exception);
        }
    }

    private function string(stdClass $row, string $field): string
    {
        $value = $row->{$field} ?? null;
        if (! is_string($value) || $value === '') {
            throw new InvalidBookingStorageStateException(sprintf('History field %s must be a non-empty string.', $field));
        }

        return $value;
    }

    private function nullableString(stdClass $row, string $field): ?string
    {
        $value = $row->{$field} ?? null;
        if ($value === null || is_string($value)) {
            return $value;
        }
        throw new InvalidBookingStorageStateException(sprintf('History field %s must be a string or null.', $field));
    }

    private function dateTime(mixed $value): DateTimeImmutable
    {
        if ($value instanceof DateTimeInterface) {
            return DateTimeImmutable::createFromInterface($value);
        }
        if (is_string($value)) {
            return new DateTimeImmutable($value);
        }
        throw new InvalidBookingStorageStateException('History occurred_at must be a timestamp.');
    }
}
