<?php

declare(strict_types=1);

namespace App\Modules\TenantManagement\Domain\TenantContext\Exceptions;

use InvalidArgumentException;

final class InvalidTenantContextValueException extends InvalidArgumentException {}
