<?php

declare(strict_types=1);

use App\Modules\PlatformAdministration\Presentation\Http\Responses\ProblemDetailsResponse as PlatformProblemDetailsResponse;
use App\Modules\TenantManagement\Presentation\Http\Middleware\AttachRequestIdentifiers;
use App\Modules\TenantManagement\Presentation\Http\Responses\ProblemDetailsResponse;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Inertia\Middleware as InertiaMiddleware;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(
            prepend: [AttachRequestIdentifiers::class],
            append: [InertiaMiddleware::class],
        );
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(static function (Throwable $exception, Request $request) {
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

            if ($exception instanceof HttpExceptionInterface) {
                return null;
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
    ->create();
