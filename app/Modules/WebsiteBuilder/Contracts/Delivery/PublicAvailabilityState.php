<?php

declare(strict_types=1);

namespace App\Modules\WebsiteBuilder\Contracts\Delivery;

/**
 * The closed, three-state public Availability vocabulary (ADR-028). `Unknown`
 * is an honest admission that no signal exists — never a fourth degree of
 * availability, and never rendered as if it were `Available` or `Unavailable`.
 */
enum PublicAvailabilityState
{
    case Available;
    case Unavailable;
    case Unknown;
}
