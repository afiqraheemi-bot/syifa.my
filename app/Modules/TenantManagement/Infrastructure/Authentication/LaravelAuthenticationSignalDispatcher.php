<?php

declare(strict_types=1);

namespace App\Modules\TenantManagement\Infrastructure\Authentication;

use App\Modules\TenantManagement\Contracts\Authentication\AuthenticationSignalDispatcherInterface;
use App\Modules\TenantManagement\Contracts\Authentication\Signals\ClinicOwnerAuthenticationRejected;
use App\Modules\TenantManagement\Contracts\Authentication\Signals\ClinicOwnerAuthenticationSucceeded;
use Illuminate\Contracts\Events\Dispatcher;

final readonly class LaravelAuthenticationSignalDispatcher implements AuthenticationSignalDispatcherInterface
{
    public function __construct(private Dispatcher $events) {}

    public function dispatch(ClinicOwnerAuthenticationSucceeded|ClinicOwnerAuthenticationRejected $signal): void
    {
        $this->events->dispatch($signal);
    }
}
