<?php

declare(strict_types=1);

namespace App\Modules\SubscriptionBilling\Presentation\Http\Support;

use App\Modules\SubscriptionBilling\Contracts\CommercialCatalogue\Pagination\OffsetPaginationMeta;

final class CommercialCataloguePaginationLinkBuilder
{
    /**
     * @return array{first: string, last: string, previous: string|null, next: string|null}
     */
    public static function build(string $path, OffsetPaginationMeta $meta): array
    {
        $first = self::link($path, 1, $meta->perPage);
        $last = self::link($path, $meta->lastPage, $meta->perPage);
        $previous = $meta->currentPage > 1
            ? self::link($path, $meta->currentPage - 1, $meta->perPage)
            : null;
        $next = $meta->currentPage < $meta->lastPage
            ? self::link($path, $meta->currentPage + 1, $meta->perPage)
            : null;

        return [
            'first' => $first,
            'last' => $last,
            'previous' => $previous,
            'next' => $next,
        ];
    }

    private static function link(string $path, int $page, int $perPage): string
    {
        return sprintf('%s?page=%d&per_page=%d', $path, $page, $perPage);
    }
}
