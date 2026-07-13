<?php

declare(strict_types=1);

namespace App\Modules\TenantManagement\Application\Authentication\Exceptions;

use RuntimeException;

final class InvalidClinicOwnerPasswordException extends RuntimeException {}
