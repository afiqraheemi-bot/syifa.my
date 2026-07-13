<?php

declare(strict_types=1);

namespace App\Modules\TenantManagement\Presentation\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

final class AttachRequestIdentifiers
{
    public function handle(Request $request, Closure $next): Response
    {
        $requestId = (string) Str::uuid();
        $candidate = $request->header('X-Correlation-ID');
        $correlationId = is_string($candidate) && Str::isUuid($candidate)
            ? $candidate
            : (string) Str::uuid();
        $request->attributes->set('request_id', $requestId);
        $request->attributes->set('correlation_id', $correlationId);

        $response = $next($request);

        $response->headers->set('X-Request-ID', $requestId);
        $response->headers->set('X-Correlation-ID', $correlationId);

        return $response;
    }
}
