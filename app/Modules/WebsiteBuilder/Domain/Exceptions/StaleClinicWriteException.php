<?php

declare(strict_types=1);

namespace App\Modules\WebsiteBuilder\Domain\Exceptions;

use RuntimeException;

final class StaleClinicWriteException extends RuntimeException {}
