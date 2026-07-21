<?php

declare(strict_types=1);

namespace App\Modules\Commercial\Presentation\Http\Responses;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

final class ProblemDetailsResponse
{
    /**
     * @param  array<string, list<string>>|null  $errors
     */
    public static function make(
        Request $request,
        string $type,
        string $title,
        int $status,
        string $detail,
        ?array $errors = null,
    ): JsonResponse {
        $correlationId = $request->attributes->get('correlation_id');

        if (! is_string($correlationId) || ! Str::isUuid($correlationId)) {
            $correlationId = (string) Str::uuid();
        }

        $body = [
            'type' => $type,
            'title' => $title,
            'status' => $status,
            'detail' => $detail,
            'correlation_id' => $correlationId,
        ];

        if ($errors !== null) {
            $body['errors'] = $errors;
        }

        return new JsonResponse($body, $status, ['Content-Type' => 'application/problem+json']);
    }
}
