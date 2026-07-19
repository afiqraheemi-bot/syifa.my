<?php

declare(strict_types=1);

namespace App\Modules\SubscriptionBilling\Presentation\Http\Support;

use App\Modules\SubscriptionBilling\Contracts\Authorization\CommercialCatalogueAuthorizationDecision;
use App\Modules\SubscriptionBilling\Contracts\Authorization\CommercialCatalogueAuthorizationInterface;
use App\Modules\SubscriptionBilling\Presentation\Collections\BaseCollection;
use App\Modules\SubscriptionBilling\Presentation\Contracts\ErrorResponseMapperInterface;
use App\Modules\SubscriptionBilling\Presentation\Resources\BaseResource;
use App\Modules\SubscriptionBilling\Presentation\Responses\CollectionResponse;
use App\Modules\SubscriptionBilling\Presentation\Responses\ProblemDetails;
use App\Modules\SubscriptionBilling\Presentation\Responses\ResourceResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use LogicException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Throwable;

abstract readonly class CommercialCatalogueControllerSupport
{
    public function __construct(
        protected CommercialCatalogueAuthorizationInterface $authorization,
        protected ErrorResponseMapperInterface $errors,
    ) {}

    protected function authorize(string $action): ?CommercialCatalogueAuthorizationDecision
    {
        $decision = $this->authorization->authorize($action);

        if ($decision->allowed) {
            return $decision;
        }

        return null;
    }

    protected function deny(Request $request): JsonResponse
    {
        return $this->problem(
            $request,
            new AccessDeniedHttpException('Commercial Catalogue authorization is not available.'),
        );
    }

    protected function actorId(CommercialCatalogueAuthorizationDecision $decision): string
    {
        if (! is_string($decision->actorPlatformIdentityId) || $decision->actorPlatformIdentityId === '') {
            throw new LogicException('Authorized Commercial Catalogue actions require an actor platform identity.');
        }

        return $decision->actorPlatformIdentityId;
    }

    protected function resource(Request $request, BaseResource $resource, int $status = 200): JsonResponse
    {
        return new JsonResponse(
            (new ResourceResponse($this->correlationId($request), $resource))->toArray(),
            $status,
            ['Content-Type' => 'application/json'],
        );
    }

    protected function collection(Request $request, BaseCollection $collection): JsonResponse
    {
        return new JsonResponse(
            (new CollectionResponse($this->correlationId($request), $collection))->toArray(),
            200,
            ['Content-Type' => 'application/json'],
        );
    }

    /**
     * @param  array<string, list<string>>|null  $validationErrors
     */
    protected function problem(
        Request $request,
        Throwable $throwable,
        ?array $validationErrors = null,
        int $statusOverride = 0,
    ): JsonResponse {
        $problem = $this->errors->map(
            $throwable,
            $this->correlationId($request),
            $request->path(),
            $validationErrors,
        );

        if ($statusOverride > 0 && $problem->status !== $statusOverride) {
            $problem = new ProblemDetails(
                $problem->correlationId,
                $problem->type,
                $problem->title,
                $statusOverride,
                $problem->detail,
                $problem->instance,
                $problem->validationErrors,
            );
        }

        return CommercialCatalogueProblemDetailsResponseFactory::make($problem);
    }

    private function correlationId(Request $request): string
    {
        $correlationId = $request->attributes->get('correlation_id');

        if (is_string($correlationId) && $correlationId !== '') {
            return $correlationId;
        }

        $bodyCorrelationId = $request->string('correlation_id')->toString();

        if ($bodyCorrelationId !== '') {
            return $bodyCorrelationId;
        }

        return (string) Str::uuid();
    }
}
