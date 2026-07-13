<?php

declare(strict_types=1);

namespace App\Modules\PlatformAdministration\Infrastructure\Persistence\WorkforceCredentials\Exceptions;

use RuntimeException;

final class StalePlatformWorkforceCredentialWriteException extends RuntimeException {}
