<?php

declare(strict_types=1);

namespace App\Modules\AcquisitionOffer\Domain\Exceptions;

use RuntimeException;

final class StaleCommercialOfferWriteException extends RuntimeException {}
