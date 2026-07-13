<?php

declare(strict_types=1);

namespace App\Modules\Onboarding\Infrastructure\Persistence\Exceptions;

use RuntimeException;

final class InvalidOnboardingJobStorageStateException extends RuntimeException {}
