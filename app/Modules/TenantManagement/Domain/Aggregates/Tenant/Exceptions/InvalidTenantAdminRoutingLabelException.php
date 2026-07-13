<?php

declare(strict_types=1);

namespace App\Modules\TenantManagement\Domain\Aggregates\Tenant\Exceptions;

use DomainException;

final class InvalidTenantAdminRoutingLabelException extends DomainException {}
