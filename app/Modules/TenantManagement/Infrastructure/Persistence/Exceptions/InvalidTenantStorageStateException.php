<?php

declare(strict_types=1);

namespace App\Modules\TenantManagement\Infrastructure\Persistence\Exceptions;

use RuntimeException;

final class InvalidTenantStorageStateException extends RuntimeException {}
