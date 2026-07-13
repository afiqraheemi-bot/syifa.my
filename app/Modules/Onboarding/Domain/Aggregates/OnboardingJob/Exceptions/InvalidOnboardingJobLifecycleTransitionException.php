<?php

declare(strict_types=1);

namespace App\Modules\Onboarding\Domain\Aggregates\OnboardingJob\Exceptions;

use DomainException;

final class InvalidOnboardingJobLifecycleTransitionException extends DomainException {}
