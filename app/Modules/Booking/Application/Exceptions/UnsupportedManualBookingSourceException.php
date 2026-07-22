<?php

declare(strict_types=1);

namespace App\Modules\Booking\Application\Exceptions;

use DomainException;

final class UnsupportedManualBookingSourceException extends DomainException {}
