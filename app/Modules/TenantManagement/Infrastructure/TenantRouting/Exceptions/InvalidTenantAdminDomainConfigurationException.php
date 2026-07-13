<?php

declare(strict_types=1);

namespace App\Modules\TenantManagement\Infrastructure\TenantRouting\Exceptions;

use RuntimeException;

final class InvalidTenantAdminDomainConfigurationException extends RuntimeException {}
