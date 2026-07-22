<?php

declare(strict_types=1);

namespace App\Modules\Booking\Domain\Exceptions;

use RuntimeException;

final class StaleBookingFormConfigurationWriteException extends RuntimeException {}
