<?php

declare(strict_types=1);

namespace App\Modules\WebsiteBuilder\Application\Delivery;

/**
 * The in-progress visitor selections carried across Booking's multi-step flow
 * (Public Booking UI Specification V1). Immutable — every "update" returns a
 * new instance. Never persisted anywhere beyond the visitor's own session
 * (BookingDraftStore, Presentation-owned); this class itself has no
 * framework dependency and no knowledge of how or where it is stored.
 */
final readonly class BookingDraft
{
    public function __construct(
        public ?string $serviceId = null,
        public ?string $appointmentDate = null,
        public ?string $appointmentTime = null,
        public ?string $patientName = null,
        public ?string $phone = null,
        public ?string $email = null,
        public ?string $notes = null,
        public bool $consent = false,
    ) {}

    public function withService(?string $serviceId): self
    {
        return new self($serviceId, $this->appointmentDate, $this->appointmentTime, $this->patientName, $this->phone, $this->email, $this->notes, $this->consent);
    }

    public function withDate(string $appointmentDate): self
    {
        return new self($this->serviceId, $appointmentDate, null, $this->patientName, $this->phone, $this->email, $this->notes, $this->consent);
    }

    public function withTime(string $appointmentTime): self
    {
        return new self($this->serviceId, $this->appointmentDate, $appointmentTime, $this->patientName, $this->phone, $this->email, $this->notes, $this->consent);
    }

    public function withPatientDetails(string $patientName, string $phone, ?string $email, ?string $notes, bool $consent): self
    {
        return new self($this->serviceId, $this->appointmentDate, $this->appointmentTime, $patientName, $phone, $email, $notes, $consent);
    }

    /** Availability-category recovery (ADR-027/029): keeps the date, clears only the time. */
    public function withoutTime(): self
    {
        return new self($this->serviceId, $this->appointmentDate, null, $this->patientName, $this->phone, $this->email, $this->notes, $this->consent);
    }

    public function isEmpty(): bool
    {
        return $this->serviceId === null && $this->appointmentDate === null && $this->appointmentTime === null
            && $this->patientName === null && $this->phone === null && $this->email === null && $this->notes === null
            && ! $this->consent;
    }

    /** @return array{serviceId: ?string, appointmentDate: ?string, appointmentTime: ?string, patientName: ?string, phone: ?string, email: ?string, notes: ?string, consent: bool} */
    public function toArray(): array
    {
        return [
            'serviceId' => $this->serviceId,
            'appointmentDate' => $this->appointmentDate,
            'appointmentTime' => $this->appointmentTime,
            'patientName' => $this->patientName,
            'phone' => $this->phone,
            'email' => $this->email,
            'notes' => $this->notes,
            'consent' => $this->consent,
        ];
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            self::nullableString($data['serviceId'] ?? null),
            self::nullableString($data['appointmentDate'] ?? null),
            self::nullableString($data['appointmentTime'] ?? null),
            self::nullableString($data['patientName'] ?? null),
            self::nullableString($data['phone'] ?? null),
            self::nullableString($data['email'] ?? null),
            self::nullableString($data['notes'] ?? null),
            (bool) ($data['consent'] ?? false),
        );
    }

    private static function nullableString(mixed $value): ?string
    {
        return is_string($value) ? $value : null;
    }
}
