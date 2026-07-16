<?php

declare(strict_types=1);

namespace App\Modules\SubscriptionBilling\Contracts\CommercialCatalogue\Pagination;

use App\Modules\SubscriptionBilling\Contracts\CommercialCatalogue\Pagination\Exceptions\InvalidPaginatedResultException;

final readonly class OffsetPaginationMeta
{
    public function __construct(
        public int $currentPage,
        public int $perPage,
        public int $total,
        public int $lastPage,
        public ?int $from,
        public ?int $to,
    ) {
        if ($this->currentPage < 1) {
            throw new InvalidPaginatedResultException('Pagination currentPage must be at least 1.');
        }

        if ($this->perPage < 1 || $this->perPage > 100) {
            throw new InvalidPaginatedResultException('Pagination perPage must be between 1 and 100.');
        }

        if ($this->total < 0) {
            throw new InvalidPaginatedResultException('Pagination total cannot be negative.');
        }

        if ($this->lastPage < 1) {
            throw new InvalidPaginatedResultException('Pagination lastPage must be at least 1.');
        }

        if ($this->total === 0) {
            if ($this->from !== null || $this->to !== null) {
                throw new InvalidPaginatedResultException('Pagination from and to must both be null when total is 0.');
            }

            return;
        }

        if ($this->from === null || $this->to === null) {
            throw new InvalidPaginatedResultException('Pagination from and to must both be non-null when total is greater than 0.');
        }

        if ($this->from < 1) {
            throw new InvalidPaginatedResultException('Pagination from must be at least 1.');
        }

        if ($this->to < $this->from) {
            throw new InvalidPaginatedResultException('Pagination to cannot be less than from.');
        }

        if ($this->to > $this->total) {
            throw new InvalidPaginatedResultException('Pagination to cannot exceed total.');
        }
    }
}
