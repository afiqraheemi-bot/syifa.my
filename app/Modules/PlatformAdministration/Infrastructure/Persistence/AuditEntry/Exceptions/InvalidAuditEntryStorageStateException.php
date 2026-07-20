<?php

declare(strict_types=1);

namespace App\Modules\PlatformAdministration\Infrastructure\Persistence\AuditEntry\Exceptions;

use RuntimeException;

final class InvalidAuditEntryStorageStateException extends RuntimeException {}
