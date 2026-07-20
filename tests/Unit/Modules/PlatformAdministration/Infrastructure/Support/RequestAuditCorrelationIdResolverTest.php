<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\PlatformAdministration\Infrastructure\Support;

use App\Modules\PlatformAdministration\Infrastructure\Support\RequestAuditCorrelationIdResolver;
use Illuminate\Http\Request;
use PHPUnit\Framework\TestCase;

final class RequestAuditCorrelationIdResolverTest extends TestCase
{
    public function test_it_uses_only_the_trusted_request_attribute_and_ignores_body_input(): void
    {
        $request = Request::create('/platform/audit', 'POST', [
            'correlation_id' => '11111111-1111-4111-8111-111111111111',
        ]);
        $request->attributes->set('correlation_id', '00000000-0000-4000-8000-000000000999');

        $resolver = new RequestAuditCorrelationIdResolver($request);

        self::assertSame('00000000-0000-4000-8000-000000000999', $resolver->resolve());
    }

    public function test_it_generates_a_fallback_uuid_when_no_trusted_attribute_exists(): void
    {
        $resolver = new RequestAuditCorrelationIdResolver(Request::create('/platform/audit', 'POST'));

        self::assertMatchesRegularExpression(
            '/^[0-9a-f-]{36}$/i',
            $resolver->resolve(),
        );
    }
}
