<?php

declare(strict_types=1);

namespace App\Modules\Booking\Infrastructure\Persistence\Exceptions;

use RuntimeException;

final class InvalidBookingStorageStateException extends RuntimeException {}
