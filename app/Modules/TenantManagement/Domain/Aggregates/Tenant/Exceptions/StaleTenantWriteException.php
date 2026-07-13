<?php

declare(strict_types=1);

namespace App\Modules\TenantManagement\Domain\Aggregates\Tenant\Exceptions;

use RuntimeException;

final class StaleTenantWriteException extends RuntimeException {}
