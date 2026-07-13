<?php

declare(strict_types=1);

namespace App\Modules\TenantManagement\Domain\Aggregates\Tenant\Exceptions;

use InvalidArgumentException;

final class InvalidTenantIdentityValueException extends InvalidArgumentException {}
