<?php

declare(strict_types=1);

namespace App\Modules\SubscriptionBilling\Presentation\Http\Support;

use App\Modules\SubscriptionBilling\Presentation\Responses\ProblemDetails;
use Illuminate\Http\JsonResponse;

final class CommercialCatalogueProblemDetailsResponseFactory
{
    public static function make(ProblemDetails $problemDetails): JsonResponse
    {
        return new JsonResponse(
            $problemDetails->toArray(),
            $problemDetails->status,
            ['Content-Type' => 'application/problem+json'],
        );
    }
}
