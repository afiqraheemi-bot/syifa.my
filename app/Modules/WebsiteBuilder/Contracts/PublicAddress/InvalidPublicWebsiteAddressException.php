<?php

declare(strict_types=1);

namespace App\Modules\WebsiteBuilder\Contracts\PublicAddress;

use RuntimeException;

/**
 * Contracts-level boundary exception for {@see PublicWebsiteAddressAvailabilityInterface}.
 * Callers outside WebsiteBuilder must depend on this type, never on the
 * Domain exception an implementation happens to throw internally.
 */
final class InvalidPublicWebsiteAddressException extends RuntimeException {}
