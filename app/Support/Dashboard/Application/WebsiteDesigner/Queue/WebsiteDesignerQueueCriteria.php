<?php

declare(strict_types=1);

namespace App\Support\Dashboard\Application\WebsiteDesigner\Queue;

final readonly class WebsiteDesignerQueueCriteria
{
    private const array STATUSES = [
        'awaiting_inputs', 'assigned', 'in_progress', 'blocked', 'in_review',
        'correction_required', 'ready_for_launch', 'reopened',
    ];

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
        $search = $search === '' ? null : mb_substr((string) $search, 0, 80);
        $status = $input['status'] ?? null;
        $cursor = $input['cursor'] ?? null;
        $perPage = filter_var($input['per_page'] ?? null, FILTER_VALIDATE_INT);

        return new self(
            $search,
            is_string($status) && in_array($status, self::STATUSES, true) ? $status : null,
            is_string($cursor) && $cursor !== '' ? mb_substr($cursor, 0, 64) : null,
            is_int($perPage) ? max(10, min(50, $perPage)) : 20,
        );
    }

    /** @return list<array{value: string, label: string}> */
    public static function statusOptions(): array
    {
        return array_map(
            static fn (string $status): array => [
                'value' => $status,
                'label' => ucwords(str_replace('_', ' ', $status)),
            ],
            self::STATUSES,
        );
    }
}
