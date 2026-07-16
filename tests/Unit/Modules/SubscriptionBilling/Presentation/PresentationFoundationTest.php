<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\SubscriptionBilling\Presentation;

use App\Modules\SubscriptionBilling\Presentation\ApiVersion;
use App\Modules\SubscriptionBilling\Presentation\Collections\BaseCollection;
use App\Modules\SubscriptionBilling\Presentation\Contracts\ErrorResponseMapperInterface;
use App\Modules\SubscriptionBilling\Presentation\Resources\BaseResource;
use App\Modules\SubscriptionBilling\Presentation\Responses\CollectionResponse;
use App\Modules\SubscriptionBilling\Presentation\Responses\ProblemDetails;
use App\Modules\SubscriptionBilling\Presentation\Responses\ResourceResponse;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class PresentationFoundationTest extends TestCase
{
    public function test_api_version_constants_are_canonical(): void
    {
        self::assertSame('v1', ApiVersion::CURRENT);
        self::assertSame('/api/v1', ApiVersion::PREFIX);
        self::assertSame('/api/v1/platform', ApiVersion::PLATFORM_PREFIX);
        self::assertSame('/api/v1/platform/commercial-catalogue', ApiVersion::COMMERCIAL_CATALOGUE_PREFIX);
    }

    public function test_problem_details_and_response_envelopes_export_consistent_payloads(): void
    {
        $problemDetails = new ProblemDetails(
            '00000000-0000-4000-8000-000000000001',
            'https://example.test/problems/validation',
            'Validation Failed',
            422,
            'The submitted payload is invalid.',
            '/api/v1/platform/commercial-catalogue/plans',
            ['name' => ['This field is required.']],
        );

        self::assertSame(
            [
                'type' => 'https://example.test/problems/validation',
                'title' => 'Validation Failed',
                'status' => 422,
                'detail' => 'The submitted payload is invalid.',
                'correlation_id' => '00000000-0000-4000-8000-000000000001',
                'instance' => '/api/v1/platform/commercial-catalogue/plans',
                'errors' => ['name' => ['This field is required.']],
            ],
            $problemDetails->toArray(),
        );

        $resource = new class extends BaseResource
        {
            public function toArray(): array
            {
                return ['id' => 'resource-id', 'name' => 'Resource'];
            }
        };

        $collection = new class([$resource], ['total' => 1], ['self' => '/api/v1/platform/commercial-catalogue/resources']) extends BaseCollection {};

        self::assertSame(
            [
                'data' => ['id' => 'resource-id', 'name' => 'Resource'],
                'links' => [],
                'correlation_id' => '00000000-0000-4000-8000-000000000002',
            ],
            (new ResourceResponse('00000000-0000-4000-8000-000000000002', $resource))->toArray(),
        );

        self::assertSame(
            [
                'data' => [['id' => 'resource-id', 'name' => 'Resource']],
                'meta' => ['total' => 1],
                'links' => ['self' => '/api/v1/platform/commercial-catalogue/resources'],
                'correlation_id' => '00000000-0000-4000-8000-000000000003',
            ],
            (new CollectionResponse('00000000-0000-4000-8000-000000000003', $collection))->toArray(),
        );
    }

    public function test_problem_details_accept_platform_supported_uuid_variants_and_reject_malformed_ids(): void
    {
        $v4 = new ProblemDetails(
            '00000000-0000-4000-8000-000000000004',
            'https://example.test/problems/generic',
            'Accepted',
            500,
            'Mapped error.',
        );

        $v7 = new ProblemDetails(
            '00000000-0000-7000-8000-000000000007',
            'https://example.test/problems/generic',
            'Accepted',
            500,
            'Mapped error.',
        );

        self::assertSame('00000000-0000-4000-8000-000000000004', $v4->correlationId);
        self::assertSame('00000000-0000-7000-8000-000000000007', $v7->correlationId);

        $this->expectException(InvalidArgumentException::class);
        new ProblemDetails(
            'not-a-uuid',
            'https://example.test/problems/generic',
            'Rejected',
            500,
            'Mapped error.',
        );
    }

    public function test_error_response_mapper_contract_remains_framework_free(): void
    {
        $mapper = new class implements ErrorResponseMapperInterface
        {
            public function map(
                \Throwable $throwable,
                string $correlationId,
                ?string $instance = null,
                ?array $validationErrors = null,
            ): ProblemDetails {
                return new ProblemDetails(
                    $correlationId,
                    'https://example.test/problems/generic',
                    $throwable->getMessage(),
                    500,
                    'Mapped error.',
                    $instance,
                    $validationErrors,
                );
            }
        };

        $mapped = $mapper->map(new InvalidArgumentException('Broken'), '00000000-0000-4000-8000-000000000004');
        self::assertSame('Mapped error.', $mapped->detail);
        self::assertSame(500, $mapped->status);
    }
}
