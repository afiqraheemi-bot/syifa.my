<?php

declare(strict_types=1);

namespace App\Modules\PlatformAdministration\Infrastructure\Support;

use App\Modules\PlatformAdministration\Contracts\AuditEntry\AuditCorrelationIdResolverInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

final readonly class RequestAuditCorrelationIdResolver implements AuditCorrelationIdResolverInterface
{
    public function __construct(private Request $request) {}

    public function resolve(): string
    {
        $correlationId = $this->request->attributes->get('correlation_id');

        if (is_string($correlationId) && $correlationId !== '' && Str::isUuid($correlationId)) {
            return $correlationId;
        }

        return (string) Str::uuid();
    }
}
