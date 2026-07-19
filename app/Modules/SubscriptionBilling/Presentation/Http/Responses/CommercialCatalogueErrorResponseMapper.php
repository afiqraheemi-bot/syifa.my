<?php

declare(strict_types=1);

namespace App\Modules\SubscriptionBilling\Presentation\Http\Responses;

use App\Modules\SubscriptionBilling\Application\CommercialCatalogue\Exceptions\CommercialCatalogueResourceNotFoundException;
use App\Modules\SubscriptionBilling\Application\CommercialCatalogue\Exceptions\CommercialCatalogueVersionMismatchException;
use App\Modules\SubscriptionBilling\Domain\CommercialCatalogue\Exceptions\InvalidCommercialCatalogueValueException;
use App\Modules\SubscriptionBilling\Domain\CommercialCatalogue\Exceptions\InvalidPlanLifecycleTransitionException;
use App\Modules\SubscriptionBilling\Domain\CommercialCatalogue\Exceptions\InvalidPlanOfferingException;
use App\Modules\SubscriptionBilling\Infrastructure\Persistence\Exceptions\InvalidCommercialCatalogueStorageStateException;
use App\Modules\SubscriptionBilling\Infrastructure\Persistence\Exceptions\StaleCommercialCatalogueWriteException;
use App\Modules\SubscriptionBilling\Presentation\Contracts\ErrorResponseMapperInterface;
use App\Modules\SubscriptionBilling\Presentation\Responses\ProblemDetails;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\QueryException;
use Illuminate\Validation\ValidationException;
use PDOException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

final class CommercialCatalogueErrorResponseMapper implements ErrorResponseMapperInterface
{
    public function map(
        Throwable $throwable,
        string $correlationId,
        ?string $instance = null,
        ?array $validationErrors = null,
    ): ProblemDetails {
        [$type, $title, $status, $detail] = $this->resolve($throwable);

        return new ProblemDetails(
            $correlationId,
            $type,
            $title,
            $status,
            $detail,
            $instance,
            $validationErrors ?? ($throwable instanceof ValidationException ? $throwable->errors() : null),
        );
    }

    /**
     * @return array{0:string,1:string,2:int,3:string}
     */
    private function resolve(Throwable $throwable): array
    {
        return match (true) {
            $throwable instanceof AuthenticationException => [
                'unauthenticated',
                'Unauthenticated',
                401,
                'Authentication is required to access the commercial catalogue.',
            ],
            $throwable instanceof AccessDeniedHttpException,
            $throwable instanceof AuthorizationException => [
                'authorization_forbidden',
                'Forbidden',
                403,
                'The commercial catalogue is not available to the current actor.',
            ],
            $throwable instanceof CommercialCatalogueResourceNotFoundException,
            $throwable instanceof NotFoundHttpException => [
                'resource_not_found',
                'Resource Not Found',
                404,
                'The requested commercial catalogue resource could not be found.',
            ],
            $throwable instanceof CommercialCatalogueVersionMismatchException => [
                'expected_version_mismatch',
                'Expected Version Mismatch',
                409,
                'The supplied expected version does not match the current catalogue record.',
            ],
            $throwable instanceof StaleCommercialCatalogueWriteException => [
                'stale_write_conflict',
                'Stale Write Conflict',
                409,
                'The commercial catalogue record changed before the write could be applied.',
            ],
            $throwable instanceof InvalidPlanLifecycleTransitionException,
            $throwable instanceof InvalidPlanOfferingException => [
                'invalid_lifecycle_transition',
                'Invalid Lifecycle Transition',
                409,
                'The requested commercial catalogue lifecycle transition is not allowed.',
            ],
            $throwable instanceof InvalidCommercialCatalogueValueException => [
                'invalid_commercial_value',
                'Invalid Commercial Value',
                422,
                'The submitted commercial value is not valid.',
            ],
            $throwable instanceof ValidationException => [
                'validation_failed',
                'Validation Failed',
                422,
                'The submitted commercial catalogue input is invalid.',
            ],
            $throwable instanceof InvalidCommercialCatalogueStorageStateException,
            $throwable instanceof QueryException,
            $throwable instanceof PDOException => [
                'unexpected_infrastructure_failure',
                'Internal Server Error',
                500,
                'The commercial catalogue request could not be completed.',
            ],
            default => [
                'unexpected_infrastructure_failure',
                'Internal Server Error',
                500,
                'The commercial catalogue request could not be completed.',
            ],
        };
    }
}
