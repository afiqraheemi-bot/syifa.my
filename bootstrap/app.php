<?php

declare(strict_types=1);

use App\Http\Middleware\ApplyHttpSecurityHeaders;
use App\Http\Middleware\ApplyPlatformEdgeSecurity;
use App\Http\Middleware\HandleInertiaRequests;
use App\Modules\PlatformAdministration\Presentation\Http\Responses\ProblemDetailsResponse as PlatformProblemDetailsResponse;
use App\Modules\SubscriptionBilling\Infrastructure\Payment\Jobs\PublishPaymentOutboxJob;
use App\Modules\SubscriptionBilling\Presentation\Contracts\ErrorResponseMapperInterface;
use App\Modules\SubscriptionBilling\Presentation\Http\Support\CommercialCatalogueProblemDetailsResponseFactory;
use App\Modules\TenantManagement\Presentation\Http\Middleware\AttachRequestIdentifiers;
use App\Modules\TenantManagement\Presentation\Http\Responses\ProblemDetailsResponse;
use App\Support\Authorization\Http\Middleware\AuthorizeRequest;
use App\Support\Identity\Http\Middleware\EnsureGuestForGuard;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->append([
            ApplyPlatformEdgeSecurity::class,
            ApplyHttpSecurityHeaders::class,
        ]);

        $middleware->web(
            prepend: [AttachRequestIdentifiers::class],
            append: [HandleInertiaRequests::class],
        );

        $middleware->alias([
            'authorize.context' => AuthorizeRequest::class,
            'guest.guard' => EnsureGuestForGuard::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $correlationId = static function (Request $request): string {
            $correlationId = $request->attributes->get('correlation_id');

            if (is_string($correlationId) && $correlationId !== '') {
                return $correlationId;
            }

            return (string) Str::uuid();
        };

        $exceptions->render(static function (Throwable $exception, Request $request) use ($correlationId) {
            if (str_starts_with($request->path(), 'api/v1/platform/commercial-catalogue')) {
                if ($exception instanceof HttpResponseException) {
                    return $exception->getResponse();
                }

                $mapper = app(ErrorResponseMapperInterface::class);
                $validationErrors = $exception instanceof ValidationException ? $exception->errors() : null;

                return CommercialCatalogueProblemDetailsResponseFactory::make(
                    $mapper->map($exception, $correlationId($request), $request->path(), $validationErrors),
                );
            }

            if (str_starts_with($request->path(), 'api/v1/platform/sessions')) {
                if ($exception instanceof HttpResponseException) {
                    return $exception->getResponse();
                }

                if ($exception instanceof HttpExceptionInterface) {
                    return null;
                }

                return PlatformProblemDetailsResponse::make(
                    $request,
                    'internal_error',
                    'Internal Server Error',
                    500,
                    'The request could not be completed.',
                );
            }

            if (! str_starts_with($request->path(), 'api/v1/sessions')) {
                return null;
            }

            if ($exception instanceof HttpResponseException) {
                return $exception->getResponse();
            }

            return ProblemDetailsResponse::make(
                $request,
                'internal_error',
                'Internal Server Error',
                500,
                'The request could not be completed.',
            );
        });
    })
    ->withRouting(web: __DIR__.'/../routes/web.php')
    ->withSchedule(function (Schedule $schedule): void {
        // Sweep/recovery entry point: the transactional outbox must not
        // depend solely on the after-commit queue dispatch. A committed
        // outbox row that never reached the queue (or whose lease expired
        // after a worker crash) is still discovered and delivered here.
        $schedule->job(new PublishPaymentOutboxJob, 'payment-outbox', 'redis')
            ->onOneServer()
            ->everyMinute();
    })
    ->create();
