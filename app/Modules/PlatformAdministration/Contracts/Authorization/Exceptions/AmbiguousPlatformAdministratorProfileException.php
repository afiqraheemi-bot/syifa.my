<?php

declare(strict_types=1);

namespace App\Modules\PlatformAdministration\Contracts\Authorization\Exceptions;

use App\Modules\PlatformAdministration\Contracts\Authorization\PlatformAdministratorLookupInterface;
use RuntimeException;

/**
 * Thrown by a {@see PlatformAdministratorLookupInterface}
 * implementation when more than one Platform Administrator profile is found for a single
 * Platform Identity — a state the PostgreSQL unique constraint makes unreachable in ordinary
 * operation. This exists as part of the Contract so the Application layer can depend on it
 * without depending on any specific Infrastructure implementation.
 */
final class AmbiguousPlatformAdministratorProfileException extends RuntimeException {}
