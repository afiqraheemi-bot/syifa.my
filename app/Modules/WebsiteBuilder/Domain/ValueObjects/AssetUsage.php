<?php

declare(strict_types=1);

namespace App\Modules\WebsiteBuilder\Domain\ValueObjects;

enum AssetUsage
{
    case Logo;
    case Favicon;
    case ContentImage;
    case DoctorPhoto;
    case OpenGraphImage;
}
