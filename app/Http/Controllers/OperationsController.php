<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Operations\OperationsResponseFactory;
use Illuminate\Http\JsonResponse;

final readonly class OperationsController
{
    public function __construct(private OperationsResponseFactory $responses) {}

    public function health(): JsonResponse
    {
        return $this->responses->health();
    }

    public function ready(): JsonResponse
    {
        return $this->responses->ready();
    }

    public function live(): JsonResponse
    {
        return $this->responses->live();
    }

    public function info(): JsonResponse
    {
        return $this->responses->info();
    }

    public function build(): JsonResponse
    {
        return $this->responses->build();
    }

    public function version(): JsonResponse
    {
        return $this->responses->version();
    }

    public function release(): JsonResponse
    {
        return $this->responses->release();
    }
}
