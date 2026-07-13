<?php

declare(strict_types=1);

namespace App\Modules\TenantManagement\Domain\Aggregates\Tenant\ValueObjects;

use App\Modules\TenantManagement\Domain\Aggregates\Tenant\Exceptions\InvalidTenantAdminRoutingLabelException;

final readonly class TenantAdminRoutingLabel
{
    /** @var list<string> */
    private const RESERVED = [
        'www',
        'app',
        'admin',
        'api',
        'support',
        'status',
        'assets',
        'static',
    ];

    public function __construct(public string $value)
    {
        if (strlen($value) < 3
            || strlen($value) > 63
            || preg_match('/^[a-z0-9](?:[a-z0-9-]*[a-z0-9])?$/D', $value) !== 1
            || str_contains($value, '--')
            || in_array($value, self::RESERVED, true)) {
            throw new InvalidTenantAdminRoutingLabelException(
                'Tenant Admin Routing Label must be an available lowercase ASCII DNS label of 3 to 63 characters.',
            );
        }
    }
}
