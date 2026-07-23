<?php

declare(strict_types=1);

namespace App\Support\Dashboard\Application\Booking;

final readonly class BookingOverviewCriteria
{
    private const array STATUSES = ['submitted', 'confirmed', 'cancelled', 'completed'];

    private const array SOURCES = ['WEBSITE', 'WHATSAPP', 'PHONE', 'WALK_IN', 'STAFF'];

    public function __construct(
        public ?string $search,
        public ?string $status,
        public ?string $source,
        public ?string $cursor,
        public int $perPage,
    ) {}

    /** @param array<string, mixed> $input */
    public static function fromInput(array $input): self
    {
        $search = self::text($input['search'] ?? null, 80);
        $status = self::option($input['status'] ?? null, self::STATUSES);
        $source = self::option($input['source'] ?? null, self::SOURCES);
        $cursor = self::text($input['cursor'] ?? null, 64);
        $perPage = filter_var($input['per_page'] ?? null, FILTER_VALIDATE_INT);

        return new self(
            $search,
            $status,
            $source,
            $cursor,
            is_int($perPage) ? max(10, min(50, $perPage)) : 20,
        );
    }

    /** @return list<array{value: string, label: string}> */
    public static function statusOptions(): array
    {
        return array_map(
            static fn (string $status): array => ['value' => $status, 'label' => ucfirst($status)],
            self::STATUSES,
        );
    }

    /** @return list<array{value: string, label: string}> */
    public static function sourceOptions(): array
    {
        return array_map(
            static fn (string $source): array => [
                'value' => $source,
                'label' => ucwords(strtolower(str_replace('_', ' ', $source))),
            ],
            self::SOURCES,
        );
    }

    private static function text(mixed $value, int $maximum): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : mb_substr($value, 0, $maximum);
    }

    /** @param list<string> $allowed */
    private static function option(mixed $value, array $allowed): ?string
    {
        return is_string($value) && in_array($value, $allowed, true) ? $value : null;
    }
}
