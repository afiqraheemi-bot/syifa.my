<?php

declare(strict_types=1);

namespace App\Modules\ClinicRegistration\Domain\Exceptions;

use RuntimeException;

final class StaleClinicRegistrationWriteException extends RuntimeException {}
