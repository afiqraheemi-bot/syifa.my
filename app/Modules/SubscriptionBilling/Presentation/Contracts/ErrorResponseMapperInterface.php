<?php

declare(strict_types=1);

namespace App\Modules\SubscriptionBilling\Presentation\Contracts;

use App\Modules\SubscriptionBilling\Presentation\Responses\ProblemDetails;
use Throwable;

interface ErrorResponseMapperInterface
{
    /**
     * @param  array<string, list<string>>|null  $validationErrors
     */
    public function map(
        Throwable $throwable,
        string $correlationId,
        ?string $instance = null,
        ?array $validationErrors = null,
    ): ProblemDetails;
}
