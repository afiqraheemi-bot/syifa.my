<?php

declare(strict_types=1);

namespace App\Support\Dashboard\Application\SuperAdmin\Tenants;

final readonly class TenantOverviewCriteria
{
    private const array STATUSES = ['provisioning', 'active', 'suspended', 'reactivated', 'offboarding'];

    public function __construct(
        public ?string $search,
        public ?string $status,
        public ?string $cursor,
        public int $perPage,
    ) {}

    /** @param array<string, mixed> $input */
    public static function fromInput(array $input): self
    {
        $search = is_string($input['search'] ?? null) ? trim($input['search']) : null;
        $status = $input['status'] ?? null;
        $cursor = $input['cursor'] ?? null;
        $perPage = filter_var($input['per_page'] ?? null, FILTER_VALIDATE_INT);

        return new self(
            $search === '' ? null : mb_substr((string) $search, 0, 80),
            is_string($status) && in_array($status, self::STATUSES, true) ? $status : null,
            is_string($cursor) && $cursor !== '' ? mb_substr($cursor, 0, 64) : null,
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
}
