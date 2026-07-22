<?php

declare(strict_types=1);

namespace App\Modules\Booking\Application\Exceptions;

use InvalidArgumentException;

final class RequiredBookingFieldMissingException extends InvalidArgumentException {}
