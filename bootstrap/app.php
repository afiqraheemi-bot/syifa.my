<?php

declare(strict_types=1);

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Inertia\Middleware as InertiaMiddleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [InertiaMiddleware::class]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {})
    ->withRouting(web: __DIR__.'/../routes/web.php')
    ->create();
