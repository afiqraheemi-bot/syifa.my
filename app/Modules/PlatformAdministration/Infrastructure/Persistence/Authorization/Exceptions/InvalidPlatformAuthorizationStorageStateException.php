<?php

declare(strict_types=1);

namespace App\Modules\PlatformAdministration\Infrastructure\Persistence\Authorization\Exceptions;

use RuntimeException;

final class InvalidPlatformAuthorizationStorageStateException extends RuntimeException {}
