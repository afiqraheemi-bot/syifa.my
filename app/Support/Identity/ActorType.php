<?php

declare(strict_types=1);

namespace App\Support\Identity;

enum ActorType: string
{
    case PlatformIdentity = 'platform_identity';
    case ClinicOwner = 'clinic_owner';
}
