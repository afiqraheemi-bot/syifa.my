<?php

declare(strict_types=1);

namespace App\Modules\TenantManagement\Contracts\Authentication\Signals;

use DateTimeImmutable;

final readonly class ClinicOwnerAuthenticationRejected
{
    public function __construct(public DateTimeImmutable $occurredAt) {}
}
