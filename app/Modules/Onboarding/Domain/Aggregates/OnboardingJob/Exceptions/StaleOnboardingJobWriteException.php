<?php

declare(strict_types=1);

namespace App\Modules\Onboarding\Domain\Aggregates\OnboardingJob\Exceptions;

use RuntimeException;

final class StaleOnboardingJobWriteException extends RuntimeException {}
