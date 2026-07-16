<?php

declare(strict_types=1);

namespace App\Modules\SubscriptionBilling\Contracts\CommercialCatalogue\Pagination\Exceptions;

use InvalidArgumentException;

/**
 * A stable, Contract-layer validation failure for a malformed paginated result:
 * an invalid pagination metadata combination, a non-list item array, or an item
 * that is not the exact expected DTO type. Never an HTTP error — mapping this
 * to a protocol-level response is a delivery-layer concern, not decided here.
 */
final class InvalidPaginatedResultException extends InvalidArgumentException {}
