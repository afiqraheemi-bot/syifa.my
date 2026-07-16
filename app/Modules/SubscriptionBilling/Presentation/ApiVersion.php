<?php

declare(strict_types=1);

namespace App\Modules\SubscriptionBilling\Presentation;

final class ApiVersion
{
    public const string CURRENT = 'v1';

    public const string PREFIX = '/api/v1';

    public const string PLATFORM_PREFIX = '/api/v1/platform';

    public const string COMMERCIAL_CATALOGUE_PREFIX = '/api/v1/platform/commercial-catalogue';
}
